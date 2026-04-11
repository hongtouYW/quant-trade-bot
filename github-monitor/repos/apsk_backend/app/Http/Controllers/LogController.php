<?php

namespace App\Http\Controllers;

use App\Models\Manager;
use App\Models\Shop;
use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Collection;

class LogController extends Controller
{

    /**
     * search filter tbl_log for manager.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchfiltermanager(Request $request )
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }     
        $validator = Validator::make($request->all(), [
            'manager_id' => 'required|integer',
            'type' => 'required|string|in:all,manager,shop',
        ]);
        if ($validator->fails()) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unvalidation'),
                    'error' => $validator->errors(),
                ],
                422
            );
        }
        try {
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('manager.no_data_found'),
                        'error' => __('manager.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $alldata = [];
            switch ($request->input('type')) {
                case 'all':
                    $alldata['manager'] = Manager::get();
                    $alldata['shop'] = Shop::where('status', 1)
                                           ->where('delete', 0)
                                           ->where('manager_id', $request->input('manager_id'))
                                           ->get();
                    break;
                case 'manager':
                    $alldata['manager'] = Manager::get();
                    break;
                case 'shop':
                    $alldata['shop'] = Shop::where('status', 1)
                                           ->where('delete', 0)
                                           ->where('manager_id', $request->input('manager_id'))
                                           ->get();
                    break;
                default:
                    break;
            }
            return sendEncryptedJsonResponse([
                'status' => true,
                'message' => __('messages.list_success'),
                'error' => "",
                'data' => $alldata,
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * list tbl_log for manager.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function listmanager(Request $request )
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }     
        $validator = Validator::make($request->all(), [
            'manager_id' => 'required|integer',
            'type' => 'required|string|in:all,manager,shop',
            'manager_ids' => 'nullable|array',
            'shop_ids' => 'nullable|array',
        ]);
        if ($validator->fails()) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unvalidation'),
                    'error' => $validator->errors(),
                ],
                422
            );
        }
        try {
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('manager.no_data_found'),
                        'error' => __('manager.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $manager_ids = $request->filled('manager_ids') ? $request->input('manager_ids') : [];
            $shop_ids = $request->filled('shop_ids') ? $request->input('shop_ids') : [];
            $tbl_log = new Collection();
            switch ($request->input('type')) {
                case 'all':
                    $query = Manager::where('delete', 0)
                                    ->with(['Logs' => function ($q) {
                                        $q->where('log_type', '!=', 'user');
                                    }]);
                    if (!empty($manager_ids)) {
                        $query->whereIn('manager_id', $manager_ids);
                    }
                    $managers = $query->get();
                    $tbl_log = $tbl_log->merge($managers->pluck('Logs')->flatten());
                    $query = Shop::where('delete', 0)
                                ->with(['Logs' => function ($q) {
                                    $q->where('log_type', '!=', 'user');
                                }]);
                    if (!empty($shop_ids)) {
                        $query->whereIn('shop_id', $shop_ids);
                    } else {
                        $query->where('manager_id', $request->input('manager_id'));
                    }
                    $shops = $query->get();
                    $tbl_log = $tbl_log->merge($shops->pluck('Logs')->flatten());
                    break;
                case 'manager':
                    $query = Manager::where('delete', 0)
                                    ->with(['Logs' => function ($q) {
                                        $q->where('log_type', '!=', 'user');
                                    }]);
                    if (!empty($manager_ids)) {
                        $query->whereIn('manager_id', $manager_ids);
                    }
                    $managers = $query->get();
                    $tbl_log = $tbl_log->merge($managers->pluck('Logs')->flatten());
                    break;
                case 'shop':
                    $query = Shop::where('delete', 0)
                                ->with(['Logs' => function ($q) {
                                    $q->where('log_type', '!=', 'user');
                                }]);
                    if (!empty($shop_ids)) {
                        $query->whereIn('shop_id', $shop_ids);
                    } else {
                        $query->where('manager_id', $request->input('manager_id'));
                    }
                    $shops = $query->get();
                    $tbl_log = $tbl_log->merge($shops->pluck('Logs')->flatten());
                    break;
            }
            $tbl_log = $tbl_log->map(function ($log) {
                $log->log_desc = LogDescTranslate($log->log_desc);
                return $log;
            })->sortByDesc('created_on');
            return sendEncryptedJsonResponse([
                'status' => true,
                'message' => __('messages.list_success'),
                'error' => "",
                'data' => $tbl_log,
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $e->getMessage(),
                ],
                500
            );
        }
    }
}
