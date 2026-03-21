<?php

namespace App\Livewire;

use App\Models\DiagnosisQuestion;
use App\Models\DiagnosisSession;
use App\Services\AnthropicService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Diagnose your service')]
class Diagnose extends Component
{
    public string $description = '';

    public ?int $sessionId = null;

    public int $step = 1;

    public string $currentAnswer = '';

    public int $currentQuestionIndex = 0;

    public function mount(): void
    {
        $id = session('diagnosis_session_id');

        if ($id && $session = DiagnosisSession::with('questions')->find($id)) {
            $this->sessionId = $session->id;
            $this->description = $session->service_description;
            $this->step = $session->step;

            if ($this->step === 3) {
                $this->currentQuestionIndex = $session->questions
                    ->filter(fn (DiagnosisQuestion $q): bool => $q->answer !== null)
                    ->count();
            }
        }
    }

    #[Computed]
    public function session(): ?DiagnosisSession
    {
        return $this->sessionId
            ? DiagnosisSession::with('questions')->find($this->sessionId)
            : null;
    }

    /**
     * Step 1: Submit service description → AI diagnosis.
     */
    public function diagnose(AnthropicService $anthropic): void
    {
        $validated = $this->validate([
            'description' => ['required', 'string', 'min:20', 'max:5000'],
        ], [
            'description.required' => 'Please describe your service before submitting.',
            'description.min' => 'Please provide at least 20 characters to get a meaningful analysis.',
        ]);

        $system = <<<'PROMPT'
You are a brutally honest service design expert.
You analyze freelancer service descriptions and find
exactly where they lose potential clients.

Rules:
- Be specific, not generic
- Point out vagueness, missing target audience,
  missing value proposition, jargon, trying to do too much
- Score 1-10 (1 = completely unclear, 10 = crystal clear)
- Suggest how many decision questions the user needs (4-8)
- Return ONLY valid JSON, no other text

JSON schema:
{
  "score": <int 1-10>,
  "summary": "<one sentence overall assessment>",
  "strengths": ["<specific strength>", ...],
  "weaknesses": ["<specific weakness>", ...],
  "missing_target_audience": "<who this seems aimed at, or note it's missing>",
  "missing_value_proposition": "<what value is unclear or absent>",
  "jargon": ["<jargon term>", ...],
  "decision_questions_needed": <int 4-8>,
  "decision_questions_reason": "<why this many questions are needed>"
}
PROMPT;

        $response = $anthropic->ask(
            prompt: $validated['description'],
            system: $system,
        );

        $json = preg_replace('/^```(?:json)?\s*|\s*```$/s', '', trim($response));
        $diagnosis = (array) json_decode($json, true);

        $session = DiagnosisSession::create([
            'service_description' => $validated['description'],
            'diagnosis' => $diagnosis,
            'step' => 2,
        ]);

        $this->sessionId = $session->id;
        session(['diagnosis_session_id' => $session->id]);
        $this->step = 2;
    }

    /**
     * Step 2: Generate questions based on diagnosis → move to step 3.
     */
    public function generateQuestions(AnthropicService $anthropic): void
    {
        $session = $this->session;

        $system = <<<'PROMPT'
You are a service design strategist. Based on the service description and its diagnosis,
generate clarifying questions that will help the user sharpen their service offering.

Rules:
- Each question should address a specific weakness or gap from the diagnosis
- Questions should be yes/no or short-answer, easy to respond to
- Order from most important to least important
- Return ONLY valid JSON, no other text

JSON schema:
{
  "questions": ["<question text>", ...]
}
PROMPT;

        $prompt = "Service description:\n{$session->service_description}\n\nDiagnosis:\n".json_encode($session->diagnosis)."\n\nGenerate exactly {$session->diagnosis['decision_questions_needed']} questions.";

        $response = $anthropic->ask(prompt: $prompt, system: $system);
        $json = preg_replace('/^```(?:json)?\s*|\s*```$/s', '', trim($response));
        $data = (array) json_decode($json, true);

        foreach ($data['questions'] ?? [] as $index => $question) {
            $session->questions()->create([
                'question' => $question,
                'sort_order' => $index,
            ]);
        }

        $session->update(['step' => 3]);
        $this->step = 3;
        $this->currentQuestionIndex = 0;
        unset($this->session);
    }

    /**
     * Step 3: Submit answer to current question.
     */
    public function submitAnswer(): void
    {
        $this->validate([
            'currentAnswer' => ['required', 'string', 'min:2', 'max:2000'],
        ], [
            'currentAnswer.required' => 'Please provide an answer.',
        ]);

        $session = $this->session;
        $question = $session->questions->get($this->currentQuestionIndex);

        if ($question) {
            $question->update(['answer' => $this->currentAnswer]);
        }

        $this->currentAnswer = '';
        $this->currentQuestionIndex++;

        if ($this->currentQuestionIndex >= $session->questions->count()) {
            $session->update(['step' => 4]);
            $this->step = 4;
        }
    }

    /**
     * Step 4: Generate final recommendation using all context.
     */
    public function generateResult(AnthropicService $anthropic): void
    {
        $session = $this->session;

        $qaPairs = $session->questions->map(
            fn (DiagnosisQuestion $q): string => "Q: {$q->question}\nA: {$q->answer}"
        )->implode("\n\n");

        $system = <<<'PROMPT'
You are a service design expert delivering a final recommendation.
Using the original service description, diagnosis, and the user's answers to clarifying questions,
produce a clear, actionable result.

Rules:
- Be specific and practical
- The rewritten description should be ready to use
- Return ONLY valid JSON, no other text

JSON schema:
{
  "rewritten_description": "<a clear, compelling service description ready to use>",
  "target_audience": "<specific target audience based on answers>",
  "value_proposition": "<clear value proposition>",
  "positioning_statement": "<one sentence positioning>",
  "next_steps": ["<actionable next step>", ...]
}
PROMPT;

        $prompt = "Service description:\n{$session->service_description}\n\nDiagnosis:\n".json_encode($session->diagnosis)."\n\nClarifying Q&A:\n{$qaPairs}\n\nProduce the final recommendation.";

        $response = $anthropic->ask(prompt: $prompt, system: $system);
        $json = preg_replace('/^```(?:json)?\s*|\s*```$/s', '', trim($response));
        $result = (array) json_decode($json, true);

        $session->update([
            'final_result' => $result,
            'step' => 5,
            'status' => 'completed',
        ]);

        $this->step = 5;
        unset($this->session);
    }

    public function startOver(): void
    {
        $this->reset(['description', 'sessionId', 'currentAnswer', 'currentQuestionIndex']);
        $this->step = 1;
        session()->forget('diagnosis_session_id');
    }

    public function render()
    {
        return view('livewire.diagnose')
            ->layout('layouts.guest');
    }
}
