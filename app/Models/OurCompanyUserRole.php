<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OurCompanyUserRole extends Model
{
    protected $table = 'our_company_user_role';

    protected $fillable = [
        'user_id',
        'our_company_id',
        'user_group_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(OurCompany::class, 'our_company_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class, 'user_group_id');
    }
}