<?php

namespace KikiPunk\MinecraftModrinth\Enums;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Exception;
use Filament\Support\Contracts\HasLabel;

enum ModrinthProjectType: string implements HasLabel
{
    case Mod = 'mod';
    case Plugin = 'plugin';
    case Datapack = 'datapack';

    public function getLabel(): string
    {
        return match ($this) {
            self::Mod => trans('minecraft-modrinth::strings.minecraft_mods'),
            self::Plugin => trans('minecraft-modrinth::strings.minecraft_plugins'),
            self::Datapack => trans('minecraft-modrinth::strings.minecraft_datapacks'),
        };
    }

    public function getFolder(?Server $server = null): string
    {
        return match ($this) {
            self::Mod => 'mods',
            self::Plugin => 'plugins',
            self::Datapack => $this->getDatapackFolder($server),
        };
    }

    /**
     * Get the datapack folder path based on server.properties level-name
     */
    private function getDatapackFolder(?Server $server): string
    {
        $worldName = 'world'; // default

        if ($server) {
            // First try to read from server.properties
            $worldName = $this->getWorldNameFromServerProperties($server) ?? $worldName;
        }

        return $worldName . '/datapacks';
    }

    /**
     * Read world name from server.properties file
     */
    private function getWorldNameFromServerProperties(Server $server): ?string
    {
        try {
            $fileRepository = app(DaemonFileRepository::class);
            $content = $fileRepository->setServer($server)->getContent('server.properties');

            // Parse server.properties to find level-name
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $line = trim($line);
                if (str_starts_with($line, 'level-name=')) {
                    $value = trim(substr($line, strlen('level-name=')));
                    if (!empty($value)) {
                        return $value;
                    }
                }
            }
        } catch (Exception $exception) {
            // File doesn't exist or can't be read, fall back to default
        }

        return null;
    }

    /**
     * Check if this project type requires a loader filter
     */
    public function requiresLoader(): bool
    {
        return match ($this) {
            self::Mod, self::Plugin => true,
            self::Datapack => false,
        };
    }

    public static function fromServer(Server $server): ?ModrinthProjectType
    {
        $server->loadMissing('egg');

        $features = $server->egg->features ?? [];
        $tags = $server->egg->tags ?? [];

        if (in_array('modrinth_plugins', $features) || (in_array('minecraft', $tags) && in_array('plugins', $features))) {
            return self::Plugin;
        }

        if (in_array('modrinth_mods', $features) || (in_array('minecraft', $tags) && in_array('mods', $features))) {
            return self::Mod;
        }

        if (in_array('modrinth_datapacks', $features) || (in_array('minecraft', $tags) && in_array('datapacks', $features))) {
            return self::Datapack;
        }

        return null;
    }

    /**
     * Get all project types available for a server
     */
    public static function allFromServer(Server $server): array
    {
        $server->loadMissing('egg');

        $features = $server->egg->features ?? [];
        $tags = $server->egg->tags ?? [];
        $types = [];

        if (in_array('modrinth_plugins', $features) || (in_array('minecraft', $tags) && in_array('plugins', $features))) {
            $types[] = self::Plugin;
        }

        if (in_array('modrinth_mods', $features) || (in_array('minecraft', $tags) && in_array('mods', $features))) {
            $types[] = self::Mod;
        }

        if (in_array('modrinth_datapacks', $features) || (in_array('minecraft', $tags) && in_array('datapacks', $features))) {
            $types[] = self::Datapack;
        }

        return $types;
    }
}
