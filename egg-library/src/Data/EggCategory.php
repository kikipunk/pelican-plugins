<?php

namespace Kikipunk\EggLibrary\Data;

class EggCategory
{
    public function __construct(
        public string $name,
        public string $description,
        public string $url,
        public string $apiUrl,
        public int $eggCount = 0,
    ) {}

    /**
     * Create from GitHub API repository data
     */
    public static function fromGitHub(array $repo): self
    {
        return new self(
            name: $repo['name'],
            description: $repo['description'] ?? '',
            url: $repo['html_url'],
            apiUrl: $repo['contents_url'] ?? "https://api.github.com/repos/{$repo['full_name']}/contents",
        );
    }

    /**
     * Get human-readable label
     */
    public function getLabel(): string
    {
        return str($this->name)
            ->title()
            ->replace('-', ' ')
            ->toString();
    }

    /**
     * Get appropriate icon based on category name
     */
    public function getIcon(): string
    {
        return match (true) {
            str_contains($this->name, 'minecraft') => 'tabler-brand-minecraft',
            str_contains($this->name, 'game') => 'tabler-device-gamepad-2',
            str_contains($this->name, 'steam') => 'tabler-brand-steam',
            str_contains($this->name, 'database') => 'tabler-database',
            str_contains($this->name, 'voice') => 'tabler-microphone',
            str_contains($this->name, 'monitor') => 'tabler-activity',
            str_contains($this->name, 'storage') => 'tabler-server',
            str_contains($this->name, 'software') => 'tabler-apps',
            str_contains($this->name, 'bot') || str_contains($this->name, 'chat') => 'tabler-robot',
            str_contains($this->name, 'generic') => 'tabler-box',
            default => 'tabler-egg',
        };
    }

    /**
     * Get badge color for UI
     */
    public function getColor(): string
    {
        return match (true) {
            str_contains($this->name, 'minecraft') => 'success',
            str_contains($this->name, 'game') => 'primary',
            str_contains($this->name, 'database') => 'warning',
            str_contains($this->name, 'voice') => 'info',
            str_contains($this->name, 'monitor') => 'danger',
            default => 'gray',
        };
    }

    /**
     * Get the API URL for contents (remove {+path} placeholder)
     */
    public function getContentsApiUrl(string $path = ''): string
    {
        $baseUrl = str_replace('{+path}', '', $this->apiUrl);

        return rtrim($baseUrl, '/') . ($path ? '/' . ltrim($path, '/') : '');
    }
}
