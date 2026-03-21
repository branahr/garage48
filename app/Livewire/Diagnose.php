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

    public int $currentQuestionIndex = 0;

    public string $selectedOption = '';

    /** @var array<int, string> */
    public array $selectedOptions = [];

    public string $otherText = '';

    private const STEP_NAMES = ['describe', 'decide', 'value'];

    private const STEP_MAP = [3 => 'describe', 4 => 'decide', 5 => 'value'];

    public function mount(): void
    {
        $id = session('diagnosis_session_id');

        if ($id && $session = DiagnosisSession::with('questions')->find($id)) {
            $this->sessionId = $session->id;
            $this->description = $session->service_description;
            $this->step = $session->step;

            if (isset(self::STEP_MAP[$this->step])) {
                $stepName = self::STEP_MAP[$this->step];
                $this->currentQuestionIndex = $session->questions
                    ->where('step', $stepName)
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
     * Step 1 → 2: Validate description, run AI diagnosis, create session.
     */
    public function diagnose(AnthropicService $anthropic): void
    {
        $validated = $this->validate([
            'description' => ['required', 'string', 'min:20', 'max:5000'],
        ], [
            'description.required' => 'Please describe your service before submitting.',
            'description.min' => 'Please provide at least 20 characters to get a meaningful analysis.',
        ]);

        $response = $anthropic->ask(
            prompt: $this->getDiagnoseUserPrompt($validated['description']),
            system: $this->getDiagnoseSystemPrompt(),
            temperature: 0.5,
        );

        $diagnosis = $this->extractJson($response);

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
     * Step 2 → 3/4/5/6: Check routing and proceed to the first non-skipped question step, or straight to generate.
     */
    public function proceedFromDiagnosis(AnthropicService $anthropic): void
    {
        $this->advanceToNextQuestionStep(2, $anthropic);
    }

    /**
     * Submit the current question answer and advance.
     */
    public function submitQuestionAnswer(AnthropicService $anthropic): void
    {
        $session = $this->session;
        $stepName = self::STEP_MAP[$this->step] ?? null;

        if (! $stepName) {
            return;
        }

        $questions = $session->questions->where('step', $stepName)->values();
        $question = $questions->get($this->currentQuestionIndex);

        if (! $question) {
            return;
        }

        if ($question->type === 'multi') {
            $this->validate([
                'selectedOptions' => ['required', 'array', 'min:1'],
            ], ['selectedOptions.required' => 'Please select at least one option.']);

            $answer = [
                'selected' => $this->selectedOptions,
                'other_text' => in_array('other', $this->selectedOptions) ? $this->otherText : null,
            ];
        } else {
            $this->validate([
                'selectedOption' => ['required', 'string'],
            ], ['selectedOption.required' => 'Please select an option.']);

            $answer = [
                'selected' => [$this->selectedOption],
                'other_text' => $this->selectedOption === 'other' ? $this->otherText : null,
            ];
        }

        $question->update(['answer' => $answer]);

        $this->resetQuestionState();
        $this->currentQuestionIndex++;

        if ($this->currentQuestionIndex >= $questions->count()) {
            $this->advanceToNextQuestionStep($this->step, $anthropic);
        } else {
            unset($this->session);
        }
    }

    /**
     * Step 5/6 → 6: Generate final service description using all context.
     */
    public function generateResult(AnthropicService $anthropic): void
    {
        $session = $this->session;

        $response = $anthropic->ask(
            prompt: $this->getGenerateUserPrompt($session),
            system: $this->getGenerateSystemPrompt(),
            temperature: 0.8,
        );

        $result = $this->extractJson($response);

        // Ensure new scores don't go below original scores
        $oldScore = $session->diagnosis['clarity_score'] ?? 0;
        if (($result['new_clarity_score'] ?? 0) < $oldScore) {
            $result['new_clarity_score'] = max($oldScore, 7);
        }

        $session->update([
            'final_result' => $result,
            'step' => 6,
            'status' => 'completed',
        ]);

        $this->step = 6;
        unset($this->session);
    }

    public function startOver(): void
    {
        $this->reset(['description', 'sessionId', 'selectedOption', 'selectedOptions', 'otherText', 'currentQuestionIndex']);
        $this->step = 1;
        session()->forget('diagnosis_session_id');
    }

    public function render()
    {
        return view('livewire.diagnose')
            ->layout('layouts.guest');
    }

    // ── Routing helpers ──

    private function getRoutingForStep(string $stepName): string
    {
        $session = $this->session;

        return $session->computedRouting()[$stepName] ?? 'deep';
    }

    /**
     * From the current step number, find the next non-skipped question step or go to generate.
     */
    private function advanceToNextQuestionStep(int $fromStep, AnthropicService $anthropic): void
    {
        $stepOrder = [3 => 'describe', 4 => 'decide', 5 => 'value'];
        $session = $this->session;

        foreach ($stepOrder as $stepNum => $stepName) {
            if ($stepNum <= $fromStep) {
                continue;
            }

            $routing = $this->getRoutingForStep($stepName);

            if ($routing === 'skip') {
                continue;
            }

            $count = $this->generateStepQuestions($stepName, $anthropic);

            if ($count === 0) {
                continue; // AI returned no questions — skip this step
            }

            $session->update(['step' => $stepNum]);
            $this->step = $stepNum;
            $this->currentQuestionIndex = 0;
            unset($this->session);

            return;
        }

        // All question steps skipped or done — go to generate
        $session->update(['step' => 6]);
        $this->step = 6;
        $this->generateResult($anthropic);
    }

    private function generateStepQuestions(string $stepName, AnthropicService $anthropic): int
    {
        $session = $this->session;

        [$system, $user] = match ($stepName) {
            'describe' => [$this->getDescribeSystemPrompt(), $this->getDescribeUserPrompt($session)],
            'decide' => [$this->getDecideSystemPrompt(), $this->getDecideUserPrompt($session)],
            'value' => [$this->getValueSystemPrompt(), $this->getValueUserPrompt($session)],
        };

        $response = $anthropic->ask(prompt: $user, system: $system, temperature: 0.7);
        $data = $this->extractJson($response);

        $count = 0;

        foreach ($data['questions'] ?? [] as $index => $q) {
            $options = collect($q['options'] ?? [])->map(function (array|string $opt, int $i): array {
                if (is_string($opt)) {
                    return ['id' => chr(97 + $i), 'label' => $opt];
                }

                return [
                    'id' => $opt['id'] ?? $opt['value'] ?? chr(97 + $i),
                    'label' => $opt['label'] ?? $opt['text'] ?? (string) $opt['id'],
                ];
            })->values()->all();

            $session->questions()->create([
                'step' => $stepName,
                'question_key' => $q['id'] ?? $stepName[0].$index,
                'type' => $q['type'] ?? 'single',
                'question' => $q['question'],
                'intro_text' => $q['intro_text'] ?? null,
                'options' => $options,
                'sort_order' => $index,
            ]);

            $count++;
        }

        return $count;
    }

    private function resetQuestionState(): void
    {
        $this->selectedOption = '';
        $this->selectedOptions = [];
        $this->otherText = '';
    }

    /**
     * Extract JSON from AI response, handling markdown fences and embedded text.
     *
     * @return array<string, mixed>
     */
    private function extractJson(string $response): array
    {
        $trimmed = trim($response);

        // Strip markdown code fences
        $trimmed = preg_replace('/^```(?:json)?\s*/s', '', $trimmed);
        $trimmed = preg_replace('/\s*```$/s', '', $trimmed);

        // Try direct parse first
        $result = json_decode($trimmed, true);
        if (is_array($result) && $result !== []) {
            return $result;
        }

        // Try to find JSON object in the response
        if (preg_match('/\{[\s\S]*\}/s', $trimmed, $matches)) {
            $result = json_decode($matches[0], true);
            if (is_array($result) && $result !== []) {
                return $result;
            }
        }

        return [];
    }

    // ── Collected answers helpers ──

    /**
     * @return array<string, mixed>
     */
    private function collectStepAnswers(DiagnosisSession $session, string $stepName): array
    {
        return $session->questions
            ->where('step', $stepName)
            ->mapWithKeys(function (DiagnosisQuestion $q): array {
                $selected = $q->answer['selected'] ?? [];
                $optionMap = collect($q->options)->keyBy('id');

                $selectedLabels = collect($selected)->map(
                    fn (string $id): string => $optionMap[$id]['label'] ?? $id
                )->all();

                return [
                    $q->question_key => [
                        'question' => $q->question,
                        'selected' => $selectedLabels,
                        'other_text' => $q->answer['other_text'] ?? null,
                    ],
                ];
            })
            ->all();
    }

    /**
     * @return array<string>
     */
    private function collectWeaknessCategories(DiagnosisSession $session): array
    {
        return collect($session->diagnosis['weaknesses'] ?? [])
            ->pluck('category')
            ->filter()
            ->values()
            ->all();
    }

    // ── Prompt methods ──

    private function getDiagnoseSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert service analyst combining service design,
marketing, business strategy, and behavioural psychology.

Analyze a freelancer's service description from a POTENTIAL
CLIENT's perspective. Score 5 dimensions, each 0-2 points:
1. AUDIENCE (0=missing, 1=broad, 2=specific person+situation)
2. PROBLEM (0=missing, 1=vague, 2=specific+nameable)
3. OFFER (0=multiple/unclear, 1=identifiable, 2=one clear service)
4. VALUE (0=missing, 1=generic, 2=specific+differentiated)
5. LANGUAGE (0=jargon, 1=some jargon, 2=clear+simple)

Tag weaknesses with categories: AUDIENCE_MISSING, AUDIENCE_BROAD,
SITUATION_MISSING, PROBLEM_MISSING, PROBLEM_VAGUE,
TOO_MANY_SERVICES, NO_BOUNDARIES, VALUE_MISSING,
VALUE_GENERIC, DIFFERENTIATION_MISSING

Also identify strengths. Determine routing for next steps:
  describe: deep|light|skip
  decide: deep|light|skip
  value: deep|light|skip

Tone: Warm but direct. Quote the user's own words in feedback.
CRITICAL: Return ONLY valid JSON. No markdown.
PROMPT;
    }

    private function getDiagnoseUserPrompt(string $description): string
    {
        return <<<PROMPT
Analyze this service description:

\"\"\"{$description}\"\"\"

Return JSON:
{
  "clarity_score": <0-10>,
  "dimension_scores": {
    "audience": {"score": <0-2>, "reason": "..."},
    "problem": {"score": <0-2>, "reason": "..."},
    "offer": {"score": <0-2>, "reason": "..."},
    "value": {"score": <0-2>, "reason": "..."},
    "language": {"score": <0-2>, "reason": "..."}
  },
  "weaknesses": [
    {"category": "...", "issue": "<3-5 words>",
     "explanation": "<1 sentence, quote user>"}
  ],
  "strengths": [
    {"area": "...", "feedback": "<1 sentence positive>"}
  ],
  "routing": {
    "describe": "deep|light|skip",
    "decide": "deep|light|skip",
    "value": "deep|light|skip"
  },
  "coach_message": "<2-3 sentences, warm+direct>"
}
PROMPT;
    }

    private function getDescribeSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a service design expert using SITUATION-FIRST methodology:
- Ask about the CLIENT'S SITUATION first, not the client type
- A situation = a specific moment/trigger that creates the need
- The person is DEFINED by the situation

Principle: 'When you lock the situation, you lock the service.
The client range can even expand.'

Rules for generating options:
- Each option describes a MOMENT, not a demographic
- Must be specific enough to visualize
- Include emotional/practical consequence
- Bad: 'Small businesses that need design' (persona)
- Good: 'When a freelancer lost a client because they said
  I didn't understand what you offer' (moment + consequence)

Tone: Warm but direct coach.
CRITICAL: Return ONLY valid JSON.
PROMPT;
    }

    private function getDescribeUserPrompt(DiagnosisSession $session): string
    {
        $diagnosis = json_encode($session->diagnosis);
        $routing = $this->getRoutingForStep('describe');

        return <<<PROMPT
Original description: \"\"\"{$session->service_description}\"\"\"
Diagnosis: \"\"\"{$diagnosis}\"\"\"
Routing: "{$routing}"

If deep: generate 2 questions (situation + person).
If light: generate 1 question (situation only).

Q1 (always): 'Think about your best client. What was happening
in their life or work right BEFORE they came to you?'
3 specific situation options + 'My situation is different'

Q2 (deep only): 'Who is typically the person in that situation?
Not a job title, but what kind of person and what stage.'
3 person options + 'Someone else'

Return JSON:
{
  "step": "describe",
  "questions": [{
    "id": "d1", "type": "single",
    "question": "...",
    "options": [
      {"id": "a", "label": "..."},
      {"id": "b", "label": "..."},
      {"id": "c", "label": "..."},
      {"id": "other", "label": "My situation is different"}
    ]
  }]
}
PROMPT;
    }

    private function getDecideSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a service design and business strategy expert helping
a freelancer make hard decisions about their service.

Expertise:
- Service design: without boundaries, a service expands until
  meaningless. Boundaries create TRUST.
- Business: a service that solves ONE thing is sellable.
- Psychology: people resist narrowing down because it feels like
  losing income. Make it safe: 'This doesn't mean you stop doing
  the others. It means you LEAD with one.'

Generate multiple-choice questions that FORCE choices.
Tone: Warm but direct coach.
CRITICAL: Return ONLY valid JSON.
PROMPT;
    }

    private function getDecideUserPrompt(DiagnosisSession $session): string
    {
        $diagnosis = json_encode($session->diagnosis);
        $describeAnswers = json_encode($this->collectStepAnswers($session, 'describe'));
        $routing = $this->getRoutingForStep('decide');
        $categories = implode(', ', $this->collectWeaknessCategories($session));

        return <<<PROMPT
Original: \"\"\"{$session->service_description}\"\"\"
Diagnosis: \"\"\"{$diagnosis}\"\"\"
Who they serve: \"\"\"{$describeAnswers}\"\"\"
Routing: "{$routing}"
Weakness categories: "{$categories}"

ALWAYS generate:
C1: 'What is the ONE core problem you solve for someone in
that situation?' single-select, 3 options + Other.

ONLY IF TOO_MANY_SERVICES in categories:
C2: 'You mentioned [services]. If a client could only know
you for ONE, which?' single-select with user's own services.
Add intro: 'This doesn't mean you stop doing the others.'

ALWAYS generate:
C3: 'What does your service NOT do? Pick all that apply.'
multi-select, 4-6 boundary options phrased as 'I don't...'

Return JSON: {"step": "decide", "questions": [{
  "id": "c1", "type": "single|multi",
  "question": "...", "intro_text": null|"...",
  "options": [...]
}]}
PROMPT;
    }

    private function getValueSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a marketing and positioning expert with deep
knowledge of behavioural psychology.

Expertise:
- Marketing: people buy transformations, not processes.
  'After working with me, you can...' beats 'I offer a programme.'
- Psychology: freelancers default to describing deliverables.
  Redirect from features to outcomes.
- Loss framing (Kahneman): 'Without this, you keep losing clients'
  is 2x more powerful than 'With this, you gain clients.'

Three real alternatives every client considers:
1. Do it themselves (free, but hard)
2. Use ChatGPT or AI (cheap, but generic)
3. Hire someone else (comparable, different)

Tone: Warm, make value discovery feel natural, not salesy.
CRITICAL: Return ONLY valid JSON.
PROMPT;
    }

    private function getValueUserPrompt(DiagnosisSession $session): string
    {
        $diagnosis = json_encode($session->diagnosis);
        $describeAnswers = json_encode($this->collectStepAnswers($session, 'describe'));
        $decideAnswers = json_encode($this->collectStepAnswers($session, 'decide'));
        $routing = $this->getRoutingForStep('value');
        $categories = implode(', ', $this->collectWeaknessCategories($session));

        return <<<PROMPT
Original: \"\"\"{$session->service_description}\"\"\"
Diagnosis: \"\"\"{$diagnosis}\"\"\"
Who they serve: \"\"\"{$describeAnswers}\"\"\"
Decisions: \"\"\"{$decideAnswers}\"\"\"
Routing: "{$routing}"
Weakness categories: "{$categories}"

ALWAYS generate:
V1: 'After working with you, what's the main thing that's
DIFFERENT in your client's life? Not what you delivered —
what changed for THEM.' single-select, 3 OBSERVABLE outcomes.
Options must be 'they can...' or 'they stop...' NOT abstracts.

ALWAYS generate:
V2: 'A client is comparing you to: doing it themselves,
using ChatGPT, or hiring someone else. Why choose you?'
single-select, 3 differentiators + Other.

ONLY IF DIFFERENTIATION_MISSING in categories:
V3: 'What happens if they DON'T use your service?'
single-select, 3 consequences. Use loss framing.

Return JSON: {"step": "value", "questions": [...]}
PROMPT;
    }

    private function getGenerateSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert copywriter combining service design,
