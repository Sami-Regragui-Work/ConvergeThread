<?php

namespace App\Providers;

use App\Models\Duo;
use App\Models\Group;
use App\Models\MergeSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
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
            'user' => User::class,
        ]);

        Route::bind('mergeSession', function (string $value) {
            $user = Auth::user();

            abort_unless($user !== null, 403);

            return MergeSession::query()
                ->forTenant($user->tenant_id)
                ->findOrFail($value);
        });
    }
}
