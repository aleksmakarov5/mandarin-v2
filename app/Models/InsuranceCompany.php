<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsuranceCompany extends Model
{
    use HasFactory;

    protected $table = 'insurance_companies';

    protected $fillable = [
        'name',
        'bg_color',
        'text_color',
        'curator',
        'manager',
        'phone',
        'email',
        'address',
        'our_company_id',
        'legal_entity_id',
        'theme',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'id_insuranceCompany');
    }

    public function ourCompany(): BelongsTo
    {
        return $this->belongsTo(OurCompany::class, 'our_company_id');
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class, 'legal_entity_id');
    }
}