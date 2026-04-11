<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gamelog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class GamelogController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('gamelog_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = Gamelog::query()
                            ->with('Gamemember','Gamemember.Member','Gamemember.Provider','Gamemember.Gameplatform');
            if ($authorizedUser->user_role !== 'masteradmin') {
                $query->where('agent_id', $authorizedUser->agent_id);
            }
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('gamelog_id', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('gamelogtarget_id', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('gamemember_id', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('tableid', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('remark', 'LIKE', '%' . $searchTerm . '%');
                    // 🔗 Gamemember relation
                    $q->orWhereHas('Gamemember', function ($gm) use ($searchTerm) {
                        $gm->where(function ($sub) use ($searchTerm) {
                            $sub->where('loginId', 'LIKE', "%{$searchTerm}%")
                                ->orWhere('name', 'LIKE', "%{$searchTerm}%");
                        })
                        ->orWhereHas('Member', function ($p) use ($searchTerm) {
                            $p->where('member_name', 'LIKE', "%{$searchTerm}%");
                        })
                        ->orWhereHas('Provider', function ($p) use ($searchTerm) {
                            $p->where('provider_name', 'LIKE', "%{$searchTerm}%");
                        })
                        ->orWhereHas('Gameplatform', function ($gp) use ($searchTerm) {
                            $gp->where('gameplatform_name', 'LIKE', "%{$searchTerm}%");
                        });
                    });
                });
            }
            // Provider filter
            if ($request->filled('provider_id')) {
                $query->whereHas('Gamemember.Provider', function($q) use ($request) {
                    $q->where('provider_id', $request->input('provider_id'));
                });
            }
            // Platform filter
            if ($request->filled('platform_id')) {
                $query->whereHas('Gamemember.Gameplatform', function($q) use ($request) {
                    $q->where('gameplatform_id', $request->input('platform_id'));
                });
            }
            // Start datetime filter
            if ($request->filled('start_from') && $request->filled('start_to')) {
                $query->whereBetween('startdt', [
                    Carbon::parse($request->start_from)->startOfSecond(),
                    Carbon::parse($request->start_to)->endOfSecond(),
                ]);
            }
            // End datetime filter
            if ($request->filled('end_from') && $request->filled('end_to')) {
                $query->whereBetween('enddt', [
                    Carbon::parse($request->end_from)->startOfSecond(),
                    Carbon::parse($request->end_to)->endOfSecond(),
                ]);
            }
            // Agent filter (only masteradmin)
            if ($request->filled('agent_name')) {
                $query->whereHas('Agent', function ($q) use ($request) {
                    $q->where('agent_name', 'LIKE', '%' . $request->agent_name . '%');
                });
            }
            // Load providers, platforms, agents for filter dropdowns
            $providers = \App\Models\Provider::where('status',1)->get();
            $platforms = \App\Models\Gameplatform::where('status',1)->get();
            $agents = [];
            if ($authorizedUser->user_role === 'masteradmin') {
                $agents = \App\Models\User::where('user_role', 'agent')->get();
            }

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDateEloquent($query, $request, 'created_on');
            $query = $query->orderBy('startdt', 'desc');
            $gamelogs = $query->paginate(10)->appends($request->all());
            return view('module.gamelog.list', ['gamelogs' => $gamelogs, 'providers' => $providers, 'platforms' => $platforms, 'agents' => $agents,]);
        } catch (\Exception $e) {
            Log::error("Error fetching gamelog list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
