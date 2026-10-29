<?php

use App\Models\Document;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Notifications\SignatureCompleted;
use App\Notifications\SignatureInvitation;
use App\Services\PdfConversionService;
use App\Services\PdfSignatureService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
});

it('invites multiple signers and emails each a signing link', function () {
    Notification::fake();

    Storage::disk('local')->put('uploads/contract.pdf', '%PDF-1.4');
    $doc = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/contract.pdf',
    ]);

    $this->actingAs($this->user)
        ->post(route('pdf.sign.invite', $doc), [
            'signers' => [
                ['email' => 'alice@example.com', 'name' => 'Alice'],
                ['email' => 'bob@example.com', 'name' => 'Bob'],
            ],
        ])
        ->assertRedirect(route('pdf.sign.create', $doc))
        ->assertSessionHas('success');

    expect(SignatureRequest::query()->where('source_document_id', $doc->id)->count())->toBe(2);
    expect(SignatureRequest::query()->where('status', SignatureRequest::STATUS_PENDING)->count())->toBe(2);

    Notification::assertSentOnDemand(SignatureInvitation::class, function (SignatureInvitation $notification, array $channels, object $notifiable): bool {
        return in_array($notifiable->routes['mail'] ?? null, ['alice@example.com', 'bob@example.com'], true);
    });
});

it('skips duplicate pending invites for the same email', function () {
    Notification::fake();

    Storage::disk('local')->put('uploads/contract.pdf', '%PDF-1.4');
    $doc = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/contract.pdf',
    ]);

    SignatureRequest::factory()->pendingInvite()->create([
        'document_id' => $doc->id,
        'source_document_id' => $doc->id,
        'requester_email' => $this->user->email,
        'signer_email' => 'alice@example.com',
        'signer_name' => 'Alice',
    ]);

    $this->actingAs($this->user)
        ->from(route('pdf.sign.create', $doc))
        ->post(route('pdf.sign.invite', $doc), [
            'signers' => [
                ['email' => 'alice@example.com', 'name' => 'Alice'],
            ],
        ])
        ->assertRedirect(route('pdf.sign.create', $doc))
        ->assertSessionHasErrors('signers');

    expect(SignatureRequest::query()->where('source_document_id', $doc->id)->count())->toBe(1);
    Notification::assertNothingSent();
});

it('removes a pending signer from a document signing request', function () {
    Storage::disk('local')->put('uploads/contract.pdf', '%PDF-1.4');
    $doc = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/contract.pdf',
    ]);

    $alice = SignatureRequest::factory()->pendingInvite()->create([
        'document_id' => $doc->id,
        'source_document_id' => $doc->id,
        'requester_email' => $this->user->email,
        'signer_email' => 'alice@example.com',
        'signer_name' => 'Alice',
    ]);

    $bob = SignatureRequest::factory()->pendingInvite()->create([
        'document_id' => $doc->id,
        'source_document_id' => $doc->id,
        'requester_email' => $this->user->email,
        'signer_email' => 'bob@example.com',
        'signer_name' => 'Bob',
    ]);

    $this->actingAs($this->user)
        ->delete(route('pdf.sign.invite.destroy', [$doc, $alice]))
        ->assertRedirect(route('pdf.sign.create', ['document' => $doc, 'tab' => 'invite']))
        ->assertSessionHas('success');

    expect(SignatureRequest::query()->whereKey($alice->id)->exists())->toBeFalse();
    expect(SignatureRequest::query()->whereKey($bob->id)->exists())->toBeTrue();

    $this->get(route('sign.guest.show', $alice->token))
        ->assertRedirect(route('login'));
});

it('does not remove a signer who has already signed', function () {
    Storage::disk('local')->put('uploads/contract.pdf', '%PDF-1.4');
    $doc = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/contract.pdf',
    ]);

    $signed = SignatureRequest::factory()->signed()->create([
        'document_id' => $doc->id,
        'source_document_id' => $doc->id,
        'requester_email' => $this->user->email,
        'signer_email' => 'alice@example.com',
        'signer_name' => 'Alice',
    ]);

    $this->actingAs($this->user)
        ->from(route('pdf.sign.create', $doc))
        ->delete(route('pdf.sign.invite.destroy', [$doc, $signed]))
        ->assertRedirect()
        ->assertSessionHasErrors('signers');

    expect(SignatureRequest::query()->whereKey($signed->id)->exists())->toBeTrue();
});

