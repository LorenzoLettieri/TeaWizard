<?php

use App\Http\Controllers\Auth\RegistrationRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/build/{path}', function (string $path) {
    $buildRoot = realpath(public_path('build'));
    $assetPath = realpath(public_path('build/'.$path));

    abort_unless(
        $buildRoot !== false
            && $assetPath !== false
            && str_starts_with($assetPath, $buildRoot.DIRECTORY_SEPARATOR)
            && is_file($assetPath),
        404
    );

    $extension = strtolower(pathinfo($assetPath, PATHINFO_EXTENSION));
    $contentType = match ($extension) {
        'js', 'mjs' => 'application/javascript; charset=UTF-8',
        'css' => 'text/css; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'ico' => 'image/x-icon',
        'txt' => 'text/plain; charset=UTF-8',
        default => mime_content_type($assetPath) ?: 'application/octet-stream',
    };

    return response()->file($assetPath, [
        'Content-Type' => $contentType,
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->where('path', '.*');

foreach ([
    'favicon.ico' => 'image/x-icon',
    'favicon.svg' => 'image/svg+xml',
    'apple-touch-icon.png' => 'image/png',
    'robots.txt' => 'text/plain; charset=UTF-8',
] as $publicAsset => $contentType) {
    Route::get('/'.$publicAsset, fn () => response()->file(public_path($publicAsset), [
        'Content-Type' => $contentType,
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]));
}

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegistrationRequestController::class, 'create'])->name('register');
    Route::post('/register', [RegistrationRequestController::class, 'store'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::redirect('/', '/dashboard')->name('home');

    Route::livewire('/dashboard', 'pages::tcg.dashboard')->name('dashboard');
    Route::livewire('/teams', 'pages::tcg.team-index')->name('teams.index');
    Route::livewire('/archetypes', 'pages::tcg.archetype-index')->name('archetypes.index');
    Route::livewire('/decks', 'pages::tcg.deck-index')->name('decks.index');
    Route::livewire('/results', 'pages::tcg.result-index')->name('results.index');
    Route::livewire('/stats', 'pages::tcg.stats-index')->name('stats.index');
    Route::livewire('/admin/registration-requests', 'pages::admin.registration-requests')->name('admin.registration-requests');
});
