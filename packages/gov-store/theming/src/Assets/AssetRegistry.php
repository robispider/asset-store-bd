<?php

namespace GovStore\Theming\Assets;

use InvalidArgumentException;

/**
 * Gov-store packages register their CSS / JS here (from ServiceProvider::boot()).
 * gs-theme:build bundles registered CSS into `packages.{hash}.css` inside @layer gs-packages.
 */
final class AssetRegistry
{
    /** @var array<string, string[]> */
    private array $css = [];

    /** @var array<string, string[]> */
    private array $js = [];

    /** @var array<string, string[]> bundle => files, in registration order */
    private array $lazy = [];

    /** Lazy bundles are loaded only on pages that ask for them with data-gs-lazy="<bundle>" (geo-view plan D2). */
    public const LAZY_NAME = '/^[a-z][a-z0-9-]{0,31}$/';

    public function css(string $package, string $path): self
    {
        $this->css[$package][] = $this->check($path);

        return $this;
    }

    public function js(string $package, string $path): self
    {
        $this->js[$package][] = $this->check($path);

        return $this;
    }

    /** Script files of an on-demand bundle, concatenated in the order given. */
    public function lazy(string $bundle, string ...$paths): self
    {
        if (! preg_match(self::LAZY_NAME, $bundle)) {
            throw new InvalidArgumentException("Invalid lazy bundle name [{$bundle}].");
        }
        foreach ($paths as $path) {
            $this->lazy[$bundle][] = $this->check($path);
        }
        $this->lazy[$bundle] = array_values(array_unique($this->lazy[$bundle]));

        return $this;
    }

    /** @return array<string, string[]> */
    public function lazyBundles(): array
    {
        ksort($this->lazy);

        return $this->lazy;
    }

    /** @return array<string, string[]> */
    public function stylesheets(): array
    {
        ksort($this->css);

        return array_map(fn ($paths) => array_values(array_unique($paths)), $this->css);
    }

    /** @return array<string, string[]> */
    public function scripts(): array
    {
        ksort($this->js);

        return array_map(fn ($paths) => array_values(array_unique($paths)), $this->js);
    }

    public function files(): array
    {
        return array_merge(...array_values($this->stylesheets()), ...array_values($this->scripts()), ...array_values($this->lazyBundles()));
    }

    private function check(string $path): string
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("Registered theme asset [{$path}] does not exist.");
        }

        return realpath($path) ?: $path;
    }
}
