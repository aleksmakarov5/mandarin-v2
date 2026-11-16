<?php
// app/Models/FailureVar.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FailureVar extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function failures(): HasMany
    {
        return $this->hasMany(Failure::class);
    }
}