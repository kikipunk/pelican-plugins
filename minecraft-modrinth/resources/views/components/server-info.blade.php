<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1rem;">
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10" style="padding: 1rem; text-align: center;">
        <div style="font-size: 0.875rem; font-weight: 500; color: #6b7280; margin-bottom: 0.5rem;">
            {{ trans('minecraft-modrinth::strings.page.minecraft_version') }}
        </div>
        <x-filament::badge color="primary" size="lg">
            {{ $minecraft_version }}
        </x-filament::badge>
    </div>

    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10" style="padding: 1rem; text-align: center;">
        <div style="font-size: 0.875rem; font-weight: 500; color: #6b7280; margin-bottom: 0.5rem;">
            {{ trans('minecraft-modrinth::strings.page.loader') }}
        </div>
        <x-filament::badge color="primary" size="lg">
            {{ $loader }}
        </x-filament::badge>
    </div>

    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10" style="padding: 1rem; text-align: center;">
        <div style="font-size: 0.875rem; font-weight: 500; color: #6b7280; margin-bottom: 0.5rem;">
            {{ $installed_label }}
        </div>
        <x-filament::badge color="primary" size="lg">
            {{ $installed_count }}
        </x-filament::badge>
    </div>
</div>
