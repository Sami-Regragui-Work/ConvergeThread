<?php

namespace App\Providers;

use App\Events\UnreadNotificationsUpdated;
use App\Models\Duo;
use App\Models\Group;
use App\Models\MergeSession;
use App\Models\User;
use App\Support\WorkspaceSync;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
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
        Broadcast::routes(['middleware' => ['web', 'auth']]);

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

        Event::listen(NotificationSent::class, function (NotificationSent $event) {
            $notifiable = $event->notifiable;
            if (! $notifiable instanceof User) {
                return;
            }

            UnreadNotificationsUpdated::dispatch(
                (int) $notifiable->id,
                (int) $notifiable->unreadNotifications()->count(),
            );

            if ($notifiable->tenant_id) {
                WorkspaceSync::bump((int) $notifiable->tenant_id, ['notifications']);
            }
        });
    }
}
