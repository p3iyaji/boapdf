<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompressController;
use App\Http\Controllers\ConvertController;
use App\Http\Controllers\GuestSignatureController;
use App\Http\Controllers\MergeController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PdfController;
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

Route::middleware('guest')->group(function (): void {
    Route::get('/', [AuthController::class, 'showLogin'])->name('login');
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

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [AuthController::class, 'dashboard'])->name('dashboard');

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

        Route::get('/{document}', [PdfController::class, 'show'])->name('show');
        Route::get('/{document}/stream', [PdfController::class, 'stream'])->name('stream');
        Route::get('/{document}/download', [PdfController::class, 'download'])->name('download');
        Route::delete('/{document}', [PdfController::class, 'destroy'])->name('destroy');

        Route::get('/{document}/sign', [SignatureController::class, 'create'])->name('sign.create');
        Route::post('/{document}/sign', [SignatureController::class, 'store'])
            ->middleware('throttle:pdf-heavy')
            ->name('sign.store');
        Route::post('/{document}/sign/invite', [SignatureController::class, 'invite'])
            ->middleware('throttle:6,1')
            ->name('sign.invite');
    });
});
