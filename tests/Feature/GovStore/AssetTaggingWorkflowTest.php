<?php

namespace Tests\Feature\GovStore;

use App\Models\Asset;
use App\Models\Setting;
use GovStore\AssetTagging\Capabilities\GovtTagCapability;
use GovStore\AssetTagging\Factories\TagFormatFactory;
use GovStore\AssetTagging\Formats\FinanceDivisionV1Format;
use GovStore\AssetTagging\Services\EnablementGate;
use GovStore\AssetTagging\Services\OfficeTagFacts;
use GovStore\Classification\Services\FinanceCatalog;
use GovStore\Classification\Services\ProductTypeCommodity;
use GovStore\Classification\Services\ProductTypeFinanceDefault;
use GovStore\Metadata\Services\MetadataValueStore;
use GovStore\Metadata\Support\StaleMetadataRevision;
use GovStore\StoreOperations\Contracts\AssetTagIssuer;
use GovStore\StoreOperations\Services\CapabilityRegistry;
use GovStore\StoreOperations\Services\DocumentValidationService;
use GovStore\StoreOperations\Services\PostingPipelineManager;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Feature\GovStore\Concerns\AssetTaggingFixtures;

/** Asset tagging (revision 7) on an isolated SQLite database only; never the live DB. */
class AssetTaggingWorkflowTest extends TestCase
{
    use AssetTaggingFixtures;

    protected const OFFICE = 10;

    protected const OTHER_OFFICE = 11;

    protected const COMPANY = 20;

    // ---------------------------------------------------------------- formats

    public function test_fd18_format_encodes_decodes_and_rejects_the_source_negative_examples(): void
    {
        $format = new FinanceDivisionV1Format;
        $facts = ['gov_code' => '090809', 'acquired_on' => '2026-10-01', 'location_code' => '01', 'division_code' => '09'];
        $this->assertSame('09080910260109', $format->prefix($facts));
        $this->assertSame('090809102601090001', $format->encode($facts, 1));
        $decoded = $format->decode('090809102601090001');
        $this->assertSame(['090809', '10', '26', '01', '09', 1], [$decoded['gov_code'], $decoded['month'], $decoded['year'],
            $decoded['location_code'], $decoded['division_code'], $decoded['sequence']]);
        foreach (['090809132601090001', '090809102601090000', '90809102601090001', '000809102601090001'] as $bad) {
            $this->assertNull($format->decode($bad), $bad);
        }
        $this->expectException(\InvalidArgumentException::class);
        $format->encode($facts, 10000);
    }

    public function test_format_factory_only_builds_allowlisted_formats(): void
    {
        $this->assertSame('fd18-v1', app(TagFormatFactory::class)->make('fd18-v1')->version());
        $this->expectException(\InvalidArgumentException::class);
        app(TagFormatFactory::class)->make('fd19-v2');
    }

    // ---------------------------------------------------------------- metadata, code lists, office facts

    public function test_metadata_writes_need_the_expected_revision(): void
    {
        $store = app(MetadataValueStore::class);
        $row = $store->put('company', self::COMPANY, OfficeTagFacts::CODELISTS, $this->lists(), null, 1);
        $this->assertSame(1, $row->revision);
        $this->assertSame(2, $store->put('company', self::COMPANY, OfficeTagFacts::CODELISTS, $this->lists(), 1, 1)->revision);
        try {
            $store->put('company', self::COMPANY, OfficeTagFacts::CODELISTS, $this->lists(), 1, 1);
            $this->fail('A stale revision must be refused.');
        } catch (StaleMetadataRevision $conflict) {
            $this->assertSame(409, $conflict->getStatusCode());
            $this->assertSame(2, $conflict->current->revision);
        }
    }

    public function test_code_lists_validate_codes_and_protect_entries_in_use(): void
    {
        $store = app(MetadataValueStore::class);
        foreach ([[['code' => '1', 'name' => 'Bad']], [['code' => '00', 'name' => 'Zero']], [['code' => '01', 'name' => 'A'], ['code' => '01', 'name' => 'B']]] as $locations) {
            try {
                $store->put('company', self::COMPANY, OfficeTagFacts::CODELISTS, ['locations' => $locations, 'divisions' => []], null, 1);
                $this->fail('Invalid code list accepted');
            } catch (ValidationException) {
                $this->assertNull($store->find('company', self::COMPANY, OfficeTagFacts::CODELISTS));
            }
        }
        $this->enableOffice();
        $lists = $this->lists();
        $lists['locations'][0]['name'] = 'Head Office (renamed)';
        $row = $store->put('company', self::COMPANY, OfficeTagFacts::CODELISTS, $lists, 1, 1);
        $this->assertSame('Head Office (renamed)', $row->payload['locations'][0]['name']);
        $lists['locations'] = [['code' => '02', 'name' => 'Branch']];
        $this->expectException(ValidationException::class);
        $store->put('company', self::COMPANY, OfficeTagFacts::CODELISTS, $lists, 2, 1);
    }

