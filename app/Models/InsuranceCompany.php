<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsuranceCompany extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'manager',
        'phone',
        'address',
        'our_company_id',
        'legal_entity_id',
        'theme'
    ];

    public function ourCompany(): BelongsTo
    {
        return $this->belongsTo(OurCompany::class);
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}