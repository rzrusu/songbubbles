<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Spotify;
use Illuminate\Support\Facades\Cache;
use Debugbar;
class SpotifyController extends Controller
{
    public function index()
    {
        Debugbar::addMessage('Hello world!');
        // Get or cache daily featured albums
        $tracks_array = Cache::remember('daily_featured_tracks', 43200, function () {
            try {
                Debugbar::addMessage('test');
                Debugbar::startMeasure('spotify_total', 'Total Spotify API Processing');
                $tracks_array = [];
                
                // Get new releases instead of featured playlists
                Debugbar::info('Fetching new releases from Spotify API...');
                Debugbar::startMeasure('new_releases', 'Fetch New Releases');
                $new_releases = Spotify::newReleases()
                    ->limit(3)
                    ->get();
                Debugbar::stopMeasure('new_releases');
                    
                if (!empty($new_releases['albums']['items'])) {
                    Debugbar::startMeasure('process_albums', 'Process Albums');
                    foreach ($new_releases['albums']['items'] as $album) {
                        Debugbar::startMeasure('album_' . $album['id'], 'Fetch Album ' . $album['id']);
                        $album_tracks = Spotify::albumTracks($album['id'])->get();
                        Debugbar::stopMeasure('album_' . $album['id']);
                        
                        foreach ($album_tracks['items'] as $track) {
                            Debugbar::startMeasure('track_' . $track['id'], 'Fetch Track ' . $track['id']);
                            $track_info = Spotify::track($track['id'])->get();
                            Debugbar::stopMeasure('track_' . $track['id']);
                            $tracks_array[] = $track_info;
                        }
                    }
                    Debugbar::stopMeasure('process_albums');
                }
                
                Debugbar::stopMeasure('spotify_total');
                Debugbar::info('Total tracks processed: ' . count($tracks_array));
                return $tracks_array;

            } catch (\Exception $e) {
                Debugbar::error('Spotify API Error: ' . $e->getMessage());
                return [];
            }
        });

        return Inertia::render('Index/Index', [
            'tracks' => $tracks_array
        ]);
    }

    public function search(Request $request)
    {
        Debugbar::startMeasure('search_total', 'Total Search Processing');
        $query = $request->input('query');

        if (!$query) {
            Debugbar::info('Search aborted: Empty query');
            return back()->withErrors(['query' => 'Search query is required.']);
        }

        // Search albums by query
        Debugbar::info('Searching Spotify for: ' . $query);
        Debugbar::startMeasure('album_search', 'Search Albums');
        $albums = Spotify::searchAlbums($query)->get();
        Debugbar::stopMeasure('album_search');

        if (empty($albums['albums']['items'])) {
            Debugbar::info('No results found for query: ' . $query);
            return back()->withErrors(['query' => 'No results found for this query.']);
        }

        $album_id = $albums['albums']['items'][0]['id'];
        
        Debugbar::startMeasure('fetch_album_tracks', 'Fetch Album Tracks');
        $tracks = Spotify::albumTracks($album_id)->get();
        Debugbar::stopMeasure('fetch_album_tracks');

        // Fetch detailed track data
        Debugbar::startMeasure('process_tracks', 'Process Track Details');
        $tracks_array = [];
        foreach ($tracks['items'] as $track) {
            Debugbar::startMeasure('track_' . $track['id'], 'Fetch Track ' . $track['id']);
            $track_info = Spotify::track($track['id'])->get();
            Debugbar::stopMeasure('track_' . $track['id']);
            $tracks_array[] = $track_info;
        }
        Debugbar::stopMeasure('process_tracks');
        
        Debugbar::info('Total tracks processed: ' . count($tracks_array));
        Debugbar::stopMeasure('search_total');

        // Send the new tracks to the frontend
        return Inertia::render('Index/Index', [
            'tracks' => $tracks_array
        ]);
    }

    public function searchPreview(Request $request)
    {
        $query = $request->input('query');
        $currentTracks = $request->input('currentTracks', []);

        if (!$query) {
            return Inertia::render('Index/Index', [
                'tracks' => $currentTracks,
                'results' => []
            ]);
        }

        // Search albums by query
        $albums = Spotify::searchAlbums($query)->get();

        return Inertia::render('Index/Index', [
            'tracks' => $currentTracks,
            'results' => $albums['albums']['items'] ?? []
        ]);
    }

    public function addAlbums(Request $request)
    {
        Debugbar::startMeasure('add_albums_total', 'Total Add Albums Processing');
        $albumIds = $request->input('albumIds');
        $new_tracks = [];

        Debugbar::info('Processing ' . count($albumIds) . ' albums');

        foreach ($albumIds as $albumId) {
            // Get tracks for each album
            Debugbar::startMeasure('album_' . $albumId, 'Fetch Album ' . $albumId);
            $tracks = Spotify::albumTracks($albumId)->get();
            Debugbar::stopMeasure('album_' . $albumId);

            // Fetch detailed track data
            Debugbar::startMeasure('process_tracks_' . $albumId, 'Process Tracks for Album ' . $albumId);
            foreach ($tracks['items'] as $track) {
                Debugbar::startMeasure('track_' . $track['id'], 'Fetch Track ' . $track['id']);
                $track_info = Spotify::track($track['id'])->get();
                Debugbar::stopMeasure('track_' . $track['id']);
                $new_tracks[] = $track_info;
            }
            Debugbar::stopMeasure('process_tracks_' . $albumId);
        }

        // Get current tracks from the request
        $current_tracks = $request->input('currentTracks', []);
        
        // Merge current tracks with new tracks
        $all_tracks = array_merge($current_tracks, $new_tracks);
        
        Debugbar::info('Total new tracks added: ' . count($new_tracks));
        Debugbar::info('Total tracks after merge: ' . count($all_tracks));
        Debugbar::stopMeasure('add_albums_total');

        // Return to the view with all tracks
        return Inertia::render('Index/Index', [
            'tracks' => $all_tracks
        ]);
    }
}
