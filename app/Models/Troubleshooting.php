<?php
// app/Models/Troubleshooting.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Troubleshooting extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'part_name',
        'work_type',
        'expert_opinion',
        'conclusion',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}