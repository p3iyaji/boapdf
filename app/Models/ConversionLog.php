<?php

namespace App\Models;

use Database\Factories\ConversionLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversionLog extends Model
{
    /** @use HasFactory<ConversionLogFactory> */
    use HasFactory;

    protected $fillable = [
        'document_id',
        'source_format',
        'target_format',
        'processing_time_ms',
        'memory_usage_mb',
        'status',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'processing_time_ms' => 'integer',
            'memory_usage_mb' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
