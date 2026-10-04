<?php

namespace GovStore\Experimentation\Services;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Company;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\License;
use App\Models\LicenseSeat;
use App\Models\Location;
use App\Models\Maintenance;
use App\Models\MaintenanceType;
use App\Models\Manufacturer;
use App\Models\Statuslabel;
use App\Models\Supplier;
use App\Models\User;
use GovStore\Classification\Models\CatalogCollection;
use GovStore\Classification\Models\CatalogCollectionNode;
use GovStore\Classification\Models\CatalogNode;
use GovStore\Classification\Models\CatalogSnipeMapping;
use GovStore\Classification\Models\CategoryGovernance;
use GovStore\Classification\Services\CategoryAdoptionService;
use GovStore\CustomRequests\Models\ApprovalPolicy;
use GovStore\CustomRequests\Services\ApprovalService;
use GovStore\CustomRequests\Services\BasketService;
use GovStore\CustomRequests\Services\FulfillmentService;
use GovStore\Experimentation\Models\ExperimentRun;
use GovStore\GeoAreas\Models\GeoArea;
use GovStore\Metadata\Services\ConvergenceEngine;
use GovStore\OfficeMembership\Services\OfficeMembershipService;
use GovStore\OfficeMembership\Services\RoleHandshakeService;
use GovStore\Organization\Models\CompanyAdmin;
use GovStore\Organization\Models\IctJurisdiction;
use GovStore\Organization\Models\MinistryDirectory;
use GovStore\Organization\Services\OfficeConfigurationService;
use GovStore\Organization\Services\OfficeProvisioningService;
use GovStore\StoreOperations\Enums\DocumentState;
use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Models\InventoryMovement;
use GovStore\StoreOperations\Models\Profile;
use GovStore\StoreOperations\Models\ProfileAssignment;
use GovStore\StoreOperations\Models\ProfileCapability;
use GovStore\StoreOperations\Services\GoodsReceiptService;
use GovStore\StoreOperations\Services\PostingPipelineManager;
use GovStore\Tracking\Models\FundingType;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\OperationUnit;
use GovStore\Tracking\Models\TrackingAllocation;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Models\TrackingScope;
use GovStore\Tracking\Models\TrackingTarget;
use GovStore\Tracking\Services\ScopeValidatorService;
use GovStore\UserOnboarding\Models\UserOnboarding;
use GovStore\UserOnboarding\Services\UserOnboardingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BangladeshScenario
{
    private const ASSET_NAMES = ['Desktop Computers / ডেস্কটপ কম্পিউটার', 'Laptop / ল্যাপটপ', 'Printers / প্রিন্টার',
        'Office Chairs / অফিস চেয়ার', 'Office Desks / অফিস ডেস্ক', 'Steel Cabinets / স্টিল আলমারি', 'Scanners / স্ক্যানার',
        'UPS / ইউপিএস', 'Network Routers / নেটওয়ার্ক রাউটার', 'Projectors / প্রজেক্টর', 'Air Conditioners / শীতাতপ নিয়ন্ত্রক',
        'Ceiling Fans / সিলিং ফ্যান', 'Whiteboards / হোয়াইটবোর্ড', 'CCTV Cameras / সিসিটিভি ক্যামেরা', 'Photocopiers / ফটোকপিয়ার',
        'Network Switches / নেটওয়ার্ক সুইচ', 'Water Filters / পানির ফিল্টার', 'Meeting Tables / সভার টেবিল', 'Bookshelves / বইয়ের তাক'];

    private const BULK_NAMES = [
        'consumables' => ['A4 Paper / এ৪ কাগজ', 'Printer Toner / প্রিন্টার টোনার', 'Printer Ink / প্রিন্টার কালি', 'Ballpoint Pens / বলপেন', 'Pencils / পেন্সিল', 'Staples / স্ট্যাপল', 'Envelopes / খাম', 'File Folders / ফাইল ফোল্ডার', 'Register Books / রেজিস্টার খাতা', 'Whiteboard Markers / মার্কার', 'Batteries / ব্যাটারি', 'Cleaning Supplies / পরিষ্কার সামগ্রী'],
        'accessories' => ['Keyboard / কিবোর্ড', 'Mouse / মাউস', 'Webcam / ওয়েবক্যাম', 'Headset / হেডসেট', 'HDMI Cable / এইচডিএমআই কেবল', 'USB Hub / ইউএসবি হাব', 'Network Cable / নেটওয়ার্ক কেবল', 'Surge Protector / সার্জ প্রটেক্টর'],
        'components' => ['Memory Module / মেমোরি', 'SSD / এসএসডি', 'Hard Drive / হার্ড ড্রাইভ', 'Power Supply / পাওয়ার সাপ্লাই'],
    ];

    private ExperimentRun $run;

    private User $actor;

    private array $size;

    private string $prefix;

    private string $password;

    private array $companies = [];

    private array $offices = [];

    private array $people = [];

    private array $categories = [];

    private array $models = [];

    private array $bulk = [];

    private array $codes = [];

    public function __construct(private RecordRegistry $records, private ActorContext $contexts, private BangladeshPeople $names) {}

    public function populate(ExperimentRun $run, User $actor, string $password): void
    {
        $this->run = $run;
        $this->actor = $actor;
        $this->password = $password;
        $this->size = $run->report['profile_counts'] ?? config('govstore-experiments.profiles.'.$run->profile);
        $this->prefix = $run->report['record_prefix'] ?? 'EXP-'.substr($run->id, 0, 8);
        $this->records->current = $run;
        try {
            $this->contexts->run($actor, null, function () {
                foreach (['organization', 'people', 'classification', 'products', 'tracking', 'stock', 'requests', 'examples'] as $phase) {
                    $this->run->update(['phase' => $phase]);
                    $this->{$phase}();
                }
            });
        } finally {
            $this->records->current = null;
        }
    }

    private function unit(string $key, callable $callback, bool $transaction = true): mixed
    {
        $report = $this->run->report ?? [];
        if (isset($report['units'][$key])) {
            return $report['units'][$key];
        }
        $execute = function () use ($callback, $key) {
            $result = $callback();
            $report = $this->run->report ?? [];
            $report['units'][$key] = $result;
            $this->run->update(['report' => $report]);

            return $result;
        };

        // Model metadata may create asset columns. Keep those DDL units outside a transaction.
        return $transaction ? DB::transaction($execute) : $execute();
    }

    private function create(string $class, array $attributes, string $key): Model
    {
        $record = DB::table('gov_experiment_records')->where('run_id', $this->run->id)->where('logical_key', $key)->first();
        if ($record) {
            $identity = json_decode($record->record_key, true);
            $existing = $class::withoutGlobalScopes()->where($identity)->first();
            if ($existing) {
                if ($existing instanceof AssetModel) {
                    app(ConvergenceEngine::class)->converge($existing);
                }

                return $existing;
            }
        }
        $model = new $class;
        $model->forceFill($attributes);
        try {
            if (! $model->save()) {
                throw new RuntimeException($class.': '.implode('; ', $model->getErrors()->all()));
            }
        } finally {
            // Metadata listeners can fail after INSERT. Preserve its identity for recovery/wipe.
            if ($model->exists && $model->getKey()) {
                $this->records->record($this->run, $model->getTable(), $model->getAttributes(), $key);
            }
        }

        return $model;
    }

    private function organization(): void
    {
        $this->unit('reference-setup', function () {
            app(ReferenceSetup::class)->populate();

            return true;
        });
        if (! GeoArea::whereNotNull('hid')->exists()) {
            throw new RuntimeException('Bangladesh geographic master data is missing. Run the existing geo-areas migration/import first.');
        }
        $sectors = [
            ['তথ্য ও যোগাযোগ প্রযুক্তি বিভাগ / ICT Division', 'তথ্য ও যোগাযোগ প্রযুক্তি অধিদপ্তর / Department of ICT'],
            ['শিক্ষা মন্ত্রণালয় / Ministry of Education', 'মাধ্যমিক শিক্ষা অধিদপ্তর / Secondary Education Department'],
            ['স্বরাষ্ট্র মন্ত্রণালয় / Ministry of Home Affairs', 'বাংলাদেশ পুলিশ / Bangladesh Police'],
        ];
        foreach ($sectors as $s => $pair) {
            foreach ($pair as $level => $name) {
                $index = $s * 2 + $level;
                $id = $this->unit('company-'.$index, function () use ($name, $level, $index) {
                    $company = $this->create(Company::class, ['name' => $name.' (পরীক্ষামূলক '.$this->prefix.')', 'parent_id' => $level ? $this->companies[$index - 1]->id : null,
                        'created_by' => $this->actor->id, 'notes' => 'Fictional Bangladesh government experiment', 'tag_color' => '#008060'], 'company-'.$index);
                    $directory = $this->create(MinistryDirectory::class, ['id' => (DB::table('gov_ministries_directory')->max('id') ?? 0) + 1,
                        'bn_name' => explode(' / ', $name)[0].' (পরীক্ষামূলক)', 'en_name' => explode(' / ', $name)[1].' (Experiment)',
                        'org_type' => $level ? 'Department' : 'Ministry/Division', 'parent_id' => null, 'hid' => '/'.$company->id.'/', 'company_id' => $company->id], 'directory-'.$index);
                    if ($level) {
                        $parent = MinistryDirectory::where('company_id', $this->companies[$index - 1]->id)->first();
                        $directory->update(['parent_id' => $parent->id, 'hid' => $parent->hid.$directory->id.'/']);
                    }

                    return $company->id;
                });
                $this->companies[$index] = Company::withoutGlobalScopes()->findOrFail($id);
            }
        }
        $divisions = GeoArea::where('GeoLevel', 1)->whereNotNull('hid')->where('hid', '!=', '')->orderBy('GeoAreaId')->get();
        foreach (range(0, $this->size['offices'] - 1) as $i) {
            $id = $this->unit('office-'.$i, function () use ($i, $divisions) {
                $division = $divisions[$i % $divisions->count()];
                $districts = GeoArea::where('geo_type', 'district')->where('hid', 'like', rtrim($division->hid, '/').'/%')->orderBy('GeoAreaId')->get();
                $district = $districts[intdiv($i, $divisions->count()) % $districts->count()];
                $upazilas = GeoArea::whereIn('geo_type', ['upazilla', 'upazila'])->where('hid', 'like', rtrim($district->hid, '/').'/%')->orderBy('GeoAreaId')->get();
                $area = $i < 8 ? $division : ($i < 24 || $upazilas->isEmpty() ? $district : $upazilas[$i % $upazilas->count()]);
                $company = $this->companies[($i % 3) * 2 + 1];
                $name = ($i < 8 ? 'আঞ্চলিক কার্যালয়' : ($i < 24 ? 'জেলা কার্যালয়' : 'উপজেলা কার্যালয়')).' / '.$area->en_name.' '.$this->prefix.'-'.($i + 1);
                $office = app(OfficeProvisioningService::class)->provisionOffice(['name' => $name, 'company_id' => $company->id,
                    'geo_area_id' => $area->GeoAreaId, 'city' => $area->en_name, 'state' => $district->en_name], $this->actor->id);
                $office->update(['created_by' => $this->actor->id, 'notes' => 'পরীক্ষামূলক সরকারি কার্যালয় / Experiment office']);
                $this->records->record($this->run, 'locations', $office->getAttributes(), 'office-'.$i);

                return $office->id;
            });
            $this->offices[$i] = Location::withoutGlobalScopes()->findOrFail($id);
        }
    }

    private function people(): void
    {
        // Dedicated oversight accounts do not inherit an unrelated office's home company.
        $staffCount = $this->size['users'] - $this->size['onboarding'] - 14;
        foreach (range(0, $this->size['users'] - 1) as $i) {
            $id = $this->unit('person-'.$i, function () use ($i, $staffCount) {
                $office = $i < $staffCount ? $this->offices[$i % count($this->offices)] : null;
                $person = $this->create(User::class, array_merge($this->names->name($i, $this->run->seed), [
                    'username' => strtolower($this->prefix).'.'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                    'email' => strtolower($this->prefix).'.'.($i + 1).'@example.invalid', 'password' => Hash::make($this->password),
                    'permissions' => '{}', 'activated' => true, 'created_by' => $this->actor->id,
                    'locale' => $i % 4 ? 'bn-BD' : 'en-US', 'country' => 'BD', 'location_id' => $office?->id,
                    'company_id' => $office?->company_id, 'employee_num' => $this->prefix.'-'.($i + 1),
                    'jobtitle' => 'অফিস সহকারী / Office Assistant', 'notes' => 'Fictional Bangladesh-style name; experiment data',
                ]), 'person-'.$i);
                app(ExperimentAccounts::class)->assignUsername($person);
                if ($office) {
                    app(OfficeMembershipService::class)->grantMembership($person->id, $office->id, true);
                    $queue = UserOnboarding::where('user_id', $person->id)->where('status', 'WAITING')->first();
                    if ($queue) {
                        app(UserOnboardingService::class)->assignToOffice($queue->id, $office->id);
                    }
                    $person->companies()->syncWithoutDetaching([$office->company_id]);
                }

                return $person->id;
            });
            $this->people[$i] = User::withoutGlobalScopes()->findOrFail($id);
            // Reconcile accounts from interrupted fixtures without touching another user's credentials.
            if (hash_equals($this->password, (string) $this->people[$i]->password)) {
                $this->people[$i]->forceFill(['password' => Hash::make($this->password)])->saveQuietly();
            }
        }
        foreach ($this->offices as $i => $office) {
            $this->unit('roles-'.$i, function () use ($i, $office) {
                $members = $this->officePeople($i);
                app(OfficeProvisioningService::class)->assignOfficeAdmin($office->id, $members[0]->id, $this->actor->id);
                $roles = ['storekeeper_id' => $members[1]->id, 'primary_approver_id' => $members[2]->id, 'final_approver_id' => $members[3]->id];
                if (! $this->operational($i)) {
                    unset($roles['storekeeper_id']);
                }
                app(OfficeConfigurationService::class)->saveRoles($office->id, $roles, $this->actor->id);
                foreach (['Office Administrator', 'Storekeeper', 'Primary Approver', 'Final Approver'] as $k => $title) {
                    $members[$k]->update(['jobtitle' => $title]);
                }

                return true;
            });
        }
        $this->unit('overseers', function () use ($staffCount) {
            foreach ($this->companies as $i => $company) {
                $this->create(CompanyAdmin::class, ['company_id' => $company->id, 'user_id' => $this->people[$staffCount + $i]->id], 'company-admin-'.$i);
            }
            $divisions = GeoArea::where('GeoLevel', 1)->whereNotNull('hid')->orderBy('GeoAreaId')->get();
            foreach ($divisions as $i => $division) {
                $person = $this->people[$staffCount + 6 + $i];
                $this->create(IctJurisdiction::class, ['user_id' => $person->id, 'geo_area_id' => $division->GeoAreaId], 'jurisdiction-'.$i);
            }
            // Oversight accounts are intentionally not pending ordinary office onboarding.
            UserOnboarding::whereIn('user_id', array_map(fn ($p) => $p->id, array_slice($this->people, $staffCount, 14)))->delete();
            $member = $this->officePeople(0)[4];
            app(OfficeMembershipService::class)->grantMembership($member->id, $this->offices[1]->id, false);

            return true;
        });
    }

    private function classification(): void
    {
        $this->unit('catalog-masters', function () {
            $handle = fopen(base_path('packages/gov-store/classification/src/database/data/compiled_nodes.csv'), 'r');
            $header = fgetcsv($handle, 0, ',', '"', '');
            $selected = [];
            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if (count($values) !== count($header)) {
                    continue;
                }
                $row = array_combine($header, $values);
                if ((int) $row['level'] !== 4 || ! preg_match('/computer|printer|paper|keyboard|memory|software|office|furniture|desk|chair|scanner|network|storage|monitor|stationery|cabinet/i', $row['title_en'])) {
                    continue;
                }
                $selected[$row['code']] = $row;
                if (count($selected) >= $this->size['catalog_nodes']) {
                    break;
                }
            }
            fclose($handle);
            // Stream the bundled catalog; retain only selected commodities and their ancestors.
            for ($level = 3; $level >= 1; $level--) {
                $needed = array_fill_keys(array_column(array_values($selected), 'parent_code'), true);
                $handle = fopen(base_path('packages/gov-store/classification/src/database/data/compiled_nodes.csv'), 'r');
                fgetcsv($handle, 0, ',', '"', '');
                while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                    if (count($values) === count($header) && isset($needed[$values[0]])) {
                        $selected[$values[0]] = array_combine($header, $values);
                    }
                }
                fclose($handle);
            }
            // Master nodes/definitions are shared reference setup, never wipe-owned.
            foreach ($selected as $row) {
                DB::table('gov_catalog_nodes')->insertOrIgnore([
                    'code' => $row['code'], 'parent_code' => $row['parent_code'] ?: null, 'level' => $row['level'], 'title_en' => $row['title_en'],
                    'hid' => $row['hid'], 'is_selectable' => $row['is_selectable'], 'scheme' => 'UNSPSC', 'version' => 'bundled', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $definitions = fopen(base_path('packages/gov-store/classification/src/database/data/compiled_definitions.csv'), 'r');
            fgetcsv($definitions, 0, ',', '"', '');
            while (($row = fgetcsv($definitions, 0, ',', '"', '')) !== false) {
                if (isset($selected[$row[0]])) {
                    DB::table('gov_catalog_definitions')->insertOrIgnore(['code' => $row[0], 'definition_en' => $row[1] ?? '', 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            fclose($definitions);
            foreach (range(0, 5) as $i) {
                $collection = $this->create(CatalogCollection::class, ['name' => $this->prefix.' সরকারি সরঞ্জাম / Government Supplies '.($i + 1), 'description' => 'পরীক্ষামূলক সংগ্রহ', 'is_active' => true, 'created_by' => $this->actor->id], 'collection-'.$i);
                foreach (array_slice(array_values($selected), $i * 4, 4) as $j => $node) {
                    $this->create(CatalogCollectionNode::class, ['collection_id' => $collection->id, 'code' => $node['code']], 'collection-node-'.$i.'-'.$j);
                }
            }

            return true;
        });
        $types = ['asset', 'consumable', 'accessory', 'component', 'license'];
        $names = ['Assets / সম্পদ', 'Office Supplies / দাপ্তরিক ব্যবহার্য সামগ্রী', 'ICT Accessories / আইসিটি আনুষঙ্গিক সরঞ্জাম', 'Computer Components / কম্পিউটার উপাদান', 'Office Software / দাপ্তরিক সফটওয়্যার'];
        foreach (range(0, $this->size['categories'] - 1) as $i) {
            $id = $this->unit('category-'.$i, function () use ($i, $types, $names) {
                $categoryName = $i % 5 === 0 ? self::ASSET_NAMES[intdiv($i, 5) % count(self::ASSET_NAMES)] : $names[$i % 5];
                $category = $this->create(Category::class, ['name' => $this->prefix.' '.$categoryName.' '.($i + 1),
                    'category_type' => $types[$i % 5], 'created_by' => $this->actor->id, 'require_acceptance' => false, 'use_default_eula' => false], 'category-'.$i);
                $global = $i < 10 || $i % 5 === 0 || $i % 3 === 0;
                $owner = $global ? null : $this->companies[($i % 3) * 2 + 1]->id;
                $this->create(CategoryGovernance::class, ['category_id' => $category->id, 'governance_type' => $global ? 'global' : 'local', 'created_by_company_id' => $owner, 'created_by_user_id' => $this->actor->id], 'governance-'.$i);
                foreach ($this->offices as $office) {
                    if (! $global && $owner !== $office->company_id) {
                        continue;
                    }
                    app(CategoryAdoptionService::class)->useCategory($category->id, 'location', $office->id);
                    foreach (DB::table('gov_tenant_scope_mappings')->where('reference_type', 'category')->where('reference_id', $category->id)->where('scope_type', 'location')->where('scope_id', $office->id)->get() as $mapping) {
                        $this->records->record($this->run, 'gov_tenant_scope_mappings', $mapping);
                    }
                }
                $keyword = ['computer', 'paper', 'keyboard', 'memory', 'software'][$i % 5];
                $node = CatalogNode::where('is_selectable', true)->where('title_en', 'like', '%'.$keyword.'%')->orderBy('code')->first();
                if ($node && ! CatalogSnipeMapping::where('code', $node->code)->exists()) {
                    $this->create(CatalogSnipeMapping::class, ['code' => $node->code, 'category_id' => $category->id], 'classification-map-'.$i);
                }

                return $category->id;
            });
            $this->categories[$i] = Category::withoutGlobalScopes()->findOrFail($id);
        }
    }

    private function products(): void
    {
        $statusId = $this->unit('status-ready', fn () => $this->create(Statuslabel::class, ['name' => $this->prefix.' Ready to Deploy / ব্যবহারযোগ্য', 'deployable' => 1, 'pending' => 0, 'archived' => 0, 'created_by' => $this->actor->id], 'status-ready')->id);
        foreach (range(0, 11) as $i) {
            $this->unit('supplier-'.$i, fn () => $this->create(Supplier::class, ['name' => $this->prefix.' '.['ঢাকা অফিস সরবরাহ', 'চট্টগ্রাম কম্পিউটার সেবা', 'খুলনা স্টেশনারি', 'রাজশাহী প্রযুক্তি'][$i % 4].' '.($i + 1), 'country' => 'BD', 'created_by' => $this->actor->id, 'email' => 'supplier'.$i.'@example.invalid'], 'supplier-'.$i)->id);
        }
        foreach (range(0, 7) as $i) {
            $this->unit('manufacturer-'.$i, fn () => $this->create(Manufacturer::class, ['name' => $this->prefix.' Sample Manufacturer '.($i + 1), 'created_by' => $this->actor->id], 'manufacturer-'.$i)->id);
        }
        $supplierIds = $this->records->ids($this->run, 'suppliers');
        $manufacturerIds = $this->records->ids($this->run, 'manufacturers');
        $assetCategories = array_values(array_filter($this->categories, fn ($c) => $c->category_type === 'asset'));
        foreach (range(0, $this->size['models'] - 1) as $i) {
            $id = $this->unit('model-'.$i, fn () => $this->create(AssetModel::class, ['name' => $assetCategories[$i % count($assetCategories)]->name.' Model '.($i + 1), 'category_id' => $assetCategories[$i % count($assetCategories)]->id,
                'manufacturer_id' => $manufacturerIds[$i % count($manufacturerIds)], 'created_by' => $this->actor->id, 'model_number' => 'BD-'.($i + 1), 'require_serial' => true], 'model-'.$i)->id, false);
            $this->models[$i] = AssetModel::withoutGlobalScopes()->findOrFail($id);
        }
        $this->unit('policies', function () use ($statusId) {
            foreach ($this->categories as $i => $category) {
                $profile = $this->create(Profile::class, ['name' => $this->prefix.' '.$category->name, 'scope' => 'COMPANY', 'company_id' => $this->companies[1]->id, 'status' => 'PUBLISHED', 'version' => '1.0.0'], 'profile-'.$i);
                foreach (['require_quantity', 'create_assets', 'post_inventory'] as $code) {
                    $this->create(ProfileCapability::class, ['profile_id' => $profile->id, 'capability_code' => $code,
                        'behavior' => $code === 'require_quantity' || ($category->category_type === 'asset' ? $code === 'create_assets' : $code === 'post_inventory') ? 'ENFORCE' : 'DISABLE', 'config_payload' => ['status_id' => $statusId]], 'profile-'.$i.'-'.$code);
                }
                $assignment = ProfileAssignment::where('target_type', Category::class)->where('target_id', $category->id)->where('scope_level', 'NATIVE')->first();
                if ($assignment) {
                    // The category observer created this assignment for our new category.
                    $assignment->update(['profile_id' => $profile->id, 'effective_from' => $this->run->anchor_date->copy()->subYear()]);
                    $this->records->record($this->run, $assignment->getTable(), $assignment->getAttributes(), 'assignment-'.$i);
                } else {
                    $this->create(ProfileAssignment::class, ['profile_id' => $profile->id, 'target_type' => Category::class, 'target_id' => $category->id, 'scope_level' => 'NATIVE', 'assigned_by' => $this->actor->id, 'effective_from' => $this->run->anchor_date->copy()->subYear()], 'assignment-'.$i);
                }
            }
            foreach ($this->models as $i => $model) {
                $this->create(ApprovalPolicy::class, ['target_type' => 'asset_model', 'target_id' => $model->id, 'policy_name' => $i % 2 ? 'PRIMARY_AND_FINAL' : 'PRIMARY_ONLY'], 'approval-model-'.$i);
            }

            return true;
        });
        foreach (['consumables' => [Consumable::class, 'consumable', 'কাগজ / Paper'], 'accessories' => [Accessory::class, 'accessory', 'কিবোর্ড / Keyboard'], 'components' => [Component::class, 'component', 'মেমোরি / Memory']] as $kind => [$class, $type, $name]) {
            $matching = array_values(array_filter($this->categories, fn ($c) => $c->category_type === $type));
            foreach (range(0, $this->size[$kind] - 1) as $i) {
                $id = $this->unit($kind.'-'.$i, function () use ($i, $class, $matching, $kind, $name) {
                    $office = $this->offices[$i % $this->operationalCount()];
                    $visible = array_values(array_filter($matching, fn ($category) => DB::table('gov_tenant_scope_mappings')->where('reference_type', 'category')->where('reference_id', $category->id)->where('scope_type', 'location')->where('scope_id', $office->id)->exists()));
                    $names = self::BULK_NAMES[$kind];
                    $name = $names[intdiv($i, $this->operationalCount()) % count($names)];

                    return $this->create($class, ['name' => $this->prefix.' '.$name.' '.($i + 1), 'category_id' => $visible[$i % count($visible)]->id,
                        'company_id' => $office->company_id, 'location_id' => $office->id, 'qty' => $class === Component::class ? 1 : 0, 'min_amt' => 5, 'created_by' => $this->actor->id], $kind.'-'.$i)->id;
                });
                $this->bulk[$kind][$i] = $class::withoutGlobalScopes()->findOrFail($id);
            }
        }
        $licenseCategories = array_values(array_filter($this->categories, fn ($c) => $c->category_type === 'license' && DB::table('gov_category_governance')->where('category_id', $c->id)->where('governance_type', 'global')->exists()));
        foreach (range(0, $this->size['licenses'] - 1) as $i) {
            $this->unit('license-'.$i, function () use ($i, $licenseCategories, $supplierIds) {
                $license = $this->create(License::class, ['name' => $this->prefix.' Office Software '.($i + 1), 'category_id' => $licenseCategories[$i % count($licenseCategories)]->id,
                    'company_id' => $this->offices[$i % count($this->offices)]->company_id, 'created_by' => $this->actor->id,
                    'supplier_id' => $supplierIds[$i % count($supplierIds)], 'seats' => 5, 'serial' => $this->prefix.'-SAMPLE-'.($i + 1), 'license_email' => 'licensing@example.invalid', 'reassignable' => true], 'license-'.$i);
                if (DB::table('license_seats')->where('license_id', $license->id)->count() === 0) {
                    foreach (range(1, 5) as $seat) {
                        $this->create(LicenseSeat::class, ['license_id' => $license->id, 'created_by' => $this->actor->id], 'license-'.$i.'-seat-'.$seat);
                    }
                }

                return $license->id;
            });
        }
    }

    private function tracking(): void
    {
        $fundId = $this->unit('fund', fn () => $this->create(FundingType::class, ['primary_type' => 'ADP', 'name' => $this->prefix.' GoB (Taka) / সরকারি অর্থায়ন', 'description' => 'পরীক্ষামূলক উন্নয়ন বরাদ্দ'], 'fund')->id);
        $initiatives = [];
        foreach (range(0, $this->size['initiatives'] - 1) as $i) {
            $id = $this->unit('initiative-'.$i, function () use ($i) {
                $initiative = $this->create(Initiative::class, ['title' => $this->prefix.' '.['সরকারি অফিস ডিজিটাল সক্ষমতা', 'শিক্ষা সহায়তা সরঞ্জাম', 'পুলিশ অফিস প্রযুক্তি আধুনিকায়ন'][$i % 3].' '.($i + 1),
                    'purpose' => 'পরীক্ষামূলক সরকারি সরঞ্জাম বিতরণ ও পরিবীক্ষণ', 'status' => 'Planning', 'primary_funding' => 'ADP',
                    'owner_company_id' => $this->companies[($i % 3) * 2 + 1]->id, 'require_documents' => true, 'require_metadata' => false, 'allow_overshoot' => false], 'initiative-'.$i);
                foreach (['HEAD', 'OFFICER', 'SUPPORT', 'MONITOR'] as $j => $designation) {
                    $this->create(OperationUnit::class, ['initiative_id' => $initiative->id, 'user_id' => $this->people[($i * 4 + $j) % count($this->people)]->id, 'designation' => $designation], 'initiative-'.$i.'-'.$designation);
                }
                if (! $initiative->isOperationallyReady()) {
                    throw new RuntimeException('Initiative team is incomplete.');
                }
                $initiative->update(['status' => 'Active']);

                return $initiative->id;
            });
            $initiatives[] = Initiative::withoutGlobalScopes()->findOrFail($id);
        }
        foreach (range(0, $this->size['codes'] - 1) as $i) {
            $id = $this->unit('code-'.$i, function () use ($i, $fundId, $initiatives) {
                $initiative = $initiatives[$i % count($initiatives)];
                $fiscalStart = $this->run->anchor_date->month >= 7 ? $this->run->anchor_date->year : $this->run->anchor_date->year - 1;
                $code = $this->create(TrackingCode::class, ['initiative_id' => $initiative->id, 'funding_type_id' => $fundId, 'tracking_code' => $this->prefix.'-ADP-'.($i + 1),
                    'task_title' => 'অফিস সরঞ্জাম বরাদ্দ / Office Equipment Allocation '.($i + 1), 'specificity_level' => 'CATEGORY', 'fiscal_year' => $fiscalStart.'-'.($fiscalStart + 1), 'status' => 'ACTIVE'], 'code-'.$i);
                $office = $this->offices[$i % $this->operationalCount()];
                $profile = DB::table('gov_location_profiles')->where('location_id', $office->id)->first();
                $this->create(TrackingScope::class, ['tracking_code_id' => $code->id, 'dimension' => 'GEOGRAPHY', 'target_type' => 'GeoArea', 'target_id' => $profile->geo_area_id], 'code-'.$i.'-geo');
                $this->create(TrackingScope::class, ['tracking_code_id' => $code->id, 'dimension' => 'PARTICIPANTS', 'target_type' => 'SpecificLocations', 'target_id' => $office->id], 'code-'.$i.'-participant');
                foreach ($this->officeAssetModels($i) as $model) {
                    $target = $this->create(TrackingTarget::class, ['tracking_code_id' => $code->id, 'category_id' => $model->category_id, 'planned_qty' => 200, 'economic_code' => 'SAMPLE-OFFICE'], 'code-'.$i.'-target-'.$model->category_id);
                    $this->create(TrackingAllocation::class, ['target_id' => $target->id, 'location_id' => $office->id, 'allocated_qty' => 200], 'code-'.$i.'-allocation-'.$model->category_id);
                }

                return $code->id;
            });
            $this->codes[$i] = TrackingCode::with('initiative')->findOrFail($id);
        }
    }

    private function stock(): void
    {
        foreach ($this->offices as $i => $office) {
            if (! $this->operational($i)) {
                continue;
            }
            $this->unit('opening-components-'.$i, function () use ($i, $office) {
                $items = array_values(array_filter($this->bulk['components'], fn ($item) => (int) $item->location_id === (int) $office->id));
                if (! $items) {
                    return false;
                }

                // Native Component validation requires an initial quantity of at least one.
                // Represent that already-existing opening stock without applying its projection twice.
                return $this->contexts->run($this->officePeople($i)[1], $office, function () use ($items, $office, $i) {
                    $document = app(GoodsReceiptService::class)->saveDraft(['reference_no' => $this->prefix.'-OPENING-'.$i, 'reference_date' => $this->run->anchor_date->format('Y-m-d')], array_map(fn ($item) => ['type' => 'component', 'id' => $item->id, 'qty' => 1, 'unit_cost' => 0], $items), $this->officePeople($i)[1]->id);
                    $this->attachExample($document, $this->officePeople($i)[1]->id);
                    foreach ($items as $item) {
                        $this->create(InventoryMovement::class, ['stockable_type' => 'component', 'stockable_id' => $item->id, 'movement_type' => 'IN', 'quantity' => 1, 'balance_after' => 1, 'document_type' => Document::class, 'document_id' => $document->id, 'company_id' => $office->company_id, 'location_id' => $office->id, 'created_by' => $this->officePeople($i)[1]->id], 'component-opening-'.$item->id);
                    }
                    $document->transitionTo(DocumentState::POSTED, $this->officePeople($i)[1]->id, 'Opening balance for native component fixture');

                    return $document->id;
                }, 'storekeeper');
            });
            $this->unit('receipt-assets-'.$i, function () use ($i, $office) {
                $qty = intdiv($this->size['assets'], $this->operationalCount()) + ($i < $this->size['assets'] % $this->operationalCount() ? 1 : 0);
                $models = $this->officeAssetModels($i);
                $lines = [];
                foreach ($models as $index => $model) {
                    $quantity = intdiv($qty, count($models)) + ($index < $qty % count($models) ? 1 : 0);
                    if ($quantity) {
                        $lines[] = ['type' => 'assetmodel', 'id' => $model->id, 'qty' => $quantity, 'unit_cost' => preg_match('/Office Chairs|Office Desks|Steel Cabinets|Meeting Tables|Whiteboards|Bookshelves/i', $model->name) ? 12500 : 65000];
                    }
                }

                return $this->document($office, $i, $lines, 'receipt', 'POSTED', $this->codes[$i] ?? null)->id;
            });
            $this->unit('receipt-bulk-'.$i, function () use ($i, $office) {
                $lines = [];
                foreach ($this->bulk as $kind => $items) {
                    foreach ($items as $item) {
                        if ((int) $item->location_id === (int) $office->id) {
                            $lines[] = ['type' => strtolower(class_basename($item)), 'id' => $item->id, 'qty' => 100, 'unit_cost' => 250];
                        }
                    }
                }

                return $this->document($office, $i, $lines, 'receipt', 'POSTED')->id;
            });
        }
        $already = count($this->records->ids($this->run, 'gov_documents'));
        foreach (range(0, max(0, $this->size['documents'] - $already) - 1) as $i) {
            if ($this->size['documents'] <= $already) {
                break;
            }
            $this->unit('extra-document-'.$i, function () use ($i) {
                $officeIndex = $i % $this->operationalCount();
                $office = $this->offices[$officeIndex];
                $item = collect($this->bulk['consumables'])->first(fn ($item) => $item->location_id === $office->id);
                $isIssue = $i % 5 === 0 && $item;
                $state = $isIssue ? 'POSTED' : ['DRAFT', 'READY', 'CANCELLED'][$i % 3];

                return $this->document($office, $officeIndex, $item ? [['type' => 'consumable', 'id' => $item->id, 'qty' => 2, 'unit_cost' => 250]] : [], $isIssue ? 'issue' : 'receipt', $state)->id;
            });
        }
    }

    private function document(Location $office, int $officeIndex, array $lines, string $type, string $state, ?TrackingCode $code = null): Document
    {
        $keeper = $this->officePeople($officeIndex)[1];

        return $this->contexts->run($keeper, $office, function () use ($office, $lines, $type, $state, $code, $keeper) {
            $header = ['reference_no' => $this->prefix.'-স্মারক-'.($this->run->report['units'] ? count($this->run->report['units']) : 1), 'reference_date' => $this->run->anchor_date->format('Y-m-d'), 'purchase_type' => 'Government Purchase'];
            $document = app(GoodsReceiptService::class)->saveDraft($header, $lines, $keeper->id);
            $this->attachExample($document, $keeper->id);
            if ($type !== 'receipt') {
                $document->update(['type' => $type]);
            }
            $document->references()->create(['reference_type' => 'Supplier Challan', 'reference_number' => $this->prefix.'-CHALLAN-'.$document->document_number, 'reference_date' => $this->run->anchor_date->format('Y-m-d')]);
            foreach ($document->items as $item) {
                if ($item->product_type === 'assetmodel') {
                    foreach (range(0, $item->quantity - 1) as $row) {
                        foreach (['serial_number' => $this->prefix.'-'.$document->document_number.'-'.$item->product_id.'-'.$row, 'asset_tag' => $this->prefix.'-'.$document->document_number.'-'.$item->product_id.'-'.$row, 'warranty_months' => '36'] as $field => $value) {
                            $item->metadata()->create(['field_key' => $field, 'value' => $value, 'row_index' => $row]);
                        }
                    }
                }
            }
            if ($code) {
                $scope = app(ScopeValidatorService::class)->validateExecutionScope($code, $office->id);
                if (! $scope['is_valid']) {
                    throw new RuntimeException($scope['message']);
                }
                $document->references()->create(['reference_type' => 'Special Allocation', 'reference_number' => $code->tracking_code, 'reference_date' => $this->run->anchor_date->format('Y-m-d')]);
            }
            if ($state === 'POSTED') {
                app(PostingPipelineManager::class)->materialize($document->fresh(), $keeper->id);
                foreach ($document->items as $item) {
                    if ($item->product_type === 'assetmodel') {
                        $ids = DB::table('gov_asset_registrations')->where('intake_item_id', $item->id)->pluck('asset_id');
                        $model = AssetModel::withoutGlobalScopes()->findOrFail($item->product_id);
                        DB::table('assets')->whereIn('id', $ids)->update(['name' => $model->name, 'rtd_location_id' => $office->id, 'requestable' => 1]);
                    }
                }
            } elseif ($state !== 'DRAFT') {
                $document->transitionTo(DocumentState::from($state), $keeper->id, 'পরীক্ষামূলক অবস্থা / Experiment state');
            }

            return $document;
        }, 'storekeeper');
    }

    private function requests(): void
    {
        foreach (range(0, $this->size['requests'] - 1) as $i) {
            $this->unit('request-'.$i, function () use ($i) {
                $officeIndex = $i % $this->operationalCount();
                $office = $this->offices[$officeIndex];
                $members = $this->officePeople($officeIndex);
                $employee = $members[4 + ($i % max(1, count($members) - 4))];
                $model = $this->receiptModel($officeIndex);
                $requestedType = 'asset_model';
                $requestedId = $model->id;
                $requestKind = intdiv($i, $this->operationalCount()) % 4;
                if ($requestKind === 1) {
                    $model = collect($this->officeAssetModels($officeIndex))->first(fn ($item) => preg_match('/Office Chairs|Office Desks|Steel Cabinets|Meeting Tables/i', $item->name)) ?? $model;
                    $requestedId = $model->id;
                } elseif (in_array($requestKind, [2, 3])) {
                    $kind = $requestKind === 2 ? 'consumables' : 'accessories';
                    $items = array_values(array_filter($this->bulk[$kind], fn ($item) => (int) $item->location_id === (int) $office->id));
                    $item = $items[intdiv($i, $this->operationalCount()) % count($items)];
                    $requestedType = $requestKind === 2 ? 'consumable' : 'accessory';
                    $requestedId = $item->id;
                }
                // Direct model policies use the currently supported PolicyService resolution path.
                $policy = ApprovalPolicy::where('target_type', $requestedType)->where('target_id', $requestedId)->first()
                    ?? $this->create(ApprovalPolicy::class, ['target_type' => $requestedType, 'target_id' => $requestedId, 'policy_name' => 'PRIMARY_ONLY'], 'approval-'.$requestedType.'-'.$requestedId);
                $policy->update(['policy_name' => $i % 3 === 0 ? 'PRIMARY_AND_FINAL' : ($i % 3 === 1 ? 'PRIMARY_ONLY' : 'AUTO_APPROVE')]);
                $request = $this->contexts->run($employee, $office, function () use ($employee, $requestedType, $requestedId, $office) {
                    $basket = app(BasketService::class);
                    $basket->addItem($employee->id, $requestedType, $requestedId, 2);

                    return $basket->submitBasket($employee->id, ['request_type' => 'other', 'purpose' => 'দাপ্তরিক কাজে সরঞ্জাম ব্যবহার', 'justification' => 'পরীক্ষামূলক সরকারি দপ্তরের কাজ', 'delivery_location_id' => $office->id])[0];
                });
                if ($i % 8 > 1 && $request->approval_status !== 'approved') {
                    $request = $this->contexts->run($members[2], $office, fn () => app(ApprovalService::class)->processDecision($request->fresh(), $members[2], $request->items->mapWithKeys(fn ($line) => [$line->id => ['status' => $i % 8 === 7 ? 'rejected' : 'approved', 'qty' => 2, 'notes' => 'পরীক্ষামূলক সিদ্ধান্ত']])->all()), 'primary_approver');
                    if ($request->approval_status === 'pending_final' && $i % 8 > 2) {
                        $request = $this->contexts->run($members[3], $office, fn () => app(ApprovalService::class)->processDecision($request->fresh(), $members[3], $request->items->mapWithKeys(fn ($line) => [$line->id => ['status' => 'approved', 'qty' => 2]])->all()), 'final_approver');
                    }
                }
                if ($requestedType === 'asset_model' && in_array($i % 8, [4, 5, 6]) && $request->approval_status === 'approved') {
                    $qty = $i % 8 === 4 ? 1 : 2;
                    $assetIds = Asset::withoutGlobalScopes()->where('location_id', $office->id)->where('model_id', $model->id)->whereNull('assigned_to')->limit($qty)->pluck('id')->all();
                    if (count($assetIds) === $qty) {
                        $this->contexts->run($members[1], $office, fn () => app(FulfillmentService::class)->issueItems($request->fresh(), $members[1], [$request->items->first()->id => $assetIds]), 'storekeeper');
                    }
                }

                return $request->id;
            });
        }
        foreach (range(0, $this->size['baskets'] - 1) as $i) {
            $this->unit('basket-'.$i, function () use ($i) {
                $officeIndex = $i % $this->operationalCount();
                $members = $this->officePeople($officeIndex);
                $employee = $members[4 + (intdiv($i, $this->operationalCount()) % (count($members) - 4))];

                return $this->contexts->run($employee, $this->offices[$officeIndex], function () use ($employee, $officeIndex) {
                    $service = app(BasketService::class);
                    $basket = $service->addItem($employee->id, 'asset_model', $this->receiptModel($officeIndex)->id, 1);
                    $furniture = collect($this->officeAssetModels($officeIndex))->first(fn ($model) => preg_match('/Office Chairs|Office Desks|Steel Cabinets|Meeting Tables/i', $model->name));
                    if ($furniture) {
                        $service->addItem($employee->id, 'asset_model', $furniture->id, 1);
                    }
                    foreach ($this->bulk as $kind => $items) {
                        if ($kind === 'components') {
                            continue;
                        } // The current requestable factory has no component adapter.
                        $item = collect($items)->first(fn ($item) => (int) $item->location_id === (int) $this->offices[$officeIndex]->id);
                        if ($item) {
                            $service->addItem($employee->id, strtolower(class_basename($item)), $item->id, $kind === 'consumables' ? 5 : 1);
                        }
                    }

                    return $basket->id;
                });
            });
        }
    }

    private function examples(): void
    {
        foreach (range(0, $this->size['handshakes'] - 1) as $i) {
            $this->unit('handshake-'.$i, function () use ($i) {
                $officeIndex = $i % $this->operationalCount();
                $members = $this->officePeople($officeIndex);
                $slug = ['storekeeper', 'primary_approver', 'final_approver'][$i % 3];
                $from = $members[1 + $i % 3];

                return app(RoleHandshakeService::class)->proposeHandshake($this->offices[$officeIndex]->id, $slug, $from->id, $members[4]->id)->id;
            });
        }
        $typeId = $this->unit('maintenance-type', fn () => $this->create(MaintenanceType::class, ['name' => $this->prefix.' Preventive Service / প্রতিরোধমূলক রক্ষণাবেক্ষণ', 'created_by' => $this->actor->id], 'maintenance-type')->id);
        $assets = Asset::withoutGlobalScopes()->whereIn('id', $this->records->ids($this->run, 'assets'))->get();
        foreach (range(0, $this->size['maintenance'] - 1) as $i) {
            $this->unit('maintenance-'.$i, fn () => $this->create(Maintenance::class, ['asset_id' => $assets[$i % $assets->count()]->id, 'maintenance_type_id' => $typeId,
                'asset_maintenance_type' => 'Preventive Service', 'name' => 'পরীক্ষামূলক কম্পিউটার রক্ষণাবেক্ষণ', 'start_date' => $this->run->anchor_date->copy()->subDays($i + 1)->format('Y-m-d'),
                'cost' => 1500, 'is_warranty' => false, 'created_by' => $this->actor->id], 'maintenance-'.$i)->id);
        }
    }

    private function officePeople(int $officeIndex): array
    {
        return array_values(array_filter($this->people, fn ($person) => (int) $person->location_id === $this->offices[$officeIndex]->id));
    }

    private function attachExample(Document $document, int $actorId): void
    {
        $path = 'gov-experiments/'.$this->run->id.'/documents/'.$document->id.'.txt';
        if (! Storage::disk('local')->put($path, "পরীক্ষামূলক সরকারি দপ্তরের সরঞ্জাম দলিল\nFictional Bangladesh government office inventory document\n".$document->document_number."\nNo official signature or seal.\n")) {
            throw new RuntimeException('Could not write experiment supporting file.');
        }
        $document->attachments()->create(['file_path' => $path, 'original_name' => 'experiment-office-document.txt', 'mime_type' => 'text/plain', 'uploaded_by' => $actorId]);
    }

    private function operational(int $index): bool
    {
        return $index < $this->operationalCount();
    }

    private function operationalCount(): int
    {
        return count($this->offices) - ($this->run->profile === 'quick' ? 0 : 4);
    }

    private function receiptModel(int $officeIndex): AssetModel
    {
        return $this->officeAssetModels($officeIndex)[0];
    }

    private function officeAssetModels(int $officeIndex): array
    {
        // Current create_assets capability fills GRN but does not fill mandatory laptop CPU metadata.
        // Materialize desktop templates; laptop templates still exercise the metadata package separately.
        $models = array_values(array_filter($this->models, fn ($model) => ! str_contains(strtolower($model->category->name), 'laptop') && DB::table('gov_category_governance')->where('category_id', $model->category_id)->where('governance_type', 'global')->exists()));
        $unique = [];
        foreach ($models as $model) {
            if (! isset($unique[$model->category_id])) {
                $unique[$model->category_id] = $model;
            }
        }

        return array_values($unique);
    }
}
