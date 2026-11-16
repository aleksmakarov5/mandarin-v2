<?php
// app/Models/OurCompany.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OurCompany extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'inn',
        'kpp',
        'ogrn',
        'address',
        'phone',
        'email',
        'director',
        'signature_path'
    ];

    public function insuranceCompanies(): HasMany
    {
        return $this->hasMany(InsuranceCompany::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}