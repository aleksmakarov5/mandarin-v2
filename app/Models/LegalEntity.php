<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalEntity extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'inn',
        'kpp',
        'details',
        'nds_rate',
        'income_tax_rate',
    ];

    protected $casts = [
        'nds_rate' => 'decimal:2',
        'income_tax_rate' => 'decimal:2',
    ];

    public function insuranceCompanies(): HasMany
    {
        return $this->hasMany(InsuranceCompany::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(OurCompany::class, 'id_legal');
    }
}