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

    public string $questionStep = 'who';

    public string $currentAnswer = '';

    private const STEP_ORDER = ['who', 'what', 'why'];

    private const STEP_MAP = [3 => 'who', 4 => 'what', 5 => 'why'];

    public function mount(): void
    {
        $id = session('diagnosis_session_id');

        if ($id && $session = DiagnosisSession::with('questions')->find($id)) {
            $this->sessionId = $session->id;
            $this->description = $session->service_description;
            $this->step = $session->step;
            $this->questionStep = self::STEP_MAP[$this->step] ?? 'who';
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
     * Step 2 → 3: Generate questions per step (who, what_do, what_dont, why) and move to Who.
     */
    public function proceedToQuestions(AnthropicService $anthropic): void
    {
        $session = $this->session;

        $response = $anthropic->ask(
            prompt: $this->getQuestionsUserPrompt($session),
            system: $this->getQuestionsSystemPrompt(),
            temperature: 0.7,
        );

        $data = $this->extractJson($response);

        $sortOrder = 0;

        // Who: 1 question
        if (! empty($data['who'])) {
            $session->questions()->create([
                'step' => 'who',
                'question' => is_array($data['who']) ? $data['who']['question'] : $data['who'],
                'sort_order' => $sortOrder++,
            ]);
        }

        // What: 2 questions (do + don't)
        if (! empty($data['what_do'])) {
            $session->questions()->create([
                'step' => 'what',
                'question' => is_array($data['what_do']) ? $data['what_do']['question'] : $data['what_do'],
                'sort_order' => $sortOrder++,
            ]);
        }

        if (! empty($data['what_dont'])) {
            $session->questions()->create([
                'step' => 'what',
                'question' => is_array($data['what_dont']) ? $data['what_dont']['question'] : $data['what_dont'],
                'sort_order' => $sortOrder++,
            ]);
        }

        // Why: 1 question
        if (! empty($data['why'])) {
            $session->questions()->create([
                'step' => 'why',
                'question' => is_array($data['why']) ? $data['why']['question'] : $data['why'],
                'sort_order' => $sortOrder++,
            ]);
        }

        $session->update(['step' => 3]);
        $this->step = 3;
        $this->questionStep = 'who';
        unset($this->session);
    }

    /**
     * Submit answer for the current question step.
     */
    public function submitAnswer(AnthropicService $anthropic): void
    {
        $this->validate([
            'currentAnswer' => ['required', 'string', 'min:2'],
        ], [
            'currentAnswer.required' => 'Please type your answer.',
            'currentAnswer.min' => 'Please provide a bit more detail.',
        ]);

        $session = $this->session;
        $question = $this->currentQuestion($session);

        if (! $question) {
            return;
        }

        $question->update(['answer' => $this->currentAnswer]);
        $this->currentAnswer = '';

        // Refresh to get updated answers
        unset($this->session);
        $session = $this->session;

        $stepQuestions = $session->questionsForStep($this->questionStep)->get();
        $unanswered = $stepQuestions->filter(fn (DiagnosisQuestion $q): bool => $q->answer === null);
        $answeredCount = $stepQuestions->count() - $unanswered->count();

        // If there's still an unanswered question on this step, stay
        if ($unanswered->isNotEmpty()) {
            return;
        }

        // All questions on step answered — check if follow-up needed (max 2 per step)
        if ($answeredCount >= 2) {
            $this->advanceToNextStep($session, $anthropic);

            return;
        }

        // Only 1 answer — evaluate if follow-up needed
        $followUp = $this->evaluateAnswer($anthropic, $session, $question);

        if ($followUp) {
            $session->questions()->create([
                'step' => $this->questionStep,
                'question' => $followUp,
                'sort_order' => $question->sort_order + 1,
            ]);
            unset($this->session);
        } else {
            $this->advanceToNextStep($session, $anthropic);
        }
    }

    /**
     * Step 6: Generate final improved service description.
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
        $this->reset(['description', 'sessionId', 'currentAnswer', 'questionStep']);
        $this->step = 1;
        session()->forget('diagnosis_session_id');
    }

    public function render()
    {
        return view('livewire.diagnose')
            ->layout('layouts.guest');
    }

    // ── Helpers ──

    private function currentQuestion(DiagnosisSession $session): ?DiagnosisQuestion
    {
        return $session->questions
            ->where('step', $this->questionStep)
            ->whereNull('answer')
            ->first();
    }

    private function advanceToNextStep(DiagnosisSession $session, AnthropicService $anthropic): void
    {
        $next = match ($this->questionStep) {
            'who' => 'what',
            'what' => 'why',
            'why' => null,
        };

        if ($next) {
            $stepNum = array_flip(self::STEP_MAP)[$next];
            $session->update(['step' => $stepNum]);
            $this->step = $stepNum;
            $this->questionStep = $next;
            unset($this->session);
        } else {
            $this->generateResult($anthropic);
        }
    }

    private function evaluateAnswer(AnthropicService $anthropic, DiagnosisSession $session, DiagnosisQuestion $question): ?string
    {
        $response = $anthropic->ask(
            prompt: $this->getEvaluateUserPrompt($session, $question),
            system: $this->getEvaluateSystemPrompt(),
            temperature: 0.7,
        );

        $data = $this->extractJson($response);

        if (! empty($data['needs_followup']) && ! empty($data['followup_question'])) {
            return $data['followup_question'];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractJson(string $response): array
    {
        $trimmed = trim($response);

        $trimmed = preg_replace('/^```(?:json)?\s*/s', '', $trimmed);
        $trimmed = preg_replace('/\s*```$/s', '', $trimmed);

        $result = json_decode($trimmed, true);
        if (is_array($result) && $result !== []) {
            return $result;
        }

        if (preg_match('/\{[\s\S]*\}/s', $trimmed, $matches)) {
            $result = json_decode($matches[0], true);
            if (is_array($result) && $result !== []) {
                return $result;
            }
        }

        return [];
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

Also identify strengths and weaknesses.
Tone: Warm but direct. Quote the user's own words in feedback.
IMPORTANT: Never use the term "ideal client". Always say "best client" instead.
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
  "coach_message": "<2-3 sentences, warm+direct>"
}
PROMPT;
    }

    private function getQuestionsSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a warm, curious service design coach helping a freelancer
