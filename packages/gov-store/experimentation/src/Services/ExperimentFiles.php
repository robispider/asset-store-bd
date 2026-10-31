<?php

namespace GovStore\Experimentation\Services;

use GovStore\Experimentation\Models\ExperimentRun;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** File ownership survives a rolled-back database unit, without trusting uploaded paths. */
class ExperimentFiles
{
    public function owned(ExperimentRun $run): array
    {
        $path = 'gov-experiments/'.$run->id.'/artifacts.json';
        if (! Storage::disk('local')->exists($path)) {
            return [];
        }
        $data = json_decode(Storage::disk('local')->get($path), true, 512, JSON_THROW_ON_ERROR);
        $payload = json_encode($data['files'], JSON_THROW_ON_ERROR);
        if (! hash_equals(hash_hmac('sha256', $run->id.$payload, config('app.key')), $data['signature'] ?? '')) {
            throw new RuntimeException('Fictional file ownership evidence failed verification.');
        }
        foreach ($data['files'] as $file) {
            $this->validate($file['disk'], $file['path']);
        }

        return [...$data['files'], ['disk' => 'local', 'path' => $path]];
    }

    public function track(ExperimentRun $run, string $disk, string $path): void
    {
        $this->validate($disk, $path);
        $files = array_values(array_filter($this->owned($run), fn ($file) => ! str_ends_with($file['path'], '/artifacts.json')));
        $files[] = ['disk' => $disk, 'path' => $path];
        $files = array_values(array_unique($files, SORT_REGULAR));
        $payload = json_encode($files, JSON_THROW_ON_ERROR);
        if (! Storage::disk('local')->put('gov-experiments/'.$run->id.'/artifacts.json',
            json_encode(['files' => $files, 'signature' => hash_hmac('sha256', $run->id.$payload, config('app.key'))], JSON_THROW_ON_ERROR))) {
            Storage::disk($disk)->delete($path);
            throw new RuntimeException('Could not persist fictional file ownership.');
        }
    }

    private function validate(string $disk, string $path): void
    {
        $valid = $disk === 'committee_private' && preg_match('~^orders/[0-9a-f-]{36}\\.(pdf|png|jpg)$~D', $path);
        $valid = $valid || ($disk === 'local' && preg_match('~^tracking-(orders|documents)/[0-9]+/[A-Za-z0-9._-]+$~D', $path));
        if (! $valid || str_contains($path, '..')) {
            throw new RuntimeException('Unexpected fictional evidence path.');
        }
    }
}
