<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\SpotifyController;

Route::get('/', [SpotifyController::class, 'index']);
Route::post('/search', [SpotifyController::class, 'search']);
