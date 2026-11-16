<?php
// app/Models/ApplicationStatus.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicationStatus extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'user_group_id', 'color', 'type'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class, 'user_group_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'status_id');
    }
}