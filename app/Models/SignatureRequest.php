<?php

namespace App\Models;

use Database\Factories\SignatureRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'requester_email',
        'signer_email',
        'signature_position',
        'status',
        'signed_file_path',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signature_position' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
