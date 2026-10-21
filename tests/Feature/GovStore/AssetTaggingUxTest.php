<?php

namespace Tests\Feature\GovStore;

use GovStore\AssetTagging\Capabilities\GovtTagCapability;
use GovStore\AssetTagging\Http\Controllers\CompanyCodeListController;
use GovStore\AssetTagging\Http\Controllers\EnablementController;
use GovStore\AssetTagging\Http\Controllers\IssuedTagsExportController;
use GovStore\AssetTagging\Http\Controllers\OfficeTagSettingsController;
use GovStore\AssetTagging\Http\Controllers\TagLookupController;
use GovStore\AssetTagging\Services\OfficeTagFacts;
use GovStore\Classification\Http\Controllers\FinanceCatalogController;
use GovStore\Classification\Services\ProductTypeFinanceDefault;
use GovStore\Metadata\Services\MetadataValueStore;
use GovStore\StoreOperations\Contracts\AssetTagIssuer;
use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Services\PostingPipelineManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Tests\Feature\GovStore\Concerns\AssetTaggingFixtures;

/** Asset tagging screens and GRN additions, rendered through their real code paths with a stub layout. */
class AssetTaggingUxTest extends TestCase
{
    use AssetTaggingFixtures {
        setUp as fixturesSetUp;
    }

    protected const OFFICE = 10;

    protected const OTHER_OFFICE = 11;

    protected const COMPANY = 20;