get clearer about their service. Ask open, friendly questions
that invite them to think and share — not interrogate.

Generate exactly 4 questions, one per key:

WHO — Help them describe who they work best with.
Ask about the kind of person or situation, not demographics.
Example: "Who's your best client? Tell me a bit about them
and what was going on when they reached out to you."

WHAT_DO — Help them describe what they actually do.
Keep it simple — what's the main thing?
Example: "In plain words, what do you do for your clients?
What would they say you helped them with?"

WHAT_DONT — Help them clarify what's outside their scope.
This sharpens the offer naturally.
Example: "What's something people sometimes ask you for
that you don't actually do?"

WHY — Help them describe what changes for the client.
Focus on results they can see or feel.
Example: "After working with you, what's different for
your client? What changes in their work or life?"

RULES:
- Each question: 1-2 sentences, warm and conversational
- Never use "ideal client" — always say "best client"
- Questions should feel like a friendly chat, not an exam
- Reference weaknesses from the diagnosis to fill gaps
CRITICAL: Return ONLY valid JSON. No markdown.
PROMPT;
    }

    private function getQuestionsUserPrompt(DiagnosisSession $session): string
    {
        $diagnosis = json_encode($session->diagnosis);

        return <<<PROMPT
Original description: \"\"\"{$session->service_description}\"\"\"
Diagnosis: \"\"\"{$diagnosis}\"\"\"

Help this freelancer get clearer about three things:
- WHO they work best with (the kind of person or situation)
- WHAT they do (their main service) and what they don't do
- WHY clients value them (what changes after working together)

Look at the diagnosis weaknesses. Generate 4 friendly questions
that help them think about the areas that need the most clarity.

Return JSON:
{
  "who": "a warm question about who they work best with...",
  "what_do": "a question about what they mainly do for clients...",
  "what_dont": "a question about what falls outside their service...",
  "why": "a question about what changes for clients after working together..."
}
PROMPT;
    }

    private function getEvaluateSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a friendly service design coach reviewing a freelancer's
answer. Decide if they've shared enough to work with, or if a
gentle follow-up would help them get clearer.

GOOD ENOUGH: The answer gives you something to work with —
a type of person, a real service, or a tangible result.
Example: "I help restaurant owners set up their online ordering"
— this is clear and usable even if not hyper-specific.

TOO VAGUE: The answer is so broad it could mean anything.
Example: "I help businesses grow" or "everyone" — there's
nothing concrete to build on.

IMPORTANT: Be generous. Most answers are good enough.
Only ask a follow-up if the answer is truly empty or
could apply to literally anyone. One useful detail is enough.

If follow-up is needed, ask ONE warm question that helps them
share a bit more. Keep it encouraging, not interrogating.

