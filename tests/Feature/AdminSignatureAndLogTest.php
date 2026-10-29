<?php

use App\Models\ConversionLog;
use App\Models\Document;
use App\Models\SignatureRequest;
use App\Models\User;

it('lists signature requests for admins', function () {
    $admin = User::factory()->admin()->create();
    $document = Document::factory()->create();
    SignatureRequest::factory()->pendingInvite()->create([
        'document_id' => $document->id,
        'source_document_id' => $document->id,
        'signer_email' => 'signer@example.com',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.signature-requests.index'))
        ->assertOk()
        ->assertSee('signer@example.com', false);
});

it('lets an admin delete a signature request', function () {
    $admin = User::factory()->admin()->create();
    $request = SignatureRequest::factory()->pendingInvite()->create();

    $this->actingAs($admin)
        ->delete(route('admin.signature-requests.destroy', $request))
        ->assertRedirect(route('admin.signature-requests.index'));

    expect(SignatureRequest::find($request->id))->toBeNull();
});

it('lists conversion logs for admins', function () {
    $admin = User::factory()->admin()->create();
    ConversionLog::factory()->create([
        'target_format' => 'docx',
        'status' => 'failed',
        'error_message' => 'LibreOffice missing',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.conversion-logs.index'))
        ->assertOk()
        ->assertSee('LibreOffice missing', false);
});
