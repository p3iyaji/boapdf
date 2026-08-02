<?php

namespace App\Models;

use App\Support\DocumentsDisk;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const OP_UPLOAD = 'upload';

    public const OP_CONVERTED = 'converted';

    public const OP_MERGED = 'merged';

    public const OP_COMPRESSED = 'compressed';

    public const OP_SIGNED = 'signed';

    public const OP_CAPTURE = 'capture';

    protected $fillable = [
        'user_id',
        'original_name',
        'file_path',
        'file_size',
        'mime_type',
        'pages',
        'status',
        'operation_type',
        'parent_document_id',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => AsArrayObject::class,
            'file_size' => 'integer',
            'pages' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_document_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_document_id');
    }

    public function signatureRequests(): HasMany
    {
        return $this->hasMany(SignatureRequest::class);
    }

    public function getHumanFileSizeAttribute(): string
    {
        $bytes = (int) $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    public function absolutePath(): string
    {
        $disk = DocumentsDisk::disk();
        $root = realpath($disk->path('')) ?: $disk->path('');
        $absolute = $disk->path($this->file_path);
        $resolved = realpath($absolute) ?: $absolute;

        if (! str_starts_with($resolved, rtrim($root, DIRECTORY_SEPARATOR))) {
            throw new \RuntimeException('Document path is outside the documents disk.');
        }

        return $resolved;
    }
}
