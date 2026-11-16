<?php
// app/Models/LegalEntity.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalEntity extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'inn', 'kpp', 'details'];

    public function insuranceCompanies(): HasMany
    {
        return $this->hasMany(InsuranceCompany::class);
    }
}