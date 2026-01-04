<?php

namespace KikiPunk\MinecraftModrinth\Facades;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Illuminate\Support\Collection;
use KikiPunk\MinecraftModrinth\Enums\ModrinthProjectType;
use KikiPunk\MinecraftModrinth\Services\MinecraftModrinthService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static ?string getMinecraftVersion(Server $server)
 * @method static ?string getMinecraftLoader(Server $server)
 * @method static ?ModrinthProjectType getModrinthProjectType(Server $server)
 * @method static array{hits: array<int, array<string, mixed>>, total_hits: int} getModrinthProjects(Server $server, int $page = 1, ?string $search = null)
 * @method static array<int, mixed> getModrinthVersions(string $projectId, Server $server)
 * @method static Collection getInstalledMods(Server $server, DaemonFileRepository $fileRepository)
 * @method static array identifyModsByHash(array $hashes)
 * @method static array getProjectsById(array $projectIds)
 * @method static array checkForUpdates(array $hashes, Server $server)
 * @method static Collection getInstalledModsWithInfo(Server $server, DaemonFileRepository $fileRepository, array $fileHashes)
 * @method static ?string getFileHash(Server $server, DaemonFileRepository $fileRepository, string $folder, string $fileName, string $modifiedAt)
 * @method static array getInstalledFileHashes(Server $server, DaemonFileRepository $fileRepository, ?ModrinthProjectType $projectType = null)
 * @method static array getProjectUpdateStatus(Server $server, DaemonFileRepository $fileRepository, ?ModrinthProjectType $projectType = null)
 *
 * @see MinecraftModrinthService
 */
class MinecraftModrinth extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MinecraftModrinthService::class;
    }
}
