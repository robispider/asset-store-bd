<?php

namespace GovStore\Experimentation\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class InstallationLock
{
    public function run(callable $callback): mixed
    {
        // A connection-owned database lock coordinates web, CLI and queue workers.
        $name = 'gov-experiment-'.substr(hash('sha256', DB::connection()->getDatabaseName()), 0, 40);
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Experiment operations currently require MySQL or MariaDB.');
        }
        if ((int) DB::selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$name])->acquired !== 1) {
            throw new RuntimeException(__('experiments::ui.busy'));
        }
        try {
            return $callback();
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$name]);
        }
    }
}
