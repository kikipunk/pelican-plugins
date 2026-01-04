<?php

namespace KikiPunk\MinecraftModrinth\Services;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use KikiPunk\MinecraftModrinth\Enums\MinecraftLoader;
use KikiPunk\MinecraftModrinth\Enums\ModrinthProjectType;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class MinecraftModrinthService
{
    public function getMinecraftVersion(Server $server): ?string
    {
        $version = $server->variables()->where(fn ($builder) => $builder->where('env_variable', 'MINECRAFT_VERSION')->orWhere('env_variable', 'MC_VERSION'))->first()?->server_value;

        if (!$version || $version === 'latest') {
            return config('minecraft-modrinth.latest_minecraft_version');
        }

        return $version;
    }

    /** @return array{hits: array<int, array<string, mixed>>, total_hits: int} */
    public function getModrinthProjects(Server $server, int $page = 1, ?string $search = null, ?ModrinthProjectType $projectType = null): array
    {
        $projectType = $projectType ?? ModrinthProjectType::fromServer($server);

        if (!$projectType) {
            return [
                'hits' => [],
                'total_hits' => 0,
            ];
        }

        $minecraftVersion = $this->getMinecraftVersion($server);

        // Build facets based on project type
        if ($projectType->requiresLoader()) {
            $minecraftLoader = MinecraftLoader::fromServer($server)?->value;

            if (!$minecraftLoader) {
                return [
                    'hits' => [],
                    'total_hits' => 0,
                ];
            }

            $facets = "[[\"categories:$minecraftLoader\"],[\"versions:$minecraftVersion\"],[\"project_type:{$projectType->value}\"],[\"server_side:required\",\"server_side:optional\"]]";
            $key = "modrinth_projects:{$projectType->value}:$minecraftVersion:$minecraftLoader:$page";
        } else {
            // Datapacks don't need loader filter
            $facets = "[[\"versions:$minecraftVersion\"],[\"project_type:{$projectType->value}\"]]";
            $key = "modrinth_projects:{$projectType->value}:$minecraftVersion:$page";
        }

        $data = [
            'offset' => ($page - 1) * 20,
            'limit' => 20,
            'facets' => $facets,
        ];

        if ($search) {
            $data['query'] = $search;
            $key .= ":$search";
        }

        return cache()->remember($key, now()->addMinutes(30), function () use ($data) {
            try {
                return Http::asJson()
                    ->timeout(5)
                    ->connectTimeout(5)
                    ->throw()
                    ->get('https://api.modrinth.com/v2/search', $data)
                    ->json();
            } catch (Exception $exception) {
                report($exception);

                return [
                    'hits' => [],
                    'total_hits' => 0,
                ];
            }
        });
    }

    /** @return array<int, mixed> */
    public function getModrinthVersions(string $projectId, Server $server, ?ModrinthProjectType $projectType = null): array
    {
        $projectType = $projectType ?? ModrinthProjectType::fromServer($server);
        $minecraftVersion = $this->getMinecraftVersion($server);

        // Build query based on project type
        if ($projectType?->requiresLoader()) {
            $minecraftLoader = MinecraftLoader::fromServer($server)?->value;

            if (!$minecraftLoader) {
                return [];
            }

            $data = [
                'game_versions' => "[\"$minecraftVersion\"]",
                'loaders' => "[\"$minecraftLoader\"]",
            ];
            $cacheKey = "modrinth_versions:$projectId:$minecraftVersion:$minecraftLoader";
        } else {
            // Datapacks don't need loader filter
            $data = [
                'game_versions' => "[\"$minecraftVersion\"]",
            ];
            $cacheKey = "modrinth_versions:$projectId:$minecraftVersion:datapack";
        }

        return cache()->remember($cacheKey, now()->addMinutes(30), function () use ($projectId, $data) {
            try {
                return Http::asJson()
                    ->timeout(5)
                    ->connectTimeout(5)
                    ->throw()
                    ->get("https://api.modrinth.com/v2/project/$projectId/version", $data)
                    ->json();
            } catch (Exception $exception) {
                report($exception);

                return [];
            }
        });
    }

    /**
     * Get installed mods/plugins/datapacks from the server folder
     * @return Collection<int, array{name: string, size: int, modified_at: string}>
     */
    public function getInstalledMods(Server $server, DaemonFileRepository $fileRepository, ?ModrinthProjectType $projectType = null): Collection
    {
        $projectType = $projectType ?? ModrinthProjectType::fromServer($server);
        $folder = $projectType?->getFolder($server);

        if (!$folder) {
            return collect();
        }

        try {
            $files = $fileRepository->setServer($server)->getDirectory($folder);

            if (isset($files['error'])) {
                return collect();
            }

            // Filter based on project type
            $extension = $projectType === ModrinthProjectType::Datapack ? '.zip' : '.jar';

            return collect($files)
                ->filter(fn ($file) => is_array($file) && isset($file['name']) && str($file['name'])->lower()->endsWith($extension))
                ->map(fn ($file) => [
                    'name' => $file['name'],
                    'size' => $file['size'] ?? 0,
                    'modified_at' => $file['modified_at'] ?? now()->toIso8601String(),
                ]);
        } catch (Exception $exception) {
            report($exception);

            return collect();
        }
    }

    /**
     * Identify mods by their SHA-512 hashes using Modrinth API
     * @param array<string> $hashes
     * @return array<string, array<string, mixed>>
     */
    public function identifyModsByHash(array $hashes): array
    {
        if (empty($hashes)) {
            return [];
        }

        // Ensure hashes is a sequential array for JSON serialization
        $hashesArray = array_values($hashes);
        $cacheKey = 'modrinth_hashes:' . md5(implode(',', $hashesArray));

        return cache()->remember($cacheKey, now()->addMinutes(30), function () use ($hashesArray) {
            try {
                return Http::asJson()
                    ->timeout(10)
                    ->connectTimeout(5)
                    ->throw()
                    ->post('https://api.modrinth.com/v2/version_files', [
                        'hashes' => $hashesArray,
                        'algorithm' => 'sha512',
                    ])
                    ->json();
            } catch (Exception $exception) {
                report($exception);

                return [];
            }
        });
    }

    /**
     * Get project details from Modrinth by project IDs
     * @param array<string> $projectIds
     * @return array<string, array<string, mixed>>
     */
    public function getProjectsById(array $projectIds): array
    {
        if (empty($projectIds)) {
            return [];
        }

        // Ensure unique and sequential array
        $projectIds = array_values(array_unique($projectIds));
        $cacheKey = 'modrinth_projects:' . md5(implode(',', $projectIds));

        return cache()->remember($cacheKey, now()->addMinutes(30), function () use ($projectIds) {
            try {
                $response = Http::asJson()
                    ->timeout(10)
                    ->connectTimeout(5)
                    ->throw()
                    ->get('https://api.modrinth.com/v2/projects', [
                        'ids' => json_encode($projectIds),
                    ])
                    ->json();

                // Key by project_id for easy lookup
                $result = [];
                foreach ($response as $project) {
                    $result[$project['id']] = $project;
                }
                return $result;
            } catch (Exception $exception) {
                report($exception);

                return [];
            }
        });
    }

    /**
     * Check for updates for installed mods
     * @param array<string> $hashes
     * @return array<string, array<string, mixed>>
     */
    public function checkForUpdates(array $hashes, Server $server, ?ModrinthProjectType $projectType = null): array
    {
        if (empty($hashes)) {
            return [];
        }

        $projectType = $projectType ?? ModrinthProjectType::fromServer($server);
        $minecraftVersion = $this->getMinecraftVersion($server);

        if (!$minecraftVersion) {
            return [];
        }

        // Ensure hashes is a sequential array (not associative) for JSON serialization
        $hashesArray = array_values($hashes);

        if ($projectType?->requiresLoader()) {
            $minecraftLoader = MinecraftLoader::fromServer($server)?->value;

            if (!$minecraftLoader) {
                return [];
            }

            $cacheKey = "modrinth_updates:" . md5(implode(',', $hashesArray)) . ":$minecraftVersion:$minecraftLoader";
            $requestData = [
                'hashes' => $hashesArray,
                'algorithm' => 'sha512',
                'loaders' => [$minecraftLoader],
                'game_versions' => [$minecraftVersion],
            ];
        } else {
            $cacheKey = "modrinth_updates:" . md5(implode(',', $hashesArray)) . ":$minecraftVersion:datapack";
            $requestData = [
                'hashes' => $hashesArray,
                'algorithm' => 'sha512',
                'game_versions' => [$minecraftVersion],
            ];
        }

        return cache()->remember($cacheKey, now()->addMinutes(30), function () use ($requestData) {
            try {
                return Http::asJson()
                    ->timeout(10)
                    ->connectTimeout(5)
                    ->throw()
                    ->post('https://api.modrinth.com/v2/version_files/update', $requestData)
                    ->json();
            } catch (Exception $exception) {
                report($exception);

                return [];
            }
        });
    }

    /**
     * Compute SHA-512 hash for a file with caching
     */
    public function getFileHash(Server $server, DaemonFileRepository $fileRepository, string $folder, string $fileName, string $modifiedAt): ?string
    {
        $cacheKey = "file_hash:{$server->uuid}:$folder/$fileName:$modifiedAt";

        return cache()->remember($cacheKey, now()->addHours(24), function () use ($server, $fileRepository, $folder, $fileName) {
            try {
                $content = $fileRepository->setServer($server)->getContent("$folder/$fileName");
                return hash('sha512', $content);
            } catch (Exception $exception) {
                report($exception);
                return null;
            }
        });
    }

    /**
     * Get hashes for all installed files
     * @return array<string, string> filename => hash
     */
    public function getInstalledFileHashes(Server $server, DaemonFileRepository $fileRepository, ?ModrinthProjectType $projectType = null): array
    {
        $projectType = $projectType ?? ModrinthProjectType::fromServer($server);
        $folder = $projectType?->getFolder($server);

        if (!$folder) {
            return [];
        }

        $installedMods = $this->getInstalledMods($server, $fileRepository, $projectType);
        $hashes = [];

        foreach ($installedMods as $mod) {
            $hash = $this->getFileHash($server, $fileRepository, $folder, $mod['name'], $mod['modified_at']);
            if ($hash) {
                $hashes[$mod['name']] = $hash;
            }
        }

        return $hashes;
    }

    /**
     * Get update status for installed mods based on project_id
     * Returns array keyed by project_id with update info
     * @return array<string, array{installed: bool, has_update: bool, installed_version: ?array, latest_version: ?array}>
     */
    public function getProjectUpdateStatus(Server $server, DaemonFileRepository $fileRepository, ?ModrinthProjectType $projectType = null): array
    {
        $fileHashes = $this->getInstalledFileHashes($server, $fileRepository, $projectType);

        if (empty($fileHashes)) {
            return [];
        }

        $hashValues = array_values($fileHashes);

        // Get current version info for all installed files
        $currentVersions = $this->identifyModsByHash($hashValues);
        // Check for updates
        $updates = $this->checkForUpdates($hashValues, $server, $projectType);

        $result = [];

        foreach ($fileHashes as $fileName => $hash) {
            $currentVersion = $currentVersions[$hash] ?? null;
            $latestVersion = $updates[$hash] ?? null;

            if ($currentVersion && isset($currentVersion['project_id'])) {
                $projectId = $currentVersion['project_id'];
                $hasUpdate = false;

                if ($latestVersion) {
                    $hasUpdate = ($currentVersion['id'] ?? null) !== ($latestVersion['id'] ?? null);
                }

                $result[$projectId] = [
                    'installed' => true,
                    'has_update' => $hasUpdate,
                    'installed_version' => $currentVersion,
                    'latest_version' => $latestVersion,
                    'file_name' => $fileName,
                    'hash' => $hash,
                ];
            }
        }

        return $result;
    }

    /**
     * Get installed mods with their Modrinth info and update status
     * @return Collection<int, array<string, mixed>>
     */
    public function getInstalledModsWithInfo(Server $server, DaemonFileRepository $fileRepository, array $fileHashes, ?ModrinthProjectType $projectType = null): Collection
    {
        $installedMods = $this->getInstalledMods($server, $fileRepository, $projectType);

        if ($installedMods->isEmpty() || empty($fileHashes)) {
            return $installedMods->map(fn ($mod) => array_merge($mod, [
                'modrinth_info' => null,
                'project_info' => null,
                'has_update' => false,
                'latest_version' => null,
            ]));
        }

        // Get current version info
        $hashValues = array_values($fileHashes);
        $currentVersions = $this->identifyModsByHash($hashValues);
        // Check for updates
        $updates = $this->checkForUpdates($hashValues, $server, $projectType);

        // Collect project IDs to fetch project details
        $projectIds = [];
        foreach ($currentVersions as $version) {
            if (isset($version['project_id'])) {
                $projectIds[] = $version['project_id'];
            }
        }

        // Fetch project details (name, description, icon)
        $projects = $this->getProjectsById($projectIds);

        return $installedMods->map(function ($mod) use ($currentVersions, $updates, $fileHashes, $projects) {
            $hash = $fileHashes[$mod['name']] ?? null;
            $currentVersion = $hash ? ($currentVersions[$hash] ?? null) : null;
            $latestVersion = $hash ? ($updates[$hash] ?? null) : null;
            $projectInfo = null;

            if ($currentVersion && isset($currentVersion['project_id'])) {
                $projectInfo = $projects[$currentVersion['project_id']] ?? null;
            }

            $hasUpdate = false;
            if ($currentVersion && $latestVersion) {
                $hasUpdate = ($currentVersion['id'] ?? null) !== ($latestVersion['id'] ?? null);
            }

            return array_merge($mod, [
                'modrinth_info' => $currentVersion,
                'project_info' => $projectInfo,
                'has_update' => $hasUpdate,
                'latest_version' => $latestVersion,
                'hash' => $hash,
            ]);
        });
    }
}
