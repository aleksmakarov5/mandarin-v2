<?php
// app/Models/SparepartStatus.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SparepartStatus extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'color'];

    public function spareParts(): HasMany
    {
        return $this->hasMany(SparePart::class);
    }
}