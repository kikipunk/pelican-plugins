<x-filament-panels::page
    :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
    wire:submit="save"
    :has-heading="false"
>
    {{ $this->form }}

    <x-filament-actions::modals />
</x-filament-panels::page>
