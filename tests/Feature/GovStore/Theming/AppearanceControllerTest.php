<?php

namespace Tests\Feature\GovStore\Theming;

use GovStore\Theming\Http\Controllers\AppearanceController;
use GovStore\Theming\Models\ThemeAssignment;
use GovStore\Theming\Models\ThemePreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

class AppearanceControllerTest extends ThemingTestCase
{
    private function request(string $method, array $data = [], bool $json = false): Request
    {
        $request = Request::create('/gov/appearance', $method, $data);
        if ($json) {
            $request->headers->set('Accept', 'application/json');
        }
        $request->setLaravelSession(app('session')->driver('array'));
        $request->setUserResolver(fn () => auth()->user());
        app()->instance('request', $request);

        return $request;
    }

    private function controller(): AppearanceController
    {
        return app(AppearanceController::class);
    }

    public function test_routes_are_registered_with_auth_and_breadcrumbs(): void
    {
        foreach (['gs-theme.appearance' => 'gov/appearance', 'gs-theme.appearance.update' => 'gov/appearance', 'gs-theme.appearance.mode' => 'gov/appearance/mode',
            'gs-theme.assignments' => 'gov/appearance/assignments', 'gs-theme.assignments.update' => 'gov/appearance/assignments/{scope}/{id?}',
            'gs-theme.lab' => 'gov/theme-lab', 'gs-theme.lab.focus' => 'gov/theme-lab/{theme}'] as $name => $uri) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertSame($uri, $route->uri());
            $this->assertContains('auth', $route->gatherMiddleware(), $name);
            if (in_array('GET', $route->methods(), true)) {
                // Every UI route has a breadcrumb (tabuna registers it as route middleware + default).
                $this->assertContains('breadcrumbs', $route->gatherMiddleware(), $name);
                $this->assertArrayHasKey(\Tabuna\Breadcrumbs\BreadcrumbsMiddleware::class, $route->defaults, $name);
            }
        }
    }

    public function test_index_lists_published_themes_with_defaults_and_permissions(): void
    {
        $this->actor();
        ThemeAssignment::create(['scope_type' => 'office', 'scope_id' => self::LOCATION, 'theme' => 'digital-blue']);
        $view = $this->controller()->index($this->request('GET'));
        $data = $view->getData();
        $this->assertSame('gs-theme::appearance.index', $view->name());
        $this->assertSame(['default', 'digital-blue', 'executive-neutral', 'institutional-green', 'lavender'], array_keys($data['themes']));
        $this->assertSame('digital-blue', $data['current']);
        $this->assertSame(['office'], $data['tags']['digital-blue']);
        $this->assertTrue($data['canChoose']);
        $this->assertFalse($data['canAssign']);
        $this->assertFalse($data['canViewLab']);
    }

    public function test_save_theme_and_mode(): void
    {
        $user = $this->actor();
        $response = $this->controller()->update($this->request('PUT', ['theme' => 'executive-neutral', 'mode' => 'dark']));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(['executive-neutral', 'dark'], array_values(ThemePreference::where('user_id', $user->id)->first()->only(['theme', 'mode'])));

        $this->controller()->update($this->request('PUT', ['theme' => 'executive-neutral', 'mode' => 'system', 'reset' => '1']));
        $this->assertSame([null, 'system'], array_values(ThemePreference::where('user_id', $user->id)->first()->only(['theme', 'mode'])));
    }

    public function test_validation_rejects_unknown_drafts_and_bad_modes(): void
    {
        $this->actor();
        foreach ([['theme' => 'nope', 'mode' => 'light'], ['theme' => 'default', 'mode' => 'sepia'], ['theme' => 'default']] as $payload) {
            try {
                $this->controller()->update($this->request('PUT', $payload));
                $this->fail('Expected validation error for '.json_encode($payload));
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
        $this->assertSame(0, ThemePreference::count());
    }

    public function test_enforced_scope_makes_theme_read_only_but_mode_still_changes(): void
    {
        $user = $this->actor();
        ThemeAssignment::create(['scope_type' => 'company', 'scope_id' => self::COMPANY, 'theme' => 'digital-blue', 'enforced' => true]);
        $view = $this->controller()->index($this->request('GET'));
        $this->assertFalse($view->getData()['canChoose']);
        $this->assertSame('company', $view->getData()['enforced']['scope_type']);

        $response = $this->controller()->update($this->request('PUT', ['theme' => 'executive-neutral', 'mode' => 'dark']));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertTrue(session()->has('errors') || $response->getSession()?->has('errors'));
        $this->assertNull(ThemePreference::where('user_id', $user->id)->value('theme'));

        $this->controller()->update($this->request('PUT', ['mode' => 'dark']));
        $this->assertSame('dark', ThemePreference::where('user_id', $user->id)->value('mode'));
    }

    public function test_mode_endpoint_for_the_toggle(): void
    {
        $user = $this->actor();
        $response = $this->controller()->mode($this->request('POST', ['mode' => 'dark'], true));
        $this->assertSame(['mode' => 'dark'], $response->getData(true));
        $this->assertSame('dark', ThemePreference::where('user_id', $user->id)->value('mode'));
        $this->expectException(ValidationException::class);
        $this->controller()->mode($this->request('POST', ['mode' => 'blue'], true));
    }

    public function test_guests_are_redirected_by_auth_middleware(): void
    {
        auth()->logout();
        $response = $this->get('/gov/appearance');
        $this->assertContains($response->getStatusCode(), [302, 401]);
    }
}
