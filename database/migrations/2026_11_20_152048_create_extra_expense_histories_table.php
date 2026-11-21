<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('extra_expense_histories')) {
            Schema::create('extra_expense_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('extra_expense_id')->nullable();
                $table->unsignedBigInteger('application_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action_type', 30); // created, updated, deleted
                $table->string('field_name')->nullable();
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();
                $table->timestamps();

                $table->foreign('application_id')->references('id')->on('applications')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_expense_histories');
    }
};