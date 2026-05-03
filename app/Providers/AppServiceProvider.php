<?php

namespace App\Providers;

use App\Models\Duo;
use App\Models\Group;
use App\Models\MergeSession;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'group' => Group::class,
            'duo' => Duo::class,
            'merge' => MergeSession::class,
        ]);
    }
}
