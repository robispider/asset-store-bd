<?php

namespace GovStore\Theming\Themes;

use GovStore\Theming\Build\FontRegistry;
use GovStore\Theming\Build\TokenResolver;

/**
 * One shipped theme after inheritance (`extends`) has been applied.
 */
final class Theme
{
    private ?array $resolvedLight = null;

    private ?array $resolvedDark = null;

    private array $resolverErrors = [];

    public function __construct(
        public readonly string $key,
        public readonly string $path,
        public readonly array $manifest,
        public readonly array $chain,
        public readonly array $lightTokens,
        public readonly array $darkTokens,
        public readonly array $variants,
        public readonly array $fonts,
        public readonly string $overridesCss,
        public readonly bool $darkIsScaffold,
    ) {}

    public function status(): string
    {
        return $this->manifest['status'] ?? 'draft';
    }

    public function version(): string
    {
        return (string) ($this->manifest['version'] ?? '0.0.0');
    }

    public function isPublished(): bool
    {
        return $this->status() === 'published';
    }

    public function parent(): ?string
    {
        return $this->manifest['extends'] ?? null;
    }

    public function label(?string $locale = null): string
    {
        return $this->localized('label', $locale) ?? $this->key;
    }

    public function description(?string $locale = null): string
    {
        return $this->localized('description', $locale) ?? '';
    }

    public function swatches(): array
    {
        return $this->manifest['swatches'] ?? [];
    }

    public function authors(): array
    {
        return $this->manifest['authors'] ?? [];
    }

    public function previewPath(string $mode): ?string
    {
        $file = $this->path.DIRECTORY_SEPARATOR."preview.{$mode}.png";

        return is_file($file) ? $file : null;
    }

    public function isClassic(): bool
    {
        return $this->key === 'default';
    }

    /** @return array<string, string> resolved literal tokens for one mode */
    public function tokens(string $mode, ?FontRegistry $fonts = null): array
    {
        $property = $mode === 'dark' ? 'resolvedDark' : 'resolvedLight';
        if ($this->$property === null) {
            $tree = $mode === 'dark' ? $this->darkTokens : $this->lightTokens;
            if ($tree !== []) {
                $tree = $this->withFontSlots($tree, $fonts);
            }
            $resolver = new TokenResolver($tree, $fonts);
            $this->$property = $resolver->resolveAll();
            $this->resolverErrors[$mode] = $resolver->errors();
        }

        return $this->$property;
    }

    public function tokenErrors(string $mode, ?FontRegistry $fonts = null): array
    {
        $this->tokens($mode, $fonts);

        return $this->resolverErrors[$mode] ?? [];
    }

    /** data-gs-* attributes for this theme's variants (optionally overridden, e.g. by the Lab). */
    public function variantAttributes(array $overrides = []): array
    {
        $attributes = [];
        foreach (array_merge($this->variants, $overrides) as $name => $value) {
            $attributes['data-gs-'.str_replace('_', '-', $name)] = (string) $value;
        }

        return $attributes;
    }

    /**
     * theme.json `fonts` slots become font.* tokens. Latin stacks get the theme's Bengali
     * family appended so mixed English/Bengali text renders with the intended face.
     */
    private function withFontSlots(array $tree, ?FontRegistry $fonts): array
    {
        if (! $fonts) {
            return $tree;
        }
        $bengali = isset($this->fonts['bengali']) ? $fonts->stack($this->fonts['bengali']) : null;
        $stack = function (?string $key) use ($fonts, $bengali): ?string {
            $value = $key ? $fonts->stack($key) : null;
            if ($value === null) {
                return null;
            }
            if ($bengali && ! $fonts->isBengali($key)) {
                $family = trim(explode(',', $bengali)[0]);
                $parts = array_map('trim', explode(',', $value));
                $generic = array_pop($parts);
                $value = implode(', ', [...$parts, $family, $generic]);
            }

            return $value;
        };
        $slots = ['sans' => $this->fonts['sans'] ?? null, 'display' => $this->fonts['display'] ?? ($this->fonts['sans'] ?? null),
            'mono' => $this->fonts['mono'] ?? null, 'bengali' => $this->fonts['bengali'] ?? null];
        foreach ($slots as $slot => $key) {
            $value = $slot === 'bengali' ? ($key ? $fonts->stack($key) : null) : $stack($key);
            if ($value !== null) {
                $tree['font'][$slot] = ['$type' => 'fontFamily', '$value' => $value];
            }
        }

        return $tree;
    }

    private function localized(string $field, ?string $locale): ?string
    {
        $values = $this->manifest[$field] ?? null;
        if (! is_array($values)) {
            return is_string($values) ? $values : null;
        }
        $locale = $locale ?? app()->getLocale();

        return $values[$locale] ?? $values[str_replace('_', '-', $locale)] ?? $values['en-US'] ?? (reset($values) ?: null);
    }
}
