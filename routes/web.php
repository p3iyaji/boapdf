<?php

use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\ConversionLogController as AdminConversionLogController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DocumentController as AdminDocumentController;
use App\Http\Controllers\Admin\SignatureRequestController as AdminSignatureRequestController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompressController;
use App\Http\Controllers\ConvertController;
use App\Http\Controllers\CreateController;
use App\Http\Controllers\EditController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\GuestSignatureController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MergeController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SignatureController;
use Illuminate\Support\Facades\Route;

Route::prefix('sign')->name('sign.guest.')->group(function (): void {
    Route::get('/{token}/thanks', [GuestSignatureController::class, 'thanks'])->name('thanks');
    Route::get('/{token}/stream', [GuestSignatureController::class, 'stream'])
        ->middleware('throttle:60,1')
        ->name('stream');
    Route::get('/{token}', [GuestSignatureController::class, 'show'])->name('show');
    Route::post('/{token}', [GuestSignatureController::class, 'store'])
        ->middleware('throttle:pdf-heavy')
        ->name('store');
});

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'authenticate'])
        ->middleware('throttle:login')
        ->name('authenticate');

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:register')
        ->name('register.store');

    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.email');

    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])
        ->middleware('throttle:password-reset')
        ->name('password.update');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::middleware('verified')->group(function (): void {
        Route::get('/dashboard', [AuthController::class, 'dashboard'])->name('dashboard');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [ProfileController::class, 'update'])
            ->middleware('throttle:6,1')
            ->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])
            ->middleware('throttle:6,1')
            ->name('profile.password');
        Route::delete('/profile', [ProfileController::class, 'destroy'])
            ->middleware('throttle:6,1')
            ->name('profile.destroy');

        Route::get('/password', [PasswordController::class, 'edit'])->name('password.edit');
        Route::put('/password', [PasswordController::class, 'update'])
            ->middleware('throttle:6,1')
            ->name('password.change');

        Route::middleware('admin')->prefix('admin')->name('admin.')->group(function (): void {
            Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

            Route::get('/password', [PasswordController::class, 'editAdmin'])->name('password.edit');
            Route::put('/password', [PasswordController::class, 'update'])
                ->middleware('throttle:6,1')
                ->name('password.update');

            Route::get('audit-logs', AdminAuditLogController::class)->name('audit-logs.index');

            Route::post('users/{user}/restore', [AdminUserController::class, 'restore'])->name('users.restore');
            Route::patch('users/{user}/activation', [AdminUserController::class, 'updateActivation'])->name('users.activation');
            Route::resource('users', AdminUserController::class);

            Route::resource('documents', AdminDocumentController::class)->only(['index', 'show', 'destroy']);
            Route::resource('signature-requests', AdminSignatureRequestController::class)->only(['index', 'show', 'destroy']);
            Route::get('conversion-logs', AdminConversionLogController::class)->name('conversion-logs.index');
        });

        Route::prefix('pdf')->name('pdf.')->group(function (): void {
            Route::get('/', [PdfController::class, 'index'])->name('index');
            Route::post('/', [PdfController::class, 'upload'])
                ->middleware('throttle:pdf-upload')
                ->name('upload');
            Route::post('/camera', [PdfController::class, 'storeFromCamera'])
                ->middleware('throttle:pdf-upload')
                ->name('upload.camera');

            Route::get('/merge', [MergeController::class, 'create'])->name('merge.create');
            Route::post('/merge', [MergeController::class, 'store'])
                ->middleware('throttle:pdf-heavy')
                ->name('merge.store');

            Route::get('/compress', [CompressController::class, 'create'])->name('compress.create');
            Route::post('/compress', [CompressController::class, 'store'])
                ->middleware('throttle:pdf-heavy')
                ->name('compress.store');

            Route::get('/convert', [ConvertController::class, 'create'])->name('convert.create');
            Route::post('/convert', [ConvertController::class, 'store'])
                ->middleware('throttle:pdf-heavy')
                ->name('convert.store');

            Route::get('/create', [CreateController::class, 'create'])->name('create.create');
            Route::post('/create', [CreateController::class, 'store'])
                ->middleware('throttle:pdf-heavy')
                ->name('create.store');

            Route::get('/{document}', [PdfController::class, 'show'])->name('show');
            Route::get('/{document}/stream', [PdfController::class, 'stream'])->name('stream');
            Route::get('/{document}/download', [PdfController::class, 'download'])->name('download');
            Route::delete('/{document}', [PdfController::class, 'destroy'])->name('destroy');

            Route::get('/{document}/edit', [EditController::class, 'create'])->name('edit.create');
            Route::post('/{document}/edit', [EditController::class, 'store'])
                ->middleware('throttle:pdf-heavy')
                ->name('edit.store');
            Route::post('/{document}/edit/form', [EditController::class, 'storeForm'])
                ->middleware('throttle:pdf-heavy')
                ->name('edit.form');

            Route::get('/{document}/sign', [SignatureController::class, 'create'])->name('sign.create');
            Route::post('/{document}/sign', [SignatureController::class, 'store'])
                ->middleware('throttle:pdf-heavy')
                ->name('sign.store');
            Route::post('/{document}/sign/invite', [SignatureController::class, 'invite'])
                ->middleware('throttle:6,1')
                ->name('sign.invite');
            Route::delete('/{document}/sign/invite/{signatureRequest}', [SignatureController::class, 'destroyInvite'])
                ->middleware('throttle:30,1')
                ->name('sign.invite.destroy');
        });
    });
});