it('forbids removing a signer on another users document', function () {
    $other = User::factory()->create();
    Storage::disk('local')->put('uploads/secret.pdf', '%PDF-1.4');
    $doc = Document::factory()->uploaded()->for($other)->create([
        'file_path' => 'uploads/secret.pdf',
    ]);

    $invite = SignatureRequest::factory()->pendingInvite()->create([
        'document_id' => $doc->id,
        'source_document_id' => $doc->id,
        'requester_email' => $other->email,
        'signer_email' => 'alice@example.com',
    ]);

    $this->actingAs($this->user)
        ->delete(route('pdf.sign.invite.destroy', [$doc, $invite]))
        ->assertForbidden();
});

it('lets a guest open a valid signing link', function () {
    Storage::disk('local')->put('uploads/contract.pdf', '%PDF-1.4');
    $doc = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/contract.pdf',
        'pages' => 1,
    ]);

    $invite = SignatureRequest::factory()->pendingInvite()->create([
        'document_id' => $doc->id,
        'source_document_id' => $doc->id,
        'requester_email' => $this->user->email,
        'signer_email' => 'alice@example.com',
        'signer_name' => 'Alice',
    ]);

    $this->get(route('sign.guest.show', $invite->token))
        ->assertSuccessful()
        ->assertSee('Alice')
        ->assertSee($doc->original_name)
        ->assertSee('openApplyConfirm()', false)
        ->assertSee('Yes, apply my signature')
        ->assertSee('won’t be able to edit it from this link afterward');
});

it('rejects expired or invalid guest signing links', function () {
    Storage::disk('local')->put('uploads/contract.pdf', '%PDF-1.4');
    $doc = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/contract.pdf',
    ]);

    $expired = SignatureRequest::factory()->pendingInvite()->create([
        'document_id' => $doc->id,
        'source_document_id' => $doc->id,
        'requester_email' => $this->user->email,
        'signer_email' => 'alice@example.com',
        'expires_at' => now()->subDay(),
    ]);

    $this->get(route('sign.guest.show', $expired->token))
        ->assertRedirect(route('login'));

    $this->get(route('sign.guest.show', 'not-a-real-token'))
        ->assertRedirect(route('login'));
});

it('allows multiple guests to sign the same document in sequence', function () {
    Storage::disk('local')->put('uploads/contract.pdf', '%PDF-1.4');
    Storage::disk('local')->put('temp/signature.png', 'fake-png');
    Storage::disk('local')->put('signed/result-a.pdf', '%PDF-1.4 signed-a');
    Storage::disk('local')->put('signed/result-b.pdf', '%PDF-1.4 signed-b');

    $doc = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/contract.pdf',
    ]);

    $alice = SignatureRequest::factory()->pendingInvite()->create([
        'document_id' => $doc->id,
        'source_document_id' => $doc->id,
        'requester_email' => $this->user->email,
        'signer_email' => 'alice@example.com',
        'signer_name' => 'Alice',
        'sort_order' => 1,
    ]);

    $bob = SignatureRequest::factory()->pendingInvite()->create([
        'document_id' => $doc->id,
        'source_document_id' => $doc->id,
        'requester_email' => $this->user->email,
        'signer_email' => 'bob@example.com',
        'signer_name' => 'Bob',
        'sort_order' => 2,
    ]);

    $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    $sigImg = Storage::disk('local')->path('temp/signature.png');
    $pathA = Storage::disk('local')->path('signed/result-a.pdf');
    $pathB = Storage::disk('local')->path('signed/result-b.pdf');

    $signer = $this->mock(PdfSignatureService::class);
    $signer->shouldReceive('createSignatureFromDataUrl')->twice()->andReturn($sigImg);
    $calls = 0;
    $signer->shouldReceive('addSignatures')
        ->twice()
        ->andReturnUsing(function () use (&$calls, $pathA, $pathB) {
            $calls++;

            return $calls === 1 ? $pathA : $pathB;
        });

    $this->mock(PdfConversionService::class)
        ->shouldReceive('countPages')
        ->andReturn(1);

    $this->post(route('sign.guest.store', $alice->token), [
        'signature' => $png,
        'page' => 1,
        'x' => 20,
        'y' => 250,
        'width' => 60,
    ])->assertRedirect(route('sign.guest.thanks', $alice->token));

    $alice->refresh();
    expect($alice->status)->toBe(SignatureRequest::STATUS_SIGNED);
    expect($alice->signed_at)->not->toBeNull();

    $firstSigned = Document::query()
        ->where('operation_type', Document::OP_SIGNED)
        ->where('parent_document_id', $doc->id)
        ->where('status', Document::STATUS_COMPLETED)
        ->first();
    expect($firstSigned)->not->toBeNull();
    expect($alice->document_id)->toBe($firstSigned->id);

    $this->post(route('sign.guest.store', $bob->token), [
        'signature' => $png,
        'page' => 1,
        'x' => 40,
        'y' => 200,
        'width' => 55,
    ])->assertRedirect(route('sign.guest.thanks', $bob->token));

    $bob->refresh();
    expect($bob->status)->toBe(SignatureRequest::STATUS_SIGNED);

    expect(
        SignatureRequest::query()
            ->where('source_document_id', $doc->id)
            ->where('status', SignatureRequest::STATUS_SIGNED)
            ->count()
    )->toBe(2);

    expect(
        Document::query()
            ->where('parent_document_id', $doc->id)
            ->where('operation_type', Document::OP_SIGNED)
            ->where('status', Document::STATUS_COMPLETED)
            ->count()
    )->toBe(2);
});

