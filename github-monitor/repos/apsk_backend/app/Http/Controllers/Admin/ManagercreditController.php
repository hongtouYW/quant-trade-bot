<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Manager;
use App\Models\Managercredit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ManagercreditController extends Controller
{
    public function index(Request $request)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('managercredit_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = Managercredit::query()->with(['Manager','User','Agent']);
            if ($authorizedUser->user_role !== 'masteradmin') {
                $query->where('agent_id', $authorizedUser->agent_id);
            }
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('managercredit_id', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('manager_id', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('user_id', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('type', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('reason', 'LIKE', '%' . $searchTerm . '%');
                    // 🔗 Manager relation
                    $q->orWhereHas('Manager', function ($mq) use ($searchTerm) {
                        $mq->where('manager_name', 'LIKE', "%{$searchTerm}%");
                    });
                    // 🔗 User relation
                    $q->orWhereHas('User', function ($sq) use ($searchTerm) {
                        $sq->where('user_name', 'LIKE', "%{$searchTerm}%");
                    });
                });
            }
            if ($request->filled('type')) {
                $query->where('type', $request->input('type'));
            }
            if ($request->filled('manager_name')) {
                $managerName = $request->input('manager_name');
                $query->whereHas('Manager', function ($q) use ($managerName) {
                    $q->where('manager_name', 'LIKE', "%{$managerName}%");
                });
            }
            if ($request->filled('agent_name')) {
                $agentName = $request->input('agent_name');
                $query->whereHas('Agent', function ($q) use ($agentName) {
                    $q->where('agent_name', 'LIKE', "%{$agentName}%");
                });
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDateEloquent($query, $request, 'created_on');
            $managercredits = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
            return view('module.managercredit.list', ['managercredits' => $managercredits]);
        } catch (\Exception $e) {
            Log::error('Error fetching manager credit list: ' . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
