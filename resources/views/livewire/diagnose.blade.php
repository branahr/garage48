<div class="flex flex-col items-center">
    {{-- Header --}}
    <div class="mb-8 flex w-full items-center justify-between">
        <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">Service Clarity AI</h1>
        <flux:badge color="zinc" size="sm">
            @if ($step === 1) Step 1: Describe
            @elseif ($step === 2) Step 2: Diagnosis
            @elseif ($step === 3) Step 3: Questions
            @elseif ($step === 4) Step 4: Generating
            @else Step 5: Result
            @endif
        </flux:badge>
    </div>

    {{-- Progress bar --}}
    <div class="mb-8 flex w-full gap-2">
        @for ($i = 1; $i <= 5; $i++)
            <div class="h-1 flex-1 rounded-full {{ $i <= $step ? 'bg-blue-600' : 'bg-zinc-200 dark:bg-zinc-700' }}"></div>
        @endfor
    </div>

    {{-- STEP 1: Service description --}}
    @if ($step === 1)
        <div class="w-full text-center">
            <flux:heading size="lg" class="mb-2">Describe your service</flux:heading>
            <flux:text class="mb-6 text-zinc-500 dark:text-zinc-400">
                Paste your current service description — the way you'd explain it to a potential client.
            </flux:text>

            <form wire:submit="diagnose" class="space-y-6">
                <flux:textarea
                    wire:model="description"
                    placeholder="I help people and businesses with various design and strategy needs. I offer branding, UX design, service design, and consulting..."
                    rows="6"
                    resize="vertical"
                />
                <flux:error name="description" />

                <flux:button variant="primary" type="submit" class="w-full!" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="diagnose">Diagnose my service →</span>
                    <span wire:loading wire:target="diagnose">
                        <flux:icon.loading class="mr-2 size-4" />
                        Analyzing your service...
                    </span>
                </flux:button>
            </form>
        </div>

    {{-- STEP 2: Show diagnosis + generate questions --}}
    @elseif ($step === 2 && $this->session)
        @php $analysis = $this->session->diagnosis; @endphp
        <div class="w-full">
            <div class="mb-6 text-center">
                <div class="text-5xl font-bold {{ ($analysis['score'] ?? 0) >= 7 ? 'text-green-600 dark:text-green-400' : (($analysis['score'] ?? 0) >= 4 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') }}">
                    {{ $analysis['score'] ?? '?' }}/10
                </div>
                <flux:text class="mt-2 text-zinc-600 dark:text-zinc-400">{{ $analysis['summary'] ?? '' }}</flux:text>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                @if (! empty($analysis['strengths']))
                    <div class="rounded-xl border border-green-200 bg-green-50 p-5 dark:border-green-800 dark:bg-green-950">
                        <flux:heading size="sm" class="mb-3 text-green-800 dark:text-green-300">Strengths</flux:heading>
                        <ul class="space-y-2">
                            @foreach ($analysis['strengths'] as $strength)
                                <li class="flex items-start gap-2 text-sm text-green-700 dark:text-green-400">
                                    <flux:icon.check class="mt-0.5 size-4 shrink-0" />
                                    {{ $strength }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (! empty($analysis['weaknesses']))
                    <div class="rounded-xl border border-red-200 bg-red-50 p-5 dark:border-red-800 dark:bg-red-950">
                        <flux:heading size="sm" class="mb-3 text-red-800 dark:text-red-300">Weaknesses</flux:heading>
                        <ul class="space-y-2">
                            @foreach ($analysis['weaknesses'] as $weakness)
                                <li class="flex items-start gap-2 text-sm text-red-700 dark:text-red-400">
                                    <flux:icon.x-mark class="mt-0.5 size-4 shrink-0" />
                                    {{ $weakness }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="mt-6 space-y-4">
                @if (! empty($analysis['missing_target_audience']))
                    <flux:callout>
                        <flux:callout.heading>Target audience</flux:callout.heading>
                        <flux:callout.text>{{ $analysis['missing_target_audience'] }}</flux:callout.text>
                    </flux:callout>
                @endif

                @if (! empty($analysis['missing_value_proposition']))
                    <flux:callout>
                        <flux:callout.heading>Value proposition</flux:callout.heading>
                        <flux:callout.text>{{ $analysis['missing_value_proposition'] }}</flux:callout.text>
                    </flux:callout>
                @endif

                @if (! empty($analysis['jargon']))
                    <div class="flex flex-wrap gap-2">
                        <flux:text class="mr-1 text-sm font-medium text-zinc-700 dark:text-zinc-300">Jargon detected:</flux:text>
                        @foreach ($analysis['jargon'] as $term)
                            <flux:badge color="yellow" size="sm">{{ $term }}</flux:badge>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="mt-8">
                <flux:button variant="primary" wire:click="generateQuestions" class="w-full!" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="generateQuestions">Let's improve it — answer {{ $analysis['decision_questions_needed'] ?? 'a few' }} questions →</span>
                    <span wire:loading wire:target="generateQuestions">
                        <flux:icon.loading class="mr-2 size-4" />
                        Generating personalized questions...
                    </span>
                </flux:button>
            </div>
        </div>

    {{-- STEP 3: Answer questions one by one --}}
    @elseif ($step === 3 && $this->session)
        @php
            $questions = $this->session->questions;
            $currentQuestion = $questions->get($currentQuestionIndex);
            $totalQuestions = $questions->count();
        @endphp
        <div class="w-full text-center">
            <flux:text class="mb-2 text-sm text-zinc-500 dark:text-zinc-400">
                Question {{ $currentQuestionIndex + 1 }} of {{ $totalQuestions }}
            </flux:text>

            <div class="mb-2 flex w-full gap-1">
                @for ($i = 0; $i < $totalQuestions; $i++)
                    <div class="h-1 flex-1 rounded-full {{ $i < $currentQuestionIndex ? 'bg-blue-600' : ($i === $currentQuestionIndex ? 'bg-blue-400' : 'bg-zinc-200 dark:bg-zinc-700') }}"></div>
                @endfor
            </div>

            @if ($currentQuestion)
                <flux:heading size="lg" class="mb-6 mt-4">{{ $currentQuestion->question }}</flux:heading>

                <form wire:submit="submitAnswer" class="space-y-6">
                    <flux:textarea
                        wire:model="currentAnswer"
                        placeholder="Your answer..."
                        rows="3"
                        resize="vertical"
                    />
                    <flux:error name="currentAnswer" />

                    <flux:button variant="primary" type="submit" class="w-full!">
                        @if ($currentQuestionIndex + 1 < $totalQuestions)
                            Next question →
                        @else
                            Finish & get my result →
                        @endif
                    </flux:button>
                </form>

                {{-- Show previous answers --}}
                @if ($currentQuestionIndex > 0)
                    <div class="mt-8 text-left">
                        <flux:separator class="mb-4" />
                        <flux:text class="mb-3 text-xs font-medium uppercase tracking-wide text-zinc-400">Previous answers</flux:text>
                        <div class="space-y-3">
                            @foreach ($questions->take($currentQuestionIndex) as $answered)
                                <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                                    <flux:text class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ $answered->question }}</flux:text>
                                    <flux:text class="mt-1 text-sm">{{ $answered->answer }}</flux:text>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif
        </div>

    {{-- STEP 4: Generating final result --}}
    @elseif ($step === 4 && $this->session)
        <div class="w-full text-center">
            <flux:heading size="lg" class="mb-4">All answers collected!</flux:heading>
            <flux:text class="mb-8 text-zinc-500 dark:text-zinc-400">
                Ready to generate your personalized service recommendation.
            </flux:text>

            <flux:button variant="primary" wire:click="generateResult" class="w-full!" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="generateResult">Generate my result →</span>
                <span wire:loading wire:target="generateResult">
                    <flux:icon.loading class="mr-2 size-4" />
                    Creating your recommendation...
                </span>
            </flux:button>
        </div>

    {{-- STEP 5: Final result --}}
    @elseif ($step === 5 && $this->session)
        @php $result = $this->session->final_result; @endphp
        <div class="w-full">
            <div class="mb-8 text-center">
                <flux:heading size="lg" class="mb-2">Your improved service description</flux:heading>
                <flux:text class="text-zinc-500 dark:text-zinc-400">Here's your clarity-optimized service, based on your answers.</flux:text>
            </div>

            @if (! empty($result['rewritten_description']))
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-6 dark:border-blue-800 dark:bg-blue-950">
                    <flux:text class="text-lg leading-relaxed text-blue-900 dark:text-blue-100">
                        {{ $result['rewritten_description'] }}
                    </flux:text>
                </div>
            @endif

            <div class="mt-6 grid gap-4 md:grid-cols-2">
                @if (! empty($result['target_audience']))
                    <flux:callout>
                        <flux:callout.heading>Target audience</flux:callout.heading>
                        <flux:callout.text>{{ $result['target_audience'] }}</flux:callout.text>
                    </flux:callout>
                @endif

                @if (! empty($result['value_proposition']))
                    <flux:callout>
                        <flux:callout.heading>Value proposition</flux:callout.heading>
                        <flux:callout.text>{{ $result['value_proposition'] }}</flux:callout.text>
                    </flux:callout>
                @endif
            </div>

            @if (! empty($result['positioning_statement']))
                <div class="mt-4">
                    <flux:callout>
                        <flux:callout.heading>Positioning statement</flux:callout.heading>
                        <flux:callout.text>{{ $result['positioning_statement'] }}</flux:callout.text>
                    </flux:callout>
                </div>
            @endif

            @if (! empty($result['next_steps']))
                <div class="mt-6">
                    <flux:heading size="sm" class="mb-3">Next steps</flux:heading>
                    <ul class="space-y-2">
                        @foreach ($result['next_steps'] as $nextStep)
                            <li class="flex items-start gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                                <flux:icon.check class="mt-0.5 size-4 shrink-0 text-blue-600" />
                                {{ $nextStep }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-8 flex justify-center">
                <flux:button wire:click="startOver" variant="subtle">
                    ← Start over with a new service
                </flux:button>
            </div>
        </div>
    @endif
</div>
