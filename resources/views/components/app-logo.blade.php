@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="Garage48" {{ $attributes }}>
        <x-slot name="logo" class="flex items-center justify-center">
            <x-app-logo-icon class="h-6 w-auto" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="Garage48" {{ $attributes }}>
        <x-slot name="logo" class="flex items-center justify-center">
            <x-app-logo-icon class="h-6 w-auto" />
        </x-slot>
    </flux:brand>
@endif
