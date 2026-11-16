<?php
// app/Models/MrkFilter.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MrkFilter extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'filter_data'];

    protected $casts = [
        'filter_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}