<?php

namespace GovStore\Theming\Themes;

/**
 * Discovers shipped themes (`themes/<key>/theme.json`) and applies inheritance:
 * tokens deep-merge by token path (child wins), variants and fonts shallow-merge,
 * overrides.css concatenates parent first; labels, descriptions and previews never inherit.
 */
final class ThemeRepository
{
    public const MAX_DEPTH = 3;

    private ?array $raw = null;

    /** @var array<string, Theme> */
    private array $themes = [];

    /** @var array<string, string[]> */
    private array $errors = [];

    public function __construct(private string $path) {}

    public function path(): string
    {
        return $this->path;
    }

    /** @return array<string, Theme> */
    public function all(): array
    {
        $this->load();

        return $this->themes;
    }

    public function find(?string $key): ?Theme
    {
        return $key === null ? null : ($this->all()[$key] ?? null);
    }

    /** @return array<string, Theme> */
    public function published(): array
    {
        return array_filter($this->all(), fn (Theme $theme) => $theme->isPublished());
    }

    public function isSelectable(?string $key): bool
    {
        return $key !== null && isset($this->published()[$key]);
    }

    /** Schema / inheritance errors found while loading, keyed by theme key. */
    public function loadErrors(): array
    {
        $this->load();

        return $this->errors;
    }

    public function raw(): array
    {
        $this->load();

        return $this->raw;
    }

    public function flush(): void
    {
        $this->raw = null;
        $this->themes = [];
        $this->errors = [];
    }

    /** Newest modification time of every theme file — used to bust dev assets. */
    public function fingerprint(): string
    {
        $parts = [];
        foreach (glob($this->path.'/*/*') ?: [] as $file) {
            $parts[] = $file.':'.filemtime($file);
        }

        return substr(sha1(implode('|', $parts)), 0, 12);
    }

    private function load(): void
    {
        if ($this->raw !== null) {
            return;
        }
        $this->raw = [];
        foreach (glob($this->path.'/*/theme.json') ?: [] as $file) {
            $dir = dirname($file);
            $folder = basename($dir);
            $manifest = json_decode((string) file_get_contents($file), true);
            if (! is_array($manifest)) {
                $this->errors[$folder][] = 'theme.json is not valid JSON';

                continue;
            }
            $this->raw[$folder] = [
                'dir' => $dir,
                'manifest' => $manifest,
                'light' => $this->readJson($dir.'/tokens.light.json', $folder),
                'dark' => $this->readJson($dir.'/tokens.dark.json', $folder),
                'overrides' => is_file($dir.'/overrides.css') ? (string) file_get_contents($dir.'/overrides.css') : '',
            ];
        }
        foreach (array_keys($this->raw) as $key) {
            $chain = $this->chain($key);
            if ($chain === null) {
                continue;
            }
            $light = $dark = $variants = $fonts = [];
            $overrides = '';
            foreach (array_reverse($chain) as $ancestor) {
                $entry = $this->raw[$ancestor];
                $light = self::mergeTokens($light, $entry['light'] ?? []);
                $dark = self::mergeTokens($dark, $entry['dark'] ?? []);
                $variants = array_merge($variants, $entry['manifest']['variants'] ?? []);
                $fonts = array_merge($fonts, $entry['manifest']['fonts'] ?? []);
                if (trim($entry['overrides']) !== '') {
                    $overrides .= "/* {$ancestor} */\n".$entry['overrides']."\n";
                }
            }
            $own = $this->raw[$key];
            $this->themes[$key] = new Theme(
                key: $key,
                path: $own['dir'],
                manifest: $own['manifest'],
                chain: $chain,
                lightTokens: $light,
                // Dark mode starts from the light tree (dimensions, fonts, references) with every
                // dark file in the chain applied on top; an empty dark chain stays empty (validation error).
                darkTokens: $dark === [] ? [] : self::mergeTokens($light, $dark),
                variants: $variants,
                fonts: $fonts,
                overridesCss: $overrides,
                darkIsScaffold: (bool) (($own['dark']['$extensions']['gs.scaffold'] ?? false)),
            );
        }
        ksort($this->themes);
    }

    /** @return string[]|null theme followed by its ancestors, or null on error */
    private function chain(string $key): ?array
    {
        $chain = [$key];
        $current = $key;
        while (($parent = $this->raw[$current]['manifest']['extends'] ?? null) !== null) {
            if (! is_string($parent) || ! isset($this->raw[$parent])) {
                $this->errors[$key][] = "extends unknown theme [".json_encode($parent).']';

                return null;
            }
            if (in_array($parent, $chain, true)) {
                $this->errors[$key][] = 'extends cycle: '.implode(' → ', [...$chain, $parent]);

                return null;
            }
            $chain[] = $parent;
            if (count($chain) - 1 > self::MAX_DEPTH) {
                $this->errors[$key][] = 'extends depth exceeds '.self::MAX_DEPTH;

                return null;
            }
            $current = $parent;
        }

        return $chain;
    }

    /** Deep merge by token path; a node carrying `$value` replaces the parent token entirely. */
    public static function mergeTokens(array $base, array $child): array
    {
        foreach ($child as $key => $value) {
            if ($key === '$extensions' && is_array($value)) {
                // Root-level extensions (e.g. gs.scaffold) are per-file, never inherited.
                continue;
            }
            if (is_array($value) && ! array_key_exists('$value', $value) && is_array($base[$key] ?? null) && ! array_key_exists('$value', $base[$key])) {
                $base[$key] = self::mergeTokens($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    private function readJson(string $file, string $folder): ?array
    {
        if (! is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (! is_array($data)) {
            $this->errors[$folder][] = basename($file).' is not valid JSON';

            return null;
        }

        return $data;
    }
}
