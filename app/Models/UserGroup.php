<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UserGroup extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'description'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'user_group_id');
    }

    public function rolesUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(ApplicationStatus::class, 'user_group_id');
    }
}