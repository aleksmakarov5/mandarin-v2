<?php
// app/Models/Attachment.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'message_id',
        'doc_status_id',
        'file_name',
        'location',
        'mime_type',
        'file_size',
        'is_parsed'
    ];

    protected $casts = [
        'file_size' => 'integer',
        'is_parsed' => 'boolean',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function docStatus(): BelongsTo
    {
        return $this->belongsTo(DocStatus::class);
    }

    public function getUrlAttribute(): string
    {
        if (config('filesystems.default') === 's3') {
            return Storage::disk('s3')->temporaryUrl($this->location, now()->addMinutes(30));
        }
        return Storage::disk('public')->url($this->location);
    }
}