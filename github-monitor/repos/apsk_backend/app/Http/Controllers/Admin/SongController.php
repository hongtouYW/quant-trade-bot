<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Song;
use App\Models\Genre;
use App\Models\Artist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class SongController extends Controller
{
    public function __construct()
    {
    }

    public function index(Request $request)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('song_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = DB::table('tbl_song');
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('song_name', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('album', 'LIKE', '%' . $searchTerm . '%');
                });
                $artist_id = GetArtistID($searchTerm);
                $creator = GetArtistID($searchTerm);
                $genre_id = GetGenreID($searchTerm);
                if ( $artist_id ) {
                    $query->orWhere('artist_id', $artist_id);
                }
                if ( $creator ) {
                    $query->orWhere('creator', $creator);
                }
                if ( $genre_id ) {
                    $query->where('genre_id', $genre_id);
                }
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDate($query, $request, 'created_on');
            $songs = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
            return view('module.song.list', ['songs' => $songs]);
        } catch (\Exception $e) {
            Log::error("Error fetching song list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for creating a new song.
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('song_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $genres = Genre::where('status', 1)
              ->where('delete', 0)
              ->orderBy('genre_name')
              ->get();
        $artists = Artist::where('status', 1)
              ->where('delete', 0)
              ->orderBy('artist_name')
              ->get();
        return view('module.song.create', compact('genres', 'artists'));
    }

    /**
     * Store a newly created user in storage.
     * Corresponds to your API's 'add' method logic.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('song_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $validator = Validator::make($request->all(), [
            'song_name' => 'required|string|max:100|unique:tbl_song,song_name',
            'artist_id' => 'nullable|string|max:255',
            'creator' => 'nullable|string|max:255',
            'genre_id' => 'required|integer',
            'album' => 'required|string|max:255',
            'published_on' => 'required|date',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $userId = DB::table('tbl_song')->insertGetId([
                'song_name' => $request->input('song_name'),
                'artist_id' => $request->input('artist_id') ?? null,
                'creator' => $request->input('creator') ?? null,
                'genre_id' => $request->input('genre_id'),
                'album' => $request->input('album'),
                'published_on' => $request->filled('published_on') ? Carbon::parse($request->input('published_on'))->startOfDay() : now(),
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            return redirect()->route('admin.song.index')->with('success', __('song.song_added_successfully'));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error adding song: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for editing the specified song.
     *
     * @param  int  $id
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function edit($id)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('song_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $song = DB::table('tbl_song')->where('song_name', $id)->first();
        if (!$song) {
            return redirect()->route('admin.song.index')->with('error', __('messages.nodata'));
        }
        $genres = Genre::where('status', 1)
              ->where('delete', 0)
              ->orderBy('genre_name')
              ->get();
        $artists = Artist::where('status', 1)
              ->where('delete', 0)
              ->orderBy('artist_name')
              ->get();
        return view('module.song.edit', compact('song', 'genres', 'artists'));
    }

    /**
     * Update the specified user in storage.
     * Corresponds to your API's 'edit' method logic.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, $id)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('song_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $song = DB::table('tbl_song')->where('song_name', $id)->first();
        if (!$song) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        $validator = Validator::make($request->all(), [
            'song_name' => 'required|string|max:100|unique:tbl_song,song_name',
            'artist_id' => 'nullable|string|max:255',
            'creator' => 'nullable|string|max:255',
            'genre_id' => 'required|integer',
            'album' => 'required|string|max:255',
            'published_on' => 'required|date',
            'status' => 'nullable|in:1,0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $updateData = [
                'song_name' => $request->input('song_name'),
                'artist_id' => $request->input('artist_id') ?? null,
                'creator' => $request->input('creator') ?? null,
                'genre_id' => $request->input('genre_id'),
                'album' => $request->input('album'),
                'published_on' => $request->filled('published_on') ? Carbon::parse($request->input('published_on'))->startOfDay() : now(),
                'status' => $request->filled('status') ? $request->input('status'): 0,
                'updated_on' => now(),
            ];
            DB::table('tbl_song')->where('song_id', $id)->update($updateData);
            return redirect()->route('admin.song.index')->with('success', __('song.song_updated_successfully'));
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating song: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Remove the specified user from storage (soft delete).
     * Corresponds to your API's 'delete' method logic.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('song_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $song = DB::table('tbl_song')->where('song_id', $id)->first();
        if (!$song) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        try {
            DB::table('tbl_song')->where('song_id', $id)->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);

            return redirect()->route('admin.song.index')->with('success', __('song.song_deleted_successfully'));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting song: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
