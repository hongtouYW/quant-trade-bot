<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Models\Member;
use App\Models\Shop;
use App\Models\Manager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FeedbackController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('feedback_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = Feedback::with(['Member', 'Feedbacktype', 'Shop', 'Manager', 'Agent']);
            if ($authorizedUser->user_role !== 'masteradmin') {
                $query->where('agent_id', $authorizedUser->agent_id);
            }
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'LIKE', '%' . $searchTerm . '%');
                });
                $query->orWhere('member_id', $searchTerm);
                $query->orWhere('shop_id', $searchTerm);
                $query->orWhere('manager_id', $searchTerm);
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDateEloquent($query, $request, 'created_on');
            $feedbacks = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
            return view('module.feedback.list', ['feedbacks' => $feedbacks]);
        } catch (\Exception $e) {
            Log::error("Error fetching feedback list: " . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to retrieve feedback list: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified game.
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('feedback_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $feedback = Feedback::where('feedback_id', $id)
                         ->first();
        if (!$feedback) {
            return redirect()->route('admin.feedback.index')->with('error', __('messages.nodata'));
        }
        $members = Member::where('status', 1)
            ->where('delete', 0);
        if ($authorizedUser->user_role !== 'masteradmin') {
            $members = $members->where('agent_id', $authorizedUser->agent_id);
        }
        $members = $members->orderBy('member_name');
        $members = $members->get();
        $shops = Shop::where('status', 1)
            ->where('delete', 0);
        if ($authorizedUser->user_role !== 'masteradmin') {
            $shops = $shops->where('agent_id', $authorizedUser->agent_id);
        }
        $shops = $shops->orderBy('shop_name');
        $shops = $shops->get();
        $managers = Manager::where('status', 1)
            ->where('delete', 0);
        if ($authorizedUser->user_role !== 'masteradmin') {
            $managers = $managers->where('agent_id', $authorizedUser->agent_id);
        }
        $managers = $managers->orderBy('manager_name');
        $managers = $managers->get();
        $feedback_types = ['gamespeed','controlfeel','leveldifficulty','beginnerguide','others'];
        return view('module.feedback.edit', compact('feedback','members','shops','managers', 'feedback_types'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('feedback_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $validator = Validator::make($request->all(), [
            'feedback_desc' => 'nullable|string|max:10000',
            'feedback_type' => 'nullable|string|max:100',
            'status' => 'required|in:1,0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $tbl_feedback = Feedback::where('feedback_id', $id)->first();
        if (!$tbl_feedback) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }
        $picture = $tbl_feedback->photo;
        if ($request->hasFile('picture') && $request->file('picture')->isValid()) {

            // delete old image
            if ($tbl_feedback->photo && Storage::disk('public')->exists($tbl_feedback->photo)) {
                Storage::disk('public')->delete($tbl_feedback->photo);
            }

            $filename = 'feedback_' . $id . '_' . time() . '.' .
                $request->file('picture')->getClientOriginalExtension();

            $picture = $request->file('picture')->storeAs(
                'assets/img/feedback',
                $filename,
                'public'
            );
        }
        try {
            $tbl_feedback->update([
                'title' => $request->input('feedback_type'),
                'feedback_desc' => $request->input('feedback_desc') ?? null,
                'feedback_type' => $request->input('feedback_type') ?? null,
                'photo'        => $picture,
                'status' => $request->input('status'),
                'updated_on' => now(),
            ]);
            $tbl_feedback = $tbl_feedback->fresh();
            return redirect()->route('admin.feedback.index')->with('success', __('feedback.updated_successfully'));
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating feedback: " . $e->getMessage());
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
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('feedback_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $tbl_feedback = Feedback::where('feedback_id', $id)->first();
        if (!$tbl_feedback) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        try {
            $tbl_feedback->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);
            $tbl_feedback = $tbl_feedback->fresh();

            return redirect()->route('admin.feedback.index')->with('success', __('feedback.deleted_successfully'));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting feedback: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
