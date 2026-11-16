<?php
// app/Models/TrueVar.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrueVar extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function trus(): HasMany
    {
        return $this->hasMany(Tru::class);
    }
}