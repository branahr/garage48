<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="bg-background flex min-h-svh flex-col items-center px-4 py-10 sm:px-6 md:px-10">
            <div class="w-full max-w-2xl">
                {{ $slot }}
            </div>
        </div>
        @fluxScripts
    </body>
</html>
