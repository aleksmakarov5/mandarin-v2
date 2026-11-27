<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentUploadItem extends Model
{
    protected $fillable = [
        'batch_id',
        'client_file_key',
        'original_name',
        'mime_type',
        'file_size',
        'temp_disk',
        'temp_path',
        'status',
        'error',
        'document_file_id',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(
            DocumentUploadBatch::class,
            'batch_id'
        );
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(
            DocumentFile::class,
            'document_file_id'
        );
    }
}