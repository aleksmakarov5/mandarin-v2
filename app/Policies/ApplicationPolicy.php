<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ApplicationPolicy
{
    use HandlesAuthorization;

    /**
     * Системный администратор имеет полный доступ ко всем делам
     */
    public function before(User $user, string $ability): ?bool
    {
        if (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Проверка доступа к просмотру дела
     */
    public function view(User $user, Application $application): bool
    {
        return $this->userHasAccessToBranch($user, $application);
    }

    /**
     * Проверка доступа к обновлению / редактированию дела
     */
    public function update(User $user, Application $application): bool
    {
        return $this->userHasAccessToBranch($user, $application);
    }

    /**
     * Проверка доступа к удалению дела
     */
    public function delete(User $user, Application $application): bool
    {
        return $this->userHasAccessToBranch($user, $application);
    }

    /**
     * Служебный метод проверки: входит ли филиал дела в список доступных филиалов сотрудника
     */
    protected function userHasAccessToBranch(User $user, Application $application): bool
    {
        $appBranchId = (int) ($application->our_company ?? $application->our_company_id ?? $application->id_our_company);

        // Если у дела вообще не указан филиал, доступ можно разрешить или ограничить по городам
        if (!$appBranchId) {
            return true;
        }

        $availableBranches = method_exists($user, 'getAvailableBranchIds')
            ? $user->getAvailableBranchIds()
            : [];

        return in_array($appBranchId, $availableBranches, true);
    }
}