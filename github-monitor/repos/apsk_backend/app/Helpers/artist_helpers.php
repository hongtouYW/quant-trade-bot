<?php

use App\Models\Artist;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

if (!function_exists('GetArtistID')) {
    /**
     * Retrieves the artist ID for a given artist name, using caching for performance.
     *
     * @param string $artist_name The name of the artist.
     * @return ?int The ID of the artist name, or null if not found.
     */
    function GetArtistID(string $artist_name): ?int
    {
        if (empty($artist_name)) {
            return null;
        }
        try {
            $tbl_artist = Artist::where('artist_name', $artist_name)->first();
            return $tbl_artist ? (int) $tbl_artist->artist_id : null;
        } catch (\Exception $e) {
            Log::error("GetArtistID - Failed to fetch artist ID for {$artist_name}: {$e->getMessage()}");
            return null;
        }
    }
}

if (!function_exists('GetArtistName')) {
    /**
     * Retrieves the artist Name for a given artist id, using caching for performance.
     *
     * @param int $artist_id The id of the artist.
     * @return ?string The Name of the artist name, or null if not found.
     */
    function GetArtistName(int $artist_id): ?string
    {
        if (empty($artist_id)) {
            return null;
        }
        try {
            $tbl_artist = Artist::where('artist_id', $artist_id)->first();
            return $tbl_artist ? (string) $tbl_artist->artist_name : null;
        } catch (\Exception $e) {
            Log::error("GetArtistName - Failed to fetch artist Name for {$artist_id}: {$e->getMessage()}");
            return null;
        }
    }
}