    public function test_an_office_picks_from_the_lists_and_a_location_belongs_to_one_office(): void
    {
        $store = app(MetadataValueStore::class);
        $store->put('company', self::COMPANY, OfficeTagFacts::CODELISTS, $this->lists(), null, 1);
        $store->put('office', self::OFFICE, OfficeTagFacts::OFFICE, ['location_code' => '01', 'division_codes' => ['09', '01'], 'default_division_code' => '09'], null, 1);
        $this->assertSame(['01', '09'], $store->payload('office', self::OFFICE, OfficeTagFacts::OFFICE)['division_codes']);
        $this->assertSame(['01' => ['id' => self::OFFICE, 'name' => 'Office A']], app(OfficeTagFacts::class)->takenLocations(self::COMPANY, self::OTHER_OFFICE));
        foreach ([
            ['location_code' => '01', 'division_codes' => ['09'], 'default_division_code' => '09'], // taken by Office A
            ['location_code' => '03', 'division_codes' => ['09'], 'default_division_code' => '09'], // not in the list
            ['location_code' => '02', 'division_codes' => ['09'], 'default_division_code' => '01'], // default not ticked
        ] as $payload) {
            try {
                $store->put('office', self::OTHER_OFFICE, OfficeTagFacts::OFFICE, $payload, null, 1);
                $this->fail('Office facts accepted: '.json_encode($payload));
            } catch (ValidationException) {
                $this->assertNull($store->find('office', self::OTHER_OFFICE, OfficeTagFacts::OFFICE));
            }
        }
    }

    // ---------------------------------------------------------------- product type commodity and Finance default

    public function test_one_unspsc_code_per_product_type_and_defaults_follow_it(): void
    {
        $commodity = app(ProductTypeCommodity::class);
        $commodity->assign(1, '43211503');
        $this->activate('090809', '43211503');
        app(MetadataValueStore::class)->put('category', 1, ProductTypeFinanceDefault::NAMESPACE, ['gov_code' => '090809'], null, 1);
        $this->assertSame('ok', app(ProductTypeFinanceDefault::class)->resolve(1)['status']);

        $commodity->assign(1, '43211507'); // change of commodity replaces, never adds
        $this->assertSame(['43211507'], DB::table('gov_catalog_snipe_mappings')->where('category_id', 1)->pluck('code')->all());
        $this->assertSame('needs_review', app(ProductTypeFinanceDefault::class)->resolve(1)['status']);
        $this->expectException(ValidationException::class);
        app(MetadataValueStore::class)->put('category', 1, ProductTypeFinanceDefault::NAMESPACE, ['gov_code' => '090809'], 1, 1);
    }

    // ---------------------------------------------------------------- Finance catalog capacity (D-08)

    public function test_item_numbers_run_across_the_group_warn_from_90_and_stop_at_99(): void
    {
        $catalog = app(FinanceCatalog::class);
        $this->assertSame('11', $catalog->nextItemNumber('09'));
        foreach (range(11, 98) as $number) {
            DB::table('gov_finance_items')->insert(['gov_code' => '0901'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'group_name' => 'ICT', 'subgroup_name' => 'Computers', 'item_name' => 'Item '.$number, 'status' => 'held']);
        }
        $this->assertSame('warning', $catalog->capacity()->firstWhere('group', '09')['tone']);
        $item = $catalog->create(['group_code' => '09', 'subgroup_code' => '08', 'item_name' => 'Tablet'], 1);
        $this->assertSame('090899', $item->gov_code); // group-wide numbering, whatever the subgroup
        $this->assertNull($catalog->nextItemNumber('09'));
        $this->assertSame('danger', $catalog->capacity()->firstWhere('group', '09')['tone']);
        $this->expectException(ValidationException::class);
        $catalog->create(['group_code' => '09', 'subgroup_code' => '01', 'item_name' => 'One too many'], 1);
    }

