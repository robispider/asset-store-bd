<?php

namespace GovStore\AssetTagging\Console\Commands;

use GovStore\AssetTagging\Services\GovtTagSequenceAllocator;
use Illuminate\Console\Command;

class CountersCommand extends Command
{
    protected $signature = 'asset-tags:counters {--check : Report counters that are behind a retained tag}';

    protected $description = 'Check Govt. Tag counters against retained tags (read-only).';

    public function handle(GovtTagSequenceAllocator $allocator): int
    {
        $drift = $allocator->drift();
        if (! $drift) {
            $this->info('Every Govt. Tag counter is at or above its highest retained tag.');

            return self::SUCCESS;
        }
        $this->table(['prefix', 'stored', 'highest retained'], array_map(fn ($row) => [$row['prefix'], $row['stored'], $row['retained']], $drift));

        return self::FAILURE;
    }
}
