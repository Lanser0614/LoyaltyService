<x-filament-panels::page>
    <form wire:submit="simulate" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit">
            Simulate
        </x-filament::button>
    </form>

    @if ($result)
        <x-filament::section>
            <x-slot name="heading">
                Result
            </x-slot>

            <pre class="overflow-auto rounded-lg bg-gray-950 p-4 text-sm text-gray-100">{{ json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </x-filament::section>
    @endif
</x-filament-panels::page>
