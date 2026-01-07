<?php

namespace Kikipunk\EggLibrary;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Kikipunk\EggLibrary\Filament\Admin\Pages\EggLibraryPage;

class EggLibraryPlugin implements Plugin
{
    public function getId(): string
    {
        return 'egg-library';
    }

    public function register(Panel $panel): void
    {
        // Only register for admin panel
        if ($panel->getId() !== 'admin') {
            return;
        }

        // Discover admin pages
        $pagesPath = plugin_path($this->getId(), 'src/Filament/Admin/Pages');
        if (is_dir($pagesPath)) {
            $panel->discoverPages($pagesPath, 'Kikipunk\\EggLibrary\\Filament\\Admin\\Pages');
        }
    }

    public function boot(Panel $panel): void
    {
        // Only boot for admin panel
        if ($panel->getId() !== 'admin') {
            return;
        }

        // Register config
        $configPath = plugin_path($this->getId(), 'config/egg-library.php');
        if (file_exists($configPath)) {
            config()->set('egg-library', require $configPath);
        }

        // Register translations
        $langPath = plugin_path($this->getId(), 'lang');
        if (is_dir($langPath)) {
            app('translator')->addNamespace('egg-library', $langPath);
        }

        // Register views
        $viewsPath = plugin_path($this->getId(), 'resources/views');
        if (is_dir($viewsPath)) {
            app('view')->addNamespace('egg-library', $viewsPath);
        }

        // Inject "Browse Library" button on the Eggs page header
        FilamentView::registerRenderHook(
            PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE,
            function () {
                // Check if we're on the eggs list page
                $path = request()->path();
                if (! str_ends_with($path, '/eggs') && ! str_ends_with($path, '/eggs/')) {
                    return '';
                }

                $url = EggLibraryPage::getUrl();
                $label = trans('egg-library::strings.actions.browse_library');

                return Blade::render(<<<HTML
                    <script>
                        (function() {
                            function injectButton() {
                                if (document.getElementById('egg-library-btn')) return;
                                const actionsContainer = document.querySelector('.fi-header-actions-ctn .fi-ac.fi-align-start');
                                if (actionsContainer) {
                                    const btn = document.createElement('a');
                                    btn.id = 'egg-library-btn';
                                    btn.href = '{$url}';
                                    btn.title = '{$label}';
                                    btn.setAttribute('aria-label', '{$label}');
                                    btn.className = 'fi-color fi-color-primary fi-text-color-600 hover:fi-text-color-500 dark:fi-text-color-400 dark:hover:fi-text-color-300 fi-icon-btn fi-size-md fi-ac-icon-btn-action';
                                    btn.innerHTML = '<svg class="fi-icon fi-size-xl" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M21 12a9 9 0 1 0 -9 9"></path><path d="M3.6 9h16.8"></path><path d="M3.6 15h7.9"></path><path d="M11.5 3a17 17 0 0 0 0 18"></path><path d="M12.5 3a17 17 0 0 1 0 18"></path><path d="M19 16v6"></path><path d="M22 19l-3 3l-3 -3"></path></svg>';
                                    actionsContainer.insertBefore(btn, actionsContainer.firstChild);
                                }
                            }
                            if (document.readyState === 'loading') {
                                document.addEventListener('DOMContentLoaded', injectButton);
                            } else {
                                injectButton();
                            }
                            document.addEventListener('livewire:navigated', injectButton);
                        })();
                    </script>
                HTML);
            }
        );
    }
}
