<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Services\SettingService;
use App\Services\WhatsApp\HttpWhatsAppGateway;
use App\Services\WhatsApp\LogWhatsAppGateway;
use App\Services\WhatsApp\WhatsAppGateway;
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

        $this->app->singleton(WhatsAppGateway::class, fn () => match (config('services.whatsapp.driver')) {
            'http' => new HttpWhatsAppGateway((string) config('services.whatsapp.url'), (string) config('services.whatsapp.key')),
            default => new LogWhatsAppGateway,
        });
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
        // Super admin mendapat semua akses, kecuali kemampuan yang sengaja dibatasi untuknya.
        Gate::before(function (User $user, string $ability) {
            if ($ability === 'updateLoginEmail') {
                return null;
            }

            return $user->hasRole(Role::SuperAdmin->value) ? true : null;
        });
    }
}
