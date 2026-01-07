<?php

namespace Kikipunk\EggLibrary\Data;

class EggItem
{
    public function __construct(
        public string $name,
        public string $description,
        public string $author,
        public string $category,
        public string $path,
        public string $downloadUrl,
        public ?string $uuid = null,
        public array $tags = [],
        public array $features = [],
    ) {}

    /**
     * Create from GitHub file info and egg JSON content
     */
    public static function fromGitHub(array $fileInfo, array $eggContent, string $categoryName): self
    {
        return new self(
            name: $eggContent['name'] ?? pathinfo($fileInfo['name'], PATHINFO_FILENAME),
            description: $eggContent['description'] ?? '',
            author: $eggContent['author'] ?? 'Unknown',
            category: $categoryName,
            path: $fileInfo['path'],
            downloadUrl: $fileInfo['download_url'] ?? '',
            uuid: $eggContent['uuid'] ?? null,
            tags: $eggContent['tags'] ?? [],
            features: $eggContent['features'] ?? [],
        );
    }

    /**
     * Create from cached data array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            description: $data['description'] ?? '',
            author: $data['author'] ?? 'Unknown',
            category: $data['category'],
            path: $data['path'],
            downloadUrl: $data['downloadUrl'] ?? $data['download_url'] ?? '',
            uuid: $data['uuid'] ?? null,
            tags: $data['tags'] ?? [],
            features: $data['features'] ?? [],
        );
    }

    /**
     * Convert to array for table display
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'author' => $this->author,
            'category' => $this->category,
            'path' => $this->path,
            'downloadUrl' => $this->downloadUrl,
            'uuid' => $this->uuid,
            'tags' => $this->tags,
            'features' => $this->features,
        ];
    }

    /**
     * Check if egg matches search query
     */
    public function matchesSearch(string $query): bool
    {
        if (empty($query)) {
            return true;
        }

        $query = strtolower($query);

        return str_contains(strtolower($this->name), $query)
            || str_contains(strtolower($this->description), $query)
            || str_contains(strtolower($this->author), $query)
            || collect($this->tags)->contains(fn ($tag) => str_contains(strtolower($tag), $query));
    }
}
