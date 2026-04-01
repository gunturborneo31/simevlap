<?php

namespace App\Providers;

use App\Models\Kepmen;
use App\Models\Opd;
use App\Models\Program;
use App\Models\Realisasi;
use App\Models\User;
use App\Policies\KepmenPolicy;
use App\Policies\OpdPolicy;
use App\Policies\ProgramPolicy;
use App\Policies\RealisasiPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        Gate::policy(Program::class, ProgramPolicy::class);
        Gate::policy(Realisasi::class, RealisasiPolicy::class);
        Gate::policy(Opd::class, OpdPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Kepmen::class, KepmenPolicy::class);
    }
}
