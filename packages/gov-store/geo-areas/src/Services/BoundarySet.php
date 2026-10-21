<?php

namespace GovStore\GeoAreas\Services;

use RuntimeException;

/**
 * One built boundary set (bd-boundaries-<date>.<n>): its manifest, the shape index and public URLs.
 * The files are static and open-licence; access to *data* on top of them is decided elsewhere.
 */
class BoundarySet
{
    private ?array $manifest = null;

    private ?array $index = null;

    /** @var array<int, true>|null */
    private ?array $shapes = null;

    public function __construct(private ?string $version = null) {}

    public function version(): string
    {
        return $this->version ??= (string) config('geo-areas.boundary_version');
    }

    public function sourcePath(): string
    {
        return rtrim((string) config('geo-areas.boundary_source'), '/\\').'/'.$this->version();
    }

    public function exists(): bool
    {
        return is_file($this->sourcePath().'/manifest.json');
    }

    public function manifest(): array
    {
        return $this->manifest ??= $this->readJson('manifest.json');
    }

    public function attribution(): string
    {
        return $this->exists() ? (string) ($this->manifest()['attribution'] ?? '') : '';
    }

    /** Public URL of the set's folder, e.g. /vendor/geo-areas/boundaries/bd-boundaries-2026-10-22.1 */
    public function baseUrl(): string
    {
        return '/'.trim((string) config('geo-areas.boundary_url'), '/').'/'.$this->version();
    }

    public function childrenUrl(int $areaId): ?string
    {
        return $this->hasChildrenFile($areaId) ? $this->baseUrl().'/children/'.$areaId.'.json' : null;
    }

    public function root(): ?int
    {
        return $this->exists() ? (int) ($this->index()['root'] ?? 0) ?: null : null;
    }

    public function hasShape(int $areaId): bool
    {
        if ($this->shapes === null) {
            $this->shapes = [];
            foreach (($this->exists() ? $this->index()['shapes'] ?? [] : []) as $ids) {
                foreach ($ids as $id) {
                    $this->shapes[(int) $id] = true;
                }
            }
        }

        return isset($this->shapes[$areaId]);
    }

    /** True when the area has a children file, i.e. it can be opened on the map. */
    public function hasChildrenFile(int $areaId): bool
    {
        return $this->exists() && in_array($areaId, $this->index()['parents'] ?? [], true);
    }

    private function index(): array
    {
        return $this->index ??= $this->readJson('index.json');
    }

    private function readJson(string $file): array
    {
        $path = $this->sourcePath().'/'.$file;
        if (! is_file($path)) {
            throw new RuntimeException("Boundary set {$this->version()} has no {$file}");
        }

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }
}
