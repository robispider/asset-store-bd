<?php

namespace GovStore\Theming\Build;

use Throwable;

/**
 * Resolves a DTCG token tree: `{path}` references (whole value or embedded), `fonts.*`
 * registry references and `gs.derive` OKLCH derivations. Produces literal values.
 */
final class TokenResolver
{
    /** @var array<string, array{type: ?string, value: mixed, extensions: array}> */
    private array $tokens = [];

    private array $resolved = [];

    private array $errors = [];

    private array $stack = [];

    public function __construct(array $tree, private ?FontRegistry $fonts = null)
    {
        $this->flatten($tree, '', null);
    }

    public static function flattenTree(array $tree): array
    {
        return (new self($tree))->tokens;
    }

    /** @return array<string, string> path => literal value */
    public function resolveAll(): array
    {
        foreach (array_keys($this->tokens) as $path) {
            $this->resolve($path);
        }
        ksort($this->resolved);

        return array_filter($this->resolved, fn ($value) => $value !== null);
    }

    public function errors(): array
    {
        return array_values(array_unique($this->errors));
    }

    public function has(string $path): bool
    {
        return isset($this->tokens[$path]);
    }

    public function token(string $path): ?array
    {
        return $this->tokens[$path] ?? null;
    }

    public function resolve(string $path): ?string
    {
        if (array_key_exists($path, $this->resolved)) {
            return $this->resolved[$path];
        }
        if (str_starts_with($path, 'fonts.')) {
            $key = substr($path, 6);
            if ($this->fonts && $this->fonts->has($key)) {
                return $this->fonts->stack($key);
            }
            $this->errors[] = "Unknown font reference {{$path}}";

            return null;
        }
        if (! isset($this->tokens[$path])) {
            $this->errors[] = "Unknown token reference {{$path}}";

            return null;
        }
        if (in_array($path, $this->stack, true)) {
            $this->errors[] = 'Reference cycle: '.implode(' → ', [...$this->stack, $path]);

            return null;
        }
        $this->stack[] = $path;
        $token = $this->tokens[$path];
        $value = $this->substitute($token['value']);
        $derive = $token['extensions']['gs.derive'] ?? null;
        if ($value !== null && is_array($derive)) {
            if (isset($derive['mix']['with'])) {
                $derive['mix']['with'] = $this->substitute($derive['mix']['with']);
            }
            try {
                $value = isset($derive['mix']) && ($derive['mix']['with'] ?? null) === null ? null : ColorMath::derive($value, $derive);
            } catch (Throwable $e) {
                $this->errors[] = "Cannot derive {$path}: ".$e->getMessage();
                $value = null;
            }
        } elseif ($value !== null && ($token['type'] ?? null) === 'color' && ColorMath::isColor($value)) {
            $value = ColorMath::normalize($value);
        }
        array_pop($this->stack);

        return $this->resolved[$path] = $value;
    }

    private function substitute(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (! is_string($value)) {
            $this->errors[] = 'Unsupported token value '.json_encode($value);

            return null;
        }
        if (preg_match('/^\{([^{}]+)\}$/', $value, $m)) {
            return $this->resolve(trim($m[1]));
        }
        $failed = false;
        $result = preg_replace_callback('/\{([^{}]+)\}/', function ($m) use (&$failed) {
            $resolved = $this->resolve(trim($m[1]));
            $failed = $failed || $resolved === null;

            return (string) $resolved;
        }, $value);

        return $failed ? null : $result;
    }

    private function flatten(array $node, string $prefix, ?string $inheritedType): void
    {
        $type = $node['$type'] ?? $inheritedType;
        if (array_key_exists('$value', $node)) {
            $this->tokens[$prefix] = ['type' => $type, 'value' => $node['$value'], 'extensions' => $node['$extensions'] ?? []];

            return;
        }
        foreach ($node as $key => $child) {
            if (str_starts_with((string) $key, '$') || ! is_array($child)) {
                continue;
            }
            $this->flatten($child, $prefix === '' ? (string) $key : $prefix.'.'.$key, $type);
        }
    }
}
