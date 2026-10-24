<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Services\SettingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingService::class);
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureQueryMacros();
        $this->configureAuthorization();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);
        Carbon::setLocale('id');
        CarbonImmutable::setLocale('id');

        DB::prohibitDestructiveCommands(app()->isProduction());

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(8)->letters()->numbers()
            : null,
        );
    }

    protected function configureQueryMacros(): void
    {
        // Rentang tanggal yang aman untuk kolom DATE di MySQL maupun SQLite.
        Builder::macro('whereDateBetween', function (string $column, $from, $to) {
            /** @var Builder $this */
            return $this->whereDate($column, '>=', $from)->whereDate($column, '<=', $to);
        });
    }

    protected function configureAuthorization(): void
    {
        // Super admin mendapat semua akses.
        Gate::before(function (User $user) {
            return $user->hasRole(Role::SuperAdmin->value) ? true : null;
        });
    }
}
