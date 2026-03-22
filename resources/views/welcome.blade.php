<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Service Clarity') }} — Reimagine your service description</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=playfair-display:400,700,900&family=plus-jakarta-sans:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen overflow-hidden font-['Plus_Jakarta_Sans',ui-sans-serif,system-ui,sans-serif]">
        {{-- Background mesh gradient --}}
        <div class="fixed inset-0 -z-10">
            <div class="absolute inset-0 bg-white"></div>
            <div class="absolute -top-1/4 -left-1/4 h-[800px] w-[800px] rounded-full bg-[#FFE0B2] opacity-40 blur-[120px]"></div>
            <div class="absolute -right-1/4 -bottom-1/4 h-[800px] w-[800px] rounded-full bg-[#E9D5FF] opacity-40 blur-[120px]"></div>
            <div class="absolute top-1/3 right-1/3 h-[400px] w-[400px] rounded-full bg-[#FFE0B2] opacity-20 blur-[100px]"></div>
        </div>

        {{-- Hidden auth nav (preserved but invisible) --}}
        @if (Route::has('login'))
            <nav class="hidden">
                @auth
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                @else
                    <a href="{{ route('login') }}">Log in</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}">Register</a>
                    @endif
                @endauth
            </nav>
        @endif

        {{-- Main content --}}
        <main class="relative flex min-h-screen items-center">
            <div class="mx-auto w-full max-w-7xl px-6 py-12 sm:px-8 lg:px-16">
                <div class="flex flex-col items-center gap-12 lg:flex-row lg:items-center lg:justify-between lg:gap-16">

                    {{-- Left column: text content --}}
                    <div class="max-w-xl flex-1 text-center lg:text-left">
                        {{-- Brand --}}
                        <p class="mb-8 text-lg font-semibold italic text-[#1A1A1A]/60 tracking-wide"></p>

                        {{-- Heading --}}
                        <h1 class="mb-6 font-['Playfair_Display',serif] text-4xl font-black uppercase leading-[1.1] tracking-tight text-[#1A1A1A] sm:text-5xl lg:text-[3.2rem]">
                            Service Clarity:
                            expert in your pocket
                        </h1>

                        {{-- Subheading --}}
                        <p class="mb-10 text-lg text-[#1A1A1A]/60">
                            
                        </p>

                        {{-- CTA Button --}}
                        <a
                            href="{{ route('diagnose') }}"
                            class="inline-block rounded-xl bg-[#E2926B] px-8 py-3.5 text-base font-semibold text-white shadow-lg shadow-[#E2926B]/25 transition-all duration-200 hover:bg-[#D16D52] hover:shadow-xl hover:shadow-[#D16D52]/30 hover:-translate-y-0.5 active:translate-y-0"
                        >
                            Start!
                        </a>
                    </div>

                    {{-- Right column: glass card with before/after --}}
                    <div class="relative flex flex-1 items-center justify-center">
                        {{-- Glass card --}}
                        <div class="w-[320px] rotate-[-3deg] rounded-2xl border border-white/40 bg-white/40 p-6 shadow-xl backdrop-blur-[10px] sm:w-[360px]">
                            {{-- Before card --}}
                            <div class="mb-4">
                                <span class="mb-2 inline-block rounded-md bg-white/60 px-2.5 py-1 text-xs font-semibold uppercase tracking-wider text-[#1A1A1A]/50">before</span>
                                <div class="space-y-2 rounded-xl bg-white/70 p-4 shadow-sm">
                                    <div class="h-2.5 w-full rounded-full bg-[#1A1A1A]/10"></div>
                                    <div class="h-2.5 w-5/6 rounded-full bg-[#1A1A1A]/10"></div>
                                    <div class="h-2.5 w-4/6 rounded-full bg-[#1A1A1A]/10"></div>
                                    <div class="h-2.5 w-3/4 rounded-full bg-[#1A1A1A]/8"></div>
                                </div>
                            </div>

                            {{-- After card --}}
                            <div>
                                <div class="mb-2 flex items-center gap-2">
                                    <span class="inline-block rounded-md bg-white/60 px-2.5 py-1 text-xs font-semibold uppercase tracking-wider text-[#1A1A1A]/50">after</span>
                                    <svg class="h-4 w-4 text-[#E2926B]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-11.25a.75.75 0 00-1.5 0v4.59l-1.95-2.1a.75.75 0 10-1.1 1.02l3.25 3.5a.75.75 0 001.1 0l3.25-3.5a.75.75 0 10-1.1-1.02l-1.95 2.1V6.75z" clip-rule="evenodd" /></svg>
                                </div>
                                <div class="space-y-2 rounded-xl bg-white/70 p-4 shadow-sm">
                                    <div class="h-2.5 w-full rounded-full bg-[#E2926B]/30"></div>
                                    <div class="h-2.5 w-5/6 rounded-full bg-[#E2926B]/25"></div>
                                    <div class="h-2.5 w-4/6 rounded-full bg-[#E2926B]/20"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </body>
</html>
