<?php

namespace App\Models;

use App\Services\WorkdayService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Application extends Model
{
    protected $table = 'applications';

    protected $guarded = ['id'];

    protected $appends = [
        'is_overdue',
        'time_status_text',
        'soglas_title',
        'soglas_time_text',
        'soglas_overdue',
        'remont_sum',
        'total_costs',
        'remains',
        'remains_percent',
    ];

    protected $casts = [
        'DTPdate' => 'date',
        'napr_date' => 'date',
        'sp_date' => 'date',
        'view_date' => 'datetime',
        'check_date' => 'datetime',
        'start_date' => 'date',
        'end_date' => 'date',
        'ic_pay_date' => 'date',
        'sto_pay_date' => 'date',
    ];

    public function status(): BelongsTo
    {
        return $this->belongsTo(ApplicationStatus::class, 'id_status');
    }

    public function insuranceCompany(): BelongsTo
    {
        return $this->belongsTo(InsuranceCompany::class, 'id_insuranceCompany');
    }

    public function ourCompany(): BelongsTo
    {
        return $this->belongsTo(OurCompany::class, 'our_company');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'id_city');
    }

    public function mrk(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_MRK');
    }
    public function spm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_spm')->withDefault([
            'id' => null,
            'name' => 'Не назначен',
        ]);
    }

    /**
     * Определение опорной точки отсчета по статусу
     */
    protected function getBaseDateForTimer(): ?Carbon
    {
        // В зависимости от статуса берется разная дата
        return match ((int) $this->id_status) {
            11, 12 => $this->napr_date ? Carbon::parse($this->napr_date) : $this->updated_at,
            14 => $this->view_date ? Carbon::parse($this->view_date) : $this->updated_at,
            15, 16 => $this->sp_date ? Carbon::parse($this->sp_date) : $this->updated_at,
            default => $this->updated_at ?? $this->created_at,
        };
    }

    /**
     * Проверка просрочки
     */
    public function getIsOverdueAttribute(): bool
    {
        if (!$this->status || (int) $this->status->time <= 0) {
            return false;
        }

        $baseDate = $this->getBaseDateForTimer();
        if (!$baseDate)
            return false;

        $now = Carbon::now();

        if ($this->status->is_day) {
            // Расчет в РАБОЧИХ днях
            $workdaysElapsed = WorkdayService::getWorkdaysBetween($baseDate, $now);
            return $workdaysElapsed > (int) $this->status->time;
        }

        // Расчет в часах
        return $baseDate->diffInHours($now) > (int) $this->status->time;
    }

    /**
     * Текстовое отображение таймера (например: 9дней, 46часов, просрочено)
     */
    public function getTimeStatusTextAttribute(): string
    {
        $baseDate = $this->getBaseDateForTimer();
        if (!$baseDate)
            return '';

        $now = Carbon::now();

        if ($this->is_overdue) {
            return 'просрочено';
        }

        if ($this->status?->is_day) {
            $days = WorkdayService::getWorkdaysBetween($baseDate, $now);
            return "{$days}дней";
        }

        $hours = $baseDate->diffInHours($now);
        return "{$hours}часов";
    }

    /**
     * Колонка Согл/Опл: название
     */
    public function getSoglasTitleAttribute(): string
    {
        // Пример логики согласования из старой базы
        if ($this->ic_pay_date)
            return 'Оплата получена';
        if ($this->check_date)
            return 'Счет создан';
        if ($this->status?->id === 15)
            return 'ПЗН согласован';
        if ($this->status?->id === 10)
            return 'Согласование АСП';
        return '';
    }

    public function getSoglasTimeTextAttribute(): string
    {
        if (!$this->soglas_title)
            return '';
        $base = $this->check_date ?? $this->updated_at;
        if (!$base)
            return '';
        $h = Carbon::parse($base)->diffInHours(Carbon::now());
        return "{$h}часов";
    }

    public function getSoglasOverdueAttribute(): bool
    {
        return false;
    }

    /**
     * Финансовые вычисляемые поля
     */
    public function getRemontSumAttribute(): float
    {
        return (float) ($this->attributes['remont_sum'] ?? 0);
    }

    public function getTotalCostsAttribute(): float
    {
        return (float) ($this->attributes['total_costs'] ?? 0);
    }

    public function getRemainsAttribute(): float
    {
        $icSum = (float) ($this->attributes['sum_sk'] ?? 0);
        $stoPay = (float) ($this->attributes['sto_pay'] ?? 0);
        return max(0, $icSum - $stoPay);
    }

    public function getRemainsPercentAttribute(): int
    {
        $icSum = (float) ($this->attributes['sum_sk'] ?? 0);
        if ($icSum <= 0)
            return 0;
        $stoPay = (float) ($this->attributes['sto_pay'] ?? 0);
        return (int) round(($stoPay / $icSum) * 100);
    }
}