<?php
namespace GovStore\Committee\Console\Commands;
class CommitteeLedgerVerifyCommand extends \Illuminate\Console\Command
{
    protected $signature = 'committee:verify-ledger {lineage?}';
    protected $description = 'Maintain and verify the official committee registry';
    public function handle(): int
    {
        $ids = $this->argument('lineage') ? [$this->argument('lineage')] : \GovStore\Committee\Models\LedgerEntry::distinct()->pluck('lineage_id')->all();
        $broken = 0;
        foreach ($ids as $id) {
            $breaks = app(\GovStore\Committee\Services\CommitteeLedger::class)->verifyChain($id);
            if ($breaks) { $this->error($id.': broken entries '.implode(',',$breaks)); $broken++; }
        }
        $this->info(count($ids).' lineage chains checked; '.$broken.' broken.'); return $broken ? self::FAILURE : self::SUCCESS;
    }
}

