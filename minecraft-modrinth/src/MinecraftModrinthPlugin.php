<?php

namespace KikiPunk\MinecraftModrinth;

use Filament\Contracts\Plugin;
use Filament\Panel;

class MinecraftModrinthPlugin implements Plugin
{
    public function getId(): string
    {
        return 'minecraft-modrinth';
    }

    public function register(Panel $panel): void
    {
        $id = str($panel->getId())->title();

        $panel->discoverPages(plugin_path($this->getId(), "src/Filament/$id/Pages"), "KikiPunk\\MinecraftModrinth\\Filament\\$id\\Pages");
    }

    public function boot(Panel $panel): void
    {
        // Register views
        $viewPath = plugin_path($this->getId(), 'resources/views');
        if (is_dir($viewPath)) {
            view()->addNamespace('minecraft-modrinth', $viewPath);
        }
    }
}
