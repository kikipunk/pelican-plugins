<?php

namespace KikiPunk\MinecraftModrinth\Filament\Server\Pages;

use App\Filament\Server\Resources\Files\Pages\ListFiles;
use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use App\Traits\Filament\BlockAccessInConflict;
use KikiPunk\MinecraftModrinth\Enums\ModrinthProjectType;
use KikiPunk\MinecraftModrinth\Facades\MinecraftModrinth;
use Exception;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

class MinecraftDatapackPage extends Page implements HasTable
{
    use BlockAccessInConflict;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-packages';

    protected static ?string $slug = 'datapacks';

    protected static ?int $navigationSort = 31;

    #[Url(as: 'view')]
    public string $currentView = 'modrinth';

    protected ?array $installedFiles = null;

    protected ?array $projectUpdateStatus = null;

    protected ModrinthProjectType $projectType = ModrinthProjectType::Datapack;

    // Lazy loading properties for installed datapacks
    public array $modsInfoCache = [];
    public array $pendingFiles = [];
    public array $modsBasicData = [];  // Cached mod data to avoid re-reading directory
    public bool $isLoadingMods = false;
    public int $loadedCount = 0;
    public int $totalCount = 0;

    public static function canAccess(): bool
    {
        /** @var Server $server */
        $server = Filament::getTenant();
        $server->loadMissing('egg');

        $features = $server->egg->features ?? [];
        $tags = $server->egg->tags ?? [];

        $hasDatapacks = in_array('modrinth_datapacks', $features)
            || (in_array('minecraft', $tags) && in_array('datapacks', $features));

        return parent::canAccess() && $hasDatapacks;
    }

    public static function getNavigationLabel(): string
    {
        return trans('minecraft-modrinth::strings.minecraft_datapacks');
    }

    public static function getModelLabel(): string
    {
        return static::getNavigationLabel();
    }

