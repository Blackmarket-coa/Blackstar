<?php

namespace App\Providers;

use App\Models\Driver;
use App\Models\Fleet;
use App\Models\Node;
use App\Models\Vehicle;
use App\Policies\DriverPolicy;
use App\Policies\FleetPolicy;
use App\Policies\NodePolicy;
use App\Policies\VehiclePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Node::class => NodePolicy::class,
        Fleet::class => FleetPolicy::class,
        Vehicle::class => VehiclePolicy::class,
        Driver::class => DriverPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

    }
}
