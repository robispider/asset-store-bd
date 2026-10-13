<?php

namespace GovStore\Committee\Providers;

use GovStore\Committee\Contracts;
use GovStore\Committee\Services;
use GovStore\Committee\Scopes\Types\CoreScopeResolver;
use GovStore\OfficeMembership\Services\ClearanceEngine;
use GovStore\TenantScope\Navigation\MenuRegistry;
use GovStore\Theming\Facades\GsTheme;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class CommitteeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/committee.php','committee');
        $this->app->singleton(Services\Registries::class);
        foreach ([Contracts\PurposeRegistry::class=>Services\Purposes::class,Contracts\ScopeTypeRegistry::class=>Services\ScopeTypes::class,
            Contracts\CommitteeTabRegistry::class=>Services\Tabs::class,Contracts\PostHolderDirectory::class=>Services\NullPostHolderDirectory::class,
            Contracts\CommitteeResolver::class=>Services\CommitteeResolverService::class,Contracts\CommitteeQueries::class=>Services\CommitteeQueryService::class,
            Contracts\RosterSnapshotProvider::class=>Services\RosterSnapshotService::class,
            \GovStore\Committee\Repositories\CommitteeRepository::class=>\GovStore\Committee\Repositories\EloquentCommitteeRepository::class] as $contract=>$implementation) {
            $this->app->singleton($contract,$implementation);
        }
        $this->app->afterResolving(ClearanceEngine::class,fn ($engine) => $engine->registerRule(new \GovStore\Committee\Clearance\NoUnplannedSeatVacancyRule));
    }
    public function boot(): void
    {
        \Illuminate\Support\Facades\Event::listen(\GovStore\Committee\Events\CommitteeRecorded::class,\GovStore\Committee\Listeners\WriteNativeAuditLog::class);
        config(['filesystems.disks.committee_private'=>['driver'=>'local','root'=>storage_path('app/private/committee'),'visibility'=>'private','throw'=>true]]);
        $this->loadMigrationsFrom(__DIR__.'/../Database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang','committee');
        $this->loadViewsFrom(__DIR__.'/../resources/views','committee');
        if ($this->app->bound('gs.theme')) {
            GsTheme::assets()->css('committee', __DIR__.'/../resources/css/committee.css');
        }
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $registry = $this->app->make(Contracts\ScopeTypeRegistry::class);
        foreach (['office','store','ministry'] as $key) { $registry->register($key,new CoreScopeResolver($key)); }
        $menus = $this->app->make(MenuRegistry::class);
        foreach ([
            ['committee', 'gov-committees', 10, 'nav_overview', 'committee.dashboard', 'fas fa-users', ['committee.show', 'committee.history', 'committee.print', 'committee.reconstitute', 'committee.new', 'committee.search']],
            ['committee-registry', 'gov-committees', 20, 'nav_register', 'committee.registry', 'fas fa-list-alt', []],
            ['committee-transfers', 'gov-committees', 30, 'nav_transfers', 'committee.transfers', 'fas fa-exchange-alt', []],
            ['committee-mine', 'gov-workspace', 30, 'mine', 'committee.mine', 'fas fa-user-friends', []],
            ['committee-types', 'gov-rules', 30, 'nav_types', 'committee.types', 'fas fa-shapes', []],
            ['committee-purposes', 'gov-rules', 40, 'nav_purposes', 'committee.purposes', 'fas fa-bullseye', []],
        ] as [$id, $group, $order, $label, $route, $icon, $match]) {
            $menus->register(['id' => $id, 'group' => $group, 'order' => $order, 'label' => 'committee::committee.'.$label,
                'icon' => $icon, 'route' => $route, 'match' => $match]);
        }
        if ($this->app->runningInConsole()) {
            $this->commands([\GovStore\Committee\Console\Commands\CommitteeHealthCommand::class,\GovStore\Committee\Console\Commands\CommitteeExpireCommand::class,\GovStore\Committee\Console\Commands\CommitteeLedgerVerifyCommand::class]);
            $this->app->afterResolving(Schedule::class,function ($schedule) {
                $schedule->command('committee:expire')->dailyAt('00:05')->timezone('Asia/Dhaka')->withoutOverlapping();
                $schedule->command('committee:health')->dailyAt('01:00')->timezone('Asia/Dhaka')->withoutOverlapping();
            });
        }
    }
}
