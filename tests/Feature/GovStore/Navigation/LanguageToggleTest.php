<?php

namespace Tests\Feature\GovStore\Navigation;

use App\Models\User;
use GovStore\TenantScope\Http\Controllers\LocaleController;
use GovStore\TenantScope\Navigation\MenuRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Navbar language toggle (EN | বাং): saves users.locale, which core CheckLocale applies.
 */
class LanguageToggleTest extends NavigationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // In-memory only: these tests create tables and must never reach a real database.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('locale', 10)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        DB::table('users')->insert(['id' => 5, 'locale' => 'en-US']);
    }

    private function switchTo(string $locale, string $from = 'http://localhost/hardware'): \Illuminate\Http\RedirectResponse
    {
        $user = User::withoutGlobalScopes()->findOrFail(5);
        $request = Request::create('/gov-store/locale', 'POST', ['locale' => $locale], server: ['HTTP_REFERER' => $from]);
        $request->setUserResolver(fn () => $user);
        app('url')->setRequest($request);

        return app(LocaleController::class)->switch($request);
    }

    public function test_switch_saves_the_language_and_returns_to_the_same_page(): void
    {
        $response = $this->switchTo('bn-BD');

        $this->assertSame('bn-BD', DB::table('users')->where('id', 5)->value('locale'));
        $this->assertSame('http://localhost/hardware', $response->getTargetUrl());

        $this->switchTo('en-US');
        $this->assertSame('en-US', DB::table('users')->where('id', 5)->value('locale'));
    }

    public function test_switch_accepts_only_the_configured_languages(): void
    {
        try {
            $this->switchTo('fr-FR');
            $this->fail('An unlisted language was accepted');
        } catch (ValidationException) {
            $this->assertSame('en-US', DB::table('users')->where('id', 5)->value('locale'));
        }
    }

    public function test_route_is_a_signed_in_post(): void
    {
        $route = Route::getRoutes()->getByName('gov.locale.switch');
        $this->assertNotNull($route);
        $this->assertSame(['POST'], $route->methods());
        $this->assertContains('web', $route->gatherMiddleware());
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertNull(collect($route->gatherMiddleware())->first(fn ($m) => str_starts_with($m, 'gov.can:')),
            'Every signed-in user may change their own language');
    }

    public function test_toggle_sits_in_the_topbar_and_marks_the_current_language(): void
    {
        $item = collect(app(MenuRegistry::class)->all())->firstWhere('id', 'gov-nav-language');
        $this->assertSame('topbar', $item->zone);

        app()->setLocale('bn-BD');
        $html = view('govscope::nav.language')->render();
        $this->assertStringContainsString('action="'.route('gov.locale.switch').'"', $html);
        $this->assertMatchesRegularExpression('/value="bn-BD" lang="bn-BD"[^>]*aria-pressed="true">বাং</', $html);
        $this->assertMatchesRegularExpression('/value="en-US" lang="en-US"[^>]*aria-pressed="false">EN</', $html);
        $this->assertStringContainsString('aria-label="ভাষা"', $html);

        // A regional variant lights up its language.
        app()->setLocale('en-GB');
        $this->assertMatchesRegularExpression('/value="en-US"[^>]*aria-pressed="true"/', view('govscope::nav.language')->render());
    }

    public function test_toggle_hides_with_fewer_than_two_languages(): void
    {
        config(['govstore-navigation.languages' => ['en-US' => 'EN']]);

        $this->assertSame('', trim(view('govscope::nav.language')->render()));
    }
}
