<?php

namespace GovStore\Theming\Themes;

use Illuminate\Support\Facades\Log;

/**
 * Reads public/vendor/gs-theme/manifest.json written by gs-theme:build.
 */
final class Manifest
{
    private ?array $data = null;

    private bool $loaded = false;

    public function __construct(private string $path, private string $baseUrl) {}

    public function exists(): bool
    {
        return $this->data() !== null;
    }

    public function entry(string $name): ?string
    {
        $value = $this->data()[$name] ?? null;

        return is_string($value) ? $this->url($value) : null;
    }

    public function theme(string $key): ?string
    {
        $value = $this->data()['themes'][$key] ?? null;

        return $value ? $this->url($value) : null;
    }

    public function themes(): array
    {
        return $this->data()['themes'] ?? [];
    }

    public function fonts(string $key): array
    {
        return array_map(fn ($file) => $this->url($file), $this->data()['fonts'][$key] ?? []);
    }

    public function data(): ?array
    {
        if (! $this->loaded) {
            $this->loaded = true;
            $file = $this->path.'/manifest.json';
            $this->data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
            if ($this->data === null) {
                Log::warning('gs-theme: manifest.json missing — run `php artisan gs-theme:build`; falling back to stock Snipe-IT styling.');
            }
        }

        return $this->data;
    }

    private function url(string $relative): string
    {
        return asset(trim($this->baseUrl, '/').'/'.$relative);
    }
}