marketing, business strategy, and behavioural psychology.

Your job: USE the user's answers to write a DRAMATICALLY
BETTER service description than their original. The new
description MUST score higher than the original on every
dimension. The user answered specific questions — use those
answers as the foundation for the new description.

WRITING RULES (non-negotiable):
1. FIRST PERSON — 'I help...' not 'This service provides...'
2. SITUATION-ANCHORED — Reference the specific situation from their answers
3. ONE CLEAR OFFER — Not a menu of services
4. OUTCOME OVER PROCESS — Lead with what CHANGES
5. BOUNDED — Mention what you don't do (use their boundary answers)
6. CONCRETE — 'Can I picture this?' test for every sentence
7. SHORT — Description: 3-5 sentences. One-liner: <20 words.

BANNED: holistic, synergistic, leverage, transformative,
innovative, cutting-edge, game-changing, seamless, robust,
dynamic, paradigm, actionable, empower, disrupt.
BANNED PHRASES: 'meaningful experiences', 'tailored to
your needs', 'creative solutions', 'passion for helping',
'next level', 'unlock potential'.

TEST: If a phrase could appear on any competitor's website
unchanged, rewrite it.

SCORING: Re-score all 5 dimensions (0-2 each).
The new total score MUST be higher than the original.
Typical improvement: original 3-4 → new 7-9.
Each dimension should improve or stay the same, never decrease.
If vague flags exist, generate 2 variant options for that area.

