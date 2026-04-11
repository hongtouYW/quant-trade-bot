<?php

use App\Models\Genre;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

if (!function_exists('GetGenreID')) {
    /**
     * Retrieves the genre ID for a given genre name, using caching for performance.
     *
     * @param string $genre_name The name of the genre.
     * @return ?int The ID of the genre, or null if not found.
     */
    function GetGenreID(string $genre_name): ?int
    {
        if (empty($genre_name)) {
            return null;
        }
        try {
            $tbl_genre = Genre::where('genre_name', $genre_name)->first();
            return $tbl_genre ? (int) $tbl_genre->genre_id : null;
        } catch (\Exception $e) {
            Log::error("GetGenreID - Failed to fetch genre ID for {$genre_name}: {$e->getMessage()}");
            return null;
        }
    }
}

if (!function_exists('GetGenreName')) {
    /**
     * Retrieves the genre Name for a given genre id, using caching for performance.
     *
     * @param int $genre_id The id of the genre.
     * @return ?string The Name of the genre, or null if not found.
     */
    function GetGenreName(int $genre_id): ?string
    {
        if (empty($genre_id)) {
            return null;
        }
        try {
            $tbl_genre = Genre::where('genre_id', $genre_id)->first();
            return $tbl_genre ? (string) $tbl_genre->genre_name : null;
        } catch (\Exception $e) {
            Log::error("GetGenreName - Failed to fetch genre Name for {$genre_id}: {$e->getMessage()}");
            return null;
        }
    }
}