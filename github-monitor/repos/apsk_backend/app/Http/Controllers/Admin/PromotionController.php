<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Promotion;
use App\Models\Promotiontype;
use App\Models\VIP;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PromotionController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('promotion_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = Promotion::query()
                            ->with('MyVip', 'Agent', 'Promotiontype');
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
                    $q->where('title', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('promotion_desc', 'LIKE', '%' . $searchTerm . '%');
                    // 🔗 Promotiontype relation
                    $q->orWhereHas('Promotiontype', function ($sq) use ($searchTerm) {
                        $sq->where('promotion_type', 'LIKE', "%{$searchTerm}%");
                    });
                });
            }
            if ($request->filled('language')) {
                $query->where('lang', 'LIKE', $request->input('language'));
            }
            if ($request->filled('promotiontype_id')) {
                $query->where('promotiontype_id', $request->input('promotiontype_id'));
            }
            if ($authorizedUser->user_role !== 'masteradmin') {
                $query->where('agent_id', $authorizedUser->agent_id);
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDateEloquent($query, $request, 'created_on');
            $promotions = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
            if ($authorizedUser->user_role === 'masteradmin') {
                $agents = Agent::where('status', 1)
                                ->where('delete', 0)
                                ->get();
            }
            $promotiontypes = Promotiontype::where('status', 1)
                                           ->where('delete', 0)
                                           ->where('agent_id', 0 )
                                           ->get();
            return view(
                'module.promotion.list', 
                [
                    'promotions' => $promotions, 
                    'agents' => $agents, 
                    'promotiontypes' => $promotiontypes
                ]
            );
        } catch (\Exception $e) {
            Log::error("Error fetching promotion list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for creating a new promotion.
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('promotion_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $promotiontypes = Promotiontype::where('status', 1)
                                      ->where('delete', 0)
                                      ->where('agent_id', 0 )
                                      ->get();
        $langs = config('languages.supported');
        $vips = VIP::where('status', 1)
                   ->where('delete', 0)
                   ->where('agent_id', $authorizedUser->agent_id )
                   ->get();
        return view('module.promotion.create', compact('promotiontypes','langs','vips') );
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('promotion_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $validator = Validator::make($request->all(), [
            'promotiontype_id' => 'required|integer',
            'title' => 'required|string|max:50',
            'promotion_desc' => 'nullable|string|max:10000',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'amount' => 'nullable|numeric|gt:1.00',
            'percent' => 'nullable|numeric|gt:0.01',
            'newbie' => 'boolean',
            'vip_id' => 'nullable|integer',
            'url' => 'nullable|string|max:255',
            'language' => 'required|string',
        ]);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
        try {
            $photo = null;
            if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
                $sanitizedName = Str::slug($validator->validated()['title'], '_');
                $extension = $request->file('photo')->getClientOriginalExtension();
                $filename = $sanitizedName.'_' . time() . '.' . $extension;
                $photo = $request->file('photo')->storeAs(
                    'assets/img/promotion',
                    $filename,
                    'public'
                );
            }
            $masterpromotiontype = Promotiontype::where('status', 1)
                                                ->where('delete', 0)
                                                ->where('promotiontype_id', $request->input('promotiontype_id') )
                                                ->first();
            if (!$masterpromotiontype) {
                return redirect()->route('admin.promotion.index')->with('error', __('promotion.no_data_found'));
            }
            $tbl_promotiontype = Promotiontype::where('status', 1)
                                                ->where('delete', 0)
                                                ->where('promotion_type', $masterpromotiontype->promotion_type )
                                                ->where('agent_id', $authorizedUser->agent_id )
                                                ->first();
            if ( !$tbl_promotiontype ) {
                return redirect()->route('admin.promotion.index')->with('error', __('promotion.no_data_found'));
            }
            // if ( $tbl_promotiontype ) {
            //     $tbl_promotiontype->update([
            //         'amount' => $request->filled('amount') ? $request->input('amount') : 
            //                     $masterpromotiontype->amount,
            //         'updated_on' => now(),
            //     ]);
            // } else {
            //     $tbl_promotiontype = Promotiontype::create([
            //         'amount' => $request->filled('amount') ? $request->input('amount') : 
            //                     $masterpromotiontype->amount,
            //         'promotion_type' => $masterpromotiontype->promotion_type,
            //         'event' => $masterpromotiontype->event,
            //         'agent_id' => $authorizedUser->agent_id,
            //         'status' => 1,
            //         'delete' => 0,
            //         'created_on' => now(),
            //         'updated_on' => now(),
            //     ]);
            // }
            $tbl_promotion = Promotion::create([
                'promotiontype_id' => $tbl_promotiontype->promotiontype_id,
                'title' => $request->input('title'),
                'promotion_desc' => $request->input('promotion_desc') ?? null,
                'photo' => $photo,
                'amount' => $request->input('amount') ?? $masterpromotiontype->amount,
                'percent' => $request->input('percent') ?? null,
                'newbie' => $request->boolean('newbie'),
                'agent_id' => $authorizedUser->agent_id,
                'vip_id' => $request->input('vip_id') ?? null,
                'url' => $request->input('url') ?? null,
                'lang' => $request->input('language'),
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            // Change all related lang
            Promotion::where('agent_id', $tbl_promotion->agent_id)
                ->where('promotiontype_id', $tbl_promotiontype->promotiontype_id)
                ->update([
                    'status' => 1,
                    'updated_on' => now(),
                ]);
            return redirect()->route('admin.promotion.index')->with('success', __('promotion.promotion_added_successfully'));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error adding promotion: " . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Failed to add promotion: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified promotion.
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('promotion_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $promotion = Promotion::where('promotion_id', $id)->first();
        if (!$promotion) {
            return redirect()->route('admin.promotion.index')->with('error', __('messages.nodata'));
        }
        $promotiontypes = Promotiontype::where('status', 1)
                                      ->where('delete', 0)
                                      ->where('agent_id', 0 )
                                      ->get();
        $langs = config('languages.supported');
        $vips = VIP::where('status', 1)
                   ->where('delete', 0)
                   ->where('agent_id', $authorizedUser->agent_id )
                   ->get();
        return view('module.promotion.edit', compact('promotion', 'promotiontypes','langs','vips'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('promotion_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $tbl_promotion = Promotion::where('promotion_id', $id)->first();
        if (!$tbl_promotion) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }
        $validator = Validator::make($request->all(), [
            'promotiontype_id' => 'required|integer',
            'title' => 'required|string|max:50',
            'promotion_desc' => 'nullable|string|max:10000',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'amount' => 'nullable|numeric|gt:1.00',
            'percent' => 'nullable|numeric|gt:0.01',
            'newbie' => 'boolean',
            'vip_id' => 'nullable|integer',
            'url' => 'nullable|string|max:255',
            'language' => 'required|string',
            'status' => 'nullable|in:1,0',
        ]);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
        try {
            $photo = $tbl_promotion->photo;
            if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
                if ($tbl_promotion->photo && Storage::disk('public')->exists($tbl_promotion->photo)) {
                    Storage::disk('public')->delete($tbl_promotion->photo);
                }
                $sanitizedName = Str::slug($validator->validated()['title'], '_');
                $extension = $request->file('photo')->getClientOriginalExtension();
                $filename = $sanitizedName.'_' . time() . '.' . $extension;
                $photo = $request->file('photo')->storeAs(
                    'assets/img/promotion',
                    $filename,
                    'public'
                );
            }
            $masterpromotiontype = Promotiontype::where('status', 1)
                                                ->where('delete', 0)
                                                ->where('promotiontype_id', $request->input('promotiontype_id') )
                                                ->first();
            if (!$masterpromotiontype) {
                return redirect()->route('admin.promotion.index')->with('error', __('promotion.no_data_found'));
            }
            $tbl_promotiontype = Promotiontype::where('status', 1)
                                                ->where('delete', 0)
                                                ->where('promotion_type', $masterpromotiontype->promotion_type )
                                                ->where('agent_id', $authorizedUser->agent_id )
                                                ->first();
            if ( !$tbl_promotiontype ) {
                return redirect()->route('admin.promotion.index')->with('error', __('promotion.no_data_found'));
            }
            // if ( $tbl_promotiontype ) {
            //     $tbl_promotiontype->update([
            //         'amount' => $request->filled('amount') ? $request->input('amount') : 
            //                     $masterpromotiontype->amount,
            //         'updated_on' => now(),
            //     ]);
            // } else {
            //     $tbl_promotiontype = Promotiontype::create([
            //         'amount' => $request->filled('amount') ? $request->input('amount') : 
            //                     $masterpromotiontype->amount,
            //         'promotion_type' => $masterpromotiontype->promotion_type,
            //         'event' => $masterpromotiontype->event,
            //         'agent_id' => $authorizedUser->agent_id,
            //         'status' => 1,
            //         'delete' => 0,
            //         'created_on' => now(),
            //         'updated_on' => now(),
            //     ]);
            // }
            $tbl_promotion->update([
                'promotiontype_id' => $tbl_promotiontype->promotiontype_id,
                'title' => $request->input('title'),
                'promotion_desc' => $request->input('promotion_desc') ?? null,
                'photo' => $photo,
                'amount' => $request->input('amount') ?? $masterpromotiontype->amount,
                'percent' => $request->input('percent') ?? null,
                'newbie' => $request->boolean('newbie'),
                'vip_id' => $request->input('vip_id') ?? null,
                'url' => $request->input('url') ?? null,
                'lang' => $request->input('language'),
                'updated_on' => now(),
            ]);
            // Change all related lang
            Promotion::where('agent_id', $tbl_promotion->agent_id)
                ->where('promotiontype_id', $tbl_promotiontype->promotiontype_id)
                ->update([
                    'status' => $request->filled('status') ? $request->input('status'): 0,
                    'updated_on' => now(),
                ]);
            return redirect()->route('admin.promotion.index')->with('success', __('promotion.promotion_updated_successfully'));
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating promotion: " . $e->getMessage());
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
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('promotion_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $tbl_promotion = Promotion::where('promotion_id', $id)->first();
        if (!$tbl_promotion) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }
        try {
            $tbl_promotion->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);
            return redirect()->route('admin.promotion.index')->with('success', __('promotion.promotion_deleted_successfully'));
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting promotion: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
