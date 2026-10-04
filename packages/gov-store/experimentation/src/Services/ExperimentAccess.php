<?php

namespace GovStore\Experimentation\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use RuntimeException;

class ExperimentAccess
{
    public function authorize(?User $actor): void
    {
        if (! $actor || ! $actor->isSuperUser() || ! $actor->activated || $actor->deleted_at) {
            throw new AuthorizationException(__('experiments::ui.superuser_only'));
        }
        if (! config('govstore-experiments.enabled') || app()->environment('production')) {
            throw new AuthorizationException(__('experiments::ui.disabled'));
        }
    }

    public function databaseReset(): void
    {
        if (! config('govstore-experiments.database_reset_enabled') || app()->environment('production')) {
            throw new RuntimeException(__('experiments::ui.reset_disabled'));
        }
    }
}
