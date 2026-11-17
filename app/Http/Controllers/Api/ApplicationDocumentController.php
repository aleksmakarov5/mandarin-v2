<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ApplicationDocumentController extends Controller
{
    /**
     * Загрузка документов/фотографий к делу
     */
    public function store(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'file|max:25600', // до 25 МБ
        ]);

        $savedFiles = [];

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs("applications/{$id}", $filename, 'public');

                $savedFiles[] = [
                    'id' => uniqid(),
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'url' => Storage::url($path),
                    'size' => $file->getSize(),
                ];
            }
        }

        return response()->json([
            'message' => 'Файлы успешно загружены',
            'data' => $savedFiles,
        ]);
    }

    /**
     * Удаление файла
     */
    public function destroy(int $id, string $documentId): JsonResponse
    {
        return response()->json([
            'message' => 'Файл удален',
        ]);
    }

    /**
     * Генерация типовых бланков
     */
    public function generatePdf(Request $request, int $id)
    {
        $type = $request->input('type', 'inspection');

        // Простой заглушечный PDF-ответ
        $content = "%PDF-1.4 ... Бланк {$type} для дела #{$id}";

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"doc_{$type}_{$id}.pdf\"",
        ]);
    }
}