it('forbids inviting signers on another users document', function () {
    $other = User::factory()->create();
    Storage::disk('local')->put('uploads/secret.pdf', '%PDF-1.4');
    $doc = Document::factory()->uploaded()->for($other)->create([
        'file_path' => 'uploads/secret.pdf',
    ]);

    $this->actingAs($this->user)
        ->post(route('pdf.sign.invite', $doc), [
            'signers' => [
                ['email' => 'alice@example.com', 'name' => 'Alice'],
            ],
        ])
        ->assertForbidden();
});

it('shows signing progress on the document page', function () {
    Storage::disk('local')->put('uploads/contract.pdf', '%PDF-1.4');
    $doc = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/contract.pdf',
    ]);

    SignatureRequest::factory()->pendingInvite()->create([
        'document_id' => $doc->id,
        'source_document_id' => $doc->id,
        'requester_email' => $this->user->email,
        'signer_email' => 'alice@example.com',
        'signer_name' => 'Alice',
    ]);

    $this->actingAs($this->user)
        ->get(route('pdf.show', $doc))
        ->assertSuccessful()
        ->assertSee('Signature requests')
        ->assertSee('Alice')
        ->assertSee('Waiting');
});

it('still self-signs and records source_document_id', function () {
    Storage::disk('local')->put('uploads/contract.pdf', '%PDF-1.4');
    Storage::disk('local')->put('temp/signature.png', 'fake-png');
    Storage::disk('local')->put('signed/result.pdf', '%PDF-1.4 signed');

    $doc = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/contract.pdf',
    ]);

    $signer = $this->mock(PdfSignatureService::class);
    $signer->shouldReceive('createSignatureFromDataUrl')
        ->andReturn(Storage::disk('local')->path('temp/signature.png'));
    $signer->shouldReceive('addSignatures')
        ->andReturn(Storage::disk('local')->path('signed/result.pdf'));

    $this->mock(PdfConversionService::class)
        ->shouldReceive('countPages')
        ->andReturn(1);

    $this->actingAs($this->user)
        ->post(route('pdf.sign.store', $doc), [
            'signature' => 'data:image/png;base64,iVBORw0KGgo=',
            'page' => 1,
            'x' => 20,
            'y' => 250,
            'width' => 60,
        ])
        ->assertRedirect();

    $req = SignatureRequest::query()->where('status', SignatureRequest::STATUS_SIGNED)->first();
    expect($req)->not->toBeNull();
    expect($req->source_document_id)->toBe($doc->id);
    expect($req->signer_email)->toBe($this->user->email);
});

it('notifies the document owner when a guest completes signing', function () {
    Notification::fake();

    Storage::disk('local')->put('uploads/contract.pdf', '%PDF-1.4');
    Storage::disk('local')->put('temp/signature.png', 'fake-png');
    Storage::disk('local')->put('signed/result.pdf', '%PDF-1.4 signed');

    $doc = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/contract.pdf',
    ]);

    $invite = SignatureRequest::factory()->pendingInvite()->create([
        'document_id' => $doc->id,
        'source_document_id' => $doc->id,
        'requester_email' => $this->user->email,
        'signer_email' => 'alice@example.com',
        'signer_name' => 'Alice',
    ]);

    $signer = $this->mock(PdfSignatureService::class);
    $signer->shouldReceive('createSignatureFromDataUrl')
        ->andReturn(Storage::disk('local')->path('temp/signature.png'));
    $signer->shouldReceive('addSignatures')
        ->andReturn(Storage::disk('local')->path('signed/result.pdf'));

    $this->mock(PdfConversionService::class)
        ->shouldReceive('countPages')
        ->andReturn(1);

    $this->post(route('sign.guest.store', $invite->token), [
        'signature' => 'data:image/png;base64,iVBORw0KGgo=',
        'page' => 1,
        'x' => 20,
        'y' => 250,
        'width' => 60,
    ])->assertRedirect(route('sign.guest.thanks', $invite->token));

    Notification::assertSentTo($this->user, SignatureCompleted::class, function (SignatureCompleted $notification) use ($invite): bool {
        return $notification->signatureRequest->id === $invite->id;
    });
});

