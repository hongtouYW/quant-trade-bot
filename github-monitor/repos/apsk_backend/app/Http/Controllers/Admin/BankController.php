<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Access;
use App\Models\Bank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BankController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('bank_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = DB::table('tbl_bank');
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('bank_name', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('icon', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('api', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDate($query, $request, 'created_on');
            $banks = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
            return view('module.bank.list', ['banks' => $banks]);
        } catch (\Exception $e) {
            Log::error("Error fetching bank list: " . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to retrieve bank list: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for creating a new bank.
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('bank_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        return view('module.bank.create', []);
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('bank_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:255',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'api' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $iconPath = null;
            if ($request->hasFile('icon') && $request->file('icon')->isValid()) {
                $sanitizedName = Str::slug($validator->validated()['bank_name'], '_');
                $extension = $request->file('icon')->getClientOriginalExtension();
                $filename = $sanitizedName.'_' . time() . '.' . $extension;
                $iconPath = $request->file('icon')->storeAs('assets/img/bank', $filename, 'public');
            }
            $userId = DB::table('tbl_bank')->insertGetId([
                'bank_name' => $request->input('bank_name'),
                'icon' => $iconPath,
                'api' => $request->input('api') ?? null,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            return redirect()->route('admin.bank.index')->with('success', __('bank.bank_added_successfully',['bank_name' => $request->input('bank_name')]));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error adding bank: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for editing the specified bank.
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('bank_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $bank = DB::table('tbl_bank')->where('bank_id', $id)->first();
        if (!$bank) {
            return redirect()->route('admin.bank.index')->with('error', __('messages.nodata'));
        }
        return view('module.bank.edit', compact('bank'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('bank_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $bank = DB::table('tbl_bank')->where('bank_id', $id)->first();
        if (!$bank) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:255',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'api' => 'nullable|string|max:255',
            'status' => 'nullable|in:1,0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $iconPath = $bank->icon;
            if ($request->hasFile('icon') && $request->file('icon')->isValid()) {
                if ($bank->icon && Storage::disk('public')->exists($bank->icon)) {
                    Storage::disk('public')->delete($bank->icon);
                }
                $sanitizedName = Str::slug($validator->validated()['bank_name'], '_');
                $extension = $request->file('icon')->getClientOriginalExtension();
                $filename = $sanitizedName.'_' . time() . '.' . $extension;
                $iconPath = $request->file('icon')->storeAs('assets/img/bank', $filename, 'public');
            }
            $updateData = [
                'bank_name' => $request->input('bank_name'),
                'icon' => $iconPath,
                'api' => $request->input('api') ?? null,
                'status' => $request->filled('status') ? $request->input('status'): 0,
                'updated_on' => now(),
            ];
            DB::table('tbl_bank')->where('bank_id', $id)->update($updateData);
            return redirect()->route('admin.bank.index')->with('success', __('bank.bank_updated_successfully', ['bank_name' => $request->input('bank_name')]));
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating bank: " . $e->getMessage());
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
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('bank_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $bank = DB::table('tbl_bank')->where('bank_id', $id)->first();
        if (!$bank) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        try {
            DB::table('tbl_bank')->where('bank_id', $id)->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);

            return redirect()->route('admin.bank.index')->with('success', __('bank.bank_deleted_successfully', ['bank_name' => $bank->bank_name]));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting bank: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
