@php
    $currentCategory = $this->selectedCategory ?? $selectedCategory ?? null;
    $baseClass = 'fi-btn fi-btn-size-sm relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg gap-1.5 px-3 py-2 text-sm inline-grid';
    $activeClass = 'bg-primary-600 text-white hover:bg-primary-500 dark:bg-primary-500 dark:hover:bg-primary-400';
    $inactiveClass = 'bg-white text-gray-950 hover:bg-gray-50 dark:bg-white/5 dark:text-white dark:hover:bg-white/10 ring-1 ring-gray-950/10 dark:ring-white/20';
@endphp

<div class="flex flex-wrap gap-2">
    <button
        type="button"
        wire:click="selectCategory(null)"
        @class([$baseClass, $currentCategory === null ? $activeClass : $inactiveClass])
    >
        <x-filament::icon icon="tabler-apps" class="h-5 w-5" />
        <span>{{ trans('egg-library::strings.categories.all') }}</span>
    </button>

    @foreach ($categories as $category)
        <button
            type="button"
            wire:click="selectCategory('{{ $category->name }}')"
            @class([$baseClass, $currentCategory === $category->name ? $activeClass : $inactiveClass])
        >
            <x-filament::icon icon="{{ $category->getIcon() }}" class="h-5 w-5" />
            <span>{{ $category->getLabel() }}</span>
        </button>
    @endforeach
</div>
