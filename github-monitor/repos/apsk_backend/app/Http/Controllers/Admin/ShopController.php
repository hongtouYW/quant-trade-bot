<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Managercredit;
use App\Models\User;
use App\Models\Shop;
use App\Models\Manager;
use App\Models\States;
use App\Models\Areas;
use App\Models\Agent;
use App\Models\Member;
use App\Models\Shopcredit;
use App\Models\Agentcredit;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ShopController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('shop_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = Shop::query()
                            ->with('Areas','Areas.States','Manager','Agent','User');
            if ($authorizedUser->user_role !== 'masteradmin') {
                $query->where('agent_id', $authorizedUser->agent_id);
            } else {
                if ($request->filled('agent_id')) {
                    $query->where('agent_id', $request->input('agent_id') );
                }
            }
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('shop_id', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('shop_login', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('shop_code', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('shop_name', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            if ($request->filled('area_code')) {
                $query->where('area_code', $request->input('area_code') );
            }
            if ($request->filled('manager_id')) {
                $query->where('manager_id', $request->input('manager_id') );
            }
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->input('user_id') );
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDateEloquent($query, $request, 'created_on');
            $query = $query->orderBy('created_on', 'desc');
            $shops = $query->paginate(10)->appends($request->all());
            $agents = [];
            if ($authorizedUser->user_role === 'masteradmin') {
                $agents = Agent::where('status', 1)
                                ->where('delete', 0)
                                ->get();
            }
            $areas = Areas::where('status', 1)
                          ->where('delete', 0)
                          ->get();
            $managers = Manager::where('status', 1)
                               ->where('delete', 0);
            if ($authorizedUser->user_role !== 'masteradmin') {
                $managers = $managers->where('agent_id', $authorizedUser->agent_id);
            }
            $managers = $managers->get();
            $users = User::where('status', 1)
                         ->where('delete', 0);
            if ($authorizedUser->user_role !== 'masteradmin') {
                $users = $users->where('agent_id', $authorizedUser->agent_id);
            }
            $users = $users->get();
            return view(
                'module.shop.list', 
                [
                    'shops' => $shops,
                    'agents' => $agents,
                    'areas' => $areas,
                    'managers' => $managers,
                    'users' => $users,
                ]
            );
        } catch (\Exception $e) {
            Log::error("Error fetching shop list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for creating a new shop.
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('shop_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $managers = Manager::where('status', 1)
                           ->where('delete', 0);
        if ($authorizedUser->user_role !== 'masteradmin') {
            $managers->where('agent_id', $authorizedUser->agent_id);
        }
        $managers = $managers->orderBy('manager_name')->get();
        return view('module.shop.create', compact('managers'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('shop_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $validator = Validator::make($request->all(), [
            'shop_login' => 'required|string|max:255|unique:tbl_shop,shop_login',
            'shop_name' => 'required|string|min:5|max:255',
            'shop_pass' => 'required|string|min:8|max:255',
            'principal' => 'required|numeric|gte:lowestbalance',
            'lowestbalance' => 'nullable|numeric|gt:1.00',
            'manager_id' => 'required|integer',
            'can_deposit'         => 'sometimes|boolean',
            'can_withdraw'        => 'sometimes|boolean',
            'can_create'          => 'sometimes|boolean',
            'can_block'           => 'sometimes|boolean',
            'can_income'          => 'sometimes|boolean',
            'read_clear'          => 'sometimes|boolean',
            'can_view_credential' => 'sometimes|boolean',
            'no_withdrawal_fee'   => 'sometimes|boolean',
            'permission_user' => 'sometimes|array',
            'permission_user.*' => 'exists:tbl_manager,manager_id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $tbl_shop = null;
            DB::transaction(function () use ($request, $authorizedUser, &$tbl_shop) {
                $tbl_manager = Manager::where('manager_id', $request->input('manager_id') )
                    ->lockForUpdate()
                    ->first();
                if (!$tbl_manager) {
                    throw new \Exception(__('manager.no_data_found'));
                }
                if ( $tbl_manager->balance < $request->input('principal') ) {
                    throw new \Exception(__('manager.insufficient'));
                }
                $updateData = [
                    'shop_login' => $request->input('shop_login'),
                    'shop_pass' => encryptPassword( $request->input('shop_pass') ),
                    'shop_name' => $request->input('shop_name'),
                    'area_code' => $tbl_manager->area_code,
                    'principal' => $request->input('principal'),
                    'balance' => $request->input('principal'),
                    'manager_id' => $request->input('manager_id'),
                    'agent_id' => $tbl_manager->agent_id,
                    'status' => 1,
                    'delete' => 0,
                    'created_on' => now(),
                    'updated_on' => now(),
                ];
                $permissions = [
                    'can_deposit',
                    'can_withdraw',
                    'can_create',
                    'can_block',
                    'can_income',
                    'read_clear',
                    'can_view_credential',
                    'no_withdrawal_fee',
                ];
                foreach ($permissions as $perm) {
                    $updateData[$perm] = (int) $request->input($perm, 0);
                }
                $updateData['lowestbalance'] = $request->filled('lowestbalance') ? 
                                               $request->input('lowestbalance') :
                                               $request->input('principal') * 0.5;
                $updateData['lowestbalance_on'] = now();
                $tbl_shop = Shop::create($updateData);
                $tbl_shop = $tbl_shop->fresh();
                Shopcredit::create([
                    'manager_id' => $tbl_manager->manager_id,
                    'shop_id' => $tbl_shop->shop_id,
                    'type' => "shop.edit_shop",
                    'amount' => $tbl_shop->balance,
                    'before_balance' => 0.00,
                    'after_balance' => $tbl_shop->balance,
                    'submit_on' => now(),
                    'agent_id' => $authorizedUser->agent_id,
                    'status' => 1,
                    'delete' => 0,
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
                // Create Managercredit (reverse sign)
                Managercredit::create([
                    'manager_id' => $tbl_manager->manager_id,
                    'user_id' => $authorizedUser->user_id,
                    'type' => "manager.shopedit",
                    'amount' => ReverseDecimal( $tbl_shop->balance ),
                    'before_balance' => $tbl_manager->balance,
                    'after_balance' => $tbl_manager->balance - $tbl_shop->balance,
                    'submit_on' => now(),
                    'agent_id' => $authorizedUser->agent_id,
                    'status' => 1,
                    'delete' => 0,
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
                $tbl_manager->decrement('balance', $tbl_shop->balance, [
                    'updated_on' => now(),
                ]);
                $tbl_manager = $tbl_manager->fresh();
                // Manager Permission
                $permission_users = $request->input('permission_user', []);
                $permission_users[] = $request->manager_id; //include creator
                $permission_users = array_unique($permission_users);
                if (!empty($permission_users)) {
                    $allmanagerpermission = [];
                    foreach ($permission_users as $permission_user) {
                        $allmanagerpermission[] = [
                            'permission_user'   => $permission_user,
                            'user_type'         => 'manager',
                            'permission_target' => $tbl_shop->shop_id,
                            'target_type'       => 'shop',
                            'can_view'          => 1,
                            'can_edit'          => 1,
                            'can_delete'        => 1,
                            'status'            => 1,
                            'delete'            => 0,
                            'created_on'        => now(),
                            'updated_on'        => now(),
                        ];
                    }
                    Permission::insert($allmanagerpermission);
                }
            });
            return redirect()->route('admin.shop.index')
                ->with('success', __('shop.shop_added_successfully',['shop_name'=>$tbl_shop->shop_name]));
        } catch (\Exception $e) {
            Log::error("Error updating shop: " . $e->getMessage());
            return redirect()->back()
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show the form for editing the specified shop.
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('shop_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $shop = Shop::where('shop_id', $id)->with('Areas','Areas.States')->first();
        if (!$shop) {
            return redirect()->route('admin.shop.index')->with('error', __('messages.nodata'));
        }
        $managers = Manager::where('status', 1)
                           ->where('delete', 0)
                           ->where('area_code', $shop->area_code)
                           ->where('agent_id', $shop->agent_id);
        $managers = $managers->orderBy('manager_name')->get();
        $managerpermissions = Permission::where('permission_target', $shop->shop_id)
            ->where('target_type', 'shop')
            ->pluck('permission_user')
            ->toArray();
        return view('module.shop.edit', compact('shop', 'managers', 'managerpermissions'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('shop_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $tbl_shop = Shop::where('shop_id', $id)->first();
        if (!$tbl_shop) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        $validator = Validator::make($request->all(), [
            'shop_login' => ['required', 'string', 'max:255', Rule::unique('tbl_shop', 'shop_login')->ignore($id, 'shop_id')],
            'shop_name' => 'required|string|min:5|max:255',
            'shop_pass' => 'nullable|string|min:8|max:255', // For optional password change
            'principal' => 'required|numeric|gte:lowestbalance',
            'lowestbalance' => 'nullable|numeric|gt:1.00',
            'status' => 'nullable|in:1,0',
            'manager_id' => 'required|integer',
            'permission_user' => 'sometimes|array',
            'permission_user.*' => 'exists:tbl_manager,manager_id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $tbl_shop = null;
            DB::transaction(function () use ($request, $authorizedUser, $id, &$tbl_shop) {
                $tbl_shop = Shop::where('shop_id', $id)
                    ->lockForUpdate()
                    ->first();
                if (!$tbl_shop) {
                    throw new \Exception(__('messages.nodata'));
                }

                $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                    ->lockForUpdate()
                    ->first();
                if (!$tbl_manager) {
                    throw new \Exception(__('manager.no_data_found'));
                }

                $newPrincipal = (float) $request->input('principal');
                $oldPrincipal = (float) $tbl_shop->principal;
                $clearaccount = $newPrincipal - $oldPrincipal;
                if ($clearaccount > 0 && $tbl_manager->balance < $clearaccount) {
                    throw new \Exception(__('manager.insufficient'));
                }
                if ($tbl_shop->balance < ($oldPrincipal - $newPrincipal)){
                    throw new \Exception(__('shop.insufficient_balance'));
                }

                if ($clearaccount !== 0.0) {
                    Shopcredit::create([
                        'manager_id' => $tbl_manager->manager_id,
                        'shop_id' => $tbl_shop->shop_id,
                        'type' => "shop.edit_shop",
                        'amount' => $clearaccount,
                        'before_balance' => $tbl_shop->balance,
                        'after_balance' => $tbl_shop->balance + $clearaccount,
                        'submit_on' => now(),
                        'agent_id' => $authorizedUser->agent_id,
                        'status' => 1,
                        'delete' => 0,
                        'created_on' => now(),
                        'updated_on' => now(),
                    ]);
                    $tbl_shop->update([
                        'balance' => $tbl_shop->balance + $clearaccount,
                        'principal' => $newPrincipal,
                        'updated_on' => now(),
                    ]);

                    // Create Managercredit (reverse sign)
                    Managercredit::create([
                        'manager_id' => $tbl_manager->manager_id,
                        'user_id' => $authorizedUser->user_id,
                        'type' => "manager.shopedit",
                        'amount' => -1 * $clearaccount,
                        'before_balance' => $tbl_manager->balance,
                        'after_balance' => $tbl_manager->balance - $clearaccount,
                        'submit_on' => now(),
                        'agent_id' => $authorizedUser->agent_id,
                        'status' => 1,
                        'delete' => 0,
                        'created_on' => now(),
                        'updated_on' => now(),
                    ]);
                    $tbl_manager->update([
                        'balance' => $tbl_manager->balance - $clearaccount,
                        'updated_on' => now(),
                    ]);
                }

                $updateData = [
                    'shop_login' => $request->input('shop_login'),
                    'shop_name' => $request->input('shop_name'),
                    'area_code' => $tbl_manager->area_code,
                    'manager_id' => $request->input('manager_id'),
                    'agent_id' => $tbl_manager->agent_id,
                    'status' => $request->filled('status') ? $request->input('status') : 0,
                    'updated_on' => now(),
                ];
                if ($request->filled('shop_pass')) {
                    $updateData['shop_pass'] = encryptPassword($request->input('shop_pass'));
                }

                $permissions = [
                    'can_deposit',
                    'can_withdraw',
                    'can_create',
                    'can_block',
                    'can_income',
                    'read_clear',
                    'can_view_credential',
                    'no_withdrawal_fee',
                ];
                foreach ($permissions as $perm) {
                    $updateData[$perm] = (int) $request->input($perm, 0);
                }
                $updateData['lowestbalance'] = $request->filled('lowestbalance') ? 
                                               $request->input('lowestbalance') :
                                               $request->input('principal') * 0.5;
                $updateData['lowestbalance_on'] = now();
                $tbl_shop->update($updateData);
                $tbl_shop = $tbl_shop->fresh();
                // Manager Permission
                $permission_users = $request->input('permission_user', []);
                $permission_users[] = $request->manager_id; //include creator
                $permission_users = array_unique($permission_users);
                // ❗ DELETE OLD PERMISSIONS FIRST
                Permission::where('user_type', 'manager')
                          ->where('target_type', 'shop')
                          ->where('permission_target', $tbl_shop->shop_id)
                          ->delete();
                if (!empty($permission_users)) {
                    $allmanagerpermission = [];
                    foreach ($permission_users as $permission_user) {
                        $allmanagerpermission[] = [
                            'permission_user'   => $permission_user,
                            'user_type'         => 'manager',
                            'permission_target' => $tbl_shop->shop_id,
                            'target_type'       => 'shop',
                            'can_view'          => 1,
                            'can_edit'          => 1,
                            'can_delete'        => 1,
                            'status'            => 1,
                            'delete'            => 0,
                            'created_on'        => now(),
                            'updated_on'        => now(),
                        ];
                    }
                    Permission::insert($allmanagerpermission);
                }
            });
            return redirect()->route('admin.shop.index')
                ->with('success', __('shop.shop_updated_successfully',['shop_name'=>$tbl_shop->shop_name]));

        } catch (\Exception $e) {
            Log::error("Error updating shop: " . $e->getMessage());
            return redirect()->back()
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified user from storage (soft delete).
     * Corresponds to your API's 'post' method logic.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function delete(Request $request, $id)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return response()->json([
                'status' => false,
                'message' => __('messages.Unauthorized'),
                'error' => __('messages.Unauthorized'),
                'code' => 403,
            ], 403);
        }
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('shop_management'))) {
            return response()->json([
                'status' => false,
                'message' => __('messages.Unauthorized'),
                'error' => __('messages.Unauthorized'),
                'code' => 403,
            ], 403);
        }
        $tbl_agent = Agent::where('status', 1)
            ->where('delete', 0)
            ->where('agent_id', $authorizedUser->agent_id)
            ->first();
        if (!$tbl_agent) {
            return response()->json([
                'status' => false,
                'message' => __('agent.no_data_found'),
                'error' => __('agent.no_data_found'),
                'code' => 400,
            ], 400);
        }

        $tbl_shop = Shop::where('shop_id', $id)->first();
        if (!$tbl_shop) {
            return response()->json([
                'status' => false,
                'message' => __('shop.no_data_found'),
                'error' => __('shop.no_data_found'),
                'code' => 400,
            ], 400);
        }

        try {
            if ( (float) $tbl_shop->balance <= 0 ) {
                $tbl_shop->update([
                    'status' => 0, // Set status to inactive
                    'delete' => 1, // Mark as deleted
                    'updated_on' => now(),
                ]);
                return response()->json([
                    'status' => true,
                    'message' => __('shop.shop_deleted_successfully',['shop_name'=>$tbl_shop->shop_name]),
                    'error' => __('shop.shop_deleted_successfully',['shop_name'=>$tbl_shop->shop_name]),
                    'code' => 200,
                ], 200);
            }
            if ( (float) $tbl_shop->balance !== (float) $tbl_shop->principal ) {
                return response()->json([
                    'status' => false,
                    'message' => __('shop.insufficient'),
                    'error' => __('shop.insufficient'),
                    'code' => 400,
                ], 400);
            }

            $tbl_shopcredit = Shopcredit::create([
                'manager_id' => $tbl_shop->manager_id,
                'user_id' => $authorizedUser->user_id,
                'shop_id' => $tbl_shop->shop_id,
                'type' => "shopcredit.userdelete",
                'reason' => $request->reason,
                'amount' => $tbl_shop->balance,
                'before_balance' => $tbl_shop->balance,
                'after_balance' => 0.00,
                'submit_on' => now(),
                'agent_id' => $authorizedUser->agent_id,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);

            $tbl_agentcredit = Agentcredit::create([
                'agent_id' => $authorizedUser->agent_id,
                'user_id' => $authorizedUser->user_id,
                'type' => "shopcredit.userdelete",
                'reason' => $request->reason,
                'amount' => $tbl_shop->balance,
                'before_balance' => $tbl_agent->balance,
                'after_balance' => $tbl_agent->balance + $tbl_shop->balance,
                'submit_on' => now(),
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);

            $tbl_agent->increment('balance', $tbl_shop->balance, [
                'updated_on' => now(),
            ]);

            $tbl_shop->update([
                'balance' => 0.00,
                'reason' => $request->reason,
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);

            return response()->json([
                'status' => true,
                'message' => __('shop.shop_deleted_successfully',['shop_name'=>$tbl_shop->shop_name]),
                'error' => __('shop.shop_deleted_successfully',['shop_name'=>$tbl_shop->shop_name]),
                'code' => 200,
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting shop: " . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => __('messages.api_error'),
                'error' => $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    public function filterarea($state_code)
    {
        try {
            $areas = Areas::filterByState($state_code);
            return response()->json([
                'status' => true,
                'message' => __('messages.list_success'),
                'error' => "",
                'code' => 200,
                'data' => $areas,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Area filter error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => __('messages.api_error'),
                'error' => $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * Reveal the specified shop password. (masteradmin)
     * Corresponds to your API's 'post' method logic.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function revealpassword($id)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return response()->json([
                'status' => true,
                'message' => __('messages.Unauthorized'),
                'error' => __('messages.Unauthorized'),
                'code' => 403,
            ], 403);
        }
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('shop_management'))) {
            return response()->json([
                'status' => true,
                'message' => __('messages.Unauthorized'),
                'error' => __('messages.Unauthorized'),
                'code' => 403,
            ], 403);
        }
        $tbl_shop = Shop::where('shop_id', $id)->first();
        if (!$tbl_shop) {
            return response()->json([
                'status' => true,
                'message' => __('shop.no_data_found'),
                'error' => __('shop.no_data_found'),
                'code' => 400,
            ], 400);
        }
        try {
            return response()->json([
                'status' => true,
                'message' => __('messages.list_success'),
                'error' => "",
                'code' => 200,
                'password' => decryptPassword($tbl_shop->shop_pass),
            ], 200);
        } catch (\Exception $e) {
            Log::error('shop password error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => __('messages.api_error'),
                'error' => $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    public function show(Shop $shop)
    {
        $authorizedUser = Auth::user();

        if (!$authorizedUser) {
            return redirect()->route('dashboard')
                ->with('error', __('messages.Unauthorized'));
        }

        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('shop_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        if ($authorizedUser->user_role !== 'masteradmin'
            && $shop->agent_id !== $authorizedUser->agent_id) {
            abort(403);
        }

        $shop->load(['members', 'shopcredits']);

        $shopcredits = Shopcredit::where('shop_id', $shop->shop_id)
            ->where('delete', 0)
            ->orderByDesc('created_on')
            ->get();

        $members = Member::where('shop_id', $shop->shop_id)
            ->orderByDesc('created_on')
            ->get();

        return view('module.shop.show', compact(
            'shop',
            'shopcredits',
            'members'
        ));
    }

    /**
     * Donload app with tbl_shop.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function downloadlink($id)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return response()->json([
                'status' => false,
                'message' => __('messages.Unauthorized'),
                'error' => __('messages.Unauthorized'),
                'code' => 403,
            ], 403);
        }
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('shop_management'))) {
            return response()->json([
                'status' => false,
                'message' => __('messages.Unauthorized'),
                'error' => __('messages.Unauthorized'),
                'code' => 403,
            ], 403);
        }
        $tbl_shop = Shop::where('shop_id', $id)->first();
        if (!$tbl_shop) {
            return response()->json([
                'status' => false,
                'message' => __('shop.no_data_found'),
                'error' => __('shop.no_data_found'),
                'code' => 400,
            ], 400);
        }
        try {
            if ( is_null($tbl_shop->shop_code) ) {
                $tbl_shop->update([
                    'shop_code' => Shop::newcode(),
                    'updated_on' => now(),
                ]);
                $tbl_shop = $tbl_shop->fresh();
            }
            return response()->json([
                'status' => true,
                'message' => __('messages.list_success'),
                'error' => "",
                'download' => config('app.urldownload')."shop-download?shopCode=".md5($tbl_shop->shop_code),
                'code' => 200,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Shop downloadlink error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => __('messages.api_error'),
                'error' => $e->getMessage(),
                'code' => 500,
            ], 500);
        }

    }

    /**
     * Permission list filter tbl_manager.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function permissionlist(Request $request, $manager_id)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return response()->json([
                'status' => true,
                'message' => __('messages.Unauthorized'),
                'error' => "",
                'code' => 403,
            ], 403);
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('shop_management'))) {
            return response()->json([
                'status' => true,
                'message' => __('messages.Unauthorized'),
                'error' => "",
                'code' => 403,
            ], 403);
        }
        $tbl_manager = Manager::where('status', 1)
                            ->where('delete', 0)
                            ->where('manager_id', $manager_id)
                            ->first();
        if (!$tbl_manager) {
            return response()->json([
                'status' => true,
                'message' => __('manager.no_data_found'),
                'error' => __('manager.no_data_found'),
                'code' => 400,
            ], 403);
        }
        try {
            $tbl_permission = Manager::where('status', 1)
                            ->where('delete', 0)
                            ->where('area_code', $tbl_manager->area_code);
            if ($authorizedUser->user_role !== 'masteradmin') {
                $tbl_permission->where('agent_id', $authorizedUser->agent_id);
            }
            $tbl_permission = $tbl_permission->orderBy('manager_name')->get();
            return response()->json([
                'status' => true,
                'message' => __('messages.list_success'),
                'error' => "",
                'code' => 200,
                'data' => $tbl_permission,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Permission list filter error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => __('messages.api_error'),
                'error' => $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }
}
