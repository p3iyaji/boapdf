<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signature_requests', function (Blueprint $table) {
            $table->foreignId('source_document_id')
                ->nullable()
                ->after('id')
                ->constrained('documents')
                ->cascadeOnDelete();
            $table->string('signer_name')->nullable()->after('signer_email');
            $table->string('token', 64)->nullable()->unique()->after('signer_name');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('status');
            $table->timestamp('signed_at')->nullable()->after('expires_at');
        });

        $rows = DB::table('signature_requests')->select('id', 'document_id')->get();

        foreach ($rows as $row) {
            $parentId = DB::table('documents')
                ->where('id', $row->document_id)
                ->value('parent_document_id');

            DB::table('signature_requests')
                ->where('id', $row->id)
                ->update([
                    'source_document_id' => $parentId ?: $row->document_id,
                    'signed_at' => DB::raw('updated_at'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('signature_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_document_id');
            $table->dropColumn(['signer_name', 'token', 'sort_order', 'signed_at']);
        });
    }
};
