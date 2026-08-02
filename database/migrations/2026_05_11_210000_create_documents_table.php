<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('file_path', 500);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('mime_type', 100)->default('application/pdf');
            $table->unsignedInteger('pages')->default(0);
            $table->string('status')->default('completed');
            $table->string('operation_type')->default('upload');
            $table->foreignId('parent_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'operation_type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
