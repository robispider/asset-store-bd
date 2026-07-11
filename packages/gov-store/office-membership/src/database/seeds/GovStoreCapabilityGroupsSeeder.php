<?php

namespace GovStore\OfficeMembership\Database\Seeds;

use Illuminate\Database\Seeder;
use App\Models\Group;

class GovStoreCapabilityGroupsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $groups = [
            [
                'name' => 'Platform Admin',
                'permissions' => json_encode(['superuser' => 1]),
            ],
            [
                'name' => 'Company Admin',
                'permissions' => json_encode(['admin' => 1]),
            ],
            [
                'name' => 'ICT Operations',
                'permissions' => json_encode(['self.two_factor' => 1]),
            ],
            [
                'name' => 'Office Operations',
                'permissions' => json_encode(['self.two_factor' => 1]),
            ],
            [
                'name' => 'Inventory Operator',
                'permissions' => json_encode([
                    'assets.view' => 1,
                    'assets.checkout' => 1,
                    'assets.checkin' => 1,
                    'consumables.view' => 1,
                    'consumables.checkout' => 1,
                    'accessories.view' => 1,
                    'accessories.checkout' => 1,
                    'accessories.checkin' => 1,
                ]),
            ],
            [
                'name' => 'Workflow Operator',
                'permissions' => json_encode(['self.two_factor' => 1]),
            ],
            [
                'name' => 'Employee',
                'permissions' => json_encode(['self.two_factor' => 1]),
            ],
        ];

        foreach ($groups as $group) {
            // Using Snipe-IT's native Group model automatically resolves database prefixes
            Group::updateOrCreate(
                ['name' => $group['name']],
                [
                    'permissions' => $group['permissions'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}