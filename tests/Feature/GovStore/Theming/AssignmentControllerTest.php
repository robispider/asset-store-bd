<?php

namespace Tests\Feature\GovStore\Theming;

use GovStore\Theming\Http\Controllers\AssignmentController;
use GovStore\Theming\Models\ThemeAssignment;
use GovStore\Theming\ThemeManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AssignmentControllerTest extends ThemingTestCase
{
    private function putAssignment(string $scope, ?int $id, array $data): \Symfony\Component\HttpFoundation\Response
    {
        $request = Request::create('/gov/appearance/assignments/'.$scope.($id ? '/'.$id : ''), 'PUT', $data);
        $request->headers->set('Accept', 'application/json');
        $request->setLaravelSession(app('session')->driver('array'));
        $request->setUserResolver(fn () => auth()->user());
        app()->instance('request', $request);

        return app(AssignmentController::class)->update($request, $scope, $id);
    }

    private function index(array $query = [])
    {
        $request = Request::create('/gov/appearance/assignments', 'GET', $query);
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => auth()->user());
        app()->instance('request', $request);

        return app(AssignmentController::class)->index($request);
    }

    public function test_office_admin_sets_own_working_office_only(): void
    {
        $this->actor('office_admin');
        $this->assertSame(302, $this->putAssignment('office', self::LOCATION, ['theme' => 'digital-blue', 'enforced' => '1'])->getStatusCode());
        $this->assertDatabaseHas('gs_theme_assignments', ['scope_type' => 'office', 'scope_id' => self::LOCATION, 'theme' => 'digital-blue', 'enforced' => 1, 'updated_by' => 1]);

        $this->assertSame(403, $this->putAssignment('office', 11, ['theme' => 'digital-blue'])->getStatusCode());
        $this->assertSame(403, $this->putAssignment('company', self::COMPANY, ['theme' => 'digital-blue'])->getStatusCode());
        $this->assertSame(403, $this->putAssignment('organization', null, ['theme' => 'digital-blue'])->getStatusCode());
        $this->assertSame(1, ThemeAssignment::count());
    }

    public function test_company_admin_sets_own_company_only(): void
    {
        $this->actor('company_admin');
        $this->assertSame(302, $this->putAssignment('company', self::COMPANY, ['theme' => 'executive-neutral'])->getStatusCode());
        $this->assertSame(403, $this->putAssignment('company', 21, ['theme' => 'executive-neutral'])->getStatusCode());
        $this->assertFalse((bool) ThemeAssignment::where('scope_type', 'company')->value('enforced'));
    }

    public function test_superuser_any_scope_and_organisation_is_superuser_only(): void
    {
        $this->actor('superuser');
        $this->assertSame(302, $this->putAssignment('organization', null, ['theme' => 'default', 'enforced' => '1'])->getStatusCode());
        $this->assertSame(302, $this->putAssignment('company', 21, ['theme' => 'digital-blue'])->getStatusCode());
        $this->assertSame(302, $this->putAssignment('office', 11, ['theme' => 'executive-neutral'])->getStatusCode());
        $this->assertSame(3, ThemeAssignment::count());
        $this->assertNull(ThemeAssignment::where('scope_type', 'organization')->value('scope_id'));

        // Updating keeps one row per scope; clearing removes it.
        $this->putAssignment('organization', null, ['theme' => 'digital-blue']);
        $this->assertSame(1, ThemeAssignment::where('scope_type', 'organization')->count());
        $this->putAssignment('office', 11, ['theme' => '']);
        $this->assertSame(0, ThemeAssignment::where('scope_type', 'office')->count());
        $this->assertSame(404, rescue(fn () => $this->putAssignment('office', 999, ['theme' => 'default'])->getStatusCode(), fn ($e) => $e->getStatusCode()));
    }

    public function test_only_published_themes_can_be_chosen(): void
    {
        $this->actor('superuser');
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->putAssignment('organization', null, ['theme' => 'not-shipped']);
    }

    public function test_cache_is_busted_and_change_is_logged(): void
    {
        $user = $this->actor('office_admin');
        $manager = app(ThemeManager::class);
        $this->assertSame('institutional-green', $manager->key());
        Log::spy();
        $this->putAssignment('office', self::LOCATION, ['theme' => 'executive-neutral', 'enforced' => '1']);
        $manager->forget();
        $this->assertSame('executive-neutral', $manager->key());
        Log::shouldHaveReceived('info')->withArgs(fn ($message, $context) => $message === 'gs-theme: assignment changed'
            && $context['actor_id'] === $user->id && $context['scope_type'] === 'office' && $context['new_theme'] === 'executive-neutral'
            && $context['old_theme'] === null && $context['enforced'] === true)->once();
    }

    public function test_index_shows_only_manageable_scopes_with_inheritance(): void
    {
        $this->actor('superuser', 2);
        $this->putAssignment('company', self::COMPANY, ['theme' => 'digital-blue']);
        $this->actor('office_admin');
        $view = $this->index();
        $cards = $view->getData()['cards'];
        $this->assertSame(['office'], array_keys($cards));
        $this->assertSame(['key' => 'digital-blue', 'source' => 'company', 'own' => false], $cards['office']['effective']);
        $this->assertTrue($cards['office']['editable']);

        $this->actor('employee', 3);
        $this->assertSame(403, $this->index()->getStatusCode());
    }
}
