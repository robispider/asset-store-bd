<?php

namespace Tests\Feature\GovStore;

use GovStore\Classification\Services\MyCatalogService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\ViewErrorBag;

/** Office catalog (my-catalog/index): name parsing, family grouping and the page rendered with a stub layout. */
class MyCatalogTest extends TestCase
{
    public function createApplication()
    {
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'CACHE_DRIVER' => 'array',
            'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync'] as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        $app = require __DIR__.'/../../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $dir = sys_get_temp_dir().'/my-catalog-layout';
        @mkdir($dir.'/layouts', 0777, true);
        file_put_contents($dir.'/layouts/default.blade.php', "@yield('content')\n@yield('moar_scripts')");
        app('view')->getFinder()->prependLocation($dir);
        app('view')->getDispatcher()->forget('composing: layouts.default');
        view()->share('errors', new ViewErrorBag);
        app()->setLocale('en-US');
    }

    private function row(int $id, string $name, string $type, int $items): object
    {
        return (object) ['id' => $id, 'name' => $name, 'category_type' => $type, 'governance_type' => 'global',
            'total_usage_count' => $items, 'unspsc_code' => null];
    }

    private function render(string $tab, bool $readOnly, array $rows): string
    {
        $active = collect($rows);
        $families = MyCatalogService::families($rows);

        return view('gov-classification::my-catalog.index', [
            'categories' => new LengthAwarePaginator($rows, count($rows), 50),
            'families' => $families,
            'isReadOnly' => $readOnly,
            'canMap' => false,
            'activeTab' => $tab,
            'filters' => ['q' => '', 'type' => null],
            'typeCounts' => $active->countBy('category_type')->all(),
            'office' => 'আঞ্চলিক কার্যালয় / Chittagong',
            'metrics' => [
                'active' => $active, 'archived' => collect(),
                'total_active' => count($rows), 'needs_cleanup' => $active->where('total_usage_count', 0)->count(),
                'in_use' => $active->where('total_usage_count', '>', 0)->count(), 'archived_count' => 0,
                'items' => (int) $active->sum('total_usage_count'), 'families' => count($families), 'unmapped' => count($rows),
                'top' => ['name' => 'Desktop Computers', 'count' => 13],
            ],
        ])->render();
    }

    public function test_label_splits_bilingual_names_and_codes(): void
    {
        $this->assertSame(
            ['en' => 'Air Conditioners', 'bn' => 'শীতাতপ নিয়ন্ত্রক', 'code' => 'D6-51', 'family' => 'Air Conditioners / শীতাতপ নিয়ন্ত্রক'],
            MyCatalogService::label('Air Conditioners / শীতাতপ নিয়ন্ত্রক · D6-51')
        );
        $this->assertSame(['en' => 'Printers', 'bn' => null, 'code' => null, 'family' => 'Printers'], MyCatalogService::label('Printers'));
    }

    public function test_families_group_codes_that_share_a_title_and_type(): void
    {
        $families = MyCatalogService::families([
            $this->row(1, 'Computer Components / কম্পিউটার উপাদান · D6-04', 'component', 2),
            $this->row(2, 'Computer Components / কম্পিউটার উপাদান · D6-09', 'component', 0),
            $this->row(3, 'Desktop Computers / ডেস্কটপ কম্পিউটার · D6-01', 'asset', 13),
        ]);

        $this->assertCount(2, $families);
        $this->assertSame(['D6-04', 'D6-09'], $families[0]['codes']);
        $this->assertSame([2, 1], [$families[0]['items'], $families[0]['unused']]);
        $this->assertSame('ডেস্কটপ কম্পিউটার', $families[1]['bn']);
    }

    public function test_review_tab_renders_grouped_rows_with_archive_actions_in_both_languages(): void
    {
        $rows = [
            $this->row(11, 'ICT Accessories / আইসিটি আনুষঙ্গিক সরঞ্জাম · D6-08', 'accessory', 0),
            $this->row(12, 'ICT Accessories / আইসিটি আনুষঙ্গিক সরঞ্জাম · D6-28', 'accessory', 0),
            $this->row(13, 'Laptop / ল্যাপটপ · D6-06', 'asset', 0),
        ];

        foreach (['en-US', 'bn-BD'] as $locale) {
            app()->setLocale($locale);
            $html = $this->render('cleanup', false, $rows);

            $this->assertStringNotContainsString('<x-gs::', $html);
            $this->assertStringNotContainsString('classification::my_catalog.', $html);
            $this->assertStringContainsString(e(__('classification::my_catalog.archive_all', ['count' => 2])), $html);
            $this->assertStringContainsString('data-ids="11,12"', $html);
            $this->assertStringContainsString('data-catalog-select-all', $html);
            // Collapsed codes stay readable to screen readers (and to the live suite's innerText checks).
            $this->assertStringContainsString(e(__('classification::my_catalog.codes_list', ['codes' => 'D6-08, D6-28'])), $html);
        }
    }

    public function test_read_only_viewers_get_no_row_actions(): void
    {
        $html = $this->render('active', true, [$this->row(21, 'Printers / প্রিন্টার · D6-11', 'asset', 5)]);
        $tbody = substr($html, strpos($html, '<tbody>'), strpos($html, '</tbody>') - strpos($html, '<tbody>'));

        $this->assertStringContainsString('D6-11', $tbody);
        $this->assertStringNotContainsString('btn-archive', $tbody);
        $this->assertStringNotContainsString('btn-restore', $tbody);
        $this->assertStringNotContainsString('gs-btn', $tbody);
        $this->assertStringNotContainsString(e(__('classification::my_catalog.add_categories')), $html);
    }

    public function test_my_catalog_translations_have_matching_keys_in_both_languages(): void
    {
        $base = base_path('packages/gov-store/classification/src/resources/lang/');

        $this->assertSame(array_keys(require $base.'en-US/my_catalog.php'), array_keys(require $base.'bn-BD/my_catalog.php'));
    }
}
