<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchiveEntry extends Model
{
    protected $fillable = [
        'archive_file_id',
        'entry_path',
        'entry_name',
        'entry_type',
        'extension',
        'mime_type',
        'compressed_size',
        'uncompressed_size',
        'depth',
        'is_safe',
        'is_previewable',
        'status',
        'error',
    ];

    protected $casts = [
        'compressed_size' => 'integer',
        'uncompressed_size' => 'integer',
        'depth' => 'integer',
        'is_safe' => 'boolean',
        'is_previewable' => 'boolean',
    ];

    public function archiveFile(): BelongsTo
    {
        return $this->belongsTo(
            DocumentFile::class,
            'archive_file_id'
        );
    }

}