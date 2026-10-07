<?php

namespace GovStore\Committee\Providers;

use GovStore\Committee\Contracts;
use GovStore\Committee\Services;
use GovStore\Committee\Scopes\Types\CoreScopeResolver;
use GovStore\OfficeMembership\Services\ClearanceEngine;
use GovStore\TenantScope\Navigation\MenuRegistry;
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
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $registry = $this->app->make(Contracts\ScopeTypeRegistry::class);
        foreach (['office','store','ministry'] as $key) { $registry->register($key,new CoreScopeResolver($key)); }
        $menus = $this->app->make(MenuRegistry::class);
        foreach ([
            ['committee','committee.dashboard','title','committee.view',40],
            ['committee-registry','committee.registry','registry','committee.view',41],
            ['committee-transfers','committee.transfers','transfers','committee.manage',42],
            ['committee-types','committee.types','types','committee.types.view',43],
            ['committee-purposes','committee.purposes','purposes','committee.purposes.view',44],
            ['committee-mine','committee.mine','mine','committee.self',45],
        ] as [$id,$route,$label,$ability,$order]) {
            $menus->register(['id'=>$id,'parent'=>'gov-store','title'=>__('committee::committee.'.$label),'icon'=>'fas fa-users fa-fw','route'=>$route,'permission'=>$ability,'order'=>$order]);
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
