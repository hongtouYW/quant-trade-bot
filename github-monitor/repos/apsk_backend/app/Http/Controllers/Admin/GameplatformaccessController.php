<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Gameplatform;
use App\Models\Gameplatformaccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class GameplatformaccessController extends Controller
{
    public function edit($agent_id)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('gameplatformaccess_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $agent = Agent::where('status', 1)
                          ->where('delete', 0)
                          ->where('agent_id', $agent_id)
                          ->first();
        if ( !$agent ) {
            return redirect()->back()->with('error', __('agent.no_data_found'));
        }
        $gameplatforms = Gameplatform::where('status', 1)
            ->where('delete', 0)
            ->get();
        foreach ($gameplatforms as $gameplatform) {
            $gameplatformaccess = Gameplatformaccess::where( 'agent_id', $agent_id )
                    ->where( 'gameplatform_id', $gameplatform->gameplatform_id )
                    ->where('delete', 0)
                    ->first();
            $gameplatform->commission = $gameplatformaccess ? $gameplatformaccess->commission : 0.00;
            $gameplatform->can_use = $gameplatformaccess ? $gameplatformaccess->can_use : 0;
            $gameplatform->status = $gameplatformaccess ? $gameplatformaccess->status : 0;
        }
        return view('module.gameplatformaccess.edit', compact('agent', 'gameplatforms'));
    }

    public function update(Request $request, $agent_id)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('gameplatformaccess_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $agent = Agent::where('status', 1)
                          ->where('delete', 0)
                          ->where('agent_id', $agent_id)
                          ->first();
        if ( !$agent ) {
            return redirect()->back()->with('error', __('agent.no_data_found'));
        }

        $validator = Validator::make($request->all(), [
            'commission.*' => 'nullable|numeric',
            'can_use.*' => 'nullable|in:0,1',
            'status.*' => 'nullable|in:0,1',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
        
        try {
            $commissions = $request->input('commission', []);
            $can_uses = $request->input('can_use', []);
            $statuses = $request->input('status', []);

            foreach ($commissions as $gameplatform_id => $commission) {
                $gameplatformaccess = [];
                $gameplatformaccess['commission'] = $commission;
                $gameplatformaccess['can_use'] = isset($can_uses[$gameplatform_id]) ? 1 : 0;
                if ($authorizedUser->user_role === 'masteradmin') {
                    $gameplatformaccess['status'] = isset($statuses[$gameplatform_id]) ? 1 : 0;
                }
                $gameplatformaccess['delete'] = 0;
                $gameplatformaccess['updated_on'] = now();
                Gameplatformaccess::updateOrCreate(
                    [
                        'agent_id' => $agent_id,
                        'gameplatform_id' => $gameplatform_id,
                    ],
                    $gameplatformaccess
                );
            }

            return redirect()->back()->with('success', __('gameplatformaccess.gameplatformaccess_updated_successfully'));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating gameplatformaccess: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
