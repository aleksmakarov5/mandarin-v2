<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OurCompany extends Model
{
    use HasFactory;

    protected $table = 'our_companies';

    protected $fillable = [
        'name',
        'inn',
        'kpp',
        'ogrn',
        'address',
        'phone',
        'email',
        'mailer',
        'id_legal',
        'sign',
        'director',
        'signature_path',
    ];

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class, 'id_legal');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'our_company_id');
    }
    public function userRoles(): HasMany
    {
        return $this->hasMany(OurCompanyUserRole::class, 'our_company_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'our_company_user_role')
            ->withPivot('user_group_id', 'is_active')
            ->withTimestamps();
    }
}