CRITICAL: Return ONLY valid JSON. No markdown, no explanation.
PROMPT;
    }

    private function getGenerateUserPrompt(DiagnosisSession $session): string
    {
        $oldScore = $session->diagnosis['clarity_score'] ?? 0;
        $diagnosis = json_encode($session->diagnosis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $describeAnswers = json_encode($this->collectStepAnswers($session, 'describe'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $decideAnswers = json_encode($this->collectStepAnswers($session, 'decide'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $valueAnswers = json_encode($this->collectStepAnswers($session, 'value'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $categories = $this->collectWeaknessCategories($session);
        $vague = collect($categories)->filter(fn (string $c): bool => str_contains($c, 'VAGUE') || str_contains($c, 'BROAD') || str_contains($c, 'GENERIC'));
        $vagueFlags = $vague->isNotEmpty() ? $vague->implode(', ') : 'none';

        return <<<PROMPT
Write an IMPROVED service description using ALL the data below.
The original scored {$oldScore}/10. Your new version MUST score higher.

ORIGINAL DESCRIPTION:
\"\"\"{$session->service_description}\"\"\"

DIAGNOSIS (what was wrong):
{$diagnosis}

WHO (situation & audience answers):
{$describeAnswers}

WHAT (core problem & boundaries answers):
{$decideAnswers}

WHY (value & differentiation answers):
{$valueAnswers}

VAGUE FLAGS TO FIX: {$vagueFlags}

USE the selected answers above as raw material. Each answer
contains the actual text the user chose — weave it into the
new description.

Return ONLY this JSON (no markdown, no explanation):
{
  "new_clarity_score": <7-10, must be higher than {$oldScore}>,
  "new_dimension_scores": {
    "audience": {"score": <0-2>, "reason": "brief reason"},
    "problem": {"score": <0-2>, "reason": "brief reason"},
    "offer": {"score": <0-2>, "reason": "brief reason"},
    "value": {"score": <0-2>, "reason": "brief reason"},
    "language": {"score": <0-2>, "reason": "brief reason"}
  },
  "service_description": "<3-5 sentences, first person>",
  "value_proposition": "<1-2 sentences>",
  "target_audience": "<1 sentence, situation-based>",
  "boundaries": ["I don't...", "I don't..."],
  "one_liner": "<under 20 words>",
  "coach_message": "<2-3 sentences celebrating the improvement>",
  "variants": null
}
PROMPT;
    }
}
