<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notifications;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('notification_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = Notifications::query()
                                  ->with('Sender','Recipient');
            if ($authorizedUser->user_role !== 'masteradmin') {
                $query->where('agent_id', $authorizedUser->agent_id);
            }
            if ($request->filled('search')) {
                $searchTerm = $request->search;

                $query->leftJoin('tbl_shop as s_shop', function ($join) {
                    $join->on('tbl_notification.sender_id', '=', 's_shop.shop_id')
                        ->where('tbl_notification.sender_type', 'shop');
                });

                $query->leftJoin('tbl_manager as s_manager', function ($join) {
                    $join->on('tbl_notification.sender_id', '=', 's_manager.manager_id')
                        ->where('tbl_notification.sender_type', 'manager');
                });

                $query->leftJoin('tbl_member as s_member', function ($join) {
                    $join->on('tbl_notification.sender_id', '=', 's_member.member_id')
                        ->where('tbl_notification.sender_type', 'member');
                });

                $query->leftJoin('tbl_shop as r_shop', function ($join) {
                    $join->on('tbl_notification.recipient_id', '=', 'r_shop.shop_id')
                        ->where('tbl_notification.recipient_type', 'shop');
                });

                $query->leftJoin('tbl_manager as r_manager', function ($join) {
                    $join->on('tbl_notification.recipient_id', '=', 'r_manager.manager_id')
                        ->where('tbl_notification.recipient_type', 'manager');
                });

                $query->leftJoin('tbl_member as r_member', function ($join) {
                    $join->on('tbl_notification.recipient_id', '=', 'r_member.member_id')
                        ->where('tbl_notification.recipient_type', 'member');
                });

                $query->where(function ($q) use ($searchTerm) {
                    $q->where('notification_type', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('title', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('notification_desc', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('s_shop.shop_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('s_manager.manager_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('s_member.member_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('r_shop.shop_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('r_manager.manager_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('r_member.member_name', 'LIKE', "%{$searchTerm}%");
                });

                $query->select('tbl_notification.*'); // prevent column conflict
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDateEloquent($query, $request, 'created_on');
            $notifications = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
            return view('module.notification.list', ['notifications' => $notifications]);
        } catch (\Exception $e) {
            Log::error("Error fetching notification list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error').":".$e->getMessage());
        }
    }

    /**
     * Show the form for creating a new notification.
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('notification_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        return view('module.notification.create', []);
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('notification_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $validator = Validator::make($request->all(), [
            'notification_type' => 'required|string|max:50|in:admin,member,manager,shop,event,version,game,alert', //管理员分类，经理分类，角色分类，新上线活动，版本更新，新游戏
            'title' => 'required|string|max:50',
            'notification_desc' => 'nullable|string|max:10000',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $userId = DB::table('tbl_notification')->insertGetId([
                'notification_type' => $request->input('notification_type'),
                'title' => $request->input('title'),
                'notification_desc' => $request->input('notification_desc') ?? null,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            return redirect()->route('admin.notification.index')->with('success', __('notification.notification_added_successfully'));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error adding notification: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for editing the specified notification.
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('notification_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $notification = DB::table('tbl_notification')->where('notification_id', $id)->first();
        if (!$notification) {
            return redirect()->route('admin.notification.index')->with('error', __('messages.nodata'));
        }
        return view('module.notification.edit', compact('notification'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('notification_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $notification = DB::table('tbl_notification')->where('notification_id', $id)->first();
        if (!$notification) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        $validator = Validator::make($request->all(), [
            'notification_type' => 'required|string|max:50|in:admin,member,manager,shop,event,version,game,alert', //管理员分类，经理分类，角色分类，新上线活动，版本更新，新游戏
            'title' => 'required|string|max:50',
            'notification_desc' => 'nullable|string|max:10000',
            'status' => 'nullable|in:1,0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $updateData = [
                'notification_name' => $request->input('notification_name'),
                'notification_type' => $request->input('notification_type'),
                'title' => $request->input('title'),
                'notification_desc' => $request->input('notification_desc') ?? null,
                'status' => $request->filled('status') ? $request->input('status'): 0,
                'updated_on' => now(),
            ];
            DB::table('tbl_notification')->where('notification_id', $id)->update($updateData);
            return redirect()->route('admin.notification.index')->with('success', __('notification.notification_updated_successfully'));
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating notification: " . $e->getMessage());
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
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('notification_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $notification = DB::table('tbl_notification')->where('notification_id', $id)->first();
        if (!$notification) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        try {
            DB::table('tbl_notification')->where('notification_id', $id)->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);

            return redirect()->route('admin.notification.index')->with('success', __('notification.notification_deleted_successfully'));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting notification: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
