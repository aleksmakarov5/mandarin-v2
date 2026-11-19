<?php

namespace App\Models;

use App\Services\ApplicationTimerService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Lawyer;
use App\Models\User;


class Application extends Model
{
    protected $table = 'applications';
    protected $guarded = ['id'];

    // Поля, автоматически добавляемые в JSON
    protected $appends = [
        'is_overdue',
        'time_status_text',
        'max_hour',
        'count_hour',
        'max_hour_f',
        'count_hour_f',
        'app_timer',
        'finance_timer',
    ];

    protected $casts = [
        'DTPdate' => 'date',
        'napr_date' => 'date',
        'check_date' => 'datetime',
        'ic_pay_date' => 'date',
        'sto_pay_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Кеш расчета таймеров в рамках одного запроса
     */



    public function getAppTimerAttribute(): array
    {
        return $this->getTimers()['app_timer'];
    }

    public function getFinanceTimerAttribute(): array
    {
        return $this->getTimers()['finance_timer'];
    }
    protected ?array $calculatedTimers = null;

    protected function getTimers(): array
    {
        if ($this->calculatedTimers === null) {
            $this->calculatedTimers = app(ApplicationTimerService::class)->calculate($this);
        }
        return $this->calculatedTimers;
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->getTimers()['app_timer']['is_overdue'];
    }

    public function getTimeStatusTextAttribute(): string
    {
        return $this->getTimers()['app_timer']['display_text'];
    }

    public function getMaxHourAttribute(): int
    {
        return $this->getTimers()['app_timer']['max_hour'];
    }

    public function getCountHourAttribute(): int
    {
        return $this->getTimers()['app_timer']['count_hour'];
    }

    public function getMaxHourFAttribute(): int
    {
        return $this->getTimers()['finance_timer']['max_hour_f'];
    }

    public function getCountHourFAttribute(): int
    {
        return $this->getTimers()['finance_timer']['count_hour_f'];
    }

    // --- СВЯЗИ ---

    public function status(): BelongsTo
    {
        return $this->belongsTo(ApplicationStatus::class, 'id_status');
    }

    public function financeStatus(): BelongsTo
    {
        return $this->belongsTo(FinanceStatus::class, 'id_finance_status');
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
        return $this->belongsTo(User::class, 'id_spm');
    }

    public function mentor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        // В зависимости от того, как колонка называется у вас в БД: id_mentor или mentor_id
        $foreignKey = \Illuminate\Support\Facades\Schema::hasColumn('applications', 'id_mentor')
            ? 'id_mentor'
            : 'mentor_id';

        return $this->belongsTo(User::class, $foreignKey)->withDefault([
            'name' => 'Без наставника',
        ]);
    }

    public function lawyer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Lawyer::class, 'id_lawyer')->withDefault([
            'name' => 'Без юриста',
            'is_cooperating' => true,
        ]);
    }

    public function spareParts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SparePart::class, 'application_id');
    }





}
