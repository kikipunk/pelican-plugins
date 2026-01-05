<?php

namespace KikiPunk\MinecraftModrinth;

use App\Contracts\Plugins\HasPluginSettings;
use App\Traits\EnvironmentWriterTrait;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Panel;

class MinecraftModrinthPlugin implements HasPluginSettings, Plugin
{
    use EnvironmentWriterTrait;

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

    public function getSettingsForm(): array
    {
        return [
            TextInput::make('latest_minecraft_version')
                ->label(trans('minecraft-modrinth::strings.settings.latest_minecraft_version'))
                ->required()
                ->default(fn () => config('minecraft-modrinth.latest_minecraft_version', '1.21.11')),
        ];
    }

    public function saveSettings(array $data): void
    {
        $this->writeToEnvironment([
            'LATEST_MINECRAFT_VERSION' => $data['latest_minecraft_version'],
        ]);

        Notification::make()
            ->title(trans('minecraft-modrinth::strings.settings.settings_saved'))
            ->success()
            ->send();
    }
}