    // ---------------------------------------------------------------- GRN posting

    public function test_enabled_office_posts_govt_and_standard_tags_with_evidence(): void
    {
        $this->enableOffice();
        $this->activate('090809');
        app(MetadataValueStore::class)->put('category', 1, ProductTypeFinanceDefault::NAMESPACE, ['gov_code' => '090809'], null, 1);
        $receipt = $this->receipt([['model' => 1, 'qty' => 3], ['model' => 2, 'qty' => 2]], '2026-10-01');

        $summary = app(AssetTagIssuer::class)->summary($receipt);
        $this->assertSame([3, 2], [$summary['govt'], $summary['standard']]);
        app(PostingPipelineManager::class)->materialize($receipt, 1);

        $this->assertSame('POSTED', $receipt->fresh()->status);
        $rows = DB::table('gov_asset_registrations')->orderBy('id')->get();
        $this->assertSame(['090809102601090001', '090809102601090002', '090809102601090003', 'GS-000100', 'GS-000101'], $rows->pluck('asset_tag')->all());
        $this->assertSame(['fd18-v1', 'fd18-v1', 'fd18-v1', 'native-v1', 'native-v1'], $rows->pluck('format_version')->all());
        $this->assertSame([0, 1, 2, 0, 1], $rows->pluck('unit_index')->map(fn ($v) => (int) $v)->all());
        $this->assertSame('090809', $rows[0]->gov_code);
        $this->assertNull($rows[3]->gov_code);
        $snapshot = json_decode($rows[0]->tag_snapshot, true);
        $this->assertSame(['product_type', 'challan', '01', '09'], [$snapshot['finance_source'], $snapshot['date_source'], $snapshot['location_code'], $snapshot['division_code']]);
        $this->assertSame('no_finance_code', json_decode($rows[3]->tag_snapshot, true)['standard_reason']);
        $this->assertSame('2026-10-01', substr((string) Asset::where('asset_tag', '090809102601090001')->value('purchase_date'), 0, 10));
        $this->assertSame(3, (int) DB::table('gov_asset_tag_sequences')->where('scope_key', '09080910260109')->value('last_value'));
        $this->assertSame(102, (int) DB::table('settings')->value('next_auto_tag_base'));

        // The next posting continues the same prefix; nothing is reset or reused.
        $second = $this->receipt([['model' => 1, 'qty' => 1]], '2026-10-20', 'GRN-TAG-2');
        app(PostingPipelineManager::class)->materialize($second, 1);
        $this->assertTrue(DB::table('gov_asset_registrations')->where('asset_tag', '090809102601090004')->exists());
    }

    public function test_missing_acquisition_date_blocks_posting_and_rolls_back(): void
    {
        $this->enableOffice();
        $this->activate('090809');
        app(MetadataValueStore::class)->put('category', 1, ProductTypeFinanceDefault::NAMESPACE, ['gov_code' => '090809'], null, 1);
        $receipt = $this->receipt([['model' => 1, 'qty' => 2]], null);

        $checklist = app(DocumentValidationService::class)->evaluateDocument($receipt);
        $this->assertFalse($checklist['is_valid']);
        $this->assertTrue(collect($checklist['checklist'])->contains(fn ($row) => ($row['level'] ?? null) === 'required' && ! $row['passed']));
        try {
            app(PostingPipelineManager::class)->materialize($receipt, 1);
            $this->fail('A Govt. Tag line without a date must not post.');
        } catch (ValidationException) {
            $this->assertSame('DRAFT', $receipt->fresh()->status);
            $this->assertSame(0, Asset::count());
            $this->assertSame(0, DB::table('gov_asset_tag_sequences')->count());
        }

        // The fix in the line panel: knowingly keep standard tags.
        $receipt->items()->first()->metadata()->create(['field_key' => 'tag_choice', 'value' => 'standard', 'row_index' => 0]);
        app(PostingPipelineManager::class)->materialize($receipt->fresh(), 1);
        $this->assertSame(['native-v1', 'native-v1'], DB::table('gov_asset_registrations')->pluck('format_version')->all());
    }

