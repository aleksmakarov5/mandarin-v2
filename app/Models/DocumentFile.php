<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;


class DocumentFile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'document_id',
        'parent_file_id',
        'file_role',
        'original_name',
        'stored_name',
        'storage_disk',
        'storage_path',
        'extension',
        'mime_type',
        'file_size',
        'sha256',
        'width',
        'height',
        'preview_path',
        'processing_status',
        'processing_error',
        'is_current',
        'is_inline',
        'sort_order',
        'uploaded_by_user_id',
        'legacy_attachment_id',
        'parent_file_id',
        'width',
        'height',
        'archive_index_status',
        'archive_indexed_at',
        'archive_index_error',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'is_current' => 'boolean',
        'is_inline' => 'boolean',
        'archive_indexed_at' => 'datetime',
    ];

    // protected $appends = [
    //     'download_url',
    //     'preview_url',
    // ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'parent_file_id'
        );
    }

    public function getDownloadUrlAttribute(): ?string
    {
        if (!$this->storage_path) {
            return null;
        }

        return route('api.documents.files.download', [
            'file' => $this->id,
        ]);
    }

    public function getPreviewUrlAttribute(): ?string
    {
        if (!$this->preview_path) {
            return null;
        }

        return route('api.documents.files.preview', [
            'file' => $this->id,
        ]);
    }
    public function archiveEntries(): HasMany
    {
        return $this->hasMany(
            ArchiveEntry::class,
            'archive_file_id'
        );
    }
    public function isArchiveSource(): bool
    {
        return in_array(
            strtolower((string) $this->extension),
            ['zip', 'rar', '7z', 'tar', 'gz', 'tgz'],
            true
        );
    }

}