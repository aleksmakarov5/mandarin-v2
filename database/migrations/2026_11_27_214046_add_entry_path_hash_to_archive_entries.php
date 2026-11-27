<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('archive_entries', 'entry_path_hash')) {
            Schema::table('archive_entries', function (Blueprint $table) {
                /*
                 * Сначала nullable, чтобы сохранить
                 * уже существующие записи.
                 */
                $table->char('entry_path_hash', 64)
                    ->nullable()
                    ->after('entry_path');
            });
        }

        /*
         * Заполняем хеш для существующих путей.
         * Сами пути и ID записей не меняем.
         */
        DB::table('archive_entries')
            ->select(['id', 'entry_path'])
            ->orderBy('id')
            ->chunkById(500, function ($entries): void {
                foreach ($entries as $entry) {
                    DB::table('archive_entries')
                        ->where('id', $entry->id)
                        ->update([
                            'entry_path_hash' => hash(
                                'sha256',
                                (string) $entry->entry_path
                            ),
                        ]);
                }
            });

        /*
         * Дубли автоматически не удаляем:
         * сначала сообщаем, если они существуют.
         */
        $duplicate = DB::table('archive_entries')
            ->select(['archive_file_id', 'entry_path_hash'])
            ->groupBy('archive_file_id', 'entry_path_hash')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate !== null) {
            throw new \RuntimeException(
                'Найдены повторяющиеся пути в архиве с ID файла '
                . $duplicate->archive_file_id
                . '. Данные сохранены; требуется проверка дублей.'
            );
        }

        $indexName = 'archive_entries_file_path_hash_unique';

        if (!Schema::hasIndex('archive_entries', $indexName)) {
            Schema::table(
                'archive_entries',
                function (Blueprint $table) use ($indexName): void {
                    $table->unique(
                        ['archive_file_id', 'entry_path_hash'],
                        $indexName
                    );
                }
            );
        }
    }

    public function down(): void
    {
        /*
         * Намеренно не удаляем колонку при откате:
         * актуальный индексатор использует её.
         * Обратное изменение требует отдельной миграции.
         */
    }
};