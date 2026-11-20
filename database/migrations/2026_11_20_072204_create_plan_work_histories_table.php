<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('plan_work_histories')) {
            Schema::create('plan_work_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('application_id')->index();
                $table->unsignedBigInteger('plan_work_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('action_type', 40); // created, updated, deleted, status_change, service_submitted, approval_completed
                $table->string('field_name', 100)->nullable(); // name, price, approval_status, etc.
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();
                $table->timestamps();

                // Внешние ключи (мягкая привязка при каскадном удалении дела)
                if (Schema::hasTable('applications')) {
                    $table->foreign('application_id')->references('id')->on('applications')->cascadeOnDelete();
                }
                if (Schema::hasTable('users')) {
                    $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_work_histories');
    }
};