    protected function setUp(): void
    {
        $this->fixturesSetUp();
        $dir = sys_get_temp_dir().'/asset-tagging-ux-layout';
        @mkdir($dir.'/layouts', 0777, true);
        file_put_contents($dir.'/layouts/default.blade.php', "@yield('content')\n@yield('moar_scripts')");
        app('view')->getFinder()->prependLocation($dir);
        app('view')->getDispatcher()->forget('composing: layouts.default');
        view()->share('errors', new ViewErrorBag);
        app()->setLocale('en-US');
        // Tables the role resolver reads for the settings tabs.
        foreach (['gov_company_admins' => ['user_id', 'company_id'], 'gov_ict_jurisdictions' => ['user_id'],
            'gov_location_profiles' => ['location_id', 'office_admin_id', 'geo_area_id'], 'gov_office_responsibilities' => ['user_id', 'location_id', 'role_slug'],
            'gov_office_memberships' => ['user_id', 'location_id', 'status'], 'gov_access_grants' => ['user_id', 'location_id', 'role_slug', 'expires_at']] as $name => $columns) {
            Schema::create($name, function (Blueprint $t) use ($columns) { $t->increments('id'); foreach ($columns as $c) { $t->string($c)->nullable(); } $t->timestamps(); });
        }
        Schema::create('gov_access_events', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->string('ability'); $t->string('outcome'); $t->text('reason')->nullable(); $t->unsignedInteger('user_id')->nullable();
            $t->unsignedInteger('location_id')->nullable(); $t->timestamp('created_at')->nullable();
        });
    }

    private function request(array $query = []): Request
    {
        $request = Request::create('/', 'GET', $query);
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => auth()->user());
        app()->instance('request', $request);

        return $request;
    }

    private function assertKitCompiled(string $html): void
    {
        $this->assertStringNotContainsString('<x-gs::', $html);
        $this->assertStringNotContainsString('@php', $html);
    }

    private function panel(Document $receipt, int $quantity = 2): string
    {
        $this->request(['document_id' => $receipt->id, 'product_type' => 'AssetModel', 'product_id' => 1]);
        $item = $receipt->items()->first();

        return app(GovtTagCapability::class)->renderUI($item, ['row_index' => 0, 'quantity' => $quantity]);
    }

    public function test_line_panel_warns_without_a_finance_code_and_offers_both_fixes(): void
    {
        $this->enableOffice();
        $html = $this->panel($this->receipt([['model' => 1, 'qty' => 2]], '2026-10-01'));
        $this->assertKitCompiled($html);
        $this->assertStringContainsString('No Finance Code for this product type. These 2 units will get standard tags.', $html);
        $this->assertStringContainsString('name="items[0][meta][0][gov_code]"', $html);
        $this->assertStringContainsString('data-storeops-select2="'.route('gov.catalog.finance.selectlist').'"', $html);
        $this->assertStringContainsString('data-storeops-set="items[0][meta][0][tag_choice]" data-value="standard"', $html);
        $this->assertStringContainsString('Keep standard tags', $html);
        $this->assertStringContainsString('2 standard tags', $html);
    }

    public function test_line_panel_shows_the_product_type_code_read_only_and_flags_a_missing_date(): void
    {
        $this->enableOffice();
        $this->activate('090809');
        app(MetadataValueStore::class)->put('category', 1, ProductTypeFinanceDefault::NAMESPACE, ['gov_code' => '090809'], null, 1);

        $dated = $this->panel($this->receipt([['model' => 1, 'qty' => 3]], '2026-10-01'), 3);
        $this->assertStringContainsString('090809', $dated);
        $this->assertStringContainsString('From product type', $dated);
        $this->assertStringContainsString('Set by the national catalogue publisher', $dated);
        $this->assertStringContainsString('3 Govt. Tags', $dated);
        $this->assertStringContainsString('1026', $dated); // month segment from the challan date
        $this->assertStringContainsString('allocated when posted', $dated);
        $this->assertStringNotContainsString('name="items[0][meta][0][gov_code]"', $dated);

        $undated = $this->panel($this->receipt([['model' => 1, 'qty' => 3]], null, 'GRN-TAG-2'), 3);
        $this->assertStringContainsString('data-storeops-attention="references[0][reference_date]"', $undated);
        $this->assertStringContainsString('Needs attention', $undated);
        $this->assertStringContainsString('data-storeops-focus="references[0][reference_date]"', $undated);
        $this->assertStringContainsString('Use standard tags for this line', $undated);
    }

    public function test_line_panel_is_absent_when_the_office_is_not_enabled(): void
    {
        $this->assertSame('', $this->panel($this->receipt([['model' => 1, 'qty' => 1]], '2026-10-01')));
        $this->assertNull(app(AssetTagIssuer::class)->summary($this->receipt([['model' => 1, 'qty' => 1]], '2026-10-01', 'GRN-TAG-3')));
    }

    public function test_posting_modal_line_and_posted_issued_tags_box(): void
    {
        $this->enableOffice();
        $this->activate('090809');
        app(MetadataValueStore::class)->put('category', 1, ProductTypeFinanceDefault::NAMESPACE, ['gov_code' => '090809'], null, 1);
        $receipt = $this->receipt([['model' => 1, 'qty' => 2], ['model' => 2, 'qty' => 1]], '2026-10-01');
        $this->assertSame('Tags: 2 Govt. Tags · 1 standard tag — permanent', app(AssetTagIssuer::class)->summary($receipt)['line']);

        app(PostingPipelineManager::class)->materialize($receipt, 1);
        $html = app(AssetTagIssuer::class)->postedPanel($receipt->fresh());
        $this->assertKitCompiled($html);
        $this->assertStringContainsString('Issued tags', $html);
        $this->assertStringContainsString('090809102601090001', $html);
        $this->assertStringContainsString('GS-000100', $html);
        $this->assertStringContainsString('name="bulk_actions" value="labels"', $html);
        $this->assertSame(3, substr_count($html, 'name="ids[]"'));
        // The box is rendered inside the workspace form, so it must not open a nested form of its own.
        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringContainsString('formaction="'.route('hardware.bulkedit.show').'"', $html);
        $this->assertStringContainsString('formtarget="_blank"', $html);
        $this->assertStringContainsString(route('asset-tags.grn.csv', ['document' => $receipt->id]), $html);

        $csv = app(IssuedTagsExportController::class)($receipt->id, $this->policy());
        ob_start();
        $csv->sendContent();
        $content = ob_get_clean();
        $this->assertStringContainsString("GRN-TAG-1,090809102601090001,govt,090809,Laptop", $content);
        $this->assertStringContainsString('GRN-TAG-1,GS-000100,standard,,"Office chair"', $content);
    }

    public function test_office_settings_pick_from_lists_and_show_taken_codes(): void
    {
        $store = app(MetadataValueStore::class);
        $store->put('company', self::COMPANY, OfficeTagFacts::CODELISTS, $this->lists(), null, 1);
        $store->put('office', self::OTHER_OFFICE, OfficeTagFacts::OFFICE, ['location_code' => '02', 'division_codes' => ['01'], 'default_division_code' => '01'], null, 1);
        $this->request();
        $html = app()->call([app(OfficeTagSettingsController::class), 'show'], ['request' => app('request')])->render();
        $this->assertKitCompiled($html);
        $this->assertMatchesRegularExpression('/<option value="02"[^>]*disabled[^>]*>\s*02 · Branch — Used by Office B/', $html);
        $this->assertStringContainsString('name="division_codes[]" value="09"', $html);
        $this->assertStringNotContainsString('authority', strtolower($html));
        $this->assertStringContainsString('G4 · Office tag settings', $html);
    }

    public function test_company_code_lists_lock_codes_in_use(): void
    {
        $this->enableOffice();
        $this->request();
        $html = app()->call([app(CompanyCodeListController::class), 'show'], ['request' => app('request')])->render();
        $this->assertKitCompiled($html);
        $this->assertMatchesRegularExpression('/name="locations\[0\]\[code\]" value="01"[^>]*readonly/', $html);
        $this->assertStringContainsString('used by 1 office', $html);
        $this->assertDoesNotMatchRegularExpression('/name="locations\[1\]\[code\]" value="02"[^>]*readonly/', $html);
    }

    public function test_finance_catalog_shows_capacity_and_the_next_number(): void
    {
        $this->request(['new_group' => '09']);
        $html = app()->call([app(FinanceCatalogController::class), 'index'], ['request' => app('request')])->render();
        $this->assertKitCompiled($html);
        $this->assertStringContainsString('Group capacity', $html);
        $this->assertStringContainsString('09 ICT · 10 of 99 used', $html);
        $this->assertStringContainsString('gets item number 11', $html);
    }

    public function test_lookup_decodes_a_govt_tag_and_answers_missing_neutrally(): void
    {
        $this->enableOffice();
        $this->activate('090809');
        app(MetadataValueStore::class)->put('category', 1, ProductTypeFinanceDefault::NAMESPACE, ['gov_code' => '090809'], null, 1);
        app(PostingPipelineManager::class)->materialize($this->receipt([['model' => 1, 'qty' => 1]], '2026-10-01'), 1);

        $this->request(['tag' => '090809102601090001']);
        $found = app(TagLookupController::class)(app('request'), app(\GovStore\AssetTagging\Services\TagDecoder::class))->render();
        $this->assertKitCompiled($found);
        $this->assertStringContainsString('Govt. Tag', $found);
        $this->assertStringContainsString('090809 · Laptop', $found);
        $this->assertStringContainsString('Supplier Challan CH-1', $found);

        $this->request(['tag' => '090809102601099999']);
        $missing = app(TagLookupController::class)(app('request'), app(\GovStore\AssetTagging\Services\TagDecoder::class))->render();
        $this->assertStringContainsString('No asset you can view has this tag.', $missing);
        $this->assertStringNotContainsString('Laptop', $missing);
    }

    public function test_enablement_console_lists_gates_and_offices(): void
    {
        $this->enableOffice();
        $this->request();
        $html = app()->call([app(EnablementController::class), 'index'], ['request' => app('request')])->render();
        $this->assertKitCompiled($html);
        $this->assertStringContainsString('G1 · Native tag sequence', $html);
        $this->assertStringContainsString('Office A', $html);
        $this->assertStringContainsString('LL 01 · DD 09', $html);
        $this->assertStringContainsString(route('asset-tags.enablement.disable', self::OFFICE), $html);
    }

    public function test_bengali_and_english_tagging_strings_match(): void
    {
        $en = require base_path('packages/gov-store/asset-tagging/src/resources/lang/en-US/tagging.php');
        $bn = require base_path('packages/gov-store/asset-tagging/src/resources/lang/bn-BD/tagging.php');
        $this->assertSame(array_keys(\Illuminate\Support\Arr::dot($en)), array_keys(\Illuminate\Support\Arr::dot($bn)));
        $en = require base_path('packages/gov-store/classification/src/resources/lang/en-US/finance.php');
        $bn = require base_path('packages/gov-store/classification/src/resources/lang/bn-BD/finance.php');
        $this->assertSame(array_keys($en), array_keys($bn));
    }

    private function policy(): \GovStore\StoreOperations\Policies\DocumentPolicy
    {
        $policy = \Mockery::mock(\GovStore\StoreOperations\Policies\DocumentPolicy::class);
        $policy->shouldReceive('check')->andReturnNull();

        return $policy;
    }
}
