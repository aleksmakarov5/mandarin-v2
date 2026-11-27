<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * Стабильные коды новой CRM.
         *
         * Это не импорт старого doc_statuses.
         * legacy_doc_status_id намеренно не заполняем.
         *
         * category определяет расположение в интерфейсе.
         * code определяет бизнес-назначение документа.
         */
        $categories = [
            'incoming' => [
                ['insurance_direction', 'Направление на ремонт'],
                ['insurance_calculation', 'Калькуляция страховой компании'],
                ['insurance_approval', 'Согласование страховой компании'],
                ['incoming_other', 'Прочие входящие документы'],
            ],

            'client' => [
                ['client_contract', 'Договор с клиентом'],
                ['client_addendum', 'Дополнительное соглашение'],
                ['client_completed_works_act', 'Акт выполненных работ для клиента'],
                ['client_repair_agreement', 'Соглашение о ремонте'],
            ],

            'vehicle_movement' => [
                ['vehicle_acceptance_act', 'Акт приёма автомобиля'],
                ['initial_inspection_act', 'Акт первичного осмотра'],
                ['hidden_damage_act', 'Акт дефектовки / скрытых повреждений'],
                ['repair_statement', 'Ремонтная ведомость'],
                ['vehicle_release_act', 'Акт выдачи автомобиля'],
            ],

            'photo_archive' => [
                ['acceptance_photos', 'Фото приёмки'],
                ['defect_photos', 'Фото дефектовки'],
                ['initial_inspection_photos', 'Фото первичного осмотра'],
                ['release_photos', 'Фото выдачи'],
            ],

            'closing' => [
                ['outgoing_invoice', 'Исходящий счёт'],
                ['outgoing_completed_works_act', 'Исходящий акт выполненных работ'],
                ['outgoing_upd', 'Исходящий УПД'],
            ],

            'supporting' => [
                ['supporting_receipt', 'Чеки'],
                ['supplier_invoice', 'Входящие счета'],
                ['supplier_completed_works_act', 'Входящие акты'],
                ['supplier_upd', 'Входящие УПД'],
                ['supporting_other', 'Прочие подтверждающие документы'],
            ],
        ];

        $created = 0;

        DB::transaction(function () use ($categories, &$created): void {
            foreach ($categories as $category => $types) {
                foreach ($types as $index => [$code, $name]) {
                    $isPhoto = $category === 'photo_archive';

                    /*
                     * firstOrCreate:
                     * повторный запуск не создаёт дубли
                     * и не затирает изменённые настройки.
                     */
                    $type = DocumentType::firstOrCreate(
                        [
                            'code' => $code,
                        ],
                        [
                            'name' => $name,
                            'category' => $category,

                            /*
                             * Фото принимаем отдельными файлами.
                             * ZIP будет производным файлом
                             * соответствующего комплекта фотографий.
                             */
                            'document_kind' => $isPhoto
                                ? 'photo'
                                : 'file',

                            'is_photo' => $isPhoto,
                            'is_archive' => $isPhoto,

                            /*
                             * Генератор шаблонов пока не подключён.
                             * Не показываем неработающие действия.
                             */
                            'is_generated' => false,
                            'allows_generation' => false,

                            'allows_multiple' => true,
                            'allows_upload' => true,

                            /*
                             * Безопасное исходное значение.
                             * Доступ сервиса настроим явно
                             * в документной policy.
                             */
                            'default_sto_view' => false,

                            /*
                             * Загрузка файла сама по себе
                             * пока не меняет статус автомобиля.
                             */
                            'workflow_code' => null,

                            'is_active' => true,
                            'sort_order' => ($index + 1) * 10,
                        ]
                    );

                    if ($type->wasRecentlyCreated) {
                        $created++;
                    }
                }
            }
        });

        $this->command?->info(
            "Типы документов подготовлены. Добавлено: {$created}."
        );
    }
}