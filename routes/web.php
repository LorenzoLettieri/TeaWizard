<?php

use App\Http\Controllers\Auth\RegistrationRequestController;
use Illuminate\Support\Facades\Route;

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
