<?php

namespace GovStore\Theming\Compliance;

/**
 * Counts theme-compliance violations (§15.1) per file per rule across gov-store packages:
 *   R1 colour literals in views / CSS / JS
 *   R2 <style> blocks in views
 *   R3 inline style= with colour, background, border-colour, font-family or radius
 * The ratchet baseline (compliance-baseline.json) may only go down.
 */
final class ComplianceScanner
{
    private const EXCLUDE = ['/theming/themes/', '/theming/fonts/', '/vendor/', '/node_modules/', '/tests/'];

    private const NAMED = 'white|black|red|green|blue|yellow|orange|purple|gray|grey|silver|maroon|navy|teal|olive|aqua|lime|fuchsia|pink|brown|gold|crimson|darkred|darkgreen|darkblue|lightgray|lightgrey|darkgray|darkgrey|whitesmoke|gainsboro';

    public function __construct(private string $root) {}

    /** @return array<string, array<string, int>> relative path => [rule => count] */
    public function scan(): array
    {
        $results = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if (! preg_match('/(\.blade\.php|\.css|\.js)$/', $path)) {
                continue;
            }
            foreach (self::EXCLUDE as $excluded) {
                if (str_contains($path, $excluded)) {
                    continue 2;
                }
            }
            $counts = $this->scanContents((string) file_get_contents($path), str_ends_with($path, '.blade.php'));
            if (array_sum($counts) > 0) {
                $results[$this->relative($path)] = array_filter($counts);
            }
        }
        ksort($results);

        return $results;
    }

    /** @return array{R1: int, R2: int, R3: int} */
    public function scanContents(string $contents, bool $isView): array
    {
        // Comments never count.
        $code = preg_replace(['#/\*.*?\*/#s', '/\{\{--.*?--\}\}/s', '/<!--.*?-->/s'], '', $contents);
        $r1 = preg_match_all('/(?<!href=")(?<!href=\')(?<![\w&\/=-])#(?:[0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{3,4})(?![\w-])/', $code)
            + preg_match_all('/\b(?:rgba?|hsla?)\(\s*\d/i', $code)
            + preg_match_all('/(?:color|background(?:-color)?|border(?:-[a-z]+)*|fill|stroke|outline)\s*:\s*[^;"\'{}]*\b(?:'.self::NAMED.')\b/i', $code);
        $r2 = $isView ? preg_match_all('/<style\b/i', $code) : 0;
        $r3 = $isView ? preg_match_all('/\bstyle\s*=\s*(["\'])[^"\']*(?:color|background|border-color|font-family|border-radius)\s*:[^"\']*\1/i', $code) : 0;

        return ['R1' => $r1, 'R2' => $r2, 'R3' => $r3];
    }

    /**
     * Compare a scan to the baseline.
     *
     * @return array{increased: array, new: array, tightenable: array}
     */
    public static function compare(array $scan, array $baseline): array
    {
        $increased = $new = $tightenable = [];
        foreach ($scan as $file => $counts) {
            if (! isset($baseline[$file])) {
                $new[$file] = $counts;

                continue;
            }
            foreach ($counts as $rule => $count) {
                $allowed = $baseline[$file][$rule] ?? 0;
                if ($count > $allowed) {
                    $increased[$file][$rule] = [$allowed, $count];
                } elseif ($count < $allowed) {
                    $tightenable[$file][$rule] = [$allowed, $count];
                }
            }
        }
        foreach ($baseline as $file => $counts) {
            foreach ($counts as $rule => $allowed) {
                $current = $scan[$file][$rule] ?? 0;
                if ($current < $allowed && ! isset($tightenable[$file][$rule])) {
                    $tightenable[$file][$rule] = [$allowed, $current];
                }
            }
        }

        return compact('increased', 'new', 'tightenable');
    }

    /** New baseline that only ever goes down: min(old, current) per file/rule; files never added. */
    public static function tighten(array $scan, array $baseline): array
    {
        $next = [];
        foreach ($baseline as $file => $counts) {
            foreach ($counts as $rule => $allowed) {
                $value = min($allowed, $scan[$file][$rule] ?? 0);
                if ($value > 0) {
                    $next[$file][$rule] = $value;
                }
            }
        }
        ksort($next);

        return $next;
    }

    private function relative(string $path): string
    {
        $root = rtrim(str_replace('\\', '/', $this->root), '/');

        return ltrim(substr($path, strlen($root)), '/');
    }
}
