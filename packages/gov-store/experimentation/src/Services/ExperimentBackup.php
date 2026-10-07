<?php

namespace GovStore\Experimentation\Services;

use GovStore\Experimentation\Models\ExperimentRun;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ExperimentBackup
{
    public function create(ExperimentRun $run): string
    {
        $path = 'gov-experiments/backups/'.$run->id.'-'.now()->format('YmdHis').'.jsonl.enc';
        $stream = fopen('php://temp/maxmemory:1048576', 'w+');
        $hash = hash_init('sha256');
        $write = function (array $record) use ($stream, $hash) {
            $line = Crypt::encryptString(json_encode($record, JSON_THROW_ON_ERROR))."\n";
            if (fwrite($stream, $line) !== strlen($line)) {
                throw new RuntimeException('Backup stream write failed.');
            }
            hash_update($hash, $line);
        };
        try {
            $write(['kind' => 'header', 'format' => 'govstore-encrypted-jsonl-v1', 'database' => DB::connection()->getDatabaseName(), 'run' => $run->id, 'created_at' => now()->toIso8601String()]);
            foreach (Schema::getTables(DB::connection()->getDatabaseName()) as $table) {
                $name = $table['name'];
                $ddl = (array) DB::selectOne('SHOW CREATE TABLE `'.str_replace('`', '``', $name).'`');
                $write(['kind' => 'schema', 'table' => $name, 'ddl' => array_values($ddl)[1]]);
                $columns = Schema::getColumnListing($name);
                $batch = [];
                foreach (DB::table($name)->orderBy($columns[0])->lazy(250) as $row) {
                    $batch[] = $row;
                    if (count($batch) === 250) {
                        $write(['kind' => 'rows', 'table' => $name, 'rows' => $batch]);
                        $batch = [];
                    }
                }
                if ($batch) {
                    $write(['kind' => 'rows', 'table' => $name, 'rows' => $batch]);
                }
            }
            $write(['kind' => 'complete']);
            $expectedHash = hash_final($hash);
            rewind($stream);
            if (! Storage::disk('local')->put($path, $stream)) {
                throw new RuntimeException('Backup storage write failed.');
            }
            $stored = Storage::disk('local')->readStream($path);
            $actualHash = hash_init('sha256');
            $last = null;
            while (($line = fgets($stored)) !== false) {
                hash_update($actualHash, $line);
                $last = json_decode(Crypt::decryptString(trim($line)), true, 512, JSON_THROW_ON_ERROR);
            }
            fclose($stored);
            if (! hash_equals($expectedHash, hash_final($actualHash)) || ($last['kind'] ?? null) !== 'complete') {
                throw new RuntimeException('Backup verification failed.');
            }

            return $path;
        } finally {
            fclose($stream);
        }
    }
}
