<?php

namespace App\Livewire;

use App\Services\AnthropicService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Diagnose your service')]
class Diagnose extends Component
{
    public string $description = '';

    /** @var array<string, mixed> */
    public array $analysis = [];

    public bool $submitted = false;

    public function mount(): void
    {
        $this->description = session('service_description', '');
    }

    public function diagnose(AnthropicService $anthropic): void
    {
        $validated = $this->validate([
            'description' => ['required', 'string', 'min:20', 'max:5000'],
        ], [
            'description.required' => 'Please describe your service before submitting.',
            'description.min' => 'Please provide at least 20 characters to get a meaningful analysis.',
        ]);

        session(['service_description' => $validated['description']]);

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

        // Strip markdown code fences that Claude sometimes wraps around JSON
        $json = preg_replace('/^```(?:json)?\s*|\s*```$/s', '', trim($response));

        $this->analysis = (array) json_decode($json, true);
        $this->submitted = true;
    }

    public function startOver(): void
    {
        $this->reset(['description', 'submitted']);
        $this->analysis = [];
        session()->forget('service_description');
    }

    public function render()
    {
        return view('livewire.diagnose')
            ->layout('layouts.guest');
    }
}
