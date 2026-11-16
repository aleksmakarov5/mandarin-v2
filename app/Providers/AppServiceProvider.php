<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Корпоративная политика паролей по умолчанию
        Password::defaults(function () {
            return Password::min(8)
                ->mixedCase()   // Заглавные и строчные буквы
                ->numbers()     // Минимум одна цифра
                ->symbols();    // Минимум один спецсимвол (!@#$%^&*...)
        });
    }
}