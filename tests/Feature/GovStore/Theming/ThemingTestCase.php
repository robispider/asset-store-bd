<?php

namespace Tests\Feature\GovStore\Theming;

use App\Models\User;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\Theming\ThemeManager;
use GovStore\Theming\Themes\ThemeRepository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;

/**
 * Deliberately uses only an in-memory SQLite database (like the other gov-store tests),
 * never the application's database.
 */
abstract class ThemingTestCase extends TestCase
{
    protected const LOCATION = 10;

    protected const COMPANY = 20;

    protected array $tempDirs = [];

    public function createApplication()
    {
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'LOG_CHANNEL' => 'stderr', 'CACHE_DRIVER' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        $app = require __DIR__.'/../../../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'govstore-access.mode' => 'enforce', 'cache.default' => 'array', 'logging.default' => 'null']);
        DB::purge('sqlite');
        Cache::flush();
        foreach (['gov_company_admins' => ['user_id', 'company_id'], 'gov_ict_jurisdictions' => ['user_id'],
            'gov_location_profiles' => ['location_id', 'office_admin_id'], 'gov_office_responsibilities' => ['user_id', 'location_id', 'role_slug']] as $table => $columns) {
            Schema::create($table, function (Blueprint $table) use ($columns) {
                $table->increments('id');
                foreach ($columns as $column) {
                    $table->string($column)->nullable();
                }
                $table->timestamps();
            });
        }
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->integer('location_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('companies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });
        Schema::create('locations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->integer('company_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        (require base_path('packages/gov-store/tenant-scope/src/database/migrations/2026_10_04_000001_create_gov_access_tables.php'))->up();
        (require base_path('packages/gov-store/theming/database/migrations/2026_10_07_000001_create_gs_theme_preferences_table.php'))->up();
        (require base_path('packages/gov-store/theming/database/migrations/2026_10_07_000002_create_gs_theme_assignments_table.php'))->up();

        DB::table('companies')->insert([['id' => self::COMPANY, 'name' => 'Ministry of Health'], ['id' => 21, 'name' => 'Ministry of Education']]);
        DB::table('locations')->insert([['id' => self::LOCATION, 'name' => 'Dhaka District Store', 'company_id' => self::COMPANY], ['id' => 11, 'name' => 'Gazipur Office', 'company_id' => self::COMPANY]]);

        $this->context(self::COMPANY, self::LOCATION);
        app(ThemeManager::class)->forget();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            $this->deleteDir($dir);
        }
        Mockery::close();
        parent::tearDown();
    }

    protected function context(?int $company, ?int $location): TenantContext
    {
        $context = app(TenantContext::class);
        $context->reset();
        $context->isActive = true;
        $context->companyId = $company;
        $context->locationId = $location;
        app()->instance(TenantContext::class, $context);
        app(ThemeManager::class)->forget();

        return $context;
    }

    protected function actor(string $role = 'employee', int $id = 1): User
    {
        if (! DB::table('users')->where('id', $id)->exists()) {
            DB::table('users')->insert(['id' => $id, 'first_name' => 'User', 'last_name' => (string) $id]);
        }
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = $id;
        $user->first_name = 'User';
        $user->shouldReceive('isSuperUser')->andReturn($role === 'superuser');
        $user->shouldReceive('hasAccess')->andReturn(false);
        $user->shouldReceive('getAuthIdentifier')->andReturn($id);
        auth()->setUser($user);
        if (in_array($role, ['storekeeper', 'primary_approver', 'final_approver'])) {
            DB::table('gov_office_responsibilities')->insert(['user_id' => $id, 'location_id' => self::LOCATION, 'role_slug' => $role]);
        }
        if ($role === 'office_admin') {
            DB::table('gov_location_profiles')->insert(['location_id' => self::LOCATION, 'office_admin_id' => $id]);
        }
        if ($role === 'company_admin') {
            DB::table('gov_company_admins')->insert(['company_id' => self::COMPANY, 'user_id' => $id]);
        }
        app(ThemeManager::class)->forget();

        return $user;
    }

    /** Point the theme repository at a directory of fixture themes. */
    protected function useThemesPath(string $path): void
    {
        config(['gs-theme.themes_path' => $path]);
        app()->forgetInstance(ThemeRepository::class);
        app()->singleton(ThemeRepository::class, fn () => new ThemeRepository($path));
        app()->forgetInstance(ThemeManager::class);
        app()->forgetInstance(\GovStore\Theming\Themes\ThemeResolver::class);
    }

    /** Copy the shipped themes into a temp dir so tests can add / break themes safely. */
    protected function copyShippedThemes(): string
    {
        $dir = $this->tempDir();
        foreach (glob(base_path('packages/gov-store/theming/themes/*'), GLOB_ONLYDIR) as $theme) {
            mkdir($dir.'/'.basename($theme));
            foreach (glob($theme.'/*') as $file) {
                copy($file, $dir.'/'.basename($theme).'/'.basename($file));
            }
        }

        return $dir;
    }

    protected function writeTheme(string $dir, string $key, array $manifest, ?array $light = [], ?array $dark = [], ?string $overrides = null, bool $previews = true): void
    {
        @mkdir($dir.'/'.$key, 0777, true);
        $manifest += [
            'key' => $key, 'version' => '1.0.0', 'status' => 'published', 'extends' => 'default',
            'label' => ['en-US' => ucfirst($key), 'bn-BD' => ucfirst($key)],
            'description' => ['en-US' => 'Fixture', 'bn-BD' => 'Fixture'],
        ];
        file_put_contents($dir.'/'.$key.'/theme.json', json_encode($manifest));
        if ($light !== null) {
            file_put_contents($dir.'/'.$key.'/tokens.light.json', json_encode((object) $light));
        }
        if ($dark !== null) {
            file_put_contents($dir.'/'.$key.'/tokens.dark.json', json_encode((object) ($dark ?: ['seed' => ['primary' => ['$type' => 'color', '$value' => '#2A6A93']]])));
        }
        if ($overrides !== null) {
            file_put_contents($dir.'/'.$key.'/overrides.css', $overrides);
        }
        if ($previews) {
            foreach (['light', 'dark'] as $mode) {
                copy(base_path('packages/gov-store/theming/themes/default/preview.'.$mode.'.png'), $dir.'/'.$key.'/preview.'.$mode.'.png');
            }
        }
    }

    protected function tempDir(): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'gs-theme-test-'.bin2hex(random_bytes(5));
        mkdir($dir, 0777, true);
        $this->tempDirs[] = $dir;

        return $dir;
    }

    private function deleteDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
