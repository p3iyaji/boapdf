<?php

namespace App\Models;

use Database\Factories\SignatureRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SignatureRequest extends Model
{
    /** @use HasFactory<SignatureRequestFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SIGNED = 'signed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'document_id',
        'source_document_id',
        'requester_email',
        'signer_email',
        'signer_name',
        'token',
        'signature_position',
        'status',
        'sort_order',
        'signed_file_path',
        'expires_at',
        'signed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signature_position' => 'array',
            'expires_at' => 'datetime',
            'signed_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'source_document_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSigned(): bool
    {
        return $this->status === self::STATUS_SIGNED;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isOpenForSigning(): bool
    {
        return $this->isPending() && ! $this->isExpired() && filled($this->token);
    }

    public function signingUrl(): string
    {
        return route('sign.guest.show', $this->token);
    }

    public static function generateToken(): string
    {
        return Str::random(64);
    }
}
