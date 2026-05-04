<?php

namespace App\Providers;

// use Illuminate\Support\ServiceProvider;

use App\Models\Group;
use App\Models\Invitation;
use App\Models\MergeSession;
use App\Models\Message;
use App\Models\TenantRole;
use App\Policies\GroupPolicy;
use App\Policies\InvitationPolicy;
use App\Policies\MergeSessionPolicy;
use App\Policies\MessagePolicy;
use App\Policies\TenantRolePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as VendorAuthServiceProvider;

class AuthServiceProvider extends VendorAuthServiceProvider
{
    protected $policies = [
        Group::class => GroupPolicy::class,
        Message::class => MessagePolicy::class,
        MergeSession::class => MergeSessionPolicy::class,
        TenantRole::class => TenantRolePolicy::class,
        Invitation::class => InvitationPolicy::class,
    ];

    // /**
    //  * Register services.
    //  */
    // public function register(): void
    // {
    //     //
    // }

    // /**
    //  * Bootstrap services.
    //  */
    // public function boot(): void
    // {
    //     $this->registerPolicies();
    // }
}
