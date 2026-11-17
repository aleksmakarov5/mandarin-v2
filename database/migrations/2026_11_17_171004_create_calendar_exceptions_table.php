<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('calendar_exceptions', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique()->comment('Дата исключения');
            $table->boolean('is_workday')->default(false)->comment('true = рабочая суббота/воскресенье, false = праздник/перенесенный выходной');
            $table->string('comment')->nullable()->comment('Название праздника или основание переноса');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_exceptions');
    }
};