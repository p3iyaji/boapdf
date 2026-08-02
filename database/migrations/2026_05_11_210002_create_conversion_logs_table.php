<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversion_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_format', 10)->nullable();
            $table->string('target_format', 10)->nullable();
            $table->unsignedInteger('processing_time_ms')->default(0);
            $table->unsignedInteger('memory_usage_mb')->default(0);
            $table->string('status')->default('success');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['source_format', 'target_format']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversion_logs');
    }
};
