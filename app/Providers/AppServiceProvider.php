<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Models\Application;
use App\Policies\ApplicationPolicy;


class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {

        // Системный администратор видит всё
        Gate::before(function (User $user, string $ability) {
            if ($user->isSystemAdmin()) {
                return true;
            }
        });

        // Проверка роли СТО (группа 8 или привязанный Service) для дела
        Gate::define('is-sto', function (User $user, ?Application $application = null) {
            $branchId = $application ? (int) ($application->our_company ?? $application->our_company_id ?? 0) : null;
            return $user->isSto($branchId);
        });

        // Право видеть закрытые коммерческие цены (СК, ПЗН, закупка)
        Gate::define('view-sensitive-prices', function (User $user, ?Application $application = null) {
            $branchId = $application ? (int) ($application->our_company ?? $application->our_company_id ?? 0) : null;

            // Сервису СТО просмотр закрыт
            if ($user->isSto($branchId)) {
                return false;
            }

            return true;
        });

        // Право управлять сметой (добавлять/удалять запчасти)
        Gate::define('manage-spare-parts', function (User $user, ?Application $application = null) {
            $branchId = $application ? (int) ($application->our_company ?? $application->our_company_id ?? 0) : null;
            return !$user->isSto($branchId);
        });
        Gate::define(
            'viewRegistryTab',
            function (User $user, string $tab): bool {
                if ($user->isSystemAdmin()) {
                    return true;
                }

                return match ($tab) {
                    'requests',
                    'applications' => in_array(
                            $user->menuMode(),
                            ['backoffice', 'spm', 'service'],
                            true
                        ),

                    'acceptance',
                    'archive',
                    'trash',
                    'mail',
                    'finance' => in_array(
                            $user->menuMode(),
                            ['backoffice'],
                            true
                        ),

                    default => false,
                };
            }
        );
        Gate::policy(Application::class, ApplicationPolicy::class);
        // Корпоративная политика паролей по умолчанию
        Password::defaults(function () {
            return Password::min(8)
                ->mixedCase()   // Заглавные и строчные буквы
                ->numbers()     // Минимум одна цифра
                ->symbols();    // Минимум один спецсимвол (!@#$%^&*...)
        });
    }
}