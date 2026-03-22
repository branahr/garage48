<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen font-['Plus_Jakarta_Sans',ui-sans-serif,system-ui,sans-serif] antialiased">
        {{-- Background mesh gradient --}}
        <div class="fixed inset-0 -z-10">
            <div class="absolute inset-0 bg-white"></div>
            <div class="absolute -top-1/4 -left-1/4 h-[800px] w-[800px] rounded-full bg-[#FFE0B2] opacity-40 blur-[120px]"></div>
            <div class="absolute -right-1/4 -bottom-1/4 h-[800px] w-[800px] rounded-full bg-[#E9D5FF] opacity-40 blur-[120px]"></div>
            <div class="absolute top-1/3 right-1/3 h-[400px] w-[400px] rounded-full bg-[#FFE0B2] opacity-20 blur-[100px]"></div>
        </div>

        <div class="flex min-h-svh flex-col items-center px-4 py-10 sm:px-6 md:px-10">
            <div class="w-full max-w-2xl">
                {{ $slot }}
            </div>
        </div>
        @fluxScripts
    </body>
</html>
