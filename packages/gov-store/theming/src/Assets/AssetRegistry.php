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
        return array_merge(...array_values($this->stylesheets()), ...array_values($this->scripts()));
    }

    private function check(string $path): string
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("Registered theme asset [{$path}] does not exist.");
        }

        return realpath($path) ?: $path;
    }
}
