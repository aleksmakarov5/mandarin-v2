<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Application extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'insurance_company_id',
        'insurance_app_number',
        'status_id',
        'finance_status_id',
        'our_company_id',
        'service_id',
        'lawyer_id',
        'city_id',
        'car_model',
        'gnz',
        'vin',
        'color',
        'mileage',
        'vehicle_passport',
        'dtp_date',
        'ic_answer_date',
        'sp_date',
        'client_name',
        'client_phone',
        'client_passport',
        'mrk_id',
        'spm_id',
        'mrk_proc',
        'zap_proc',
        'has_nds',
    ];

    protected $casts = [
        'dtp_date' => 'date',
        'ic_answer_date' => 'date',
        'sp_date' => 'date',
        'mileage' => 'integer',
        'mrk_proc' => 'integer',
        'zap_proc' => 'integer',
        'has_nds' => 'boolean',
    ];

    public function insuranceCompany(): BelongsTo
    {
        return $this->belongsTo(InsuranceCompany::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ApplicationStatus::class, 'status_id');
    }

    public function financeStatus(): BelongsTo
    {
        return $this->belongsTo(FinanceStatus::class);
    }

    public function ourCompany(): BelongsTo
    {
        return $this->belongsTo(OurCompany::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function lawyer(): BelongsTo
    {
        return $this->belongsTo(Lawyer::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function mrk(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mrk_id');
    }

    public function spm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'spm_id');
    }

    public function spareParts(): HasMany
    {
        return $this->hasMany(SparePart::class);
    }

    public function works(): HasMany
    {
        return $this->hasMany(Work::class);
    }

    public function planWorks(): HasMany
    {
        return $this->hasMany(PlanWork::class);
    }

    public function troubleshootings(): HasMany
    {
        return $this->hasMany(Troubleshooting::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function estimates(): HasMany
    {
        return $this->hasMany(Estimate::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(History::class);
    }

    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class);
    }

    public function failure(): HasOne
    {
        return $this->hasOne(Failure::class);
    }

    public function tru(): HasOne
    {
        return $this->hasOne(Tru::class);
    }
}