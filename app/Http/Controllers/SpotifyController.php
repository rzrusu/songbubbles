<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Spotify;
use Illuminate\Support\Facades\Cache;

class SpotifyController extends Controller
{
    public function index()
    {
        // Get or cache daily featured albums
        $tracks_array = Cache::remember('daily_featured_tracks', 86400, function () {
            try {
                $tracks_array = [];
                
                // Get new releases instead of featured playlists
                $new_releases = Spotify::newReleases()
                    ->limit(3)
                    ->get();
                    
                if (!empty($new_releases['albums']['items'])) {
                    foreach ($new_releases['albums']['items'] as $album) {
                        $album_tracks = Spotify::albumTracks($album['id'])->get();
                        
                        foreach ($album_tracks['items'] as $track) {
                            $track_info = Spotify::track($track['id'])->get();
                            $tracks_array[] = $track_info;
                        }
                    }
                }
                
                return $tracks_array;

            } catch (\Exception $e) {
                // Return empty array if something goes wrong
                return [];
            }
        });

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
