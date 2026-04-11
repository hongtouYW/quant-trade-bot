<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\Gameplatform;
use App\Models\Gameplatformaccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProviderController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('provider_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $gameplatform_ids = Gameplatformaccess::where( 'agent_id', $authorizedUser->agent_id )
                    ->where('can_use', 1)
                    ->where('status', 1)
                    ->where('delete', 0)
                    ->pluck('gameplatform_id')
                    ->toArray();
            $query = Provider::with('Gameplatform')
                            ->whereIn('gameplatform_id', $gameplatform_ids);
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('provider_name', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('provider_category', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('icon', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('download', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            if ($request->filled('provider_category')) {
                $query->where('provider_category', $request->input('provider_category'));
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDateEloquent($query, $request, 'created_on');
            $providers = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
            $provider_categorys = ['app','hot','slot','live','fish','sport','cock'];
            return view(
                'module.provider.list', 
                [
                    'providers' => $providers,
                    'provider_categorys' => $provider_categorys,
                ]
            );
        } catch (\Exception $e) {
            Log::error("Error fetching provider list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for editing the specified provider.
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('provider_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $gameplatform_ids = Gameplatformaccess::where( 'agent_id', $authorizedUser->agent_id )
                ->where('can_use', 1)
                ->where('status', 1)
                ->where('delete', 0)
                ->pluck('gameplatform_id')
                ->toArray();
        $gameplatforms = Gameplatform::where('status', 1)
                ->where('delete', 0)
                ->whereIn('gameplatform_id', $gameplatform_ids)
                ->orderBy('gameplatform_name')
                ->get();
        $provider = Provider::where('provider_id', $id)->first();
        if (!$provider) {
            return redirect()->route('admin.provider.index')->with('error', __('provider.no_data_found'));
        }
        $provider_categorys = ['app','hot','slot','live','fish','sport','cock'];
        return view('module.provider.edit', compact('provider', 'gameplatforms', 'provider_categorys'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('provider_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $provider = DB::table('tbl_provider')->where('provider_id', $id)->first();
        if (!$provider) {
            return redirect()->back()->with('error', __('provider.no_data_found'));
        }

        $validator = Validator::make($request->all(), [
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'icon_sm' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'banner' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'android' => 'nullable|string|max:255',
            'ios' => 'nullable|string|max:255',
            'download' => 'nullable|string|max:255',
            'status' => 'nullable|in:1,0',
            'provider_category' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $tbl_gameplatform = Gameplatform::where('status', 1)
                ->where('delete', 0)
                ->where('gameplatform_id', $provider->gameplatform_id )
                ->first();
            if ( !$tbl_gameplatform ) {
                return redirect()->back()->with('error', __('game.no_platform_found'));
            }
            $iconPath = $provider->icon;
            if ($request->has('delete_icon')) {
                if ($provider->icon && Storage::disk('public')->exists($provider->icon)) {
                    Storage::disk('public')->delete($provider->icon);
                }
                $iconPath = null;
            } elseif ($request->hasFile('icon') && $request->file('icon')->isValid()) {
                if ( $provider->icon ) {
                    if ($provider->icon && Storage::disk('public')->exists($provider->icon)) {
                        Storage::disk('public')->delete($provider->icon);
                    }
                }
                $extension = $request->file('icon')->getClientOriginalExtension();
                $filename = $provider->provider_name.'_' . time() . '.' . $extension;
                $iconPath = $request->file('icon')->storeAs(
                    'assets/img/provider/icon/'.$tbl_gameplatform->gameplatform_name.'/en',
                    $filename,
                    'public'
                );
            }
            $iconsmPath = $provider->icon_sm;
            if ($request->has('delete_icon_sm')) {
                if ($provider->icon_sm && Storage::disk('public')->exists($provider->icon_sm)) {
                    Storage::disk('public')->delete($provider->icon_sm);
                }
                $iconsmPath = null;
            } elseif ($request->hasFile('icon_sm') && $request->file('icon_sm')->isValid()) {
                if ( $provider->icon_sm ) {
                    if ($provider->icon_sm && Storage::disk('public')->exists($provider->icon_sm)) {
                        Storage::disk('public')->delete($provider->icon_sm);
                    }
                }
                $extension = $request->file('icon_sm')->getClientOriginalExtension();
                $filename = $provider->provider_name.'_' . time() . '.' . $extension;
                $iconsmPath = $request->file('icon_sm')->storeAs(
                    'assets/img/provider/icon/sm/'.$tbl_gameplatform->gameplatform_name.'/en',
                    $filename,
                    'public'
                );
            }
            $bannerPath = $provider->banner;
            if ($request->has('delete_banner')) {
                if ($provider->banner && Storage::disk('public')->exists($provider->banner)) {
                    Storage::disk('public')->delete($provider->banner);
                }
                $bannerPath = null;
            } elseif ($request->hasFile('banner') && $request->file('banner')->isValid()) {
                if ( $provider->banner ) {
                    if ($provider->banner && Storage::disk('public')->exists($provider->banner)) {
                        Storage::disk('public')->delete($provider->banner);
                    }
                }
                $extension = $request->file('banner')->getClientOriginalExtension();
                $filename = $provider->provider_name.'_' . time() . '.' . $extension;
                $bannerPath = $request->file('banner')->storeAs(
                    'assets/img/provider/banner/'.$tbl_gameplatform->gameplatform_name.'/en',
                    $filename,
                    'public'
                );
            }
            $updateData = [
                'icon' => $iconPath,
                'icon_sm' => $iconsmPath,
                'banner' => $bannerPath,
                'android' => $request->input('android') ?? null,
                'ios' => $request->input('ios') ?? null,
                'download' => $request->input('download') ?? null,
                'provider_category' => $request->input('provider_category') ?? null,
                'status' => $request->filled('status') ? $request->input('status'): 0,
                'updated_on' => now(),
            ];
            DB::table('tbl_provider')->where('provider_id', $id)->update($updateData);
            return redirect()->route('admin.provider.index')->with('success', __('provider.provider_updated_successfully'));
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating provider: " . $e->getMessage());
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
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('provider_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $provider = DB::table('tbl_provider')->where('provider_id', $id)->first();
        if (!$provider) {
            return redirect()->back()->with('error', __('provider.no_data_found'));
        }

        try {
            DB::table('tbl_provider')->where('provider_id', $id)->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);

            return redirect()->route('admin.provider.index')->with('success', __('provider.provider_deleted_successfully'));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting provider: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
