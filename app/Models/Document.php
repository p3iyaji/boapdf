<?php

namespace App\Models;

use App\Support\DocumentsDisk;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Builder;
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

    public const OP_EDITED = 'edited';

    public const OP_FORM_FILLED = 'form_filled';

    public const OP_CREATED = 'created';

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

    public function envelopeSignatureRequests(): HasMany
    {
        return $this->hasMany(SignatureRequest::class, 'source_document_id');
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
        ];
    }

    /**
     * @return list<string>
     */
    public static function operationTypes(): array
    {
        return [
            self::OP_UPLOAD,
            self::OP_CAPTURE,
            self::OP_MERGED,
            self::OP_COMPRESSED,
            self::OP_CONVERTED,
            self::OP_SIGNED,
            self::OP_EDITED,
            self::OP_FORM_FILLED,
            self::OP_CREATED,
        ];
    }

    /**
     * @param  Builder<Document>  $query
     * @return Builder<Document>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * @param  Builder<Document>  $query
     * @return Builder<Document>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('original_name', 'like', $like)
                ->orWhere('file_path', 'like', $like);
        });
    }

    /**
     * @param  Builder<Document>  $query
     * @return Builder<Document>
     */
    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if (! filled($status) || ! in_array($status, self::statuses(), true)) {
            return $query;
        }

        return $query->where('status', $status);
    }

    /**
     * @param  Builder<Document>  $query
     * @return Builder<Document>
     */
    public function scopeOperation(Builder $query, ?string $operation): Builder
    {
        if (! filled($operation) || ! in_array($operation, self::operationTypes(), true)) {
            return $query;
        }

        return $query->where('operation_type', $operation);
    }

    /**
     * @param  Builder<Document>  $query
     * @return Builder<Document>
     */
    public function scopeCompletedPdfs(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_COMPLETED)
            ->where('mime_type', 'application/pdf');
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

    public function isFileReady(): bool
    {
        return $this->status === self::STATUS_COMPLETED
            && filled($this->file_path)
            && DocumentsDisk::disk()->exists($this->file_path);
    }
}
