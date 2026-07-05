<?php

namespace App\Providers;

use App\Models\Ambassade;
use App\Models\Anomalie;
use App\Models\Lot;
use App\Models\Passeport;
use App\Models\Pays;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Transporteur;
use App\Models\User;
use App\Policies\AmbassadePolicy;
use App\Policies\AnomaliePolicy;
use App\Policies\LotPolicy;
use App\Policies\PasseportPolicy;
use App\Policies\PaysPolicy;
use App\Policies\TransporteurPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        Lot::class         => LotPolicy::class,
        Passeport::class   => PasseportPolicy::class,
        User::class        => UserPolicy::class,
        Anomalie::class    => AnomaliePolicy::class,
        Ambassade::class   => AmbassadePolicy::class,
        Transporteur::class=> TransporteurPolicy::class,
        Pays::class        => PaysPolicy::class,
    ];

    public function register(): void {}

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
