<?php

namespace KikiPunk\MinecraftModrinth\Services;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use KikiPunk\MinecraftModrinth\Enums\MinecraftLoader;
use KikiPunk\MinecraftModrinth\Enums\ModrinthProjectType;
use Exception;
use GuzzleHttp\Promise\Utils;
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
     * Clear all caches for a server's mods/plugins/datapacks
     * Call this after downloading or deleting files
     */
    public function clearModsCache(Server $server, ?ModrinthProjectType $projectType = null): void
    {
        $projectType = $projectType ?? ModrinthProjectType::fromServer($server);
        $folder = $projectType?->getFolder($server);

        if ($folder) {
            cache()->forget("installed_mods:{$server->uuid}:$folder");
            // Increment cache version to invalidate all_mods_info cache
            $versionKey = "mods_cache_version:{$server->uuid}:$folder";
            $currentVersion = (int) cache()->get($versionKey, 0);
            cache()->put($versionKey, $currentVersion + 1, now()->addDays(30));
        }
    }

    /**
     * Get the current cache version for a server's mods
     */
    public function getCacheVersion(Server $server, ?ModrinthProjectType $projectType = null): int
    {
        $projectType = $projectType ?? ModrinthProjectType::fromServer($server);
        $folder = $projectType?->getFolder($server);

        if (!$folder) {
            return 0;
        }

        return (int) cache()->get("mods_cache_version:{$server->uuid}:$folder", 0);
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

        // Cache the directory listing for 5 minutes to avoid repeated daemon calls
        $cacheKey = "installed_mods:{$server->uuid}:$folder";

        return cache()->remember($cacheKey, now()->addMinutes(5), function () use ($server, $fileRepository, $folder, $projectType) {
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
        });
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
     * Compute SHA-512 hashes for multiple files in parallel
     * Limits concurrency to avoid memory exhaustion
     * @param array<string, array{name: string, modified_at: string}> $files Files keyed by filename
     * @return array<string, string|null> filename => hash
     */
    public function getFileHashesParallel(Server $server, DaemonFileRepository $fileRepository, string $folder, array $files): array
    {
        $results = [];
        $filesToFetch = [];

        // Check cache first for all files
        foreach ($files as $fileName => $fileData) {
            $cacheKey = "file_hash:{$server->uuid}:$folder/$fileName:{$fileData['modified_at']}";
            $cachedHash = cache()->get($cacheKey);

            if ($cachedHash !== null) {
                $results[$fileName] = $cachedHash;
            } else {
                $filesToFetch[$fileName] = $fileData;
            }
        }

        // If all files were cached, return early
        if (empty($filesToFetch)) {
            return $results;
        }

        // Build parallel requests using Guzzle promises
        $node = $server->node;
        $baseUrl = $node->getConnectionAddress();

        try {
            $client = new \GuzzleHttp\Client([
                'base_uri' => $baseUrl,
                'timeout' => 30,
                'connect_timeout' => 5,
                'headers' => [
                    'Authorization' => 'Bearer ' . $node->daemon_token,
                    'Accept' => 'application/json',
                ],
                'verify' => false,
            ]);

            // Process in batches of 10 to limit memory usage
            // Each jar file can be several MB, so we can't load all at once
            $batches = array_chunk($filesToFetch, 10, true);

            foreach ($batches as $batch) {
                $promises = [];
                foreach ($batch as $fileName => $fileData) {
                    $filePath = urlencode("$folder/$fileName");
                    $promises[$fileName] = $client->getAsync("/api/servers/{$server->uuid}/files/contents?file=$filePath");
                }

                // Wait for this batch to complete
                $responses = Utils::settle($promises)->wait();

                // Process responses and free memory immediately
                foreach ($responses as $fileName => $response) {
                    $fileData = $batch[$fileName];
                    $cacheKey = "file_hash:{$server->uuid}:$folder/$fileName:{$fileData['modified_at']}";

                    if ($response['state'] === 'fulfilled') {
                        $content = (string) $response['value']->getBody();
                        $hash = hash('sha512', $content);
                        // Free memory immediately after hashing
                        unset($content);
                        cache()->put($cacheKey, $hash, now()->addHours(24));
                        $results[$fileName] = $hash;
                    } else {
                        // Request failed
                        cache()->put($cacheKey, '', now()->addHours(1));
                        $results[$fileName] = null;
                    }

                    // Free response body memory
                    if (isset($response['value'])) {
                        $response['value']->getBody()->close();
                    }
                }

                // Force garbage collection between batches
                unset($promises, $responses);
            }
        } catch (Exception $exception) {
            report($exception);
            // Fall back to sequential fetching
            foreach ($filesToFetch as $fileName => $fileData) {
                $results[$fileName] = $this->getFileHash($server, $fileRepository, $folder, $fileName, $fileData['modified_at']);
            }
        }

        return $results;
    }

    /**
     * Get hashes for all installed files (uses parallel fetching)
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

        if ($installedMods->isEmpty()) {
            return [];
        }

        // Convert to format expected by getFileHashesParallel
        $files = $installedMods->keyBy('name')->toArray();

        // Use parallel fetching for speed
        $hashes = $this->getFileHashesParallel($server, $fileRepository, $folder, $files);

        // Filter out null/empty hashes
        return array_filter($hashes, fn ($hash) => !empty($hash));
    }

    /**
     * Get update status for installed mods based on project_id
     * Returns array keyed by project_id with update info
     * Cached for 5 minutes based on file modification times
     * @return array<string, array{installed: bool, has_update: bool, installed_version: ?array, latest_version: ?array}>
     */
    public function getProjectUpdateStatus(Server $server, DaemonFileRepository $fileRepository, ?ModrinthProjectType $projectType = null): array
    {
        $projectType = $projectType ?? ModrinthProjectType::fromServer($server);
        $folder = $projectType?->getFolder($server);

        if (!$folder) {
            return [];
        }

        // Get installed mods to create a cache key based on their modification times
        $installedMods = $this->getInstalledMods($server, $fileRepository, $projectType);

        if ($installedMods->isEmpty()) {
            return [];
        }

        // Create cache key based on file count and names only (not modification times)
        // This ensures cache isn't invalidated just because a timestamp changed
        $fileNames = $installedMods->pluck('name')->sort()->implode(',');
        $cacheKey = "project_update_status:{$server->uuid}:$folder:" . md5($fileNames);

        return cache()->remember($cacheKey, now()->addMinutes(5), function () use ($server, $fileRepository, $projectType, $installedMods, $folder) {
            // Convert to format expected by getFileHashesParallel
            $files = $installedMods->keyBy('name')->toArray();

            // Use parallel fetching for speed
            $fileHashes = $this->getFileHashesParallel($server, $fileRepository, $folder, $files);
            $fileHashes = array_filter($fileHashes, fn ($hash) => !empty($hash));

            if (empty($fileHashes)) {
                return [];
            }

            $hashValues = array_values($fileHashes);

            // Get current version info for all installed files
            $currentVersions = $this->identifyModsByHash($hashValues);

            // Only check for updates if we have identified mods
            $updates = [];
            if (!empty($currentVersions)) {
                $identifiedHashes = array_keys($currentVersions);
                $updates = $this->checkForUpdates($identifiedHashes, $server, $projectType);
            }

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
        });
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

    /**
     * Get installed mods with basic info only (no hash computation or API calls)
     * Used for fast initial page load
     * @return Collection<int, array<string, mixed>>
     */
    public function getInstalledModsBasic(Server $server, DaemonFileRepository $fileRepository, ?ModrinthProjectType $projectType = null): Collection
    {
        return $this->getInstalledMods($server, $fileRepository, $projectType)
            ->map(fn ($mod) => array_merge($mod, [
                'loading' => true,
                'modrinth_info' => null,
                'project_info' => null,
                'has_update' => false,
                'latest_version' => null,
                'hash' => null,
            ]));
    }

    /**
     * Get Modrinth info for a specific batch of files
     * Used for lazy loading in batches
     * @param array<string> $fileNames
     * @return array<string, array<string, mixed>>
     */
    public function getModInfoForFiles(Server $server, DaemonFileRepository $fileRepository, array $fileNames, ?ModrinthProjectType $projectType = null): array
    {
        $projectType = $projectType ?? ModrinthProjectType::fromServer($server);
        $folder = $projectType?->getFolder($server);

        if (!$folder || empty($fileNames)) {
            return [];
        }

        $allMods = $this->getInstalledMods($server, $fileRepository, $projectType);

        // Compute hashes for the requested files only
        $hashes = [];
        foreach ($allMods as $mod) {
            if (in_array($mod['name'], $fileNames, true)) {
                $hash = $this->getFileHash($server, $fileRepository, $folder, $mod['name'], $mod['modified_at']);
                if ($hash) {
                    $hashes[$mod['name']] = $hash;
                }
            }
        }

        if (empty($hashes)) {
            // Return basic info without Modrinth data
            $result = [];
            foreach ($fileNames as $fileName) {
                $result[$fileName] = [
                    'modrinth_info' => null,
                    'project_info' => null,
                    'has_update' => false,
                    'latest_version' => null,
                    'hash' => null,
                    'loading' => false,
                ];
            }
            return $result;
        }

        // Get Modrinth info for the hashes
        $hashValues = array_values($hashes);
        $currentVersions = $this->identifyModsByHash($hashValues);

        // Only check for updates and fetch projects if we found some mods
        $updates = [];
        $projects = [];

        if (!empty($currentVersions)) {
            // Only check updates for hashes that were actually identified
            $identifiedHashes = array_keys($currentVersions);
            $updates = $this->checkForUpdates($identifiedHashes, $server, $projectType);

            // Collect project IDs
            $projectIds = [];
            foreach ($currentVersions as $version) {
                if (isset($version['project_id'])) {
                    $projectIds[] = $version['project_id'];
                }
            }

            // Fetch project details only if we have project IDs
            if (!empty($projectIds)) {
                $projects = $this->getProjectsById($projectIds);
            }
        }

        // Build result
        $result = [];
        foreach ($fileNames as $fileName) {
            $hash = $hashes[$fileName] ?? null;
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

            $result[$fileName] = [
                'modrinth_info' => $currentVersion,
                'project_info' => $projectInfo,
                'has_update' => $hasUpdate,
                'latest_version' => $latestVersion,
                'hash' => $hash,
                'loading' => false,
            ];
        }

        return $result;
    }

    /**
     * Get Modrinth info for files using cached mod data (avoids re-reading directory)
     * Uses parallel file fetching for better performance
     * @param array<string> $fileNames
     * @param array<string, array<string, mixed>> $cachedModData Mod data keyed by filename
     * @return array<string, array<string, mixed>>
     */
    public function getModInfoForFilesCached(Server $server, DaemonFileRepository $fileRepository, array $fileNames, array $cachedModData, ?ModrinthProjectType $projectType = null): array
    {
        $projectType = $projectType ?? ModrinthProjectType::fromServer($server);
        $folder = $projectType?->getFolder($server);

        if (!$folder || empty($fileNames)) {
            return [];
        }

        $result = [];
        $filesToProcess = [];

        // Check cache first for each file
        foreach ($fileNames as $fileName) {
            if (!isset($cachedModData[$fileName])) {
                $result[$fileName] = [
                    'modrinth_info' => null,
                    'project_info' => null,
                    'has_update' => false,
                    'latest_version' => null,
                    'hash' => null,
                    'loading' => false,
                ];
                continue;
            }

            $mod = $cachedModData[$fileName];
            $cacheKey = "mod_info:{$server->uuid}:$folder/{$fileName}:{$mod['modified_at']}";

            $cachedInfo = cache()->get($cacheKey);
            if ($cachedInfo !== null) {
                // Use cached Modrinth info
                $result[$fileName] = $cachedInfo;
            } else {
                // Need to fetch info for this file
                $filesToProcess[$fileName] = $cachedModData[$fileName];
            }
        }

        // If all files were cached, return early
        if (empty($filesToProcess)) {
            return $result;
        }

        // Compute hashes for the files that need processing - IN PARALLEL
        $hashes = $this->getFileHashesParallel($server, $fileRepository, $folder, $filesToProcess);

        // Filter out null/empty hashes
        $validHashes = array_filter($hashes, fn ($hash) => !empty($hash));

        if (empty($validHashes)) {
            // Return basic info without Modrinth data for remaining files
            foreach ($filesToProcess as $fileName => $mod) {
                $info = [
                    'modrinth_info' => null,
                    'project_info' => null,
                    'has_update' => false,
                    'latest_version' => null,
                    'hash' => null,
                    'loading' => false,
                ];
                $result[$fileName] = $info;

                // Cache this result
                $cacheKey = "mod_info:{$server->uuid}:$folder/{$fileName}:{$mod['modified_at']}";
                cache()->put($cacheKey, $info, now()->addHours(6));
            }
            return $result;
        }

        // Get Modrinth info for the hashes
        $hashValues = array_values($validHashes);
        $currentVersions = $this->identifyModsByHash($hashValues);

        // Only check for updates and fetch projects if we found some mods
        $updates = [];
        $projects = [];

        if (!empty($currentVersions)) {
            // Only check updates for hashes that were actually identified
            $identifiedHashes = array_keys($currentVersions);
            $updates = $this->checkForUpdates($identifiedHashes, $server, $projectType);

            // Collect project IDs
            $projectIds = [];
            foreach ($currentVersions as $version) {
                if (isset($version['project_id'])) {
                    $projectIds[] = $version['project_id'];
                }
            }

            // Fetch project details only if we have project IDs
            if (!empty($projectIds)) {
                $projects = $this->getProjectsById($projectIds);
            }
        }

        // Build result for files that were processed
        foreach ($filesToProcess as $fileName => $mod) {
            $hash = $hashes[$fileName] ?? null;
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

            $info = [
                'modrinth_info' => $currentVersion,
                'project_info' => $projectInfo,
                'has_update' => $hasUpdate,
                'latest_version' => $latestVersion,
                'hash' => $hash,
                'loading' => false,
            ];

            $result[$fileName] = $info;

            // Cache this result (6 hours for update checks)
            $cacheKey = "mod_info:{$server->uuid}:$folder/{$fileName}:{$mod['modified_at']}";
            cache()->put($cacheKey, $info, now()->addHours(6));
        }

        return $result;
    }
}