it('does not notify the document owner on self-sign', function () {
    Notification::fake();

    Storage::disk('local')->put('uploads/contract.pdf', '%PDF-1.4');
    Storage::disk('local')->put('temp/signature.png', 'fake-png');
    Storage::disk('local')->put('signed/result.pdf', '%PDF-1.4 signed');

    $doc = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/contract.pdf',
    ]);

    $signer = $this->mock(PdfSignatureService::class);
    $signer->shouldReceive('createSignatureFromDataUrl')
        ->andReturn(Storage::disk('local')->path('temp/signature.png'));
    $signer->shouldReceive('addSignatures')
        ->andReturn(Storage::disk('local')->path('signed/result.pdf'));

    $this->mock(PdfConversionService::class)
        ->shouldReceive('countPages')
        ->andReturn(1);

    $this->actingAs($this->user)
        ->post(route('pdf.sign.store', $doc), [
            'signature' => 'data:image/png;base64,iVBORw0KGgo=',
            'page' => 1,
            'x' => 20,
            'y' => 250,
            'width' => 60,
        ])
        ->assertRedirect();

    Notification::assertNotSentTo($this->user, SignatureCompleted::class);
});

it('notifies the document owner after each guest signs in a multi-signer envelope', function () {
    Notification::fake();

    Storage::disk('local')->put('uploads/contract.pdf', '%PDF-1.4');
    Storage::disk('local')->put('temp/signature.png', 'fake-png');
    Storage::disk('local')->put('signed/result-a.pdf', '%PDF-1.4 signed-a');
    Storage::disk('local')->put('signed/result-b.pdf', '%PDF-1.4 signed-b');

    $doc = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/contract.pdf',
    ]);

    $alice = SignatureRequest::factory()->pendingInvite()->create([
        'document_id' => $doc->id,
        'source_document_id' => $doc->id,
        'requester_email' => $this->user->email,
        'signer_email' => 'alice@example.com',
        'signer_name' => 'Alice',
        'sort_order' => 1,
    ]);

    $bob = SignatureRequest::factory()->pendingInvite()->create([
        'document_id' => $doc->id,
        'source_document_id' => $doc->id,
        'requester_email' => $this->user->email,
        'signer_email' => 'bob@example.com',
        'signer_name' => 'Bob',
        'sort_order' => 2,
    ]);

    $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    $sigImg = Storage::disk('local')->path('temp/signature.png');
    $pathA = Storage::disk('local')->path('signed/result-a.pdf');
    $pathB = Storage::disk('local')->path('signed/result-b.pdf');

    $signer = $this->mock(PdfSignatureService::class);
    $signer->shouldReceive('createSignatureFromDataUrl')->twice()->andReturn($sigImg);
    $calls = 0;
    $signer->shouldReceive('addSignatures')
        ->twice()
        ->andReturnUsing(function () use (&$calls, $pathA, $pathB) {
            $calls++;

            return $calls === 1 ? $pathA : $pathB;
        });

    $this->mock(PdfConversionService::class)
        ->shouldReceive('countPages')
        ->andReturn(1);

    $this->post(route('sign.guest.store', $alice->token), [
        'signature' => $png,
        'page' => 1,
        'x' => 20,
        'y' => 250,
        'width' => 60,
    ])->assertRedirect(route('sign.guest.thanks', $alice->token));

    Notification::assertSentToTimes($this->user, SignatureCompleted::class, 1);

    $this->post(route('sign.guest.store', $bob->token), [
        'signature' => $png,
        'page' => 1,
        'x' => 40,
        'y' => 200,
        'width' => 55,
    ])->assertRedirect(route('sign.guest.thanks', $bob->token));

    Notification::assertSentToTimes($this->user, SignatureCompleted::class, 2);
});
