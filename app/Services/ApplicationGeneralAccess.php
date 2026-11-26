<?php

namespace App\Services;

use App\Models\Application;
use Illuminate\Support\Facades\Gate;

class ApplicationGeneralAccess
{
    /**
     * Фильтрация основных данных с учётом прав
     * текущего авторизованного пользователя.
     */
    public function filter(
        Application $application,
        array $data
    ): array {
        // Сам фильтр также проверяет право открытия дела.
        Gate::authorize('view', $application);

        $fullAccess = Gate::allows(
            'viewGeneralFull',
            $application
        );

        $canEdit = Gate::allows(
            'updateGeneral',
            $application
        );

        if (!$fullAccess) {
            $data = $this->limitedData(
                $application,
                $data
            );
        }

        $permissions = is_array($data['permissions'] ?? null)
            ? $data['permissions']
            : [];

        $permissions['general'] = [
            'view_vehicle' => true,
            'view_branch_service_dates' => true,
            'view_responsible_employees' => true,

            'view_client' => $fullAccess,
            'view_passport' => $fullAccess,
            'view_insurance' => $fullAccess,
            'view_internal_note' => $fullAccess,

            'edit' => $canEdit,
        ];

        $permissions['menu'] = [
            'correspondence' => Gate::allows(
                'viewCorrespondence',
                $application
            ),

            'history' => Gate::allows(
                'viewHistory',
                $application
            ),
        ];

        $permissions['status'] = [
            'change_status' => Gate::allows(
                'changeStatus',
                $application
            ),

            'change_finance_status' => Gate::allows(
                'changeFinanceStatus',
                $application
            ),
        ];

        $data['permissions'] = $permissions;

        return $data;
    }

    /**
     * Ограниченный ответ для пользователей без
     * права полного просмотра вкладки «Основное».
     *
     * Используется белый список: остальные поля,
     * включая финансы и сведения клиента, не выдаются.
     */
    private function limitedData(
        Application $application,
        array $data
    ): array {
        $allowedFields = [
            'id',

            // Автомобиль.
            'CarModel',
            'gnz',
            'vin',
            'mnf_year',
            'color',
            'Color',
            'mileage',
            'vehicle_passport',

            // Филиал, город и автосервис.
            'our_company',
            'our_company_id',
            'id_city',
            'id_service',
            'service_id',

            // Сроки.
            'view_date',
            'start_date',
            'sp_date',
            'end_date',

            // Назначенные сотрудники.
            'id_MRK',
            'id_mentor',
            'mentor_id',
            'id_curator',
            'id_spm',
            'spm_id',
            'id_parts_manager',
            'id_lawyer',
        ];

        $result = array_intersect_key(
            $data,
            array_flip($allowedFields)
        );

        /*
         * Получаем именно атрибуты модели.
         * Это исключает конфликт числового our_company
         * с одноимённым ключом сериализованной связи.
         */
        $attributes = $application->getAttributes();

        $branchId = $attributes['our_company']
            ?? $attributes['our_company_id']
            ?? $attributes['id_our_company']
            ?? null;

        $result['id'] = (int) $application->getKey();

        $result['our_company'] = $branchId !== null
            ? (int) $branchId
            : null;

        $result['our_company_id'] = $result['our_company'];

        /*
         * Только назначенные объекты и разрешённые
         * поля — без полной выдачи моделей пользователей.
         */
        $relations = [
            'ourCompany' => [
                'id',
                'name',
            ],
            'city' => [
                'id',
                'name',
            ],
            'service' => [
                'id',
                'name',
            ],
            'mrk' => [
                'id',
                'name',
                'last_name',
                'patronymic',
            ],
            'spm' => [
                'id',
                'name',
                'last_name',
                'patronymic',
            ],
            'mentor' => [
                'id',
                'name',
                'last_name',
                'patronymic',
            ],
            'lawyer' => [
                'id',
                'name',
            ],
        ];

        foreach ($relations as $relation => $fields) {
            if (!method_exists($application, $relation)) {
                continue;
            }

            $application->loadMissing($relation);

            $related = $application->getRelation($relation);

            $responseKey = $relation === 'ourCompany'
                ? 'our_company_rel'
                : $relation;

            $result[$responseKey] = $related
                ? array_intersect_key(
                    $related->attributesToArray(),
                    array_flip($fields)
                )
                : null;
        }

        return $result;
    }
}