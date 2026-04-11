<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Paymentgateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PaymentgatewayController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('payment_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = DB::table('tbl_paymentgateway');
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('payment_name', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('amount_type', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDate($query, $request, 'created_on');
            $paymentgateways = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
            return view('module.paymentgateway.list', ['paymentgateways' => $paymentgateways]);
        } catch (\Exception $e) {
            Log::error("Error fetching paymentgateway list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for creating a new paymentgateway.
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('payment_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        return view('module.paymentgateway.create', []);
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('payment_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $validator = Validator::make($request->all(), [
            'payment_name' => 'required|string|max:255',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'amount_type' => 'nullable|string|max:255',
            'min_amount' => 'nullable|numeric|gte:1.00|required_with:max_amount',
            'max_amount' => 'nullable|numeric|gt:min_amount|required_with:min_amount',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $iconPath = null;
            if ($request->hasFile('icon') && $request->file('icon')->isValid()) {
                $sanitizedName = Str::slug($validator->validated()['payment_name'], '_');
                $extension = $request->file('icon')->getClientOriginalExtension();
                $filename = $sanitizedName.'_' . time() . '.' . $extension;
                $iconPath = $request->file('icon')->storeAs('assets/img/paymentgateway', $filename, 'public');
            }
            $userId = DB::table('tbl_paymentgateway')->insertGetId([
                'payment_name' => $request->input('payment_name'),
                'icon' => $iconPath,
                'amount_type' => $request->input('amount_type') ?? null,
                'min_amount' => $request->filled('min_amount') ? $request->input('min_amount'): 0.00,
                'max_amount' => $request->filled('max_amount') ? $request->input('max_amount'): 0.00,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            return redirect()->route('admin.paymentgateway.index')->with('success', __('paymentgateway.paymentgateway_added_successfully', ['paymentname'=>$request->input('payment_name')]));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error adding paymentgateway: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for editing the specified paymentgateway.
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('payment_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $paymentgateway = DB::table('tbl_paymentgateway')->where('payment_id', $id)->first();
        if (!$paymentgateway) {
            return redirect()->route('admin.paymentgateway.index')->with('error', __('messages.nodata'));
        }
        return view('module.paymentgateway.edit', compact('paymentgateway'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('payment_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $paymentgateway = DB::table('tbl_paymentgateway')->where('payment_id', $id)->first();
        if (!$paymentgateway) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        $validator = Validator::make($request->all(), [
            'payment_name' => 'required|string|max:255',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'amount_type' => 'nullable|string|max:255',
            'min_amount' => 'nullable|numeric|gte:1.00|required_with:max_amount',
            'max_amount' => 'nullable|numeric|gt:min_amount|required_with:min_amount',
            'status' => 'nullable|in:1,0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $iconPath = $paymentgateway->icon;
            if ($request->hasFile('icon') && $request->file('icon')->isValid()) {
                if ($paymentgateway->icon && Storage::disk('public')->exists($paymentgateway->icon)) {
                    Storage::disk('public')->delete($paymentgateway->icon);
                }
                $sanitizedName = Str::slug($validator->validated()['payment_name'], '_');
                $extension = $request->file('icon')->getClientOriginalExtension();
                $filename = $sanitizedName.'_' . time() . '.' . $extension;
                $iconPath = $request->file('icon')->storeAs('assets/img/paymentgateway', $filename, 'public');
            }
            $updateData = [
                'payment_name' => $request->input('payment_name'),
                'icon' => $iconPath,
                'amount_type' => $request->input('amount_type') ?? null,
                'min_amount' => $request->filled('min_amount') ? $request->input('min_amount'): 0.00,
                'max_amount' => $request->filled('max_amount') ? $request->input('max_amount'): 0.00,
                'status' => $request->filled('status') ? $request->input('status'): 0,
                'updated_on' => now(),
            ];
            DB::table('tbl_paymentgateway')->where('payment_id', $id)->update($updateData);
            return redirect()->route('admin.paymentgateway.index')->with('success', __('paymentgateway.paymentgateway_updated_successfully', ['paymentname'=>$request->input('payment_name')]));
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating paymentgateway: " . $e->getMessage());
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
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('payment_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $paymentgateway = DB::table('tbl_paymentgateway')->where('payment_id', $id)->first();
        if (!$paymentgateway) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        try {
            DB::table('tbl_paymentgateway')->where('payment_id', $id)->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);

            return redirect()->route('admin.paymentgateway.index')->with('success', __('paymentgateway.paymentgateway_deleted_successfully', ['paymentname'=>$paymentgateway->payment_name]));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting paymentgateway: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
