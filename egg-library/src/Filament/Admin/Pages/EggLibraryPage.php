<?php

namespace Kikipunk\EggLibrary\Filament\Admin\Pages;

use App\Models\Egg;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Kikipunk\EggLibrary\Services\EggImportService;
use Kikipunk\EggLibrary\Services\GitHubEggService;
use Livewire\Attributes\Url;

class EggLibraryPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-world-download';

    protected static ?string $slug = 'egg-library';

    protected static bool $shouldRegisterNavigation = false;

    #[Url]
    public ?string $selectedCategory = null;

    public function getTitle(): string
    {
        return trans('egg-library::strings.page.title');
    }

    public function getSubheading(): ?string
    {
        return trans('egg-library::strings.page.description');
    }

    public function selectCategory(?string $category): void
    {
        $this->selectedCategory = $category;
        $this->resetTable();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label(trans('egg-library::strings.actions.refresh'))
                ->icon('tabler-refresh')
                ->color('gray')
                ->action(function () {
                    app(GitHubEggService::class)->refreshCache();

                    Notification::make()
                        ->title(trans('egg-library::strings.notifications.cache_refreshed'))
                        ->success()
                        ->send();
                }),
            Action::make('view_eggs')
                ->label(trans('egg-library::strings.actions.view_eggs'))
                ->icon('tabler-list')
                ->url('/admin/eggs'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(trans('egg-library::strings.sections.available_eggs'))
            ->searchable()
            ->deferLoading()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->records(function (?string $search, int $page) {
                $service = app(GitHubEggService::class);

                $category = $this->selectedCategory
                    ? $service->getCategoryByName($this->selectedCategory)
                    : null;

                $eggs = $service->searchEggs($search ?? '', $category);

                // Get all installed egg names
                $installedNames = Egg::pluck('name')->toArray();

                $eggsArray = $eggs->map(function ($egg) use ($installedNames) {
                    $data = $egg->toArray();
                    $data['installed_status'] = in_array($data['name'], $installedNames) ? 'installed' : null;

                    return $data;
                });

                $perPage = $this->getTableRecordsPerPage();

                return new LengthAwarePaginator(
                    $eggsArray->forPage($page, $perPage)->values()->all(),
                    $eggsArray->count(),
                    $perPage,
                    $page
                );
            })
            ->columns([
                TextColumn::make('name')
                    ->label(trans('egg-library::strings.labels.name'))
                    ->weight('bold')
                    ->searchable()
                    ->description(fn (array $record) => Str::limit($record['description'] ?? '', 80))
                    ->url(function (array $record) {
                        $url = $record['downloadUrl'];
                        $url = str_replace('raw.githubusercontent.com', 'github.com', $url);
                        $url = str_replace('/refs/heads/main/', '/tree/main/', $url);

                        return dirname($url);
                    }, true),
                TextColumn::make('installed_status')
                    ->label(trans('egg-library::strings.labels.status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state === 'installed' ? trans('egg-library::strings.status.similar') : '')
                    ->icon(fn (?string $state) => $state === 'installed' ? 'tabler-tilde' : null)
                    ->color(fn (?string $state) => $state === 'installed' ? 'warning' : null)
                    ->placeholder(''),
                TextColumn::make('category')
                    ->label(trans('egg-library::strings.labels.category'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => str($state)->title()->replace('-', ' '))
                    ->color('primary'),
            ])
            ->recordActions([
                Action::make('import')
                    ->label(trans('egg-library::strings.actions.import'))
                    ->icon('tabler-download')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalIcon('tabler-download')
                    ->modalIconColor('primary')
                    ->modalHeading(fn (array $record) => trans('egg-library::strings.modals.import_heading', ['name' => $record['name']]))
                    ->modalDescription(fn (array $record) => $record['description'] ?? '')
                    ->form(function (array $record) {
                        // Fetch the actual egg content to get the UUID and name
                        $githubService = app(GitHubEggService::class);
                        $importService = app(EggImportService::class);

                        $eggContent = $githubService->getEggContent($record['downloadUrl']);
                        if (! $eggContent) {
                            return [];
                        }

                        $uuid = $eggContent['uuid'] ?? null;
                        $name = $eggContent['name'] ?? $record['name'];

                        $existsByUuid = $importService->eggExists($uuid);
                        $existsByName = $importService->eggExistsByName($name);

                        $hasConflict = $existsByUuid || $existsByName;

                        if ($hasConflict) {
                            $warningMessage = $existsByUuid
                                ? trans('egg-library::strings.warnings.egg_exists_uuid')
                                : trans('egg-library::strings.warnings.egg_exists_name', ['name' => $name]);

                            return [
                                Placeholder::make('warning')
                                    ->label('')
                                    ->content($warningMessage)
                                    ->extraAttributes(['class' => 'text-danger-600 dark:text-danger-400 font-medium']),
                                Radio::make('import_mode')
                                    ->label(trans('egg-library::strings.labels.import_mode'))
                                    ->options([
                                        'update' => trans('egg-library::strings.options.update_existing'),
                                        'new' => trans('egg-library::strings.options.create_new'),
                                    ])
                                    ->default('update')
                                    ->descriptions([
                                        'update' => trans('egg-library::strings.options.update_existing_desc'),
                                        'new' => trans('egg-library::strings.options.create_new_desc'),
                                    ])
                                    ->live(),
                                TextInput::make('custom_name')
                                    ->label(trans('egg-library::strings.labels.custom_name'))
                                    ->placeholder($name)
                                    ->visible(fn ($get) => $get('import_mode') === 'new'),
                            ];
                        }

                        // No conflict - no form needed
                        return [];
                    })
                    ->action(function (array $record, array $data) {
                        $mode = $data['import_mode'] ?? 'create';

                        $githubService = app(GitHubEggService::class);
                        $importService = app(EggImportService::class);

                        // Content is cached, so this won't make another HTTP request
                        $eggContent = $githubService->getEggContent($record['downloadUrl']);

                        if (! $eggContent) {
                            Notification::make()
                                ->title(trans('egg-library::strings.notifications.import_failed'))
                                ->body(trans('egg-library::strings.notifications.fetch_failed'))
                                ->danger()
                                ->send();

                            return;
                        }

                        $customName = $data['custom_name'] ?? null;
                        $result = $importService->importFromJson($eggContent, $mode === 'new', $customName);

                        if ($result['success']) {
                            Notification::make()
                                ->title(trans('egg-library::strings.notifications.import_success'))
                                ->body(trans('egg-library::strings.notifications.import_success_body', [
                                    'name' => $result['egg']->name,
                                    'action' => $result['action'],
                                ]))
                                ->success()
                                ->send();

                            // Refresh table to show updated status
                            $this->resetTable();
                        } else {
                            Notification::make()
                                ->title(trans('egg-library::strings.notifications.import_failed'))
                                ->body($result['message'])
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('view_github')
                    ->label(trans('egg-library::strings.actions.view_source'))
                    ->icon('tabler-brand-github')
                    ->color('gray')
                    ->url(function (array $record) {
                        $url = $record['downloadUrl'];
                        // Convert raw.githubusercontent.com to github.com
                        $url = str_replace('raw.githubusercontent.com', 'github.com', $url);
                        // Convert /refs/heads/main/ to /tree/main/
                        $url = str_replace('/refs/heads/main/', '/tree/main/', $url);
                        // Get directory path (remove filename)
                        return dirname($url);
                    }, true),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getCategoryFilterSection(),
                EmbeddedTable::make(),
            ]);
    }

    protected function getCategoryFilterSection(): Section
    {
        $categories = app(GitHubEggService::class)->getCategories();
        $selected = $this->selectedCategory;

        $actions = [
            Action::make('filter_all')
                ->label(trans('egg-library::strings.categories.all'))
                ->icon('tabler-apps')
                ->size('sm')
                ->color(fn () => $this->selectedCategory === null ? 'primary' : 'gray')
                ->action(fn () => $this->selectCategory(null)),
        ];

        foreach ($categories as $category) {
            $categoryName = $category->name;
            $actions[] = Action::make('filter_' . $categoryName)
                ->label($category->getLabel())
                ->icon($category->getIcon())
                ->size('sm')
                ->color(fn () => $this->selectedCategory === $categoryName ? 'primary' : 'gray')
                ->action(function () use ($categoryName) {
                    $this->selectCategory($categoryName);
                });
        }

        return Section::make(trans('egg-library::strings.sections.categories'))
            ->compact()
            ->schema([
                \Filament\Schemas\Components\Actions::make($actions)->fullWidth(),
            ]);
    }
}
