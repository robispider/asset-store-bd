<?php

namespace GovStore\TenantScope\Services;

use Closure;
use Illuminate\Database\Eloquent\Model;

/** A request-local permit held only while an authorized provisioning service saves. */
class ReferenceCreation
{
    private array $permits = [];

    public function run(string $class, Closure $callback): mixed
    {
        $this->permits[$class] = ($this->permits[$class] ?? 0) + 1;
        try {
            return $callback();
        } finally {
            $this->permits[$class]--;
        }
    }

    public function permits(Model $model): bool
    {
        return ($this->permits[get_class($model)] ?? 0) > 0;
    }
}
