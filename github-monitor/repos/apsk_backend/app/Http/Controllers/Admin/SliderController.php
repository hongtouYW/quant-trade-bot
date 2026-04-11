<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Slider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SliderController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('slider_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = Slider::query();
            if ($authorizedUser->user_role !== 'masteradmin') {
                $query->where('agent_id', $authorizedUser->agent_id);
            }
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('slider_desc', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDateEloquent($query, $request, 'created_on');
            $sliders = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
            return view('module.slider.list', ['sliders' => $sliders]);
        } catch (\Exception $e) {
            Log::error("Error fetching slider list: " . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to retrieve slider list: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for creating a new slider.
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('slider_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $langs = config('languages.supported');
        return view('module.slider.create', compact('langs') );
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('slider_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:100',
            'slider_desc' => 'nullable|string|max:10000',
            'language' => 'required|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $userId = DB::table('tbl_slider')->insertGetId([
                'title' => $request->input('title'),
                'slider_desc' => $request->input('slider_desc') ?? null,
                'lang' => $request->input('language'),
                'agent_id' => $authorizedUser->agent_id,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            return redirect()->route('admin.slider.index')->with('success', __('slider.slider_added_successfully', ['title' => $request->input('title')]));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error adding slider: " . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Failed to add slider: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified slider.
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('slider_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $slider = DB::table('tbl_slider')->where('slider_id', $id)->first();
        if (!$slider) {
            return redirect()->route('admin.slider.index')->with('error', 'slider not found.');
        }
        $langs = config('languages.supported');
        return view('module.slider.edit', compact('slider','langs'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('slider_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $tbl_slider = Slider::where('slider_id', $id)->first();
        if (!$tbl_slider) {
            return redirect()->back()->with('error', __('slider.no_data_found'));
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:100',
            'slider_desc' => 'nullable|string|max:10000',
            'language' => 'required|string',
            'status' => 'nullable|in:1,0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $tbl_slider->update([
                'title' => $request->input('title'),
                'slider_desc' => $request->input('slider_desc') ?? null,
                'lang' => $request->input('language'),
                'status' => $request->filled('status') ? $request->input('status'): 0,
                'updated_on' => now(),
            ]);
            $tbl_slider = $tbl_slider->fresh();
            return redirect()->route('admin.slider.index')->with('success', __('slider.slider_updated_successfully', ['title' => $request->input('title')]));
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating slider: " . $e->getMessage());
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
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('slider_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $tbl_slider = Slider::where('slider_id', $id)->first();
        if (!$tbl_slider) {
            return redirect()->back()->with('error', __('slider.no_data_found'));
        }

        try {
            $tbl_slider->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);
            $tbl_slider = $tbl_slider->fresh();
            return redirect()->route('admin.slider.index')->with('success', __('slider.slider_deleted_successfully', ['title' => $slider->title]));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting slider: " . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to delete slider: ' . $e->getMessage());
        }
    }
}
