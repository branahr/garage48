<div class="flex flex-col items-center">
    {{-- Header --}}
    <div class="mb-8 flex w-full items-center justify-between">
        <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">Service Clarity AI</h1>
        <flux:badge color="zinc" size="sm">Step 1: Diagnose</flux:badge>
    </div>

    {{-- Progress bar --}}
    <div class="mb-8 flex w-full gap-2">
        <div class="h-1 flex-1 rounded-full bg-blue-600"></div>
        <div class="h-1 flex-1 rounded-full bg-zinc-200 dark:bg-zinc-700"></div>
        <div class="h-1 flex-1 rounded-full bg-zinc-200 dark:bg-zinc-700"></div>
        <div class="h-1 flex-1 rounded-full bg-zinc-200 dark:bg-zinc-700"></div>
        <div class="h-1 flex-1 rounded-full bg-zinc-200 dark:bg-zinc-700"></div>
    </div>

    {{-- Form --}}
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
                :disabled="$submitted"
            />
            <flux:error name="description" />

            @if (! $submitted)
                <flux:button
                    variant="primary"
                    type="submit"
                    class="w-full!"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="diagnose">Diagnose my service →</span>
                    <span wire:loading wire:target="diagnose">
                        <flux:icon.loading class="mr-2 size-4" />
                        Analyzing your service...
                    </span>
                </flux:button>
            @endif
        </form>
    </div>

    {{-- Results --}}
    @if ($submitted && ! empty($analysis))
        <div class="mt-10 w-full">
            <flux:separator class="mb-8" />

            {{-- Score --}}
            <div class="mb-6 text-center">
                <div class="text-5xl font-bold {{ ($analysis['score'] ?? 0) >= 7 ? 'text-green-600 dark:text-green-400' : (($analysis['score'] ?? 0) >= 4 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') }}">
                    {{ $analysis['score'] ?? '?' }}/10
                </div>
                <flux:text class="mt-2 text-zinc-600 dark:text-zinc-400">{{ $analysis['summary'] ?? '' }}</flux:text>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                {{-- Strengths --}}
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

                {{-- Weaknesses --}}
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

            {{-- Details --}}
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

                @if (! empty($analysis['decision_questions_needed']))
                    <flux:callout>
                        <flux:callout.heading>Next: {{ $analysis['decision_questions_needed'] }} questions needed</flux:callout.heading>
                        <flux:callout.text>{{ $analysis['decision_questions_reason'] ?? '' }}</flux:callout.text>
                    </flux:callout>
                @endif
            </div>

            <div class="mt-8 flex justify-center">
                <flux:button wire:click="startOver" variant="subtle">
                    ← Start over
                </flux:button>
            </div>
        </div>
    @endif
</div>
