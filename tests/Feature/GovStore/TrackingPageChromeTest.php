<?php

namespace Tests\Feature\GovStore;

use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Services\TrackingMonitorMetrics;
use GovStore\Tracking\Services\TrackingTrail;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Tabuna\Breadcrumbs\Breadcrumbs;
use Tabuna\Breadcrumbs\BreadcrumbsMiddleware;

/**
 * Page chrome for programme tracking. The core layout prints the bare page title when a route has no
 * breadcrumb, which repeated every tracking page's own heading. These tests keep each page on a
 * breadcrumb trail, keep names in that trail to pages that rendered, and keep the views on the gs kit.
 */
class TrackingPageChromeTest extends TestCase
{
    /** GET routes that answer with a file or JSON rather than a page. */
    private const NOT_PAGES = [
        'gov.tracking.tracking-codes.check-uniqueness', 'gov.tracking.tracking-codes.download',
        'gov.tracking.documents.download', 'gov.tracking.operation-unit.search-users',
    ];

    /** Isolated SQLite-only application, as in StoreOperationsWorkflowTest; these tests never touch a database. */
    public function createApplication()
    {
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'LOG_CHANNEL' => 'stderr', 'CACHE_DRIVER' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $value) {
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
        app()->setLocale('en-US');
        // A fresh trail per test, as each request gets one.
        app()->forgetScopedInstances();
    }

    private function pageRoutes(): Collection
    {
        return collect(Route::getRoutes()->getRoutes())->filter(fn (RoutingRoute $route) => str_starts_with((string) $route->getName(), 'gov.tracking.')
            && ! str_starts_with($route->getName(), 'gov.tracking.api.')
            && in_array('GET', $route->methods(), true)
            && ! in_array($route->getName(), self::NOT_PAGES, true));
    }

    /** Crumb titles (and URLs) for a route, after registering every route's breadcrumb like the middleware does. */
    private function trail(string $name, array $parameters): Collection
    {
        app(BreadcrumbsMiddleware::class)->handle(Request::create('/'), fn () => null);

        return Breadcrumbs::generate($name, $parameters)->slice(1)->map(fn ($crumb) => [$crumb->title(), $crumb->url()])->values();
    }

    private function remember(): void
    {
        $code = new TrackingCode;
        $code->forceFill(['tracking_code' => 'CLINIC-01', 'initiative_id' => 5]);
        app(TrackingTrail::class)->remember([
            'initiative' => new Initiative(['title' => 'Clinic equipment']),
            'trackingCode' => $code,
        ]);
    }

    public function test_every_tracking_page_route_has_a_breadcrumb(): void
    {
        $routes = $this->pageRoutes();
        $this->assertGreaterThanOrEqual(17, $routes->count());
        foreach ($routes as $route) {
            $this->assertContains('breadcrumbs', $route->gatherMiddleware(),
                $route->getName().' has no breadcrumb, so the layout would print its title above the page heading again.');
        }
    }

    public function test_trail_uses_generic_labels_until_a_page_renders_its_subject(): void
    {
        // An error or "no access" page renders no programme, so its trail must not name one.
        $this->assertSame(['Programmes', 'Programme', 'Tracking code', 'Office'],
            $this->trail('gov.tracking.initiatives.tracking-codes.offices.show', [5, 7, 9])->pluck(0)->all());

        $this->remember();
        $trail = $this->trail('gov.tracking.initiatives.tracking-codes.offices.show', [5, 7, 9]);
        $this->assertSame(['Programmes', 'Clinic equipment', 'CLINIC-01', 'Office'], $trail->pluck(0)->all());
        $this->assertSame(route('gov.tracking.initiatives.show', 5), $trail[1][1]);
        $this->assertSame(route('gov.tracking.initiatives.tracking-codes.show', [5, 7]), $trail[2][1]);
    }