    public static function getPluralModelLabel(): string
    {
        return static::getNavigationLabel();
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    /**
     * Switch to Modrinth view
     */
    public function switchToModrinth(): void
    {
        // Stop any ongoing loading before switching
        $this->resetModsLoading();
        $this->redirect(static::getUrl(['view' => 'modrinth']));
    }

    /**
     * Switch to Installed view
     */
    public function switchToInstalled(): void
    {
        // Reset loading state to start fresh
        $this->resetModsLoading();
        $this->redirect(static::getUrl(['view' => 'installed']));
    }

    /**
     * Load next batch of datapack info (called by Alpine.js polling)
     */
    public function loadModsBatch(): void
    {
        // Only run on installed view
        if ($this->currentView !== 'installed') {
            return;
        }

        // Skip if already loading
        if ($this->isLoadingMods) {
            return;
        }

        /** @var Server $server */
        $server = Filament::getTenant();
        $fileRepository = app(DaemonFileRepository::class);

        // Initialize pending files if not yet done (only read directory once!)
        if (empty($this->pendingFiles) && empty($this->modsInfoCache)) {
            // Cache the mod data to avoid re-reading the directory for each batch
            if (empty($this->modsBasicData)) {
                $installedDatapacks = MinecraftModrinth::getInstalledMods($server, $fileRepository, $this->projectType);
                if ($installedDatapacks->isEmpty()) {
                    return;
                }
                $this->modsBasicData = $installedDatapacks->keyBy('name')->toArray();
            }

            // Pre-load cached mod info from Laravel cache
            $this->preloadCachedModInfo($server);

            // Only add to pending the files that are NOT already cached
            $this->pendingFiles = array_diff(array_keys($this->modsBasicData), array_keys($this->modsInfoCache));
            $this->totalCount = count($this->modsBasicData);
            $this->loadedCount = count($this->modsInfoCache);
        }

        // Nothing left to load
        if (empty($this->pendingFiles)) {
            return;
        }

        $this->isLoadingMods = true;

        // Load 25 datapacks at a time (uses parallel fetching for speed)
        $batch = array_splice($this->pendingFiles, 0, 25);

        try {
            // Use cached mod data to avoid re-reading directory for each batch
            $infos = MinecraftModrinth::getModInfoForFilesCached($server, $fileRepository, $batch, $this->modsBasicData, $this->projectType);
            $this->modsInfoCache = array_merge($this->modsInfoCache, $infos);
            $this->loadedCount += count($batch);

            // If all files are loaded, save to cache for next visit
            if (empty($this->pendingFiles)) {
                $this->saveCachedModInfo($server);
            }
        } catch (Exception $exception) {
            report($exception);
        }

        $this->isLoadingMods = false;
    }

    /**
     * Pre-load mod info from Laravel cache into Livewire state
     * Uses a single cache entry for all mods (faster and more reliable)
     */
    protected function preloadCachedModInfo(Server $server): void
    {
        $folder = $this->projectType->getFolder($server);

        if (!$folder) {
            return;
        }

        // Simple cache key: server + folder + version (version invalidates on download/delete)
        $version = MinecraftModrinth::getCacheVersion($server, $this->projectType);
        $cacheKey = "all_mods_info:{$server->uuid}:$folder:v{$version}";

        $cachedAllInfo = cache()->get($cacheKey);
        if ($cachedAllInfo !== null && is_array($cachedAllInfo)) {
            // Only use cached entries that match current files
            $currentFileNames = array_keys($this->modsBasicData);
            $this->modsInfoCache = array_intersect_key($cachedAllInfo, array_flip($currentFileNames));
        }
    }

    /**
     * Save all mod info to cache after loading completes
     */
    protected function saveCachedModInfo(Server $server): void
    {
        $folder = $this->projectType->getFolder($server);

        if (!$folder || empty($this->modsInfoCache)) {
            return;
        }

        // Simple cache key: server + folder + version
        $version = MinecraftModrinth::getCacheVersion($server, $this->projectType);
        $cacheKey = "all_mods_info:{$server->uuid}:$folder:v{$version}";

        // Cache for 6 hours
        cache()->put($cacheKey, $this->modsInfoCache, now()->addHours(6));
    }

    /**
     * Reset lazy loading state
     */
    public function resetModsLoading(): void
    {
        $this->modsInfoCache = [];
        $this->pendingFiles = [];
        $this->modsBasicData = [];
        $this->loadedCount = 0;
        $this->totalCount = 0;
        $this->isLoadingMods = false;
    }

    /**
     * Get installed file names for comparison
     */
    protected function getInstalledFiles(DaemonFileRepository $fileRepository): array
    {
        if ($this->installedFiles !== null) {
            return $this->installedFiles;
        }

        /** @var Server $server */
        $server = Filament::getTenant();
        $folder = $this->projectType->getFolder($server);

        try {
            $files = $fileRepository->setServer($server)->getDirectory($folder);

            if (isset($files['error'])) {
                return $this->installedFiles = [];
            }

            $this->installedFiles = collect($files)
                ->filter(fn ($file) => str($file['name'])->lower()->endsWith('.zip'))
                ->pluck('name')
                ->map(fn ($name) => strtolower($name))
                ->toArray();

            return $this->installedFiles;
        } catch (Exception $exception) {
            return $this->installedFiles = [];
        }
    }

    /**
     * Check if a datapack is installed by matching slug in filenames (fallback)
     */
    protected function isDatapackInstalled(string $slug, array $installedFiles): bool
    {
        $slugLower = strtolower($slug);
        foreach ($installedFiles as $fileName) {
            if (str_contains($fileName, $slugLower)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get project update status with caching
     * @return array<string, array{installed: bool, has_update: bool, installed_version: ?array, latest_version: ?array}>
     */
    protected function getProjectUpdateStatus(DaemonFileRepository $fileRepository): array
    {
        if ($this->projectUpdateStatus !== null) {
            return $this->projectUpdateStatus;
        }

        /** @var Server $server */
        $server = Filament::getTenant();

        try {
            $this->projectUpdateStatus = MinecraftModrinth::getProjectUpdateStatus($server, $fileRepository, $this->projectType);
        } catch (Exception $exception) {
            report($exception);
            $this->projectUpdateStatus = [];
        }

        return $this->projectUpdateStatus;
    }

    /**
     * Get status info for a project
     */
    protected function getProjectStatus(string $projectId, DaemonFileRepository $fileRepository, array $installedFiles, string $slug): array
    {
        $updateStatus = $this->getProjectUpdateStatus($fileRepository);

        // Check by project_id (accurate, hash-based)
        if (isset($updateStatus[$projectId])) {
            $status = $updateStatus[$projectId];
            return [
                'installed' => true,
                'has_update' => $status['has_update'],
                'status_label' => $status['has_update']
                    ? trans('minecraft-modrinth::strings.status.update_available')
                    : trans('minecraft-modrinth::strings.status.up_to_date'),
                'status_color' => $status['has_update'] ? 'warning' : 'success',
            ];
        }

        // Fallback to slug matching
        if ($this->isDatapackInstalled($slug, $installedFiles)) {
            return [
                'installed' => true,
                'has_update' => false,
                'status_label' => trans('minecraft-modrinth::strings.status.installed'),
                'status_color' => 'success',
            ];
        }

        return [
            'installed' => false,
            'has_update' => false,
            'status_label' => trans('minecraft-modrinth::strings.status.not_installed'),
            'status_color' => 'gray',
        ];
    }

    /**
     * @throws Exception
     */
    public function table(Table $table): Table
    {
        $isInstalled = $this->currentView === 'installed';

        return $table
            ->records(function (?string $search, int $page) use ($isInstalled) {
                /** @var Server $server */
                $server = Filament::getTenant();

                if ($isInstalled) {
                    // Use cached data if available, otherwise read from directory
                    $fileRepository = app(DaemonFileRepository::class);

                    if (!empty($this->modsBasicData)) {
                        // Use cached data
                        $installedDatapacks = collect($this->modsBasicData);
                    } else {
                        // Initial load - read file list
                        $installedDatapacks = MinecraftModrinth::getInstalledMods($server, $fileRepository, $this->projectType);

                        // Cache for later use
                        if ($installedDatapacks->isNotEmpty()) {
                            $this->modsBasicData = $installedDatapacks->keyBy('name')->toArray();
                            $installedDatapacks = collect($this->modsBasicData);
                        }
                    }

                    // Initialize lazy loading if not yet done
                    if (empty($this->pendingFiles) && empty($this->modsInfoCache) && $installedDatapacks->isNotEmpty()) {
                        // Load from cache FIRST before setting pendingFiles
                        $this->preloadCachedModInfo($server);

                        // Only set pendingFiles for files NOT in cache
                        $this->pendingFiles = array_diff(array_keys($this->modsBasicData), array_keys($this->modsInfoCache));
                        $this->totalCount = count($this->modsBasicData);
                        $this->loadedCount = count($this->modsInfoCache);
                    }

                    // Merge with cached Modrinth info, add defaults for unloaded records
                    $installedDatapacks = $installedDatapacks->map(function ($item) {
                        if (isset($this->modsInfoCache[$item['name']])) {
                            return array_merge($item, $this->modsInfoCache[$item['name']]);
                        }
                        // Add default values for records not yet enriched
                        return array_merge($item, [
                            'modrinth_info' => null,
                            'project_info' => null,
                            'has_update' => false,
                            'latest_version' => null,
                            'hash' => null,
                            'loading' => true,
                        ]);
                    });

                    // Filter by search term
                    if ($search) {
                        $searchLower = strtolower($search);
                        $installedDatapacks = $installedDatapacks->filter(function ($item) use ($searchLower) {
                            $title = strtolower($item['project_info']['title'] ?? $item['name'] ?? '');
                            $description = strtolower($item['project_info']['description'] ?? '');
                            $name = strtolower($item['name'] ?? '');
                            return str_contains($title, $searchLower)
                                || str_contains($description, $searchLower)
                                || str_contains($name, $searchLower);
                        });
                    }

                    // Simple pagination
                    $offset = ($page - 1) * 20;
                    $items = $installedDatapacks->slice($offset, 20)->values()->toArray();

                    return new LengthAwarePaginator($items, $installedDatapacks->count(), 20, $page);
                }

                // Modrinth search for datapacks
                $response = MinecraftModrinth::getModrinthProjects($server, $page, $search, $this->projectType);

                return new LengthAwarePaginator($response['hits'], $response['total_hits'], 20, $page);
            })
            ->paginated([20])
            ->columns($isInstalled ? $this->getInstalledColumns() : $this->getModrinthColumns())
            ->recordUrl(function (array $record) use ($isInstalled) {
                if (!$isInstalled) {
                    return "https://modrinth.com/datapack/{$record['slug']}";
                }
                // For installed datapacks, link to Modrinth if we have the info
                if (isset($record['modrinth_info']['project_id'])) {
                    return "https://modrinth.com/datapack/{$record['modrinth_info']['project_id']}";
                }
                return null;
            }, true)
            ->recordActions($isInstalled ? $this->getInstalledActions() : $this->getModrinthActions());
    }

    protected function getModrinthColumns(): array
    {
        $fileRepository = app(DaemonFileRepository::class);
        $installedFiles = $this->getInstalledFiles($fileRepository);

        return [
            ImageColumn::make('icon_url')
                ->label('')
                ->size(40),
            TextColumn::make('title')
                ->label(trans('minecraft-modrinth::strings.table.columns.title'))
                ->searchable()
                ->wrap()
                ->description(fn (array $record) => str(strlen($record['description']) > 80 ? substr($record['description'], 0, 80).'...' : $record['description'])->limit(80)),
            TextColumn::make('status')
                ->label(trans('minecraft-modrinth::strings.table.columns.status'))
                ->badge()
                ->state(fn (array $record) => $this->getProjectStatus($record['project_id'], $fileRepository, $installedFiles, $record['slug'])['status_label'])
                ->color(fn (array $record) => $this->getProjectStatus($record['project_id'], $fileRepository, $installedFiles, $record['slug'])['status_color']),
            TextColumn::make('author')
                ->label(trans('minecraft-modrinth::strings.table.columns.author'))
                ->url(fn ($state) => "https://modrinth.com/user/$state", true)
                ->toggleable()
                ->toggledHiddenByDefault(),
            TextColumn::make('downloads')
                ->label(trans('minecraft-modrinth::strings.table.columns.downloads'))
                ->icon('tabler-download')
                ->numeric()
                ->toggleable(),
            TextColumn::make('date_modified')
                ->label(trans('minecraft-modrinth::strings.table.columns.date_modified'))
                ->icon('tabler-calendar')
                ->formatStateUsing(fn ($state) => Carbon::parse($state, 'UTC')->diffForHumans())
                ->tooltip(fn ($state, Table $table) => Carbon::parse($state, 'UTC')->timezone(user()->timezone ?? 'UTC')->format($table->getDefaultDateTimeDisplayFormat()))
                ->toggleable(),
        ];
    }

    protected function getInstalledColumns(): array
    {
        return [
            ImageColumn::make('icon')
                ->label('')
                ->size(40)
                ->getStateUsing(fn (array $record) => $record['project_info']['icon_url'] ?? null),
            TextColumn::make('title')
                ->label(trans('minecraft-modrinth::strings.table.columns.title'))
                ->searchable()
                ->wrap()
                ->getStateUsing(fn (array $record) => $record['project_info']['title'] ?? $record['name'])
                ->description(function (array $record) {
                    if (isset($record['project_info']['description'])) {
                        return str(strlen($record['project_info']['description']) > 80
                            ? substr($record['project_info']['description'], 0, 80) . '...'
                            : $record['project_info']['description'])->limit(80);
                    }
                    return $record['name'];
                }),
            TextColumn::make('version')
                ->label(trans('minecraft-modrinth::strings.table.columns.version'))
                ->badge()
                ->getStateUsing(fn (array $record) => $record['modrinth_info']['version_number'] ?? trans('minecraft-modrinth::strings.page.unknown'))
                ->color(fn (array $record) => ($record['has_update'] ?? false) ? 'warning' : 'success')
                ->icon(fn (array $record) => ($record['has_update'] ?? false) ? 'tabler-refresh' : 'tabler-check'),
        ];
    }

    protected function getModrinthActions(): array
    {
        $fileRepository = app(DaemonFileRepository::class);
        $installedFiles = $this->getInstalledFiles($fileRepository);

        return [
            // Up to date button (disabled)
            Action::make('up_to_date')
                ->label(trans('minecraft-modrinth::strings.status.up_to_date'))
                ->color('success')
                ->icon('tabler-check')
                ->disabled()
                ->visible(fn (array $record) => $this->getProjectStatus($record['project_id'], $fileRepository, $installedFiles, $record['slug'])['installed']
                    && !$this->getProjectStatus($record['project_id'], $fileRepository, $installedFiles, $record['slug'])['has_update']),

            // Update button (for datapacks with updates available)
            Action::make('update')
                ->label(trans('minecraft-modrinth::strings.actions.update'))
                ->color('warning')
                ->icon('tabler-refresh')
                ->visible(fn (array $record) => $this->getProjectStatus($record['project_id'], $fileRepository, $installedFiles, $record['slug'])['has_update'])
                ->modalSubmitAction(false)
                ->modalFooterActionsAlignment('end')
                ->schema(fn (array $record) => $this->getVersionSchema($record)),

            // Download button (for non-installed datapacks)
            Action::make('download')
                ->label(trans('minecraft-modrinth::strings.actions.download'))
                ->color('primary')
                ->visible(fn (array $record) => !$this->getProjectStatus($record['project_id'], $fileRepository, $installedFiles, $record['slug'])['installed'])
                ->modalSubmitAction(false)
                ->modalFooterActionsAlignment('end')
                ->schema(fn (array $record) => $this->getVersionSchema($record)),
        ];
    }

    protected function getVersionSchema(array $record): array
    {
        $schema = [];

        /** @var Server $server */
        $server = Filament::getTenant();

        $versions = array_slice(MinecraftModrinth::getModrinthVersions($record['project_id'], $server, $this->projectType), 0, 10);
        foreach ($versions as $versionData) {
            $files = $versionData['files'] ?? [];
            $primaryFile = null;

            foreach ($files as $fileData) {
                if ($fileData['primary']) {
                    $primaryFile = $fileData;
                    break;
                }
            }

            $schema[] = Section::make($versionData['name'])
                ->description($versionData['version_number'] . ($primaryFile ? ' (' . convert_bytes_to_readable($primaryFile['size']) . ')' : ' (' . trans('minecraft-modrinth::strings.version.no_file_found') . ')'))
                ->collapsed(!$versionData['featured'])
                ->collapsible()
                ->icon($versionData['version_type'] === 'alpha' ? 'tabler-circle-letter-a' : ($versionData['version_type'] === 'beta' ? 'tabler-circle-letter-b' : 'tabler-circle-letter-r'))
                ->iconColor($versionData['version_type'] === 'alpha' ? 'danger' : ($versionData['version_type'] === 'beta' ? 'warning' : 'success'))
                ->columns(3)
                ->schema([
                    TextEntry::make('type')
                        ->badge()
                        ->color($versionData['version_type'] === 'alpha' ? 'danger' : ($versionData['version_type'] === 'beta' ? 'warning' : 'success'))
                        ->state($versionData['version_type']),
                    TextEntry::make('downloads')
                        ->badge()
                        ->state($versionData['downloads']),
                    TextEntry::make('published')
                        ->badge()
                        ->state(Carbon::parse($versionData['date_published'], 'UTC')->diffForHumans())
                        ->tooltip(Carbon::parse($versionData['date_published'], 'UTC')->timezone(user()->timezone ?? 'UTC')->format('M j, Y H:i:s')),
                    TextEntry::make('changelog')
                        ->columnSpanFull()
                        ->markdown()
                        ->state($versionData['changelog']),
                ])
                ->headerActions([
                    Action::make('download_' . $versionData['id'])
                        ->label(trans('minecraft-modrinth::strings.actions.download'))
                        ->visible(!is_null($primaryFile))
                        ->action(function (DaemonFileRepository $fileRepository) use ($server, $versionData, $primaryFile) {
                            try {
                                $folder = $this->projectType->getFolder($server);
                                $fileRepository->setServer($server)->pull($primaryFile['url'], $folder);

                                // Clear cache to refresh update status
                                $this->projectUpdateStatus = null;
                                $this->installedFiles = null;
                                MinecraftModrinth::clearModsCache($server, $this->projectType);

                                Notification::make()
                                    ->title(trans('minecraft-modrinth::strings.notifications.download_started'))
                                    ->body($versionData['name'])
                                    ->success()
                                    ->send();
                            } catch (Exception $exception) {
                                report($exception);

                                Notification::make()
                                    ->title(trans('minecraft-modrinth::strings.notifications.download_failed'))
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->after(fn () => $this->redirect(static::getUrl(['view' => $this->currentView]))),
                ]);
        }

        return $schema;
    }

    protected function getInstalledActions(): array
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return [
            // Update button - auto download latest version
            Action::make('update')
                ->label(trans('minecraft-modrinth::strings.actions.update'))
                ->color('warning')
                ->icon('tabler-refresh')
                ->visible(fn (array $record) => ($record['has_update'] ?? false) && isset($record['latest_version']))
                ->requiresConfirmation()
                ->action(function (array $record, DaemonFileRepository $fileRepository) use ($server) {
                    try {
                        $latestVersion = $record['latest_version'];
                        $files = $latestVersion['files'] ?? [];
                        $primaryFile = null;

                        foreach ($files as $fileData) {
                            if ($fileData['primary']) {
                                $primaryFile = $fileData;
                                break;
                            }
                        }

                        if (!$primaryFile) {
                            throw new Exception(trans('minecraft-modrinth::strings.version.no_file_found'));
                        }

                        $folder = $this->projectType->getFolder($server);

                        // Delete old file first
                        $fileRepository->setServer($server)->deleteFiles($folder, [$record['name']]);

                        // Download new version
                        $fileRepository->setServer($server)->pull($primaryFile['url'], $folder);

                        // Clear cache
                        $this->projectUpdateStatus = null;
                        $this->installedFiles = null;
                        MinecraftModrinth::clearModsCache($server, $this->projectType);

                        Notification::make()
                            ->title(trans('minecraft-modrinth::strings.notifications.download_started'))
                            ->body($latestVersion['name'])
                            ->success()
                            ->send();
                    } catch (Exception $exception) {
                        report($exception);

                        Notification::make()
                            ->title(trans('minecraft-modrinth::strings.notifications.download_failed'))
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->after(fn () => $this->resetTable()),

            // Change version button - opens version selection modal
            Action::make('change_version')
                ->label(trans('minecraft-modrinth::strings.actions.change_version'))
                ->color('success')
                ->icon('tabler-versions')
                ->visible(fn (array $record) => isset($record['modrinth_info']['project_id']))
                ->modalSubmitAction(false)
                ->modalFooterActionsAlignment('end')
                ->schema(fn (array $record) => $this->getInstalledVersionSchema($record)),

            Action::make('delete')
                ->label(trans('minecraft-modrinth::strings.actions.delete'))
                ->color('danger')
                ->icon('tabler-trash')
                ->requiresConfirmation()
                ->action(function (array $record, DaemonFileRepository $fileRepository) use ($server) {
                    try {
                        $folder = $this->projectType->getFolder($server);
                        $fileRepository->setServer($server)->deleteFiles($folder, [$record['name']]);

                        // Clear cache
                        $this->installedFiles = null;
                        $this->projectUpdateStatus = null;
                        MinecraftModrinth::clearModsCache($server, $this->projectType);
                        $this->resetModsLoading();

                        Notification::make()
                            ->title(trans('minecraft-modrinth::strings.notifications.delete_success'))
                            ->body($record['name'])
                            ->success()
                            ->send();
                    } catch (Exception $exception) {
                        report($exception);

                        Notification::make()
                            ->title(trans('minecraft-modrinth::strings.notifications.delete_failed'))
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->after(fn () => $this->resetTable()),
        ];
    }

    protected function getInstalledVersionSchema(array $record): array
    {
        if (!isset($record['modrinth_info']['project_id'])) {
            return [];
        }

        $schema = [];

        /** @var Server $server */
        $server = Filament::getTenant();

        $versions = array_slice(MinecraftModrinth::getModrinthVersions($record['modrinth_info']['project_id'], $server, $this->projectType), 0, 10);
        foreach ($versions as $versionData) {
            $files = $versionData['files'] ?? [];
            $primaryFile = null;

            foreach ($files as $fileData) {
                if ($fileData['primary']) {
                    $primaryFile = $fileData;
                    break;
                }
            }

            $schema[] = Section::make($versionData['name'])
                ->description($versionData['version_number'] . ($primaryFile ? ' (' . convert_bytes_to_readable($primaryFile['size']) . ')' : ' (' . trans('minecraft-modrinth::strings.version.no_file_found') . ')'))
                ->collapsed(!$versionData['featured'])
                ->collapsible()
                ->icon($versionData['version_type'] === 'alpha' ? 'tabler-circle-letter-a' : ($versionData['version_type'] === 'beta' ? 'tabler-circle-letter-b' : 'tabler-circle-letter-r'))
                ->iconColor($versionData['version_type'] === 'alpha' ? 'danger' : ($versionData['version_type'] === 'beta' ? 'warning' : 'success'))
                ->columns(3)
                ->schema([
                    TextEntry::make('type')
                        ->badge()
                        ->color($versionData['version_type'] === 'alpha' ? 'danger' : ($versionData['version_type'] === 'beta' ? 'warning' : 'success'))
                        ->state($versionData['version_type']),
                    TextEntry::make('downloads')
                        ->badge()
                        ->state($versionData['downloads']),
                    TextEntry::make('published')
                        ->badge()
                        ->state(Carbon::parse($versionData['date_published'], 'UTC')->diffForHumans())
                        ->tooltip(Carbon::parse($versionData['date_published'], 'UTC')->timezone(user()->timezone ?? 'UTC')->format('M j, Y H:i:s')),
                    TextEntry::make('changelog')
                        ->columnSpanFull()
                        ->markdown()
                        ->state($versionData['changelog']),
                ])
                ->headerActions([
                    Action::make('download_' . $versionData['id'])
                        ->label(trans('minecraft-modrinth::strings.actions.download'))
                        ->visible(!is_null($primaryFile))
                        ->action(function (DaemonFileRepository $fileRepository) use ($server, $versionData, $primaryFile, $record) {
                            try {
                                $folder = $this->projectType->getFolder($server);

                                // Delete old file first
                                $fileRepository->setServer($server)->deleteFiles($folder, [$record['name']]);

                                // Download new version
                                $fileRepository->setServer($server)->pull($primaryFile['url'], $folder);

                                // Clear cache to refresh update status
                                $this->projectUpdateStatus = null;
                                $this->installedFiles = null;
                                MinecraftModrinth::clearModsCache($server, $this->projectType);

                                Notification::make()
                                    ->title(trans('minecraft-modrinth::strings.notifications.download_started'))
                                    ->body($versionData['name'])
                                    ->success()
                                    ->send();
                            } catch (Exception $exception) {
                                report($exception);

                                Notification::make()
                                    ->title(trans('minecraft-modrinth::strings.notifications.download_failed'))
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->after(fn () => $this->redirect(static::getUrl(['view' => $this->currentView]))),
                ]);
        }

        return $schema;
    }

    protected function getHeaderActions(): array
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        $folder = $this->projectType->getFolder($server);

        return [
            Action::make('open_folder')
                ->label(fn () => trans('minecraft-modrinth::strings.page.open_folder', ['folder' => $folder]))
                ->icon('tabler-folder')
                ->url(fn () => ListFiles::getUrl(['path' => $folder]), true),
        ];
    }

    public function content(Schema $schema): Schema
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return $schema
            ->components([
                \Filament\Schemas\Components\View::make('minecraft-modrinth::components.server-info-datapack')
                    ->viewData([
                        'minecraft_version' => MinecraftModrinth::getMinecraftVersion($server) ?? trans('minecraft-modrinth::strings.page.unknown'),
                        'installed_count' => (function () use ($server) {
                            try {
                                $fileRepository = app(DaemonFileRepository::class);
                                $folder = $this->projectType->getFolder($server);
                                $files = $fileRepository->setServer($server)->getDirectory($folder);

                                if (isset($files['error'])) {
                                    if (str_contains($files['error'], 'directory was not found')) {
                                        return 0;
                                    }
                                    return trans('minecraft-modrinth::strings.page.unknown');
                                }

                                return collect($files)
                                    ->filter(fn ($file) => str($file['name'])->lower()->endsWith('.zip'))
                                    ->count();
                            } catch (Exception $exception) {
                                report($exception);
                                return trans('minecraft-modrinth::strings.page.unknown');
                            }
                        })(),
                        'installed_label' => trans('minecraft-modrinth::strings.page.installed', ['type' => $this->projectType->getLabel()]),
                    ]),
                Tabs::make('view_tabs')
                    ->tabs([
                        Tab::make('modrinth')
                            ->label(trans('minecraft-modrinth::strings.tabs.modrinth'))
                            ->icon('tabler-world-search')
                            ->extraAttributes(['wire:click' => 'switchToModrinth', 'class' => 'cursor-pointer']),
                        Tab::make('installed')
                            ->label(trans('minecraft-modrinth::strings.tabs.installed'))
                            ->icon('tabler-package')
                            ->extraAttributes(['wire:click' => 'switchToInstalled', 'class' => 'cursor-pointer']),
                    ])
                    ->contained(false)
                    ->activeTab(fn () => $this->currentView === 'installed' ? 2 : 1),
                \Filament\Schemas\Components\View::make('minecraft-modrinth::components.loading-indicator')
                    ->viewData([
                        'loaded' => $this->loadedCount,
                        'total' => $this->totalCount,
                        // is_loading: true only if there are pending files to process
                        // Don't show if cache was loaded (modsInfoCache not empty) and no pending files
                        'is_loading' => $this->currentView === 'installed' && !empty($this->pendingFiles),
                        // show_indicator: only show visual if >30% need processing
                        'show_indicator' => $this->totalCount > 0 && count($this->pendingFiles) > ($this->totalCount * 0.3),
                    ]),
                EmbeddedTable::make(),
            ]);
    }
}
