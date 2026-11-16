<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdminUser extends Command
{
    protected $signature = 'make:admin 
                            {--last_name= : Фамилия}
                            {--name= : Имя}
                            {--patronymic= : Отчество}
                            {--email= : Email}
                            {--phone= : Телефон}
                            {--password= : Пароль}';

    protected $description = 'Создать учетную запись системного администратора';

    public function handle(): int
    {
        $this->info('--- Создание Системного Администратора ---');

        $lastName = $this->option('last_name') ?: $this->ask('Введите Фамилию', 'Администраторов');
        $name = $this->option('name') ?: $this->ask('Введите Имя', 'Администратор');
        $patronymic = $this->option('patronymic') ?: $this->ask('Введите Отчество (необязательно)', 'Главный');

        $email = $this->option('email') ?: $this->ask('Введите Email');
        while (!$this->validateEmail($email)) {
            $this->error('Некорректный Email!');
            $email = $this->ask('Введите корректный Email');
        }

        $phone = $this->option('phone') ?: $this->ask('Введите номер телефона', '+7 (999) 000-00-01');

        $password = $this->option('password');
        if (!$password) {
            $password = $this->secret('Введите пароль (мин. 8 знаков, заглавная, строчная, цифра, спецсимвол)');
            while (!$this->validatePassword($password)) {
                $this->error('Пароль не соответствует политике: минимум 8 символов, заглавная и строчная буквы, цифра и спецсимвол!');
                $password = $this->secret('Попробуйте еще раз');
            }
        }

        $adminGroup = UserGroup::firstOrCreate(
            ['code' => 'sys_admin'],
            [
                'name' => 'Системный администратор',
                'description' => 'Полный технический доступ ко всей системе'
            ]
        );

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'last_name' => $lastName,
                'name' => $name,
                'patronymic' => $patronymic,
                'phone' => $phone,
                'password' => Hash::make($password),
                'user_group_id' => $adminGroup->id,
            ]
        );

        $this->newLine();
        $this->info('Системный администратор успешно создан/обновлен!');
        $this->table(
            ['ID', 'ФИО', 'Email', 'Роль'],
            [[$user->id, $user->full_name, $user->email, $adminGroup->name]]
        );

        return Command::SUCCESS;
    }

    private function validateEmail(string $email): bool
    {
        return !Validator::make(['email' => $email], ['email' => 'required|email'])->fails();
    }

    private function validatePassword(?string $password): bool
    {
        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', Password::defaults()]]
        );

        return !$validator->fails();
    }
}