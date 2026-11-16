<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    /**
     * Имя и сигнатура консольной команды.
     * Можно передать параметры сразу: php artisan make:admin --email=root@mandarin.local
     */
    protected $signature = 'make:admin 
                            {--name= : Имя администратора}
                            {--email= : Email администратора}
                            {--phone= : Телефон}
                            {--password= : Пароль}';

    /**
     * Описание команды.
     */
    protected $description = 'Создать учетную запись системного администратора';

    /**
     * Выполнение команды.
     */
    public function handle(): int
    {
        $this->info('--- Создание Системного Администратора ---');

        // 1. Получаем или запрашиваем данные
        $name = $this->option('name') ?: $this->ask('Введите ФИО администратора', 'Главный Администратор');

        $email = $this->option('email') ?: $this->ask('Введите Email');
        while (!$this->validateEmail($email)) {
            $this->error('Некорректный или уже занятый Email!');
            $email = $this->ask('Введите корректный Email');
        }

        $phone = $this->option('phone') ?: $this->ask('Введите номер телефона (необязательно)', '+7 (999) 000-00-01');

        $password = $this->option('password');
        if (!$password) {
            $password = $this->secret('Введите пароль (минимум 6 символов)');
            while (strlen($password) < 6) {
                $this->error('Пароль должен содержать минимум 6 символов!');
                $password = $this->secret('Введите пароль еще раз');
            }
        }

        // 2. Находим или создаем группу sys_admin
        $adminGroup = UserGroup::firstOrCreate(
            ['code' => 'sys_admin'],
            [
                'name' => 'Системный администратор',
                'description' => 'Полный технический доступ ко всей системе'
            ]
        );

        // 3. Создаем или обновляем пользователя
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'phone' => $phone,
                'password' => Hash::make($password),
                'user_group_id' => $adminGroup->id,
            ]
        );

        $this->newLine();
        $this->info(' Пользователь успешно создан и наделен правами Системного Администратора!');
        $this->table(
            ['ID', 'ФИО', 'Email', 'Роль / Код', 'Группа ID'],
            [[$user->id, $user->name, $user->email, $adminGroup->name . ' (' . $adminGroup->code . ')', $adminGroup->id]]
        );

        return Command::SUCCESS;
    }

    private function validateEmail(string $email): bool
    {
        $validator = Validator::make(['email' => $email], [
            'email' => 'required|email',
        ]);

        return !$validator->fails();
    }
}