    public function test_no_finance_code_is_a_warning_and_a_line_choice_fixes_it(): void
    {
        $this->enableOffice();
        $this->activate('090809');
        $receipt = $this->receipt([['model' => 1, 'qty' => 2]], '2026-10-01');
        $capability = app(GovtTagCapability::class);
        $rows = $capability->checklist($receipt, $receipt->items()->first());
        $this->assertSame('warning', $rows[0]['level']);
        $this->assertTrue(app(DocumentValidationService::class)->evaluateDocument($receipt)['is_valid']);

        $receipt->items()->first()->metadata()->create(['field_key' => 'gov_code', 'value' => '090809', 'row_index' => 0]);
        $this->assertSame([], $capability->checklist($receipt, $receipt->items()->first()->fresh()));
        app(PostingPipelineManager::class)->materialize($receipt->fresh(), 1);
        $this->assertSame('line_choice', json_decode(DB::table('gov_asset_registrations')->value('tag_snapshot'), true)['finance_source']);
    }

    public function test_donations_get_standard_tags_and_offices_not_enabled_keep_todays_behaviour(): void
    {
        $this->enableOffice();
        $this->activate('090809');
        app(MetadataValueStore::class)->put('category', 1, ProductTypeFinanceDefault::NAMESPACE, ['gov_code' => '090809'], null, 1);
        $donation = $this->receipt([['model' => 1, 'qty' => 1]], '2026-10-01', 'GRN-DON', 'Donation');
        app(PostingPipelineManager::class)->materialize($donation, 1);
        $this->assertSame('receiving_source', json_decode(DB::table('gov_asset_registrations')->value('tag_snapshot'), true)['standard_reason']);

        app(MetadataValueStore::class)->put('office', self::OFFICE, OfficeTagFacts::ENABLEMENT, ['enabled' => false, 'reason' => 'Paused for test'], 1, 1);
        $plain = $this->receipt([['model' => 1, 'qty' => 1]], '2026-10-01', 'GRN-PLAIN');
        app(PostingPipelineManager::class)->materialize($plain, 1);
        $row = DB::table('gov_asset_registrations')->orderByDesc('id')->first();
        $this->assertSame('entered-v1', $row->format_version);
        $this->assertSame('GS-000101', $row->asset_tag);
    }

    public function test_gates_and_guards(): void
    {
        $this->enableOffice();
        $gate = app(EnablementGate::class);
        $this->assertTrue($gate->passes(self::OFFICE));
        DB::table('gov_experiment_records')->insert(['run_id' => 'run', 'table_name' => 'locations', 'record_key' => json_encode(['id' => (string) self::OFFICE])]);
        config(['asset-tagging.rehearsal' => false]);
        $this->assertFalse($gate->passes(self::OFFICE));
        // G6 rehearsal: experiment offices may issue for a live rehearsal, but never in production.
        config(['asset-tagging.rehearsal' => true]);
        $this->assertTrue($gate->passes(self::OFFICE));
        $environment = app()->environment();
        app()->instance('env', 'production');
        try {
            $this->assertFalse($gate->passes(self::OFFICE));
        } finally {
            app()->instance('env', $environment);
            config(['asset-tagging.rehearsal' => false]);
        }

        // An 18-digit tag can only come from issuance.
        $asset = new Asset(['model_id' => 1, 'status_id' => 1]);
        $asset->forceFill(['asset_tag' => '090809102601090099', 'location_id' => self::OFFICE, 'company_id' => self::COMPANY]);
        try {
            $asset->save();
            $this->fail('A typed 18-digit tag must be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('asset_tag', $e->errors());
        }

        // The native sequence cannot be broken while an office is enabled (G1).
        $settings = Setting::query()->first();
        $settings->auto_increment_prefix = '1A';
        $this->expectException(ValidationException::class);
        $settings->forceSave();
    }

    public function test_issued_tags_never_change(): void
    {
        $this->enableOffice();
        $receipt = $this->receipt([['model' => 1, 'qty' => 1]], '2026-10-01');
        app(PostingPipelineManager::class)->materialize($receipt, 1);
        $asset = Asset::firstOrFail();
        $asset->asset_tag = 'GS-999999';
        $this->expectException(ValidationException::class);
        $asset->save();
    }

    public function test_capability_is_registered_and_enforced_by_default_for_asset_models_only(): void
    {
        $this->assertSame(GovtTagCapability::class, CapabilityRegistry::getClass('issue_govt_tags'));
        $this->assertSame(['issue_govt_tags'], CapabilityRegistry::defaultsFor('assetmodel'));
        $this->assertSame([], CapabilityRegistry::defaultsFor('consumable'));
        $this->assertSame(__('asset-tagging::tagging.capability.name'), CapabilityRegistry::getDictionary()['issue_govt_tags']['name']);
    }
}
