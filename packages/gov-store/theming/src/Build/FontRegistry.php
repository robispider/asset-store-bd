<?php

namespace GovStore\Theming\Build;

/**
 * Self-hosted font registry (`fonts/fonts.json`). A woff2 file is emitted only when it is
 * present in `fonts/`; otherwise the stack falls back to installed fonts, so a release
 * without the binaries still renders correctly (no Google Fonts calls).
 */
final class FontRegistry
{
    private array $fonts;

    public function __construct(private string $directory)
    {
        $path = $directory.DIRECTORY_SEPARATOR.'fonts.json';
        $this->fonts = is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
    }

    public function has(string $key): bool
    {
        return isset($this->fonts[$key]);
    }

    public function keys(): array
    {
        return array_keys($this->fonts);
    }

    public function stack(string $key): ?string
    {
        return $this->fonts[$key]['stack'] ?? null;
    }

    public function isBengali(string $key): bool
    {
        return (bool) ($this->fonts[$key]['bengali'] ?? false);
    }

    /** @return array<int, array{key: string, family: string, file: string, path: string, weight: string, style: string, unicode-range: ?string}> */
    public function availableFiles(array $keys): array
    {
        $files = [];
        foreach (array_unique($keys) as $key) {
            foreach ($this->fonts[$key]['files'] ?? [] as $file) {
                $path = $this->directory.DIRECTORY_SEPARATOR.$file['file'];
                if (is_file($path) && ! empty($this->fonts[$key]['family'])) {
                    $files[] = ['key' => $key, 'family' => $this->fonts[$key]['family'], 'path' => $path] + $file + ['unicode-range' => null];
                }
            }
        }

        return $files;
    }

    /** @param  callable(string): string  $url  maps a font file name to its public URL */
    public function fontFaceCss(array $keys, callable $url): string
    {
        $css = '';
        foreach ($this->availableFiles($keys) as $file) {
            $css .= "@font-face{font-family:\"{$file['family']}\";src:url(\"{$url($file['file'])}\") format(\"woff2\");"
                ."font-weight:{$file['weight']};font-style:{$file['style']};font-display:swap;"
                .($file['unicode-range'] ? "unicode-range:{$file['unicode-range']};" : '')."}\n";
        }

        return $css;
    }
}
