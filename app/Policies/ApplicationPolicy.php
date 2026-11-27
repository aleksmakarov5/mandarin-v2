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
     * Просмотр:
     * - сотрудник имеет активную роль в филиале;
     * - либо дело назначено автосервису пользователя.
     *
     * Дополнительная роль СТО не отменяет права сотрудника.
     */
    public function view(
        User $user,
        Application $application
    ): bool {
        return $this->userHasAccessToBranch($user, $application)
            || $this->applicationAssignedToOwnService(
                $user,
                $application
            );
    }

    /**
     * Сохраняем существующее правило для сотрудников.
     * Само назначение автосервиса не даёт полной правки карточки.
     */
    public function update(
        User $user,
        Application $application
    ): bool {
        return $this->userHasAccessToBranch($user, $application);
    }

    /**
     * Само назначение автосервиса не даёт права удаления.
     *
     * Отдельную матрицу прав удаления сотрудников
     * эта правка не вводит.
     */
    public function delete(
        User $user,
        Application $application
    ): bool {
        return $this->userHasAccessToBranch($user, $application);
    }

    /**
     * Активное назначение сотрудника в филиале дела.
     *
     * Роль 8 — СТО — сама по себе не даёт доступ
     * ко всем делам филиала.
     */
    protected function userHasAccessToBranch(
        User $user,
        Application $application
    ): bool {
        $branchId = (int) (
            $application->our_company
            ?? $application->our_company_id
            ?? $application->id_our_company
            ?? 0
        );

        if ($branchId <= 0) {
            return false;
        }

        return $user->branchRoles()
            ->where('our_company_id', $branchId)
            ->where('is_active', true)
            ->where('user_group_id', '<>', 8)
            ->whereHas('role')
            ->exists();
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
     * Редактирование поступлений и дат ДДС:
     * владелец или бухгалтер в филиале дела.
     *
     * Системный администратор проходит через before().
     */
    public function changeFinanceCash(
        User $user,
        Application $application
    ): bool {
        $attributes = $application->getAttributes();

        $branchId = (int) (
            $attributes['our_company']
            ?? $attributes['our_company_id']
            ?? $attributes['id_our_company']
            ?? 0
        );

        return $branchId > 0
            && $user->hasBranchRole(
                ['owner', 'accountant'],
                $branchId
            );
    }

    public function manageFinanceSalaryRates(
        User $user,
        Application $application
    ): bool {
        $branchId = (int) $application->our_company;

        return $branchId > 0
            && $user->hasBranchRole('owner', $branchId);
    }
    /**
     * Проверяем фактическое назначение автосервиса делу.
     *
     * getServiceId() получает сервис, связанный с пользователем.
     * Одной роли СТО недостаточно.
     */
    protected function applicationAssignedToOwnService(
        User $user,
        Application $application
    ): bool {
        $applicationServiceId = (int) (
            $application->id_service
            ?? $application->service_id
            ?? 0
        );

        if ($applicationServiceId <= 0) {
            return false;
        }

        $userServiceId = $user->getServiceId();

        return $userServiceId !== null
            && $userServiceId > 0
            && $applicationServiceId === $userServiceId;
    }

    /**
     * Полный просмотр вкладки «Основное».
     *
     * Только СТО (8) или только запчастист (9):
     * ограниченный просмотр.
     *
     * Дополнительная активная роль сотрудника
     * в филиале дела даёт полный просмотр.
     */
    public function viewGeneralFull(
        User $user,
        Application $application
    ): bool {
        $attributes = $application->getAttributes();

        $branchId = (int) (
            $attributes['our_company']
            ?? $attributes['our_company_id']
            ?? $attributes['id_our_company']
            ?? 0
        );

        if ($branchId <= 0) {
            return false;
        }

        return $user->branchRoles()
            ->where('our_company_id', $branchId)
            ->where('is_active', true)
            ->whereNotIn('user_group_id', [8, 9])
            ->whereHas('role')
            ->exists();
    }

    /**
     * Редактирование вкладки «Основное».
     *
     * Сохраняем существующие права сотрудников,
     * исключая доступ только по ролям СТО и СПМ.
     */
    public function updateGeneral(
        User $user,
        Application $application
    ): bool {
        return $this->viewGeneralFull($user, $application);
    }

    /**
     * Переписка со страховой компанией.
     *
     * Только СТО или только СПМ — доступа нет.
     * Дополнительная разрешающая роль филиала сохраняет доступ.
     */
    public function viewCorrespondence(
        User $user,
        Application $application
    ): bool {
        return $this->view($user, $application)
            && $this->viewGeneralFull($user, $application);
    }

    /**
     * Отдельная вкладка истории дела.
     * Историю внутри таблиц этим правилом не ограничиваем.
     */
    public function viewHistory(
        User $user,
        Application $application
    ): bool {
        return $this->view($user, $application)
            && $this->viewGeneralFull($user, $application);
    }
    /**
     * Смена основного статуса дела.
     *
     * Разрешено:
     * - системному администратору через before();
     * - директору филиала;
     * - МРК, назначенному в это дело;
     * - наставнику, назначенному в это дело.
     */
    public function changeStatus(
        User $user,
        Application $application
    ): bool {
        if ($this->isAssignedMrkOrMentor($user, $application)) {
            return true;
        }

        return $this->hasOwnerRoleInApplicationBranch(
            $user,
            $application
        );
    }

    /**
     * Смена финансового статуса дела.
     */
    public function changeFinanceStatus(
        User $user,
        Application $application
    ): bool {
        if ($this->isAssignedMrkOrMentor($user, $application)) {
            return true;
        }

        return $this->hasOwnerRoleInApplicationBranch(
            $user,
            $application
        );
    }

    protected function isAssignedMrkOrMentor(
        User $user,
        Application $application
    ): bool {
        $userId = (int) $user->id;
        $attributes = $application->getAttributes();

        $mrkId = (int) (
            $attributes['id_MRK']
            ?? $attributes['mrk_id']
            ?? $attributes['mrk_user_id']
            ?? 0
        );

        $mentorId = (int) (
            $attributes['id_mentor']
            ?? $attributes['mentor_id']
            ?? 0
        );

        return $userId > 0
            && (
                $userId === $mrkId
                || $userId === $mentorId
            );
    }

    protected function hasOwnerRoleInApplicationBranch(
        User $user,
        Application $application
    ): bool {
        $attributes = $application->getAttributes();

        $branchId = (int) (
            $attributes['our_company']
            ?? $attributes['our_company_id']
            ?? $attributes['id_our_company']
            ?? 0
        );

        if ($branchId <= 0) {
            return false;
        }

        return $user->hasBranchRole(
            'owner',
            $branchId
        );
    }

    public function viewDocuments(
        \App\Models\User $user,
        \App\Models\Application $application
    ): bool {
        $branchId = (int) $application->our_company;

        return $branchId > 0
            && $user->hasBranchRole(
                [2, 3, 5, 6, 7],
                $branchId
            );
    }

    public function manageDocuments(
        \App\Models\User $user,
        \App\Models\Application $application
    ): bool {
        $branchId = (int) $application->our_company;

        return $branchId > 0
            && $user->hasBranchRole(
                [2, 5, 6, 7],
                $branchId
            );
    }

}