<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Performance;
use App\Models\Member;
use App\Models\Agent;
use App\Models\Commissionrank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class PerformanceController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('performance_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = Performance::where('delete', 0)
                                ->with('Member', 'Mydownline', 'Myupline', 'Commissionrank', 'Agent');
            if ($authorizedUser->user_role !== 'masteradmin') {
                $query->where('agent_id', $authorizedUser->agent_id);
            }
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('notes', 'LIKE', '%' . $searchTerm . '%');
                    // 🔗 Member relation
                    $q->orWhereHas('Member', function ($mq) use ($searchTerm) {
                        $mq->where('member_name', 'LIKE', "%{$searchTerm}%")
                           ->orWhere('member_login', 'LIKE', '%' . $searchTerm . '%')
                           ->orWhere('phone', 'LIKE', '%' . $searchTerm . '%');
                    });
                    // 🔗 Mydownline relation
                    $q->orWhereHas('Mydownline', function ($mq) use ($searchTerm) {
                        $mq->where('member_name', 'LIKE', "%{$searchTerm}%")
                           ->orWhere('member_login', 'LIKE', '%' . $searchTerm . '%')
                           ->orWhere('phone', 'LIKE', '%' . $searchTerm . '%');
                    });
                    // 🔗 Myupline relation
                    $q->orWhereHas('Myupline', function ($mq) use ($searchTerm) {
                        $mq->where('member_name', 'LIKE', "%{$searchTerm}%")
                           ->orWhere('member_login', 'LIKE', '%' . $searchTerm . '%')
                           ->orWhere('phone', 'LIKE', '%' . $searchTerm . '%');
                    });
                });
            }
            if ($request->filled('agent_id')) {
                $query->where('agent_id', $request->input('agent_id'));
            }
            if ($request->filled('commissionrank_id')) {
                $query->where('commissionrank_id', $request->input('commissionrank_id'));
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDateEloquent($query, $request, 'created_on');
            $performances = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
            $agents = [];
            if ($authorizedUser->user_role === 'masteradmin') {
                $agents = Agent::where('status', 1)
                                ->where('delete', 0)
                                ->get();
            }
            $commissionranks = Commissionrank::where('status', 1)
                            ->where('delete', 0)
                            ->get();
            return view(
                'module.performance.list', 
                [
                    'performances' => $performances,
                    'commissionranks' => $commissionranks,
                    'agents' => $agents
                ]
            );
        } catch (\Exception $e) {
            Log::error("Error fetching performance list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
