<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\SpotifyController;

Route::get('/', [SpotifyController::class, 'index']);
Route::post('/search', [SpotifyController::class, 'search']);
Route::post('/search-preview', [SpotifyController::class, 'searchPreview']);
Route::post('/add-albums', [SpotifyController::class, 'addAlbums']);

// add get routes for search results that just redirect to / incase of a refresh
Route::get('/search-results', function () {
    return redirect('/');
});

Route::get('/add-albums', function () {
    return redirect('/');
});


Route::get('/search-preview', function () {
    return redirect('/');
});
