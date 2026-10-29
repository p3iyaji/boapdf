<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

it('forbids non-admins from audit logs', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.audit-logs.index'))
        ->assertForbidden();
});

it('lets admins browse audit logs including account deletions', function () {
    $admin = User::factory()->admin()->create();

    AuditLog::factory()->create([
        'action' => 'account.deleted',
        'actor_name' => 'Former User',
        'actor_email' => 'former@example.com',
        'description' => 'User permanently closed their account and requested erasure of personal data.',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.audit-logs.index'))
        ->assertOk()
        ->assertSee('Audit logs', false)
        ->assertSee('account.deleted', false)
        ->assertSee('former@example.com', false)
        ->assertSee('Former User', false);
});

it('shows audit log stats on the admin dashboard', function () {
    $admin = User::factory()->admin()->create();

    AuditLog::factory()->count(2)->create();
    AuditLog::factory()->create(['action' => 'account.deleted']);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Audit logs', false)
        ->assertSee(route('admin.audit-logs.index'), false);
});

it('records login and logout in the audit log', function () {
    $user = User::factory()->create([
        'email' => 'audit@example.com',
        'password' => Hash::make('secret-pass'),
    ]);

    $this->post(route('authenticate'), [
        'email' => 'audit@example.com',
        'password' => 'secret-pass',
    ])->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'auth.login',
        'actor_email' => 'audit@example.com',
    ]);

    $this->post(route('logout'))->assertRedirect(route('login'));

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'auth.logout',
        'actor_email' => 'audit@example.com',
    ]);
});

it('records document uploads through the audit middleware', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');

    $this->actingAs($user)
        ->post(route('pdf.upload'), ['file' => $file])
        ->assertRedirect();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'pdf.upload',
        'user_id' => $user->id,
    ]);
});

it('records admin soft-deletes with the target identity preserved', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create([
        'name' => 'Target User',
        'email' => 'target@example.com',
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $user))
        ->assertRedirect(route('admin.users.index'));

    $log = AuditLog::query()->where('action', 'admin.user.deleted')->first();

    expect($log)->not->toBeNull()
        ->and($log->metadata['email'])->toBe('target@example.com')
        ->and($log->metadata['name'])->toBe('Target User');
});
