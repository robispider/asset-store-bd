<?php
namespace GovStore\Committee\Console\Commands;
class CommitteeExpireCommand extends \Illuminate\Console\Command
{
    protected $signature = 'committee:expire';
    protected $description = 'Maintain and verify the official committee registry';
    public function handle(): int
    {
        $this->info('Expired '.app(\GovStore\Committee\Services\CommitteeService::class)->expireDue().' committees.'); return self::SUCCESS;
    }
}