Never use "ideal client" — always say "best client".
CRITICAL: Return ONLY valid JSON. No markdown.
PROMPT;
    }

    private function getEvaluateUserPrompt(DiagnosisSession $session, DiagnosisQuestion $question): string
    {
        $stepLabel = match ($question->step) {
            'who' => 'WHO — describing who they work best with',
            'what' => 'WHAT — describing their service or what they don\'t do',
            'why' => 'WHY — what changes for their clients',
            default => 'getting clearer about their service',
        };

        return <<<PROMPT
A freelancer is getting clearer about their service.
Step: {$stepLabel}

Question asked: "{$question->question}"
Their answer: "{$question->answer}"

Is there enough here to work with, or would one friendly
follow-up help them share a bit more?

Return JSON:
{
  "needs_followup": true|false,
  "followup_question": "a warm follow-up to help them elaborate" or null
}
PROMPT;
    }

    private function getGenerateSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert copywriter combining service design,
marketing, business strategy, and behavioural psychology.

The freelancer has made 3 key DECISIONS during this session:
1. WHO — they named their specific best client and situation
2. WHAT — they committed to one core service AND drew a boundary
3. WHY — they claimed a specific, observable outcome

Your job: turn those decisions into a DRAMATICALLY BETTER
service description. Every sentence should trace back to one
of their answers. The new description should sound like the
freelancer on their best day — clear, confident, specific.

WRITING RULES (non-negotiable):
1. FIRST PERSON — "I help..." not "This service provides..."
2. SITUATION-ANCHORED — Open with the specific moment/trigger
   the client named in the WHO step
3. ONE CLEAR OFFER — Exactly what they committed to in WHAT
4. OUTCOME FIRST — Lead with the WHY (what changes)
5. BOUNDED — Weave the "what I don't do" answers into the
   description to sharpen the offer naturally
6. CONCRETE — "Can I picture this?" test for every sentence
7. SHORT — Description: 3-5 sentences. One-liner: <20 words.

BANNED: holistic, synergistic, leverage, transformative,
innovative, cutting-edge, game-changing, seamless, robust,
dynamic, paradigm, actionable, empower, disrupt.
Also BANNED: "ideal client" — always say "best client" instead.

NEXT STEPS: Generate 3 concrete, actionable next steps the user
should take right now with their new service description. These
should be practical actions like updating their website, testing
the new pitch, or reaching out to a specific type of client.

SCORING: Re-score all 5 dimensions (0-2 each).
The new total score MUST be higher than the original.
Each dimension should improve or stay the same, never decrease.

CRITICAL: Return ONLY valid JSON. No markdown, no explanation.
PROMPT;
    }

    private function getGenerateUserPrompt(DiagnosisSession $session): string
    {
        $oldScore = $session->diagnosis['clarity_score'] ?? 0;
        $diagnosis = json_encode($session->diagnosis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $qaByStep = [];
        foreach (self::STEP_ORDER as $stepName) {
            $stepQuestions = $session->questions->where('step', $stepName);
            $pairs = $stepQuestions->map(fn ($q): string => "Q: {$q->question}\nA: {$q->answer}")->implode("\n");
            $qaByStep[strtoupper($stepName)] = $pairs;
        }

        $whoQA = $qaByStep['WHO'] ?? '';
        $whatQA = $qaByStep['WHAT'] ?? '';
        $whyQA = $qaByStep['WHY'] ?? '';

        return <<<PROMPT
Write an IMPROVED service description using ALL the data below.
The original scored {$oldScore}/10. Your new version MUST score higher.

ORIGINAL DESCRIPTION:
\"\"\"{$session->service_description}\"\"\"

DIAGNOSIS (what was wrong):
{$diagnosis}

WHO (best client & situation):
{$whoQA}

WHAT (what they do AND what they don't do):
{$whatQA}

WHY (value & outcome):
{$whyQA}

USE the answers above as raw material. Weave the user's own words
into the new description. Use the "don't do" answers to sharpen
the offer — work them into the description naturally.

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
  "service_description": "<3-5 sentences, first person, boundaries woven in>",
  "value_proposition": "<1-2 sentences>",
  "target_audience": "<1 sentence, situation-based>",
  "one_liner": "<under 20 words>",
  "next_steps": [
    "concrete action step 1",
    "concrete action step 2",
    "concrete action step 3"
  ],
  "coach_message": "<2-3 sentences celebrating the improvement>",
  "variants": null
}
PROMPT;
    }
}
