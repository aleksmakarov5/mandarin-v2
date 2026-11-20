<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Schema\Blueprint;

class StandardWorkController extends Controller
{
    protected function ensureTablesExist(): void
    {
        if (!Schema::hasTable('standard_works')) {
            Schema::create('standard_works', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('category', 100)->default('слесарные');
                $table->decimal('default_norm_hours', 8, 2)->default(0.00);
                $table->decimal('base_price', 12, 2)->default(0.00);
                $table->decimal('price', 12, 2)->default(0.00);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('service_work_prices')) {
            Schema::create('service_work_prices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('standard_work_id');
                $table->unsignedBigInteger('service_id');
                $table->decimal('price', 12, 2)->default(0.00);
                $table->timestamps();

                $table->unique(['standard_work_id', 'service_id'], 'work_service_unique');
            });
        }
    }

    /**
     * Список автосервисов (СТО) для селектора
     */
    public function getServicesList(): JsonResponse
    {
        $services = collect();

        if (Schema::hasTable('services')) {
            $services = DB::table('services')
                ->select('id', 'name')
                ->whereNotNull('name')
                ->where('name', '!=', '')
                ->orderBy('name')
                ->get();
        } elseif (Schema::hasTable('our_companies')) {
            $services = DB::table('our_companies')
                ->select('id', 'name')
                ->whereNotNull('name')
                ->where('name', '!=', '')
                ->orderBy('name')
                ->get();
        }

        return response()->json(['data' => $services]);
    }

    /**
     * 1. Общий список работ (базовые цены)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $this->ensureTablesExist();

            $query = DB::table('standard_works')
                ->whereNotNull('name')
                ->whereRaw("TRIM(name) != ''");

            if (Schema::hasColumn('standard_works', 'is_active')) {
                $query->where('is_active', true);
            }

            if ($request->filled('search')) {
                $s = trim($request->search);
                $query->where('name', 'LIKE', "%{$s}%");
            }

            $works = $query->orderBy('name', 'asc')->get();

            $works->transform(function ($item) {
                // Извлекаем цену из любого существующего поля
                $val = 0.00;
                if (isset($item->base_price) && (float) $item->base_price > 0) {
                    $val = (float) $item->base_price;
                } elseif (isset($item->price) && (float) $item->price > 0) {
                    $val = (float) $item->price;
                }

                $item->base_price = $val;
                $item->price = $val;
                $item->service_price = null;
                $item->has_service_price = false;
                return $item;
            });

            return response()->json(['data' => $works]);
        } catch (\Throwable $e) {
            Log::error('StandardWorkController@index error: ' . $e->getMessage());
            return response()->json(['data' => []]);
        }
    }

    /**
     * 2. Список работ для выбранного СТО
     */
    public function getServicePrices(int $serviceId): JsonResponse
    {
        try {
            $this->ensureTablesExist();

            $serviceCol = Schema::hasColumn('service_work_prices', 'service_id') ? 'service_id' : 'our_company_id';

            $works = DB::table('standard_works')
                ->whereNotNull('standard_works.name')
                ->whereRaw("TRIM(standard_works.name) != ''")
                ->leftJoin('service_work_prices', function ($join) use ($serviceId, $serviceCol) {
                    $join->on('standard_works.id', '=', 'service_work_prices.standard_work_id')
                        ->where("service_work_prices.{$serviceCol}", '=', $serviceId);
                })
                ->select(
                    'standard_works.*',
                    'service_work_prices.price as service_price'
                );

            if (Schema::hasColumn('standard_works', 'is_active')) {
                $works->where('standard_works.is_active', true);
            }

            $result = $works->orderBy('standard_works.name', 'asc')->get();

            $result->transform(function ($item) {
                $val = 0.00;
                if (isset($item->base_price) && (float) $item->base_price > 0) {
                    $val = (float) $item->base_price;
                } elseif (isset($item->price) && (float) $item->price > 0) {
                    $val = (float) $item->price;
                }

                $item->base_price = $val;
                $item->price = $val;
                $item->has_service_price = $item->service_price !== null && (float) $item->service_price > 0;
                $item->service_price = $item->has_service_price ? (float) $item->service_price : null;
                return $item;
            });

            return response()->json(['data' => $result]);
        } catch (\Throwable $e) {
            Log::error('StandardWorkController@getServicePrices error: ' . $e->getMessage());
            return response()->json(['data' => []]);
        }
    }

