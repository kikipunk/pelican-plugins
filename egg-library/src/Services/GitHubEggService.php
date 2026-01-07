<?php

namespace Kikipunk\EggLibrary\Services;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Kikipunk\EggLibrary\Data\EggCategory;
use Kikipunk\EggLibrary\Data\EggItem;

class GitHubEggService
{
    private const CATALOG_URL = 'https://raw.githubusercontent.com/pelican-eggs/pelican-eggs.github.io/main/content/pelican.json';

    private int $cacheDuration;

    public function __construct()
    {
        $this->cacheDuration = config('egg-library.cache_duration', 60);
    }

    /**
     * Get all available categories
     *
     * @return Collection<EggCategory>
     */
    public function getCategories(): Collection
    {
        $data = $this->getCatalogData();

        return collect($data['categories'] ?? [])
            ->map(fn (array $cat) => new EggCategory(
                name: $cat['name'],
                description: $cat['description'] ?? '',
                url: "https://github.com/pelican-eggs/{$cat['name']}",
                apiUrl: '',
                eggCount: count($cat['eggs'] ?? []),
            ))
            ->values();
    }

    /**
     * Get all eggs from a specific category
     *
     * @return Collection<EggItem>
     */
    public function getEggsFromCategory(EggCategory $category): Collection
    {
        $data = $this->getCatalogData();

        $categoryData = collect($data['categories'] ?? [])
            ->first(fn (array $cat) => $cat['name'] === $category->name);

        if (! $categoryData) {
            return collect();
        }

        return collect($categoryData['eggs'] ?? [])
            ->map(fn (array $egg) => EggItem::fromArray($egg))
            ->values();
    }

    /**
     * Get all eggs from all categories
     *
     * @return Collection<EggItem>
     */
    public function getAllEggs(): Collection
    {
        $data = $this->getCatalogData();

        return collect($data['categories'] ?? [])
            ->flatMap(fn (array $cat) => collect($cat['eggs'] ?? [])
                ->map(fn (array $egg) => EggItem::fromArray($egg)))
            ->values();
    }

    /**
     * Search eggs across all categories or a specific one
     *
     * @return Collection<EggItem>
     */
    public function searchEggs(string $query, ?EggCategory $category = null): Collection
    {
        $eggs = $category
            ? $this->getEggsFromCategory($category)
            : $this->getAllEggs();

        if (empty($query)) {
            return $eggs;
        }

        return $eggs->filter(fn (EggItem $egg) => $egg->matchesSearch($query));
    }