    public function test_each_page_trail_ends_with_the_page_itself(): void
    {
        $this->remember();
        $code = new TrackingCode;
        $code->forceFill(['id' => 7, 'initiative_id' => 5]);
        $expected = [
            'gov.tracking.initiatives.index' => [[], ['Programmes']],
            'gov.tracking.initiatives.create' => [[], ['Programmes', 'New programme']],
            'gov.tracking.initiatives.show' => [[5], ['Programmes', 'Clinic equipment']],
            'gov.tracking.initiatives.edit' => [[5], ['Programmes', 'Clinic equipment', 'Programme settings']],
            'gov.tracking.initiatives.manage' => [[5], ['Programmes', 'Clinic equipment', 'Manage']],
            'gov.tracking.initiatives.report' => [[5], ['Programmes', 'Clinic equipment', 'Progress report']],
            'gov.tracking.initiatives.operation-unit.index' => [[5], ['Programmes', 'Clinic equipment', 'Operation team']],
            'gov.tracking.initiatives.retrospective.index' => [[5], ['Programmes', 'Clinic equipment', 'Legacy assets']],
            'gov.tracking.initiatives.tracking-codes.create' => [[5], ['Programmes', 'Clinic equipment', 'New tracking code']],
            'gov.tracking.initiatives.tracking-codes.show' => [[5, 7], ['Programmes', 'Clinic equipment', 'CLINIC-01']],
            'gov.tracking.initiatives.tracking-codes.edit' => [[5, 7], ['Programmes', 'Clinic equipment', 'CLINIC-01', 'Edit']],
            'gov.tracking.tracking-codes.view-task' => [[$code], ['Programmes', 'Clinic equipment', 'CLINIC-01', 'Code sheet']],
            'gov.tracking.offices.index' => [[], ['Offices']],
            'gov.tracking.offices.show' => [[9], ['Offices', 'Office']],
            'gov.tracking.initiatives.offices.show' => [[5, 9], ['Programmes', 'Clinic equipment', 'Office']],
            'gov.tracking.initiatives.tracking-codes.offices.show' => [[5, 7, 9], ['Programmes', 'Clinic equipment', 'CLINIC-01', 'Office']],
            'gov.tracking.funding-types.index' => [[], ['Funding types']],
        ];
        $this->assertEqualsCanonicalizing($this->pageRoutes()->map->getName()->values()->all(), array_keys($expected));
        foreach ($expected as $name => [$parameters, $titles]) {
            $this->assertSame($titles, $this->trail($name, $parameters)->pluck(0)->all(), $name);
        }
        // The code sheet links back to its own programme, taken from the code.
        $this->assertSame(route('gov.tracking.initiatives.show', 5), $this->trail('gov.tracking.tracking-codes.view-task', [$code])[1][1]);
    }

    public function test_trail_follows_the_reader_language(): void
    {
        app()->setLocale('bn-BD');
        $this->assertSame(['কর্মসূচিসমূহ', 'কর্মসূচি', 'অগ্রগতি প্রতিবেদন'], $this->trail('gov.tracking.initiatives.report', [5])->pluck(0)->all());
    }

    public function test_figures_use_the_reader_numerals(): void
    {
        $this->assertSame('৳12,345.50', TrackingMonitorMetrics::money(12345.5));
        $this->assertSame('3 / 10', TrackingMonitorMetrics::digits('3 / 10'));
        app()->setLocale('bn-BD');
        $this->assertSame('৳১২,৩৪৫.৫০', TrackingMonitorMetrics::money(12345.5));
        $this->assertSame('৳১.৫০ '.__('govtracking::general.monitor.lakh'), TrackingMonitorMetrics::money(150000));
        $this->assertSame('৩ / ১০', TrackingMonitorMetrics::digits('3 / 10'));
    }

    public function test_only_rendered_tracking_views_name_the_trail(): void
    {
        $initiative = new Initiative(['title' => 'Rendered programme']);
        app('view')->callComposer(view('govtracking::monitor.tabs', compact('initiative')));
        $this->assertSame('Rendered programme', app(TrackingTrail::class)->programme());

        // Another package's view with the same variable names does not feed the tracking trail.
        app()->forgetScopedInstances();
        app('view')->callComposer(view('gs-theme::components.trail', ['initiative' => $initiative]));
        $this->assertSame('Programme', app(TrackingTrail::class)->programme());
    }

    public function test_tracking_views_use_the_gs_kit_instead_of_legacy_styling(): void
    {
        $root = base_path('packages/gov-store/tracking/src/resources/views');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        $legacy = '/\b(text-(aqua|blue|yellow|green|red|purple|orange|light-blue)|box box-[a-z]+|btn btn-[a-z]+|label label-[a-z]+|callout callout-[a-z]+|col-md-offset-\d+|alert alert-[a-z]+)\b/';
        $checked = 0;
        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $checked++;
            $source = file_get_contents($file->getPathname());
            $name = substr($file->getPathname(), strlen($root) + 1);
            $this->assertDoesNotMatchRegularExpression($legacy, $source, $name.' uses Bootstrap/AdminLTE styling; use the gs kit and tokens.');
            // Inline styles only for JavaScript-toggled visibility and data-driven widths.
            preg_match_all('/(?<![:\w-])style="([^"]*)"/', $source, $styles);
            foreach ($styles[1] as $style) {
                $this->assertMatchesRegularExpression('/^\s*(display:\s*none;?|width:\s*\{\{[^}]+\}\}%;?)\s*$/', $style, $name.' has an inline style: '.$style);
            }
        }
        $this->assertGreaterThan(40, $checked);
    }
}
