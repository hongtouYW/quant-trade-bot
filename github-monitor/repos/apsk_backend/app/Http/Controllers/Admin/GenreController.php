<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class GenreController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('genre_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = DB::table('tbl_genre');
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('genre_name', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDate($query, $request, 'created_on');
            $genres = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
            return view('module.genre.list', ['genres' => $genres]);
        } catch (\Exception $e) {
            Log::error("Error fetching genre list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for creating a new genre.
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('genre_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        return view('module.genre.create', []);
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('genre_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $validator = Validator::make($request->all(), [
            'genre_name' => 'required|string|max:100|unique:tbl_genre,genre_name',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $userId = DB::table('tbl_genre')->insertGetId([
                'genre_name' => $request->input('genre_name'),
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            return redirect()->route('admin.genre.index')->with('success', __('genre.genre_added_successfully'));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error adding genre: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for editing the specified genre.
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('genre_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $genre = DB::table('tbl_genre')->where('genre_name', $id)->first();
        if (!$genre) {
            return redirect()->route('admin.genre.index')->with('error', __('messages.nodata'));
        }
        return view('module.genre.edit', compact('genre'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('genre_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $genre = DB::table('tbl_genre')->where('genre_name', $id)->first();
        if (!$genre) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        $validator = Validator::make($request->all(), [
            'genre_name' => ['required', 'string', 'max:100', Rule::unique('tbl_genre', 'genre_name')->ignore($id, 'genre_name')],
            'status' => 'nullable|in:1,0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $updateData = [
                'genre_name' => $request->input('genre_name'),
                'status' => $request->filled('status') ? $request->input('status'): 0,
                'updated_on' => now(),
            ];
            DB::table('tbl_genre')->where('genre_name', $id)->update($updateData);
            return redirect()->route('admin.genre.index')->with('success', __('genre.genre_updated_successfully'));
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating genre: " . $e->getMessage());
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
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('genre_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $genre = DB::table('tbl_genre')->where('genre_name', $id)->first();
        if (!$genre) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        try {
            DB::table('tbl_genre')->where('genre_name', $id)->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);

            return redirect()->route('admin.genre.index')->with('success', __('genre.genre_deleted_successfully', ['genrename' => $genre->genre_name]));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting genre: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
