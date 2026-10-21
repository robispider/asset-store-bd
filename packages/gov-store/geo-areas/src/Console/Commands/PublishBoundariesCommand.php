<?php

namespace GovStore\GeoAreas\Console\Commands;

use GovStore\GeoAreas\Services\BoundarySet;
use Illuminate\Console\Command;

/**
 * Copies a built boundary set to public/vendor/geo-areas/boundaries/<version> (plan D7) and checks every
 * file against the SHA-256 in its manifest. Published files are immutable: the version is in the URL.
 */
class PublishBoundariesCommand extends Command
{
    protected $signature = 'geo-areas:publish-boundaries
        {--set=* : Boundary set version(s) to publish (default: the configured version)}
        {--target= : Target folder (default: config geo-areas.boundary_publish_path)}
        {--verify : Only verify an existing publication, copy nothing}';

    protected $description = 'Publish the static boundary files the geo view draws, verifying SHA-256 against the manifest';

    public function handle(): int
    {
        $versions = $this->option('set') ?: [config('geo-areas.boundary_version')];
        $target = rtrim($this->option('target') ?: (string) config('geo-areas.boundary_publish_path'), '/\\');
        $ok = true;
        foreach ($versions as $version) {
            $set = new BoundarySet($version);
            if (! $set->exists()) {
                $this->error("Boundary set {$version} not found in ".config('geo-areas.boundary_source'));
                $ok = false;

                continue;
            }
            $ok = $this->publish($set, $target.'/'.$version) && $ok;
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function publish(BoundarySet $set, string $folder): bool
    {
        $files = $set->manifest()['files'] ?? [];
        $copied = 0;
        $bad = [];
        foreach (array_merge(array_keys($files), ['manifest.json']) as $relative) {
            $source = $set->sourcePath().'/'.$relative;
            $destination = $folder.'/'.$relative;
            if (! $this->option('verify')) {
                if (! is_dir(dirname($destination))) {
                    mkdir(dirname($destination), 0775, true);
                }
                if (! is_file($destination) || hash_file('sha256', $destination) !== hash_file('sha256', $source)) {
                    copy($source, $destination);
                    $copied++;
                }
            }
            $expected = $files[$relative]['sha256'] ?? null;
            if ($expected !== null && (! is_file($destination) || hash_file('sha256', $destination) !== $expected)) {
                $bad[] = $relative;
            }
        }
        if ($bad) {
            $this->error(count($bad)." file(s) of {$set->version()} do not match the manifest, e.g. ".implode(', ', array_slice($bad, 0, 3)));

            return false;
        }
        $this->info(sprintf('%s: %d files verified%s → %s', $set->version(), count($files),
            $this->option('verify') ? '' : ", {$copied} copied", $folder));

        return true;
    }
}
