<?php
namespace GovStore\Committee\Console\Commands;
class CommitteeHealthCommand extends \Illuminate\Console\Command
{
    protected $signature = 'committee:health';
    protected $description = 'Maintain and verify the official committee registry';
    public function handle(): int
    {
        $this->info('Refreshed '.app(\GovStore\Committee\Services\CommitteeHealthService::class)->sweep().' committees.'); return self::SUCCESS;
    }
}

