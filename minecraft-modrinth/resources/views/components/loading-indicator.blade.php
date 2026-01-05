{{-- Use Alpine.js x-init to start polling, with proper cleanup and debounce --}}
{{-- Always render for polling, but only show visual indicator if $show_indicator is true --}}
@if($is_loading)
<div
    x-data="{ polling: null, stopped: false, loading: false }"
    x-init="
        polling = setInterval(async () => {
            if (stopped || loading) {
                if (stopped) clearInterval(polling);
                return;
            }
            loading = true;
            try {
                await $wire.loadModsBatch();
            } finally {
                loading = false;
            }
        }, 500);
    "
    x-on:livewire:navigating.window="stopped = true; clearInterval(polling)"
    @beforeunload.window="clearInterval(polling)"
    @if($show_indicator ?? true)
    class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
    style="padding: 1rem; margin-bottom: 1rem;"
    @endif
>
    @if($show_indicator ?? true)
    <div style="display: flex; align-items: center; gap: 0.75rem;">
        <svg class="animate-spin" style="height: 1.25rem; width: 1.25rem; color: #3b82f6;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <span style="font-size: 0.875rem; color: #6b7280;">
            {{ trans('minecraft-modrinth::strings.page.loading_mods', ['loaded' => $loaded, 'total' => $total]) }}
        </span>
    </div>
    <div style="margin-top: 0.5rem; background: #e5e7eb; border-radius: 9999px; height: 0.5rem; overflow: hidden;">
        <div style="background: #3b82f6; height: 100%; width: {{ $total > 0 ? round($loaded / $total * 100) : 0 }}%; transition: width 0.3s;"></div>
    </div>
    @endif
</div>
@endif
