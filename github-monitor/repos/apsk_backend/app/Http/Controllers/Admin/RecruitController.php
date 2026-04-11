<?php

namespace App\Http\Controllers\Admin;

use App\Models\Recruit;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RecruitController extends Controller
{
    public function __construct()
    {
    }

    private static function formatNode($recruit)
    {
        return [
            'member_id' => $recruit->member_id,
            'member_name' => $recruit->Member->member_name ?? __('member.no_data_found'),
            'avatar' => $recruit->Member->avatar ?? '',
            'invitecode' => $recruit->invitecode ?? '',
            'DownlinesRecursive' => $recruit->DownlinesRecursive
                ->map(function($child) { return self::formatNode($child); })
                ->toArray(),
        ];
    }

    public function index(Request $request)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }

        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('recruit_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        try {
            $agent_id = null;

            $recruitsQuery = Recruit::with(['Member', 'DownlinesRecursive'])
                ->where(function ($q) {
                    $q->whereNull('upline')->orWhere('upline', 0);
                })
                ->where('delete', 0)
                ->orderBy('recruit_id', 'asc');

            if ($authorizedUser->user_role !== 'masteradmin') {
                $agent_id = $authorizedUser->agent_id;
                $recruitsQuery->whereHas('Member', function ($q) use ($agent_id) {
                    $q->where('agent_id', $agent_id);
                });
            }

            $recruits = $recruitsQuery->get();

            $treeData = $recruits->map(function($recruit) {
                return $this->formatNode($recruit);
            })->toArray();

            return view('module.recruit.list', compact('treeData'));

        } catch (\Exception $e) {
            Log::error("Error fetching recruit list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