    /**
     * Сохранение / редактирование операции в общем каталоге
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $this->ensureTablesExist();

            $id = $request->input('id');
            $name = trim((string) $request->input('name', ''));
            if ($name === '') {
                return response()->json(['message' => 'Наименование работы не может быть пустым'], 422);
            }

            $columns = Schema::getColumnListing('standard_works');

            // Получаем цену: пробуем и price, и base_price
            $rawPrice = $request->input('price', $request->input('base_price', 0));
            $cleanPrice = (float) str_replace([' ', ','], ['', '.'], (string) $rawPrice);

            $data = [
                'name' => $name,
            ];

            if (in_array('category', $columns)) {
                $data['category'] = $request->input('category', 'слесарные');
            }
            if (in_array('default_norm_hours', $columns)) {
                $data['default_norm_hours'] = (float) $request->input('default_norm_hours', 0);
            }
            // Заполняем оба поля, чтобы гарантировать сохранение цены
            if (in_array('base_price', $columns)) {
                $data['base_price'] = $cleanPrice;
            }
            if (in_array('price', $columns)) {
                $data['price'] = $cleanPrice;
            }
            if (in_array('is_active', $columns)) {
                $data['is_active'] = true;
            }
            if (in_array('updated_at', $columns)) {
                $data['updated_at'] = now();
            }

            if (!empty($id)) {
                DB::table('standard_works')->where('id', $id)->update($data);
                $savedId = (int) $id;
            } else {
                if (in_array('created_at', $columns)) {
                    $data['created_at'] = now();
                }
                $savedId = DB::table('standard_works')->insertGetId($data);
            }

            $item = DB::table('standard_works')->where('id', $savedId)->first();
            $item->base_price = $cleanPrice;
            $item->price = $cleanPrice;

            return response()->json(['status' => 'ok', 'data' => $item]);
        } catch (\Throwable $e) {
            Log::error('StandardWorkController@store error: ' . $e->getMessage());
            return response()->json(['message' => 'Ошибка сохранения: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Быстрое обновление одного поля из таблицы (включая inline-цену)
     */
    public function updateField(Request $request, int $id): JsonResponse
    {
        try {
            $this->ensureTablesExist();

            $field = $request->input('field');
            $val = $request->input('value');
            $columns = Schema::getColumnListing('standard_works');

            $update = ['updated_at' => now()];

            if ($field === 'price' || $field === 'base_price') {
                $clean = (float) str_replace([' ', ','], ['', '.'], (string) $val);
                if (in_array('base_price', $columns))
                    $update['base_price'] = $clean;
                if (in_array('price', $columns))
                    $update['price'] = $clean;
            } elseif (in_array($field, $columns)) {
                $update[$field] = $val;
            }

            DB::table('standard_works')->where('id', $id)->update($update);

            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Установка индивидуальной цены для СТО
     */
    public function setServicePrice(Request $request, int $serviceId): JsonResponse
    {
        try {
            $this->ensureTablesExist();

            $workId = (int) $request->input('standard_work_id');
            $rawPrice = $request->input('price');
            $price = $rawPrice !== null ? (float) str_replace([' ', ','], ['', '.'], (string) $rawPrice) : null;

            $serviceCol = Schema::hasColumn('service_work_prices', 'service_id') ? 'service_id' : 'our_company_id';

            if ($price === null || $price <= 0) {
                DB::table('service_work_prices')
                    ->where('standard_work_id', $workId)
                    ->where($serviceCol, $serviceId)
                    ->delete();
            } else {
                DB::table('service_work_prices')->updateOrInsert(
                    [
                        'standard_work_id' => $workId,
                        $serviceCol => $serviceId,
                    ],
                    [
                        'price' => $price,
                        'updated_at' => now(),
                    ]
                );
            }

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Удаление работы
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->ensureTablesExist();
            DB::table('standard_works')->where('id', $id)->delete();
            DB::table('service_work_prices')->where('standard_work_id', $id)->delete();
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    public function getApplicationOptions(Request $request): JsonResponse
    {
        try {
            $serviceId = (int) $request->input('service_id', 0);

            if (!Schema::hasTable('standard_works')) {
                return response()->json(['data' => []]);
            }

            $query = DB::table('standard_works');

            // Фильтруем по is_active только если колонка реально есть
            if (Schema::hasColumn('standard_works', 'is_active')) {
                $query->where(function ($q) {
                    $q->where('is_active', true)
                        ->orWhere('is_active', 1)
                        ->orWhereNull('is_active');
                });
            }

            $works = $query->orderBy('name', 'asc')->get();

            // Если СТО выбрано, подтягиваем индивидуальные цены сервиса
            $servicePrices = collect();
            if ($serviceId > 0 && Schema::hasTable('service_work_prices')) {
                $serviceCol = Schema::hasColumn('service_work_prices', 'service_id') ? 'service_id' : 'our_company_id';
                $servicePrices = DB::table('service_work_prices')
                    ->where($serviceCol, $serviceId)
                    ->pluck('price', 'standard_work_id');
            }

            $result = $works->map(function ($w) use ($serviceId, $servicePrices) {
                $basePrice = (float) ($w->base_price ?? $w->price ?? 0);
                $hasServicePrice = $servicePrices->has($w->id);
                $servicePrice = $hasServicePrice ? (float) $servicePrices->get($w->id) : null;

                return [
                    'id' => $w->id,
                    'name' => $w->name,
                    'base_price' => $basePrice,
                    'service_price' => $servicePrice,
                    'final_price' => $servicePrice !== null ? $servicePrice : $basePrice,
                    'has_service_price' => $hasServicePrice,
                ];
            });

            return response()->json(['data' => $result]);
        } catch (\Throwable $e) {
            \Log::error('getApplicationOptions error: ' . $e->getMessage());
            return response()->json(['data' => [], 'error' => $e->getMessage()], 500);
        }
    }
}