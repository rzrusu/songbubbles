<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Spotify;

class SpotifyController extends Controller
{
    public function index()
    {

        $spotify = new Spotify();

        // Search albums by query.
        $albums = Spotify::searchAlbums('New World Depression')->get();

        //dd($albums);    
        
        $album_id = $albums['albums']['items'][0]['id'];

        $tracks = Spotify::albumTracks($album_id)->get();

        //dd($tracks);    
        
        return Inertia::render('Index/Spotify', [
            'tracks' => $tracks
        ]);
    }
}
