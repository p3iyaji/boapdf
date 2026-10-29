<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores actor snapshots and metadata', function () {
    $user = User::factory()->create([
        'name' => 'Ada',
        'email' => 'ada@example.com',
    ]);

    $log = app(AuditLogger::class)->log(
        action: 'test.action',
        description: 'A test event',
        subject: $user,
        metadata: ['foo' => 'bar'],
        actor: $user,
    );

    expect($log)->toBeInstanceOf(AuditLog::class)
        ->and($log->action)->toBe('test.action')
        ->and($log->actor_name)->toBe('Ada')
        ->and($log->actor_email)->toBe('ada@example.com')
        ->and($log->metadata)->toBe(['foo' => 'bar'])
        ->and($log->subject_id)->toBe($user->id);
});
