<?php

use App\Http\Controllers\TextShareController;
use Illuminate\Support\Facades\Route;

// Text Share App - Root URL
Route::get('/', function () {
    return view('text-share.index');
});

// Text Share - Show saved share
Route::get('/s/{hashId}', [TextShareController::class, 'show'])->name('text-share.show');

// Filament admin panel handles all /admin routes automatically
// No need to define custom admin routes here
