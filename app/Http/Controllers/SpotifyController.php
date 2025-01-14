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

        // Search the first album by query.
        $albums = Spotify::searchAlbums('DAMN.')->get();
        $album_id = $albums['albums']['items'][0]['id'];
        $tracks = Spotify::albumTracks($album_id)->get();

        // Fetch detailed track data for the first album
        $tracks_array = [];
        foreach ($tracks['items'] as $track) {
            $track_info = Spotify::track($track['id'])->get();
            $tracks_array[] = $track_info; // Add detailed track data
        }

        // Search the second album by query.
        $albums = Spotify::searchAlbums('New World Depression')->get();
        $album_id = $albums['albums']['items'][0]['id'];
        $tracks = Spotify::albumTracks($album_id)->get();

        // Fetch detailed track data for the second album
        foreach ($tracks['items'] as $track) {
            $track_info = Spotify::track($track['id'])->get();
            $tracks_array[] = $track_info; // Add detailed track data
        }

                // Search the second album by query.
                $albums = Spotify::searchAlbums('Quintessential')->get();
                $album_id = $albums['albums']['items'][0]['id'];
                $tracks = Spotify::albumTracks($album_id)->get();
        
                // Fetch detailed track data for the second album
                foreach ($tracks['items'] as $track) {
                    $track_info = Spotify::track($track['id'])->get();
                    $tracks_array[] = $track_info; // Add detailed track data
                }

        // Return the tracks to the frontend
        return Inertia::render('Index/Spotify', [
            'tracks' => $tracks_array
        ]);
    }

    public function search(Request $request)
    {

        $query = $request->input('query');

        if (!$query) {
            return back()->withErrors(['query' => 'Search query is required.']);
        }

        $spotify = new Spotify();

        // Search albums by query
        $albums = Spotify::searchAlbums($query)->get();

        if (empty($albums['albums']['items'])) {
            return back()->withErrors(['query' => 'No results found for this query.']);
        }

        $album_id = $albums['albums']['items'][0]['id'];

        $tracks = Spotify::albumTracks($album_id)->get();

        // Fetch detailed track data
        $tracks_array = [];
        foreach ($tracks['items'] as $track) {
            $track_info = Spotify::track($track['id'])->get();
            $tracks_array[] = $track_info;
        }

        // Send the new tracks to the frontend
        return Inertia::render('Index/Spotify', [
            'tracks' => $tracks_array
        ]);
    }
}
