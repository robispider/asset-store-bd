<?php

namespace GovStore\OfficeMembership\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use GovStore\OfficeMembership\Models\OfficeMembership;

class SyncInitialMemberships extends Command
{
    protected $signature = 'gov-store:sync-memberships';
    protected $description = 'Synchronizes core Snipe-IT user locations into the new Office Membership engine.';

    public function handle()
    {
        $this->info("Starting Office Membership Synchronization...");

        $users = User::whereNotNull('location_id')->get();
        $count = 0;

        foreach ($users as $user) {
            OfficeMembership::firstOrCreate(
                ['user_id' => $user->id, 'location_id' => $user->location_id],
                ['is_default' => true, 'status' => 'active']
            );
            $count++;
        }

        $this->info("Successfully synchronized {$count} users into active office memberships.");
    }
}