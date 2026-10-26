<?php

namespace Tests\Feature\GovStore;

use App\Models\User;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\GovAccess;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Route;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;

/** The asset tagging abilities matrix, as G1AuthorizationTest does for the core abilities. No database. */
class AssetTaggingAuthorizationTest extends TestCase
{
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

    public static function matrix(): array
    {
        $abilities = ['asset-tags.office.configure', 'asset-tags.company.codelists', 'asset-tags.lookup', 'asset-tags.scope.enable', 'catalog.finance.default'];
        $allowed = [
            'employee' => ['asset-tags.lookup'],
            'storekeeper' => ['asset-tags.lookup', 'catalog.finance.default'],
            'office_admin' => ['asset-tags.office.configure', 'asset-tags.lookup', 'catalog.finance.default'],
            'company_admin' => ['asset-tags.company.codelists', 'asset-tags.lookup', 'catalog.finance.default'],
            'superuser' => $abilities,
        ];
        $rows = [];
        foreach ($allowed as $role => $grants) {
            foreach ($abilities as $ability) {
                $rows["{$role} {$ability}"] = [$role, $ability, in_array($ability, $grants, true)];
            }
        }

        return $rows;
    }

    #[DataProvider('matrix')]
    public function test_role_ability_matrix(string $role, string $ability, bool $allowed): void
    {
        $context = new TenantContext;
        $context->companyId = 20;
        $context->locationId = 10;
        app()->instance(TenantContext::class, $context);
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $user->shouldReceive('isSuperUser')->andReturn($role === 'superuser');
        $roles = array_values(array_filter(['authenticated', $role === 'employee' ? null : $role]));

        $this->assertSame($allowed, app(GovAccess::class)->decide($user, $ability, $roles)->allowed);
    }

    public function test_enablement_is_national_and_every_tagging_route_declares_one_registered_ability(): void
    {
        $this->assertTrue(config('govstore-abilities')['asset-tags.scope.enable']['national']);
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'gov-store/asset-tags') && ! str_contains($route->uri(), 'admin/catalog/finance')
                && ! str_contains($route->uri(), 'finance-code') && ! str_contains($route->uri(), 'finance-items')) {
                continue;
            }
            $abilities = array_values(array_filter($route->gatherMiddleware(), fn ($m) => is_string($m) && str_starts_with($m, 'gov.can:')));
            $this->assertCount(1, $abilities, $route->uri());
            $this->assertArrayHasKey(substr($abilities[0], 8), config('govstore-abilities'), $route->uri());
            $checked++;
        }
        $this->assertGreaterThanOrEqual(15, $checked);
    }
}
