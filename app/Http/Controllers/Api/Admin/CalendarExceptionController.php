<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CalendarException;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarExceptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $year = (int) $request->input('year', date('Y'));

        $items = CalendarException::query()
            ->whereYear('date', $year)
            ->orderBy('date', 'asc')
            ->get();

        return response()->json([
            'year' => $year,
            'data' => $items,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date', 'unique:calendar_exceptions,date'],
            'is_workday' => ['required', 'boolean'],
            'comment' => ['nullable', 'string', 'max:255'],
        ], [
            'date.unique' => 'Исключение для этой даты уже добавлено.',
        ]);

        $item = CalendarException::create($validated);

        return response()->json([
            'message' => 'День успешно добавлен в календарь',
            'data' => $item,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = CalendarException::findOrFail($id);

        $validated = $request->validate([
            'date' => ['required', 'date', 'unique:calendar_exceptions,date,' . $item->id],
            'is_workday' => ['required', 'boolean'],
            'comment' => ['nullable', 'string', 'max:255'],
        ]);

        $item->update($validated);

        return response()->json([
            'message' => 'Запись обновлена',
            'data' => $item,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $item = CalendarException::findOrFail($id);
        $item->delete();

        return response()->json([
            'message' => 'Запись удалена',
        ]);
    }

    /**
     * Автогенерация базовых праздников РФ на выбранный год
     */
    public function generateDefaults(Request $request): JsonResponse
    {
        $year = (int) $request->input('year', date('Y'));

        $defaultHolidays = [
            "{$year}-01-01" => 'Новогодние каникулы',
            "{$year}-01-02" => 'Новогодние каникулы',
            "{$year}-01-03" => 'Новогодние каникулы',
            "{$year}-01-04" => 'Новогодние каникулы',
            "{$year}-01-05" => 'Новогодние каникулы',
            "{$year}-01-06" => 'Новогодние каникулы',
            "{$year}-01-07" => 'Рождество Христово',
            "{$year}-01-08" => 'Новогодние каникулы',
            "{$year}-02-23" => 'День защитника Отечества',
            "{$year}-03-08" => 'Международный женский день',
            "{$year}-05-01" => 'Праздник Весны и Труда',
            "{$year}-05-09" => 'День Победы',
            "{$year}-06-12" => 'День России',
            "{$year}-11-04" => 'День народного единства',
        ];

        $inserted = 0;
        foreach ($defaultHolidays as $date => $comment) {
            $exists = CalendarException::where('date', $date)->exists();
            if (!$exists) {
                CalendarException::create([
                    'date' => $date,
                    'is_workday' => false,
                    'comment' => $comment,
                ]);
                $inserted++;
            }
        }

        return response()->json([
            'message' => "Базовые праздники на {$year} год добавлены ({$inserted} шт.)",
            'inserted' => $inserted,
        ]);
    }
}