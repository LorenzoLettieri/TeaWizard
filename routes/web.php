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

    return response()->file($assetPath, [
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->where('path', '.*');

foreach (['favicon.ico', 'favicon.svg', 'apple-touch-icon.png', 'robots.txt'] as $publicAsset) {
    Route::get('/'.$publicAsset, fn () => response()->file(public_path($publicAsset)));
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
