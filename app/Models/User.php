<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'user_group_id',
        'city_id',
        'def_mrk_proc',
        'def_zap_proc',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'def_mrk_proc' => 'integer',
            'def_zap_proc' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class, 'user_group_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function cities(): BelongsToMany
    {
        return $this->belongsToMany(City::class, 'user_cities');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(UserGroup::class, 'user_roles');
    }

    public function mrkApplications(): HasMany
    {
        return $this->hasMany(Application::class, 'mrk_id');
    }

    public function spmApplications(): HasMany
    {
        return $this->hasMany(Application::class, 'spm_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notify::class);
    }

    public function userFilter(): HasOne
    {
        return $this->hasOne(UserFilter::class);
    }

    public function periodFilter(): HasOne
    {
        return $this->hasOne(PeriodFilter::class);
    }

    public function mrkFilter(): HasOne
    {
        return $this->hasOne(MrkFilter::class);
    }
}