<?php

namespace Kikipunk\EggLibrary\Services;

use App\Models\Egg;
use App\Models\EggVariable;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EggImportService
{
    /**
     * Import an egg from JSON data
     *
     * @param  array  $eggData  The raw egg JSON data
     * @param  bool  $forceNew  Force creation of a new egg even if UUID exists
     * @param  string|null  $customName  Custom name to use for the egg
     * @return array{success: bool, egg: ?Egg, message: string, action: string}
     */
    public function importFromJson(array $eggData, bool $forceNew = false, ?string $customName = null): array
    {
        try {
            DB::beginTransaction();

            // Override name if custom name provided
            if ($customName) {
                $eggData['name'] = $customName;
            }

            $uuid = $eggData['uuid'] ?? null;
            $existingEgg = $uuid && ! $forceNew ? Egg::where('uuid', $uuid)->first() : null;

            if ($existingEgg) {
                $egg = $this->updateEgg($existingEgg, $eggData);
                $action = 'updated';
            } else {
                $egg = $this->createEgg($eggData, $forceNew);
                $action = 'created';
            }

            // Import variables
            $this->importVariables($egg, $eggData['variables'] ?? []);

            DB::commit();

            return [
                'success' => true,
                'egg' => $egg,
                'message' => trans('egg-library::strings.notifications.import_success'),
                'action' => $action,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to import egg', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'egg' => null,
                'message' => $e->getMessage(),
                'action' => 'failed',
            ];
        }
    }

    /**
     * Check if an egg with the given UUID exists
     */
    public function eggExists(?string $uuid): bool
    {
        if (! $uuid) {
            return false;
        }

        return Egg::where('uuid', $uuid)->exists();
    }

    /**
     * Get an existing egg by UUID
     */
    public function getExistingEgg(?string $uuid): ?Egg
    {
        if (! $uuid) {
            return null;
        }

        return Egg::where('uuid', $uuid)->first();
    }

    /**
     * Check if an egg with the given name exists
     */
    public function eggExistsByName(string $name): bool
    {
        return Egg::where('name', $name)->exists();
    }

    /**
     * Get an existing egg by name
     */
    public function getExistingEggByName(string $name): ?Egg
    {
        return Egg::where('name', $name)->first();
    }

    /**
     * Create a new egg from JSON data
     */
    private function createEgg(array $eggData, bool $generateNewUuid = false): Egg
    {
        $uuid = $generateNewUuid ? Str::uuid()->toString() : ($eggData['uuid'] ?? Str::uuid()->toString());

        return Egg::create([
            'uuid' => $uuid,
            'name' => $eggData['name'],
            'description' => $eggData['description'] ?? null,
            'author' => $eggData['author'] ?? 'Unknown',
            'startup' => $this->extractStartup($eggData),
            'stop_command' => $eggData['config']['stop'] ?? 'stop',
            'config_from' => null,
            'config_files' => $this->encodeJson($eggData['config']['files'] ?? []),
            'config_startup' => $this->encodeJson($eggData['config']['startup'] ?? ['done' => '']),
            'config_logs' => $this->encodeJson($eggData['config']['logs'] ?? []),
            'docker_images' => $this->parseDockerImages($eggData['docker_images'] ?? []),
            'file_denylist' => $eggData['file_denylist'] ?? [],
            'features' => $eggData['features'] ?? [],
            'tags' => $eggData['tags'] ?? [],
            'script_container' => $eggData['scripts']['installation']['container'] ?? 'ghcr.io/pelican-dev/installer',
            'script_entry' => $eggData['scripts']['installation']['entrypoint'] ?? 'bash',
            'script_install' => $eggData['scripts']['installation']['script'] ?? '',
        ]);
    }

    /**
     * Update an existing egg with new data
     */
    private function updateEgg(Egg $egg, array $eggData): Egg
    {
        $egg->update([
            'name' => $eggData['name'],
            'description' => $eggData['description'] ?? $egg->description,
            'author' => $eggData['author'] ?? $egg->author,
            'startup' => $this->extractStartup($eggData),
            'stop_command' => $eggData['config']['stop'] ?? $egg->stop_command,
            'config_files' => $this->encodeJson($eggData['config']['files'] ?? []),
            'config_startup' => $this->encodeJson($eggData['config']['startup'] ?? ['done' => '']),
            'config_logs' => $this->encodeJson($eggData['config']['logs'] ?? []),
            'docker_images' => $this->parseDockerImages($eggData['docker_images'] ?? []),
            'file_denylist' => $eggData['file_denylist'] ?? $egg->file_denylist,
            'features' => $eggData['features'] ?? $egg->features,
            'tags' => $eggData['tags'] ?? $egg->tags,
            'script_container' => $eggData['scripts']['installation']['container'] ?? $egg->script_container,
            'script_entry' => $eggData['scripts']['installation']['entrypoint'] ?? $egg->script_entry,
            'script_install' => $eggData['scripts']['installation']['script'] ?? $egg->script_install,
        ]);

        return $egg->fresh();
    }

    /**
     * Import egg variables
     */
    private function importVariables(Egg $egg, array $variables): void
    {
        // Remove existing variables for this egg
        EggVariable::where('egg_id', $egg->id)->delete();

        foreach ($variables as $index => $variable) {
            EggVariable::create([
                'egg_id' => $egg->id,
                'name' => $variable['name'],
                'description' => $variable['description'] ?? '',
                'env_variable' => $variable['env_variable'],
                'default_value' => $variable['default_value'] ?? '',
                'user_viewable' => $variable['user_viewable'] ?? false,
                'user_editable' => $variable['user_editable'] ?? false,
                'rules' => $variable['rules'] ?? 'string',
                'sort' => $variable['sort'] ?? $index,
            ]);
        }
    }

    /**
     * Extract startup command from egg data
     */
    private function extractStartup(array $eggData): string
    {
        // Try different possible locations for startup command
        if (isset($eggData['startup'])) {
            return $eggData['startup'];
        }

        if (isset($eggData['startup_commands']['command'])) {
            return $eggData['startup_commands']['command'];
        }

        return '';
    }

    /**
     * Parse docker images from various formats
     */
    private function parseDockerImages(array $images): array
    {
        $parsed = [];

        foreach ($images as $key => $value) {
            if (is_string($key)) {
                // Format: "name" => "image"
                $parsed[$key] = $value;
            } else {
                // Format: ["image1", "image2"]
                $parsed[$value] = $value;
            }
        }

        return $parsed;
    }

    /**
     * Encode array to JSON string if not already
     */
    private function encodeJson(array|string $data): string
    {
        if (is_string($data)) {
            return $data;
        }

        return json_encode($data);
    }
}
