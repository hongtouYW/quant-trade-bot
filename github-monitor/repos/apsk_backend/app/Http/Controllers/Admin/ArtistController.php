<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Countries;
use App\Models\Genre;
use App\Models\Artist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class ArtistController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('artist_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = DB::table('tbl_artist');
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('artist_name', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('album', 'LIKE', '%' . $searchTerm . '%');
                });
                $country_code = GetCountryID($searchTerm);
                $genre_id = GetGenreID($searchTerm);
                if ( $country_code ) {
                    $query->orWhere('country_code', $country_code);
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
            $artists = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
            return view('module.artist.list', ['artists' => $artists]);
        } catch (\Exception $e) {
            Log::error("Error fetching artist list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for creating a new artist.
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('artist_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $countries = Countries::where('status', 1)
              ->where('delete', 0)
              ->orderBy('country_name')
              ->get();
        $genres = Genre::where('status', 1)
              ->where('delete', 0)
              ->orderBy('genre_name')
              ->get();
        return view('module.artist.create', compact('countries', 'genres'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('artist_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $validator = Validator::make($request->all(), [
            'artist_name' => 'required|string|max:100|unique:tbl_artist,artist_name',
            'artist_desc' => 'nullable|string|max:10000',
            'genre_id' => 'required|integer',
            'country_code' => 'required|string|max:3',
            'dob' => 'required|date',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $userId = DB::table('tbl_artist')->insertGetId([
                'artist_name' => $request->input('artist_name'),
                'artist_desc' => $request->input('artist_desc') ?? null,
                'genre_id' => $request->input('genre_id'),
                'country_code' => $request->input('country_code'),
                'dob' => $request->filled('dob') ? Carbon::parse($request->input('dob'))->startOfDay() : now(),
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            return redirect()->route('admin.artist.index')->with('success', __('artist.artist_added_successfully'));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error adding artist: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for editing the specified artist.
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('artist_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $artist = DB::table('tbl_artist')->where('artist_name', $id)->first();
        if (!$artist) {
            return redirect()->route('admin.artist.index')->with('error', __('messages.nodata'));
        }
        $countries = Countries::where('status', 1)
              ->where('delete', 0)
              ->orderBy('country_name')
              ->get();
        $genres = Genre::where('status', 1)
              ->where('delete', 0)
              ->orderBy('genre_name')
              ->get();
        return view('module.artist.edit', compact('artist', 'countries', 'genres'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('artist_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $artist = DB::table('tbl_artist')->where('artist_name', $id)->first();
        if (!$artist) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        $validator = Validator::make($request->all(), [
            'artist_name' => 'required|string|max:100|unique:tbl_artist,artist_name',
            'artist_desc' => 'nullable|string|max:10000',
            'genre_id' => 'required|integer',
            'country_code' => 'required|string|max:3',
            'dob' => 'required|date',
            'status' => 'nullable|in:1,0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $updateData = [
                'artist_name' => $request->input('artist_name'),
                'artist_desc' => $request->input('artist_desc') ?? null,
                'genre_id' => $request->input('genre_id'),
                'country_code' => $request->input('country_code'),
                'dob' => $request->filled('dob') ? Carbon::parse($request->input('dob'))->startOfDay() : now(),
                'status' => $request->filled('status') ? $request->input('status'): 0,
                'updated_on' => now(),
            ];
            DB::table('tbl_artist')->where('artist_id', $id)->update($updateData);
            return redirect()->route('admin.artist.index')->with('success', __('artist.artist_updated_successfully'));
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating artist: " . $e->getMessage());
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
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('artist_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $artist = DB::table('tbl_artist')->where('artist_id', $id)->first();
        if (!$artist) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        try {
            DB::table('tbl_artist')->where('artist_id', $id)->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);

            return redirect()->route('admin.artist.index')->with('success', __('artist.artist_deleted_successfully'));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting artist: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
