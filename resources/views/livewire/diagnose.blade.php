@php
    $progressLabels = ['Scan', 'Who', 'What', 'Why', 'Result'];
    $progressMap = [1 => 0, 2 => 0, 3 => 1, 4 => 2, 5 => 3, 6 => 4];
    $activeSegment = $progressMap[$step] ?? 0;

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
                    <div class="mb-1.5 h-1.5 rounded-full {{ $i <= $activeSegment ? 'bg-blue-600' : 'bg-zinc-200 dark:bg-zinc-700' }}"></div>
                    <span class="block text-center text-xs {{ $i <= $activeSegment ? 'font-medium text-blue-600 dark:text-blue-400' : 'text-zinc-400 dark:text-zinc-500' }}">{{ $label }}</span>
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
                                    <span class="block mt-0.5 text-red-600 dark:text-red-400">{{ $w['explanation'] }}</span>
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
            <flux:button variant="primary" wire:click="proceedFromDiagnosis" class="w-full!" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="proceedFromDiagnosis">Let's improve it →</span>
                <span wire:loading wire:target="proceedFromDiagnosis">
                    <flux:icon.loading class="mr-2 size-4" />
                    Preparing your questions...
                </span>
            </flux:button>
        </div>

    {{-- ══════════════════════════════════════════════════ --}}
    {{-- STEPS 3/4/5: Question Wizard (describe/decide/value) --}}
    {{-- ══════════════════════════════════════════════════ --}}
    @elseif (in_array($step, [3, 4, 5]) && $this->session)
        @php
            $stepNames = [3 => 'describe', 4 => 'decide', 5 => 'value'];
            $stepName = $stepNames[$step];
            $questions = $this->session->questions->where('step', $stepName)->values();
            $currentQuestion = $questions->get($currentQuestionIndex);
            $totalQuestions = $questions->count();
        @endphp
        <div class="w-full">
            {{-- Question counter + sub-progress --}}
            <div class="mb-2 text-center">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    Question {{ $currentQuestionIndex + 1 }} of {{ $totalQuestions }}
                </flux:text>
            </div>

            <div class="mb-6 flex w-full gap-1">
                @for ($i = 0; $i < $totalQuestions; $i++)
                    <div class="h-1 flex-1 rounded-full {{ $i < $currentQuestionIndex ? 'bg-blue-600' : ($i === $currentQuestionIndex ? 'bg-blue-400' : 'bg-zinc-200 dark:bg-zinc-700') }}"></div>
                @endfor
            </div>

            @if ($currentQuestion)
                {{-- Intro text --}}
                @if ($currentQuestion->intro_text)
                    <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-300">
                        {{ $currentQuestion->intro_text }}
                    </div>
                @endif

                <flux:heading size="lg" class="mb-6 text-center">{{ $currentQuestion->question }}</flux:heading>

                <form wire:submit="submitQuestionAnswer" class="space-y-6">
                    @if ($currentQuestion->type === 'multi')
                        {{-- Multi-select: checkboxes --}}
                        <flux:checkbox.group wire:model="selectedOptions" class="flex-col">
                            @foreach ($currentQuestion->options as $opt)
                                <flux:checkbox value="{{ $opt['id'] }}" :label="$opt['label']" />
                            @endforeach
                        </flux:checkbox.group>
                        <flux:error name="selectedOptions" />

                        {{-- Other text input for multi --}}
                        @if (collect($currentQuestion->options)->contains('id', 'other') && in_array('other', $selectedOptions))
                            <flux:input wire:model="otherText" placeholder="Describe your situation..." />
                        @endif
                    @else
                        {{-- Single-select: radio cards --}}
                        <flux:radio.group wire:model="selectedOption" variant="cards" class="flex-col">
                            @foreach ($currentQuestion->options as $opt)
                                <flux:radio value="{{ $opt['id'] }}" :label="$opt['label']" />
                            @endforeach
                        </flux:radio.group>
                        <flux:error name="selectedOption" />

                        {{-- Other text input for single --}}
                        @if (collect($currentQuestion->options)->contains('id', 'other') && $selectedOption === 'other')
                            <flux:input wire:model="otherText" placeholder="Describe your situation..." />
                        @endif
                    @endif

                    <flux:button variant="primary" type="submit" class="w-full!" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="submitQuestionAnswer">
                            @if ($currentQuestionIndex + 1 < $totalQuestions)
                                Next →
                            @else
                                Continue →
                            @endif
                        </span>
                        <span wire:loading wire:target="submitQuestionAnswer">
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
            $newScore = $result['new_clarity_score'] ?? 0;
            $oldDims = $oldDiag['dimension_scores'] ?? [];
            $newDims = $result['new_dimension_scores'] ?? [];
            $dimLabels = ['audience' => 'Audience', 'problem' => 'Problem', 'offer' => 'Offer', 'value' => 'Value', 'language' => 'Language'];
        @endphp
        <div class="w-full">
            {{-- Before/After Score --}}
            <div class="mb-6 flex items-center justify-center gap-6">
                <div class="text-center">
                    <div class="text-4xl font-bold {{ $scoreColor($oldScore) }}">{{ $oldScore }}</div>
                    <flux:text class="text-xs text-zinc-500">Before</flux:text>
                </div>
                <flux:icon.arrow-right class="size-6 text-zinc-400" />
                <div class="text-center">
                    <div class="text-5xl font-bold {{ $scoreColor($newScore) }}">{{ $newScore }}</div>
                    <flux:text class="text-xs text-zinc-500">After</flux:text>
                </div>
            </div>

            {{-- Dimension comparison --}}
            <div class="mb-6 space-y-2">
                @foreach ($dimLabels as $key => $label)
                    @php
                        $oldS = $oldDims[$key]['score'] ?? 0;
                        $newS = $newDims[$key]['score'] ?? 0;
                    @endphp
                    <div class="flex items-center gap-3 text-sm">
                        <span class="w-20 shrink-0 font-medium text-zinc-700 dark:text-zinc-300">{{ $label }}</span>
                        <span class="inline-block size-2.5 rounded-full {{ $dimColor($oldS) }}"></span>
                        <span class="text-zinc-500">{{ $oldS }}</span>
                        <span class="text-zinc-400">→</span>
                        <span class="inline-block size-2.5 rounded-full {{ $dimColor($newS) }}"></span>
                        <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $newS }}</span>
                    </div>
                @endforeach
            </div>

            {{-- Coach message --}}
            @if (! empty($result['coach_message']))
                <div class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4 text-sm leading-relaxed text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-300">
                    {{ $result['coach_message'] }}
                </div>
            @endif

            {{-- Service description --}}
            @if (! empty($result['service_description']))
                <div x-data="{ copied: false }" class="relative mb-4 rounded-xl border border-blue-200 bg-blue-50 p-6 dark:border-blue-800 dark:bg-blue-950">
                    <flux:text class="text-lg leading-relaxed text-blue-900 dark:text-blue-100">
                        {{ $result['service_description'] }}
                    </flux:text>
                    <button
                        @click="navigator.clipboard.writeText(@js($result['service_description'])); copied = true; setTimeout(() => copied = false, 2000)"
                        class="absolute right-3 top-3 rounded-md p-1.5 text-blue-400 hover:bg-blue-100 hover:text-blue-600 dark:hover:bg-blue-900"
                    >
                        <template x-if="!copied"><flux:icon.clipboard class="size-4" /></template>
                        <template x-if="copied"><flux:icon.check class="size-4" /></template>
                    </button>
                </div>
            @endif

            {{-- One-liner --}}
            @if (! empty($result['one_liner']))
                <div class="mb-6 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:text class="mb-1 text-xs font-medium uppercase tracking-wide text-zinc-400">One-liner</flux:text>
                    <flux:text class="text-base font-medium text-zinc-800 dark:text-zinc-200">{{ $result['one_liner'] }}</flux:text>
                </div>
            @endif

            <div class="grid gap-4 md:grid-cols-2">
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

            {{-- Boundaries --}}
            @if (! empty($result['boundaries']))
                <div class="mt-4">
                    <flux:heading size="sm" class="mb-2">Boundaries</flux:heading>
                    <ul class="space-y-1.5">
                        @foreach ($result['boundaries'] as $boundary)
                            <li class="flex items-start gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                                <flux:icon.x-mark class="mt-0.5 size-4 shrink-0 text-red-500" />
                                {{ $boundary }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Variants --}}
            @if (! empty($result['variants']))
                <div class="mt-6">
                    <flux:heading size="sm" class="mb-2">Variants</flux:heading>
                    <flux:text class="mb-3 text-sm text-zinc-500">{{ $result['variants']['message'] ?? 'We noticed some areas could go either way. Pick your preferred version:' }}</flux:text>
                    <div class="grid gap-3 md:grid-cols-2">
                        @foreach ($result['variants']['options'] ?? [] as $variant)
                            <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
                                <flux:text class="text-sm font-medium">{{ $variant['label'] ?? $variant['id'] ?? '' }}</flux:text>
                            </div>
                        @endforeach
                    </div>
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
