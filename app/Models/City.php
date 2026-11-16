<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class City extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function userCities(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_cities');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }
}