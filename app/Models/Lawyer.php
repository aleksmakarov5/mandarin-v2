<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lawyer extends Model
{
    protected $table = 'lawyers';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'is_cooperating',
        'theme',
    ];

    protected $casts = [
        'is_cooperating' => 'boolean',
    ];
}