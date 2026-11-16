<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // Страховая и номера
            if (!Schema::hasColumn('applications', 'id_insuranceCompany')) {
                $table->unsignedBigInteger('id_insuranceCompany')->nullable()->after('id');
            }
            if (!Schema::hasColumn('applications', 'numberInsuranceCompanyApplication')) {
                $table->string('numberInsuranceCompanyApplication', 60)->nullable()->after('id_insuranceCompany');
            }
            if (!Schema::hasColumn('applications', 'ub_number')) {
                $table->string('ub_number', 60)->nullable();
            }
            if (!Schema::hasColumn('applications', 'ub_type')) {
                $table->tinyInteger('ub_type')->default(0);
            }

            // Автомобиль
            if (!Schema::hasColumn('applications', 'CarModel')) {
                $table->string('CarModel', 255)->default('');
            }
            if (!Schema::hasColumn('applications', 'gnz')) {
                $table->string('gnz', 50)->nullable();
            }
            if (!Schema::hasColumn('applications', 'vin')) {
                $table->string('vin', 50)->nullable();
            }
            if (!Schema::hasColumn('applications', 'mnf_year')) {
                $table->string('mnf_year', 20)->nullable();
            }
            if (!Schema::hasColumn('applications', 'Color')) {
                $table->string('Color', 100)->nullable();
            }
            if (!Schema::hasColumn('applications', 'mileage')) {
                $table->unsignedBigInteger('mileage')->nullable();
            }
            if (!Schema::hasColumn('applications', 'vehicle_passport')) {
                $table->string('vehicle_passport', 100)->nullable();
            }

            // Клиент
            if (!Schema::hasColumn('applications', 'ClientName')) {
                $table->string('ClientName', 255)->nullable();
            }
            if (!Schema::hasColumn('applications', 'ClientPhone')) {
                $table->string('ClientPhone', 50)->nullable();
            }
            if (!Schema::hasColumn('applications', 'birthday')) {
                $table->date('birthday')->nullable();
            }
            if (!Schema::hasColumn('applications', 'gender')) {
                $table->tinyInteger('gender')->nullable();
            }
            if (!Schema::hasColumn('applications', 'pred')) {
                $table->string('pred', 255)->nullable();
            }
            if (!Schema::hasColumn('applications', 'pred_phone')) {
                $table->string('pred_phone', 50)->nullable();
            }

            // Ответственные лица и привязка
            if (!Schema::hasColumn('applications', 'our_company')) {
                $table->bigInteger('our_company')->default(1);
            }
            if (!Schema::hasColumn('applications', 'id_city')) {
                $table->bigInteger('id_city')->default(0);
            }
            if (!Schema::hasColumn('applications', 'id_status')) {
                $table->unsignedBigInteger('id_status')->default(1);
            }
            if (!Schema::hasColumn('applications', 'id_MRK')) {
                $table->unsignedBigInteger('id_MRK')->nullable();
            }
            if (!Schema::hasColumn('applications', 'id_spm')) {
                $table->unsignedBigInteger('id_spm')->nullable();
            }
            if (!Schema::hasColumn('applications', 'id_lawyer')) {
                $table->unsignedBigInteger('id_lawyer')->nullable();
            }
            if (!Schema::hasColumn('applications', 'id_service')) {
                $table->unsignedBigInteger('id_service')->nullable();
            }

            // Даты и процесс
            if (!Schema::hasColumn('applications', 'DTPdate')) {
                $table->date('DTPdate')->nullable();
            }
            if (!Schema::hasColumn('applications', 'napr_date')) {
                $table->date('napr_date')->nullable();
            }
            if (!Schema::hasColumn('applications', 'start_date')) {
                $table->date('start_date')->nullable();
            }
            if (!Schema::hasColumn('applications', 'end_date')) {
                $table->date('end_date')->nullable();
            }
            if (!Schema::hasColumn('applications', 'sp_date')) {
                $table->date('sp_date')->nullable();
            }
            if (!Schema::hasColumn('applications', 'view_date')) {
                $table->dateTime('view_date')->nullable();
            }
            if (!Schema::hasColumn('applications', 'check_date')) {
                $table->timestamp('check_date')->nullable();
            }

            // Комментарии и договор
            if (!Schema::hasColumn('applications', 'dogovor')) {
                $table->string('dogovor', 100)->nullable();
            }
            if (!Schema::hasColumn('applications', 'comment_true')) {
                $table->string('comment_true', 255)->nullable();
            }
            if (!Schema::hasColumn('applications', 'comment_false')) {
                $table->string('comment_false', 255)->nullable();
            }

            // Финансы
            if (!Schema::hasColumn('applications', 'finance_statuses_id')) {
                $table->unsignedBigInteger('finance_statuses_id')->default(1);
            }
            if (!Schema::hasColumn('applications', 'nds')) {
                $table->tinyInteger('nds')->default(1);
            }
            if (!Schema::hasColumn('applications', 'ic_pay_date')) {
                $table->date('ic_pay_date')->nullable();
            }
            if (!Schema::hasColumn('applications', 'sto_pay_date')) {
                $table->date('sto_pay_date')->nullable();
            }
            if (!Schema::hasColumn('applications', 'zp_date')) {
                $table->date('zp_date')->nullable();
            }
            if (!Schema::hasColumn('applications', 'finish_pay_date')) {
                $table->date('finish_pay_date')->nullable();
            }
        });
    }

    public function down(): void
    {
    }
};