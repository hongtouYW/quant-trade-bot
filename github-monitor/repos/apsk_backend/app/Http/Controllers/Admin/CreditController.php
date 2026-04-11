<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Credit;
use App\Models\Member;
use App\Models\User;
use App\Models\Manager;
use App\Models\Shop;
use App\Models\Paymentgateway;
use App\Models\Notifications;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class CreditController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('credit_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $tab = $request->get('tab', 'complete');
            $query = Credit::query()
                            ->with('Member','Bankaccount','Bankaccount.Bank','Shop','Paymentgateway');
            if ($authorizedUser->user_role !== 'masteradmin') {
                $query->where('agent_id', $authorizedUser->agent_id);
            }
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('credit_id', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('member_id', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('shop_id', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('orderid', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('type', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('reason', 'LIKE', '%' . $searchTerm . '%');
                    // 🔗 Member relation
                    $q->orWhereHas('Member', function ($mq) use ($searchTerm) {
                        $mq->where('member_name', 'LIKE', "%{$searchTerm}%");
                    });
                    // 🔗 Shop relation
                    $q->orWhereHas('Shop', function ($sq) use ($searchTerm) {
                        $sq->where('shop_name', 'LIKE', "%{$searchTerm}%");
                    });
                });
            }
            if ($request->filled('type')) {
                $query->where('type', $request->input('type'));
            }
            $paymentgateways = Paymentgateway::where('status',1)->get();
            if ($request->filled('payment_id')) {
                $query->where('payment_id', $request->input('payment_id'));
            }
            if ($request->filled('submit_from') && $request->filled('submit_to')) {
                $query->whereBetween('submit_on', [
                    Carbon::parse($request->submit_from)->startOfDay(),
                    Carbon::parse($request->submit_to)->endOfDay(),
                ]);
            }
            if ($request->filled('approve_from') && $request->filled('approve_to')) {
                $query->whereBetween('updated_on', [
                    Carbon::parse($request->approve_from)->startOfDay(),
                    Carbon::parse($request->approve_to)->endOfDay(),
                ]);
            }
            if ($request->filled('agent_name')) {
                $query->whereHas('Agent', function ($q) use ($request) {
                    $q->where('agent_name', 'LIKE', '%' . $request->agent_name . '%');
                });
            }
            if ($request->filled('shop_name')) {
                $query->whereHas('Shop', function ($q) use ($request) {
                    $q->where('shop_name', 'LIKE', '%' . $request->shop_name . '%');
                });
            }
            // 🔥 TAB FILTER
            if ($tab === 'pending') {
                $query->where('status', 0);
            } else {
                if ($request->filled('status')) {
                    $query->where('status', $request->input('status'));
                } else {
                    $query->where('status', '!=', 0);
                }
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDateEloquent($query, $request, 'created_on');
            $credits = $query
                ->orderBy('created_on', 'desc')
                ->paginate(10)
                ->appends($request->all());
            $completeCount = Credit::where('status', 1)->where('delete', 0);
            if ($authorizedUser->user_role !== 'masteradmin') {
                $completeCount = $completeCount->where('agent_id', $authorizedUser->agent_id);
            }
            $completeCount = $completeCount->count();
            $pendingCount = Credit::where('status', 0)->where('delete', 0);
            if ($authorizedUser->user_role !== 'masteradmin') {
                $pendingCount = $pendingCount->where('agent_id', $authorizedUser->agent_id);
            }
            $pendingCount = $pendingCount->count();
            $failCount = Credit::where('status', -1)->where('delete', 0);
            if ($authorizedUser->user_role !== 'masteradmin') {
                $failCount = $failCount->where('agent_id', $authorizedUser->agent_id);
            }
            $failCount = $failCount->count();
            return view('module.credit.list', compact('credits', 'tab', 'completeCount', 'pendingCount', 'failCount', 'paymentgateways'));
        } catch (\Exception $e) {
            Log::error("Error fetching credit list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error').":".$e->getMessage() );
        }
    }

    /**
     * Show the form for editing the specified credit.
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('credit_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $credit = Credit::where('credit_id', $id)
                        ->with('Member','Bankaccount','Bankaccount.Bank','Shop','Paymentgateway')
                        ->first();
        if (!$credit) {
            return redirect()->route('admin.credit.index')->with('error', __('messages.nodata'));
        }
        return view('module.credit.edit', compact('credit'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('credit_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:10000',
            'status' => 'required|integer|in:-1,1', //fail, success
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        if ( $request->input('status') < 0 ) {
            if (!$request->filled('reason')) {
                return redirect()->back()->withInput()->with('error', __('messages.needreason') );
            }
        }
        try {
            $tbl_credit = Credit::where('credit_id', $id)
                            ->with('Member','Bankaccount','Bankaccount.Bank')
                            ->first();
            if (!$tbl_credit) {
                return redirect()->back()->with('error', __('credit.no_data_found'));
            }
            if ( !in_array( $tbl_credit->type, ['deposit','withdraw'] ) ) {
                return redirect()->back()->with('error', __('credit.no_data_found'));
            }
            if (!$tbl_credit->Member) {
                return redirect()->back()->with('error', __('member.no_data_found'));
            }
            if ($tbl_credit->Member->status !== 1 || $tbl_credit->Member->delete === 1 ||
                $tbl_credit->Member->alarm === 1 ) {
                return redirect()->back()->with('error', __('member.member_id') . " " . __('member.Inactive') );
            }
            $balance = (float) $tbl_credit->Member->balance;
            $amount = (float) $tbl_credit->amount;
            $tbl_credit->update([
                'reason' => $request->input('reason') ?? null,
                'status' => $request->input('status'), //fail pending success
                'updated_on' => now(),
            ]);
            $tbl_credit = $tbl_credit->fresh();
            switch ($tbl_credit->type) {
                case 'deposit':
                    if ( intval( $request->input('status') ) === 1 ) {
                        $tbl_credit->Member->increment('balance', $amount, [
                            'updated_on' => now(),
                        ]);
                        $tbl_member = $tbl_credit->Member->fresh();
                        // VIP Score
                        $tbl_score = AddScore( $tbl_member, 'deposit', $amount );
                        // Commission
                        $salestarget = AddCommission( $tbl_member, $amount );
                    }
                    return redirect()->route('admin.credit.index')->with('success', __('credit.credit_updated_successfully', ['credit_id'=>$id]));
                    break;
                case 'withdraw':
                    if ( intval( $request->input('status') ) === -1 ) {
                        $tbl_credit->Member->increment('balance', $amount, [
                            'updated_on' => now(),
                        ]);
                    }
                    break;
                default:
                    break;
            }
            // Notifications withdraw submit
            $notification_desc = NotificationDesc(
                $tbl_credit->status === 1 ? 
                    'notification.withdraw_submit_desc_success' :
                    'notification.withdraw_submit_desc_fail',
                [
                    'bank_name'=>optional($tbl_credit->Bankaccount?->Bank)->bank_name,
                    'bank_account'=>optional($tbl_credit->Bankaccount)->bank_account,
                    'credit_id'=>$tbl_credit->credit_id,
                    'reason' => $request->input('reason'),
                    'date'=>Carbon::now()->format('d/m/Y'),
                    'time'=>Carbon::now()->format('h:iA'),
                ]
            );
            $tbl_notification = Notifications::create([
                'recipient_id' => $tbl_credit->Member->member_id,
                'recipient_type' => 'member',
                'title' => $tbl_credit->status === 1 ? 'notification.withdraw_submit_success' : 
                    'notification.withdraw_submit_fail',
                'notification_type' => "member",
                'notification_desc' => $notification_desc,
                'agent_id' => $tbl_credit->Member->agent_id,
                'status' => 0,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            return redirect()->route('admin.credit.index')->with('success', __('credit.credit_updated_successfully', ['credit_id'=>$id]));
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating credit: " . $e->getMessage());
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
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('credit_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $credit = DB::table('tbl_credit')->where('credit_id', $id)->first();
        if (!$credit) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        try {
            DB::table('tbl_credit')->where('credit_id', $id)->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);

            return redirect()->route('admin.credit.index')->with('success', __('credit.credit_deleted_successfully', ['credit_id'=>$id]));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting credit: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
