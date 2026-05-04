<?php

namespace App\Providers;

// use Illuminate\Support\ServiceProvider;

use App\Models\Group;
use App\Policies\GroupPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as VendorAuthServiceProvider;

class AuthServiceProvider extends VendorAuthServiceProvider
{
    protected $policies = [
        Group::class => GroupPolicy::class,
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