    /**
     * Get the full egg JSON content
     */
    public function getEggContent(string $downloadUrl): ?array
    {
        $cacheKey = 'egg_library:content:' . md5($downloadUrl);

        return Cache::remember($cacheKey, now()->addMinutes($this->cacheDuration), function () use ($downloadUrl) {
            try {
                $response = Http::timeout(15)->get($downloadUrl);

                if ($response->successful()) {
                    return $response->json();
                }

                Log::warning('Failed to fetch egg content', [
                    'url' => $downloadUrl,
                    'status' => $response->status(),
                ]);

                return null;
            } catch (Exception $e) {
                Log::error('Error fetching egg content', [
                    'url' => $downloadUrl,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }

    /**
     * Force refresh all cached data
     */
    public function refreshCache(): void
    {
        Cache::forget('egg_library:catalog');
    }

    /**
     * Get a specific category by name
     */
    public function getCategoryByName(string $name): ?EggCategory
    {
        return $this->getCategories()->first(fn (EggCategory $cat) => $cat->name === $name);
    }

    /**
     * Get and cache the parsed catalog data
     */
    private function getCatalogData(): array
    {
        return Cache::remember('egg_library:catalog', now()->addMinutes($this->cacheDuration), function () {
            return $this->fetchAndParseCatalog();
        });
    }

    /**
     * Fetch and parse the catalog JSON
     */
    private function fetchAndParseCatalog(): array
    {
        try {
            $response = Http::timeout(30)->get(self::CATALOG_URL);

            if (! $response->successful()) {
                Log::warning('Failed to fetch egg catalog', [
                    'url' => self::CATALOG_URL,
                    'status' => $response->status(),
                ]);

                return ['categories' => []];
            }

            return $this->parseJson($response->json());
        } catch (Exception $e) {
            Log::error('Error fetching egg catalog', [
                'url' => self::CATALOG_URL,
                'error' => $e->getMessage(),
            ]);

            return ['categories' => []];
        }
    }

    /**
     * Parse the catalog JSON to extract categories and eggs
     */
    private function parseJson(array $data): array
    {
        $categories = [];

        $nests = $data['nests'] ?? [];

        foreach ($nests as $nest) {
            $nestType = $nest['nest_type'] ?? '';
            $eggs = $nest['Eggs'] ?? [];

            if (empty($nestType) || empty($eggs)) {
                continue;
            }

            $categoryName = $this->normalizeCategoryName($nestType);
            $categoryEggs = [];

            foreach ($eggs as $eggData) {
                $eggInfo = $eggData['egg'] ?? [];
                $downloadUrl = $eggData['download_url'] ?? '';

                if (empty($downloadUrl)) {
                    continue;
                }

                $eggName = $eggInfo['name'] ?? $this->extractEggNameFromUrl($downloadUrl);
                $path = $this->extractPathFromUrl($downloadUrl);

                $categoryEggs[] = [
                    'name' => $eggName,
                    'description' => $eggInfo['description'] ?? '',
                    'author' => $eggInfo['author'] ?? 'pelican-eggs',
                    'category' => $categoryName,
                    'path' => $path,
                    'downloadUrl' => $downloadUrl,
                    'uuid' => $eggInfo['uuid'] ?? null,
                    'tags' => $eggInfo['tags'] ?? [],
                    'features' => $eggInfo['features'] ?? [],
                ];
            }

            if (! empty($categoryEggs)) {
                $categories[] = [
                    'name' => $categoryName,
                    'description' => $this->getCategoryDescription($categoryName),
                    'eggs' => $categoryEggs,
                ];
            }
        }

        // Sort categories by name
        usort($categories, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return ['categories' => $categories];
    }

    /**
     * Normalize category name to lowercase with hyphens
     */
    private function normalizeCategoryName(string $name): string
    {
        return strtolower(str_replace(' ', '-', trim($name)));
    }

    /**
     * Extract egg name from download URL (fallback)
     */
    private function extractEggNameFromUrl(string $url): string
    {
        if (preg_match('/egg-([^\/]+)\.json$/i', $url, $match)) {
            $name = str_replace('-', ' ', $match[1]);

            return ucwords($name);
        }

        return 'Unknown';
    }

    /**
     * Extract the file path from the raw GitHub URL
     */
    private function extractPathFromUrl(string $url): string
    {
        // Match path after /refs/heads/main/
        if (preg_match('/\/refs\/heads\/main\/(.+)$/', $url, $matches)) {
            return $matches[1];
        }

        // Fallback: match after /main/
        if (preg_match('/\/main\/(.+)$/', $url, $matches)) {
            return $matches[1];
        }

        return basename($url);
    }

    /**
     * Get a description for a category
     */
    private function getCategoryDescription(string $name): string
    {
        return match ($name) {
            'chatbot' => 'Discord bots, Twitch bots, and chat applications',
            'database' => 'Database servers like MongoDB, Redis, PostgreSQL',
            'standalone' => 'Standalone game servers',
            'steamcmd' => 'Game servers using SteamCMD',
            'generic-language' => 'Generic language runtimes (Java, Python, Node.js)',
            'minecraft' => 'Minecraft servers and proxies',
            'monitoring' => 'Monitoring tools like Prometheus',
            'storage' => 'Storage solutions like MinIO, SFTP',
            'voice' => 'Voice servers like TeamSpeak',
            default => '',
        };
    }
}
