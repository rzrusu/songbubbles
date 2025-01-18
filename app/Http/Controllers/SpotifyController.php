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

    public function searchPreview(Request $request)
    {
        $query = $request->input('query');
        $currentTracks = $request->input('currentTracks', []);

        if (!$query) {
            return Inertia::render('Index/Spotify', [
                'tracks' => $currentTracks,
                'results' => []
            ]);
        }

        // Search albums by query
        $albums = Spotify::searchAlbums($query)->get();

        return Inertia::render('Index/Spotify', [
            'tracks' => $currentTracks,
            'results' => $albums['albums']['items'] ?? []
        ]);
    }

    public function addAlbums(Request $request)
    {
        $albumIds = $request->input('albumIds');
        $new_tracks = [];

        foreach ($albumIds as $albumId) {
            // Get tracks for each album
            $tracks = Spotify::albumTracks($albumId)->get();

            // Fetch detailed track data
            foreach ($tracks['items'] as $track) {
                $track_info = Spotify::track($track['id'])->get();
                $new_tracks[] = $track_info;
            }
        }

        // Get current tracks from the request
        $current_tracks = $request->input('currentTracks', []);
        
        // Merge current tracks with new tracks
        $all_tracks = array_merge($current_tracks, $new_tracks);

        // Return to the view with all tracks
        return Inertia::render('Index/Spotify', [
            'tracks' => $all_tracks
        ]);
    }
}
