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
            return false;
        }

        $availableBranches = method_exists($user, 'getAvailableBranchIds')
            ? $user->getAvailableBranchIds()
            : [];

        return in_array($appBranchId, $availableBranches, true);
    }

    /**
     * Просмотр полной финансовой сводки.
     *
     * Права нескольких ролей объединяются:
     * наличие СТО или СПМ не отменяет разрешение МРК,
     * наставника, admin или owner в этом же филиале.
     */
    public function viewFinance(
        \App\Models\User $user,
        \App\Models\Application $application
    ): bool {
        $branchId = (int) $application->our_company;

        if ($branchId <= 0) {
            return false;
        }

        return $user->hasBranchRole(
            [5, 6, 'admin', 'owner'],
            $branchId
        );
    }

    /**
     * Изменение режима НДС конкретного дела.
     *
     * Системный администратор проходит через before().
     * Остальные — по активной роли в филиале дела.
     */
    public function changeFinanceVat(
        User $user,
        Application $application
    ): bool {
        $branchId = (int) $application->our_company;

        return $branchId > 0
            && $user->hasBranchRole(
                [5, 6, 'admin', 'owner'],
                $branchId
            );
    }

    public function changeFinanceApproved(
        \App\Models\User $user,
        \App\Models\Application $application
    ): bool {
        return $this->viewFinance($user, $application);
    }

    /**
     * Только владелец в филиале дела.
     *
     * Системный администратор проходит через существующий before().
     */
    public function changeFinanceCash(
        User $user,
        Application $application
    ): bool {
        $branchId = (int) $application->our_company;

        return $branchId > 0
            && $user->hasBranchRole('owner', $branchId);
    }

    public function manageFinanceSalaryRates(
        User $user,
        Application $application
    ): bool {
        $branchId = (int) $application->our_company;

        return $branchId > 0
            && $user->hasBranchRole('owner', $branchId);
    }
}