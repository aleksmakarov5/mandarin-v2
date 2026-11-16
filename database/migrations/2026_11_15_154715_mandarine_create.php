<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Группы и роли пользователей
        Schema::create('user_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 2. Города
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // 3. Наши юридические лица и компании
        Schema::create('our_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('inn')->nullable();
            $table->string('kpp')->nullable();
            $table->string('ogrn')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('director')->nullable();
            $table->string('signature_path')->nullable();
            $table->timestamps();
        });

        Schema::create('legal_entities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('inn')->nullable();
            $table->string('kpp')->nullable();
            $table->text('details')->nullable();
            $table->timestamps();
        });

        // 4. Пользователи
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                $table->foreignId('user_group_id')->nullable()->constrained('user_groups')->nullOnDelete();
                $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
                $table->integer('def_mrk_proc')->nullable()->default(0);
                $table->integer('def_zap_proc')->nullable()->default(0);
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });
        }

        // Связки пользователей с городами и ролями
        Schema::create('user_cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_group_id')->constrained('user_groups')->cascadeOnDelete();
            $table->timestamps();
        });

        // 5. Страховые компании
        Schema::create('insurance_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('manager')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->foreignId('our_company_id')->nullable()->constrained('our_companies')->nullOnDelete();
            $table->foreignId('legal_entity_id')->nullable()->constrained('legal_entities')->nullOnDelete();
            $table->string('theme')->nullable();
            $table->timestamps();
        });

        // 6. Сервисы (СТО) и Юристы
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('manager')->nullable();
            $table->string('theme')->nullable();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('lawyers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('theme')->nullable();
            $table->timestamps();
        });

        // 7. Статусы заявок, документов, запчастей и финансов
        Schema::create('application_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('user_group_id')->nullable()->constrained('user_groups')->nullOnDelete();
            $table->string('color')->default('secondary');
            $table->string('type')->nullable();
            $table->timestamps();
        });

        Schema::create('doc_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color')->default('secondary');
            $table->timestamps();
        });

        Schema::create('sparepart_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color')->default('secondary');
            $table->timestamps();
        });

        Schema::create('finance_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color')->default('secondary');
            $table->timestamps();
        });

        // 8. Заявки (Applications)
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_company_id')->nullable()->constrained('insurance_companies')->nullOnDelete();
            $table->string('insurance_app_number')->index();
            $table->foreignId('status_id')->default(1)->constrained('application_statuses');
            $table->foreignId('finance_status_id')->nullable()->constrained('finance_statuses')->nullOnDelete();
            $table->foreignId('our_company_id')->nullable()->constrained('our_companies')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('lawyer_id')->nullable()->constrained('lawyers')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();

            // Данные авто
            $table->string('car_model')->nullable();
            $table->string('gnz')->nullable()->index();
            $table->string('vin')->nullable()->index();
            $table->string('color')->nullable();
            $table->unsignedInteger('mileage')->nullable();
            $table->string('vehicle_passport')->nullable();
            $table->date('dtp_date')->nullable();
            $table->date('ic_answer_date')->nullable();
            $table->date('sp_date')->nullable();

            // Клиент
            $table->string('client_name')->nullable();
            $table->string('client_phone')->nullable();
            $table->string('client_passport')->nullable();

            // Ответственные
            $table->foreignId('mrk_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('spm_id')->nullable()->constrained('users')->nullOnDelete();

            // Финансовые проценты и НДС
            $table->integer('mrk_proc')->nullable()->default(0);
            $table->integer('zap_proc')->nullable()->default(0);
            $table->boolean('has_nds')->default(false);

            $table->softDeletes();
            $table->timestamps();
        });

        // 9. Сообщения, почта (IMAP) и Вложения
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->nullable()->constrained('applications')->nullOnDelete();
            $table->string('subject')->nullable();
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->text('headers')->nullable();
            $table->boolean('is_parsed')->default(false);
            $table->timestamps();
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->nullable()->constrained('applications')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignId('doc_status_id')->nullable()->constrained('doc_statuses')->nullOnDelete();
            $table->string('file_name');
            $table->string('location'); // S3 key
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->boolean('is_parsed')->default(false);
            $table->timestamps();
        });

        // 10. Запчасти (Spare Parts)
        Schema::create('spare_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('sparepart_status_id')->nullable()->constrained('sparepart_statuses')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('part_number')->nullable();
            $table->string('order_number')->nullable();
            $table->decimal('price_ic_full', 12, 2)->default(0);
            $table->decimal('price_ic_half', 12, 2)->default(0);
            $table->decimal('price_original', 12, 2)->nullable();
            $table->decimal('price_analogue', 12, 2)->nullable();
            $table->decimal('price_used', 12, 2)->nullable();
            $table->tinyInteger('selected_price')->nullable();
            $table->string('selected_mrk')->nullable();
            $table->string('status')->nullable();
            $table->date('date_price')->nullable();
            $table->date('date_wait')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        // 11. Группы работ, Работы и Плановые работы
        Schema::create('work_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('work_group_id')->nullable()->constrained('work_groups')->nullOnDelete();
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('count_hours', 8, 2)->default(0);
            $table->decimal('summ', 12, 2)->default(0);
            $table->decimal('price_service', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('plan_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('count_hours', 8, 2)->default(0);
            $table->decimal('summ', 12, 2)->default(0);
            $table->timestamps();
        });

        // 12. Дефектовка (Troubleshooting)
        Schema::create('troubleshootings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('part_name');
            $table->string('work_type');
            $table->text('expert_opinion')->nullable();
            $table->text('conclusion')->nullable();
            $table->timestamps();
        });

        // 13. Калькуляции / Сметы (Estimates)
        Schema::create('estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->decimal('sum_works', 12, 2)->default(0);
            $table->decimal('sum_parts', 12, 2)->default(0);
            $table->decimal('sum_total', 12, 2)->default(0);
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        // 14. Складской учет (Warehouses, Inventory, Counterparties)
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('counterparties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('inn')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('name');
            $table->string('part_number')->nullable();
            $table->integer('quantity')->default(0);
            $table->decimal('purchase_price', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('moving_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('applications')->nullOnDelete();
            $table->integer('quantity');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 15. Согласования, Отказы и Варианты (Failures, Trus)
        Schema::create('failure_vars', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('failures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('failure_var_id')->nullable()->constrained('failure_vars')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('true_vars', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('trus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('true_var_id')->nullable()->constrained('true_vars')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        // 16. История изменений (Audit Log)
        Schema::create('histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('status_id')->nullable()->constrained('application_statuses')->nullOnDelete();
            $table->foreignId('finance_status_id')->nullable()->constrained('finance_statuses')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        // 17. Внутренний чат и вложения чата
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->timestamps();
        });

        Schema::create('chat_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained('chats')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('location');
            $table->timestamps();
        });

        Schema::create('chat_recs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained('chats')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });

        // 18. Уведомления и шаблоны текстов
        Schema::create('text_notifies', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('notifies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('applications')->nullOnDelete();
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });

        // 19. Книги / Справочники (Books)
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // 20. Фильтры пользователей
        Schema::create('user_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->json('filter_data')->nullable();
            $table->timestamps();
        });

        Schema::create('period_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
        });

        Schema::create('mrk_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->json('filter_data')->nullable();
            $table->timestamps();
        });

        // 21. Таблица контактов формы
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('subject');
            $table->text('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('mrk_filters');
        Schema::dropIfExists('period_filters');
        Schema::dropIfExists('user_filters');
        Schema::dropIfExists('books');
        Schema::dropIfExists('notifies');
        Schema::dropIfExists('text_notifies');
        Schema::dropIfExists('chat_recs');
        Schema::dropIfExists('chat_attachments');
        Schema::dropIfExists('chats');
        Schema::dropIfExists('histories');
        Schema::dropIfExists('trus');
        Schema::dropIfExists('true_vars');
        Schema::dropIfExists('failures');
        Schema::dropIfExists('failure_vars');
        Schema::dropIfExists('moving_inventory_items');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('counterparties');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('estimates');
        Schema::dropIfExists('troubleshootings');
        Schema::dropIfExists('plan_works');
        Schema::dropIfExists('works');
        Schema::dropIfExists('work_groups');
        Schema::dropIfExists('spare_parts');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('finance_statuses');
        Schema::dropIfExists('sparepart_statuses');
        Schema::dropIfExists('doc_statuses');
        Schema::dropIfExists('application_statuses');
        Schema::dropIfExists('lawyers');
        Schema::dropIfExists('services');
        Schema::dropIfExists('insurance_companies');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('user_cities');
        Schema::dropIfExists('users');
        Schema::dropIfExists('legal_entities');
        Schema::dropIfExists('our_companies');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('user_groups');
    }
};