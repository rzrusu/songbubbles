<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Index/View');
});

Route::get('/spotify', function () {
    return Inertia::render('Index/Spotify');
});
