@php
    $progressLabels = ['Scan', 'Who?', 'What?', 'Why?', 'Recap'];
    $progressMap = [1 => 0, 2 => 0, 3 => 1, 4 => 2, 5 => 3, 6 => 4];
    $activeSegment = $progressMap[$step] ?? 0;

    $stepThemeLabel = match ($questionStep ?? 'who') {
        'who' => 'Who is your best client?',
        'what' => 'What do you do?',
        'why' => 'Why should they choose you?',
        default => '',
    };

    $scoreColor = function (int $score): string {
        return match (true) {
            $score >= 9 => 'text-blue-600 dark:text-blue-400',
            $score >= 7 => 'text-green-600 dark:text-green-400',
            $score >= 4 => 'text-yellow-600 dark:text-yellow-400',
            default => 'text-red-600 dark:text-red-400',
        };
    };

    $dimColor = function (int $score): string {
        return match ($score) {
            2 => 'bg-green-500',
            1 => 'bg-yellow-500',
            default => 'bg-red-500',
        };
    };
@endphp

<div class="flex flex-col items-center">
    {{-- Header --}}
    <div class="mb-6 flex w-full items-center justify-between">
        <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">Service Clarity AI</h1>
    </div>

    {{-- Progress bar with labels --}}
    <div class="mb-8 w-full">
        <div class="flex w-full gap-2">
            @foreach ($progressLabels as $i => $label)
                <div class="flex-1">
                    <div class="mb-1.5 h-1.5 rounded-full {{ $i <= $activeSegment ? 'bg-green-600' : 'bg-zinc-200 dark:bg-zinc-700' }}"></div>
                    <span class="block text-center text-xs {{ $i <= $activeSegment ? 'font-medium text-green-600 dark:text-green-400' : 'text-zinc-400 dark:text-zinc-500' }}">{{ $label }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ══════════════════════════════════════ --}}
    {{-- STEP 1: INPUT — Service description   --}}
    {{-- ══════════════════════════════════════ --}}
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
                    <span wire:loading.remove wire:target="diagnose">Scan my service →</span>
                    <span wire:loading wire:target="diagnose">
                        <flux:icon.loading class="mr-2 size-4" />
                        Analyzing your service...
                    </span>
                </flux:button>
            </form>
        </div>

    {{-- ══════════════════════════════════════ --}}
    {{-- STEP 2: DIAGNOSE — 5 dimensions       --}}
    {{-- ══════════════════════════════════════ --}}
    @elseif ($step === 2 && $this->session)
        @php
            $d = $this->session->diagnosis;
            $score = $d['clarity_score'] ?? 0;
            $dims = $d['dimension_scores'] ?? [];
            $dimLabels = ['audience' => 'Audience', 'problem' => 'Problem', 'offer' => 'Offer', 'value' => 'Value', 'language' => 'Language'];
        @endphp
        <div class="w-full">
            {{-- Score --}}
            <div class="mb-6 text-center">
                <div class="text-6xl font-bold {{ $scoreColor($score) }}">{{ $score }}/10</div>
                <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Clarity Score</flux:text>
            </div>

            {{-- Coach message --}}
            @if (! empty($d['coach_message']))
                <div class="mb-6 rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm leading-relaxed text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                    {{ $d['coach_message'] }}
                </div>
            @endif

            {{-- 5 Dimension bars --}}
            <div class="mb-6 space-y-3">
                @foreach ($dimLabels as $key => $label)
                    @php $ds = $dims[$key] ?? ['score' => 0, 'reason' => '']; @endphp
                    <div class="flex items-start gap-3">
                        <div class="flex w-24 shrink-0 items-center gap-2">
                            <span class="inline-block size-2.5 rounded-full {{ $dimColor($ds['score']) }}"></span>
                            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $label }}</span>
                        </div>
                        <div class="text-sm text-zinc-700 dark:text-zinc-300">
                            <span class="font-semibold">{{ $ds['score'] }}/2</span>
                            <span class="ml-1 text-zinc-500 dark:text-zinc-400">— {{ $ds['reason'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Strengths --}}
            @if (! empty($d['strengths']))
                <div class="mb-4 rounded-xl border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950">
                    <flux:heading size="sm" class="mb-2 text-green-800 dark:text-green-300">Strengths</flux:heading>
                    <ul class="space-y-1.5">
                        @foreach ($d['strengths'] as $s)
                            <li class="flex items-start gap-2 text-sm text-green-700 dark:text-green-400">
                                <flux:icon.check class="mt-0.5 size-4 shrink-0" />
                                <span><strong>{{ $s['area'] ?? '' }}:</strong> {{ $s['feedback'] ?? $s }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Weaknesses --}}
            @if (! empty($d['weaknesses']))
                <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950">
                    <flux:heading size="sm" class="mb-2 text-red-800 dark:text-red-300">Weaknesses</flux:heading>
                    <ul class="space-y-2">
                        @foreach ($d['weaknesses'] as $w)
                            <li class="text-sm text-red-700 dark:text-red-400">
                                <flux:badge color="red" size="sm" class="mr-1">{{ $w['category'] ?? '' }}</flux:badge>
                                <strong>{{ $w['issue'] ?? '' }}</strong>
                                @if (! empty($w['explanation']))
                                    <span class="mt-0.5 block text-red-600 dark:text-red-400">{{ $w['explanation'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Collapsible original description --}}
            <div x-data="{ open: false }" class="mb-6">
                <button @click="open = !open" class="flex items-center gap-1.5 text-sm text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300">
                    <span x-text="open ? '▼' : '▶'" class="text-xs"></span>
                    View my original description
                </button>
                <div x-show="open" x-collapse class="mt-2 rounded-lg bg-zinc-100 p-4 text-sm text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                    {{ $this->session->service_description }}
                </div>
            </div>

            {{-- Continue button --}}
            <flux:button variant="primary" wire:click="proceedToQuestions" class="w-full!" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="proceedToQuestions">Let's improve it →</span>
                <span wire:loading wire:target="proceedToQuestions">
                    <flux:icon.loading class="mr-2 size-4" />
                    Preparing your questions...
                </span>
            </flux:button>
        </div>

    {{-- ══════════════════════════════════════════════════ --}}
    {{-- STEPS 3/4/5: Who? / What? / Why? questions        --}}
    {{-- ══════════════════════════════════════════════════ --}}
    @elseif (in_array($step, [3, 4, 5]) && $this->session)
        @php
            $stepQuestions = $this->session->questions->where('step', $questionStep);
            $currentQuestion = $stepQuestions->whereNull('answer')->first();
            $answeredOnStep = $stepQuestions->filter(fn ($q) => $q->answer !== null)->count();
        @endphp
        <div class="w-full">
            {{-- Step theme --}}
            <div class="mb-2 text-center">
                <flux:heading size="lg" class="mb-1">{{ $stepThemeLabel }}</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    Step {{ array_search($questionStep, ['who', 'what', 'why']) + 1 }} of 3
                    @if ($answeredOnStep > 0) — follow-up @endif
                </flux:text>
            </div>

            {{-- 3-step sub-progress --}}
            <div class="mb-6 flex w-full gap-1.5">
                @foreach (['who', 'what', 'why'] as $s)
                    @php
                        $sQuestions = $this->session->questions->where('step', $s);
                        $allAnswered = $sQuestions->count() > 0 && $sQuestions->every(fn ($q) => $q->answer !== null);
                    @endphp
                    <div class="h-1 flex-1 rounded-full {{ $allAnswered ? 'bg-green-600' : ($s === $questionStep ? 'bg-green-400' : 'bg-zinc-200 dark:bg-zinc-700') }}"></div>
                @endforeach
            </div>

            @if ($currentQuestion)
                <div class="mb-6 rounded-xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:text class="text-base leading-relaxed text-zinc-800 dark:text-zinc-200">
                        {{ $currentQuestion->question }}
                    </flux:text>
                </div>

                <form wire:submit="submitAnswer" class="space-y-6">
                    <flux:textarea
                        wire:model="currentAnswer"
                        placeholder="Type your answer here..."
                        rows="4"
                        resize="vertical"
                    />
                    <flux:error name="currentAnswer" />

                    <flux:button variant="primary" type="submit" class="w-full!" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="submitAnswer">Next →</span>
                        <span wire:loading wire:target="submitAnswer">
                            <flux:icon.loading class="mr-2 size-4" />
                            Processing...
                        </span>
                    </flux:button>
                </form>
            @endif
        </div>

    {{-- ══════════════════════════════════════ --}}
    {{-- STEP 6: RESULT — Final output         --}}
    {{-- ══════════════════════════════════════ --}}
    @elseif ($step === 6 && $this->session)
        @php
            $result = $this->session->final_result;
            $oldDiag = $this->session->diagnosis;
            $oldScore = $oldDiag['clarity_score'] ?? 0;
        @endphp
        <div class="w-full">
            {{-- Header --}}
            <div class="mb-6 flex items-center justify-between">
                <flux:heading size="lg">Your new service description</flux:heading>
                <flux:badge color="green" size="sm">DONE</flux:badge>
            </div>

            {{-- Score transformation --}}
            <div class="mb-8 flex items-center gap-5">
                {{-- Before circle --}}
                <div class="flex flex-col items-center">
                    <div class="flex size-16 items-center justify-center rounded-full border-4 border-red-500">
                        <span class="text-2xl font-bold text-red-600">{{ $oldScore }}</span>
                    </div>
                    <span class="mt-1 text-xs text-zinc-500">before</span>
                </div>

                <flux:icon.arrow-right class="size-5 text-zinc-400" />

                {{-- Client-Ready badge --}}
                <div class="flex flex-col items-center">
                    <div class="flex size-16 items-center justify-center rounded-full border-4 border-green-500 bg-green-50 dark:bg-green-950">
                        <flux:icon.check class="size-8 text-green-600" />
                    </div>
                    <span class="mt-1 text-xs font-medium text-green-600">client-ready</span>
                </div>

                {{-- Transformation text --}}
                <div class="ml-2">
                    <div class="text-lg font-bold text-zinc-900 dark:text-zinc-100">
                        Transformed from {{ $oldScore }}/10 to client-ready
                    </div>
                    <flux:text class="text-sm text-zinc-500">
                        Your service description is now clear, specific, and ready to attract clients.
                    </flux:text>
                </div>
            </div>

            {{-- Before / After comparison cards --}}
            <div class="mb-8 grid gap-4 md:grid-cols-2">
                {{-- Before card --}}
                <div class="rounded-xl border border-red-200 bg-red-50 p-5 dark:border-red-900 dark:bg-red-950">
                    <div class="mb-3 text-xs font-bold uppercase tracking-widest text-red-600 dark:text-red-400">Before</div>
                    <flux:text class="text-sm leading-relaxed text-red-900 dark:text-red-200">
                        {{ Str::limit($this->session->service_description, 200) }}
                    </flux:text>
                </div>

                {{-- After card --}}
                @if (! empty($result['service_description']))
                    <div x-data="{ copied: false }" class="relative rounded-xl border border-green-200 bg-green-50 p-5 dark:border-green-900 dark:bg-green-950">
                        <div class="mb-3 text-xs font-bold uppercase tracking-widest text-green-600 dark:text-green-400">After</div>
                        <flux:text class="text-sm leading-relaxed text-green-900 dark:text-green-200">
                            {{ $result['service_description'] }}
                        </flux:text>
                        <button
                            @click="navigator.clipboard.writeText(@js($result['service_description'])); copied = true; setTimeout(() => copied = false, 2000)"
                            class="absolute right-3 top-3 rounded-md p-1.5 text-green-400 hover:bg-green-100 hover:text-green-600 dark:hover:bg-green-900"
                        >
                            <template x-if="!copied"><flux:icon.clipboard class="size-4" /></template>
                            <template x-if="copied"><flux:icon.check class="size-4" /></template>
                        </button>
                    </div>
                @endif
            </div>

            {{-- One-liner --}}
            @if (! empty($result['one_liner']))
                <div class="mb-6 rounded-xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:text class="mb-1 text-xs font-bold uppercase tracking-widest text-zinc-400">One-liner</flux:text>
                    <flux:text class="text-base font-medium text-zinc-800 dark:text-zinc-200">{{ $result['one_liner'] }}</flux:text>
                </div>
            @endif

            {{-- Target audience + Value proposition --}}
            <div class="mb-6 grid gap-4 md:grid-cols-2">
                @if (! empty($result['target_audience']))
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-800">
                        <flux:text class="mb-1 text-xs font-bold uppercase tracking-widest text-zinc-400">Target audience</flux:text>
                        <flux:text class="text-sm text-zinc-700 dark:text-zinc-300">{{ $result['target_audience'] }}</flux:text>
                    </div>
                @endif

                @if (! empty($result['value_proposition']))
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-800">
                        <flux:text class="mb-1 text-xs font-bold uppercase tracking-widest text-zinc-400">Value proposition</flux:text>
                        <flux:text class="text-sm text-zinc-700 dark:text-zinc-300">{{ $result['value_proposition'] }}</flux:text>
                    </div>
                @endif
            </div>

            {{-- Next steps --}}
            @if (! empty($result['next_steps']))
                <div class="mb-6 rounded-xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:text class="mb-3 text-xs font-bold uppercase tracking-widest text-zinc-400">Next steps</flux:text>
                    <ul class="space-y-2">
                        @foreach ($result['next_steps'] as $i => $nextStep)
                            <li class="flex items-start gap-3 text-sm text-zinc-700 dark:text-zinc-300">
                                <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-green-100 text-xs font-bold text-green-700 dark:bg-green-900 dark:text-green-300">{{ $i + 1 }}</span>
                                {{ $nextStep }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Coach message --}}
            @if (! empty($result['coach_message']))
                <div class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4 text-sm leading-relaxed text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-300">
                    {{ $result['coach_message'] }}
                </div>
            @endif

            {{-- Action buttons --}}
            @php
                $clipboardText = ($result['service_description'] ?? '') . "\n\n" .
                    'One-liner: ' . ($result['one_liner'] ?? '') . "\n" .
                    'Target audience: ' . ($result['target_audience'] ?? '') . "\n" .
                    'Value proposition: ' . ($result['value_proposition'] ?? '');
            @endphp
            <div x-data="{ copied: false }" class="mt-8 flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
                <flux:button
                    variant="primary"
                    icon="clipboard"
                    @click="navigator.clipboard.writeText(@js($clipboardText)); copied = true; setTimeout(() => copied = false, 2000)"
                >
                    <span x-show="!copied">Copy to clipboard</span>
                    <span x-show="copied" x-cloak>Copied!</span>
                </flux:button>

                <flux:button variant="filled" icon="envelope" onclick="alert('Email functionality coming soon!')">
                    Send to email
                </flux:button>

                <flux:button variant="filled" icon="document-arrow-down" onclick="alert('PDF export coming soon!')">
                    Export to PDF
                </flux:button>
            </div>

            <div class="mt-4 flex justify-center">
                <flux:button wire:click="startOver" variant="subtle">
                    ← Start over with a new service
                </flux:button>
            </div>
        </div>
    @endif
</div>
