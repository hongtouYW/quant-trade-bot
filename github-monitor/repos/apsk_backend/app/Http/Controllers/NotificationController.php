<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\Member;
use App\Models\Manager;
use App\Models\Notifications;
use App\Models\Noticepublic;
use App\Models\Noticeread;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class NotificationController extends Controller
{

    /**
     * View Member tbl_notification.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function listmember(Request $request)
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
            'member_id' => 'required|integer',
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
            $member_id = $request->input('member_id');
            // 3️⃣ Get member
            $tbl_member = Member::find($member_id);
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $agentCondition = is_null($tbl_member->agent_id)
                ? fn($q) => $q->whereNull('agent_id')
                : fn($q) => $q->where('agent_id', $tbl_member->agent_id);

            $lang = $request->getPreferredLanguage();

            // 4️⃣ Fetch private notifications
            $privateNotifications = Notifications::where('recipient_id', $member_id)
                ->where('recipient_type', 'member')
                ->when(true, $agentCondition)
                ->orderBy('is_read', 'asc')
                ->orderBy('created_on', 'desc')
                ->get()
                ->map(function ($notification) {
                    $notification->title = __($notification->title);
                    $notification->notification_desc = NotificationDescDetail($notification->notification_desc);
                    return [
                        'notification_id' => $notification->notification_id,
                        'notification_type' => $notification->notification_type,
                        'title' => $notification->title,
                        'notification_desc' => $notification->notification_desc,
                        'is_read' => $notification->is_read,
                        'messagetype' => 'private',
                        'status' => $notification->status,
                        'delete' => $notification->delete,
                        'created_on' => $notification->created_on,
                        'updated_on' => $notification->updated_on,
                    ];
                })
                ->toArray();

            // 5️⃣ Fetch public notices
            $publicNotices = Noticepublic::when(true, $agentCondition)
                ->whereIn('recipient_type', ['all','member'] )
                ->where('lang', $lang)
                ->where('status', 1)
                ->where('delete', 0)
                ->where(function ($q) {
                    $q->whereNull('start_on')->orWhere('start_on', '<=', now());
                })
                ->where(function ($q) {
                    $q->whereNull('end_on')->orWhere('end_on', '>=', now());
                })
                ->orderBy('created_on', 'desc')
                ->get();

            // 6️⃣ Fetch read public notices once
            $readNotices = Noticeread::when(true, $agentCondition)
                ->where('member_id', $member_id)
                ->where('status', 1)
                ->where('delete', 0)
                ->pluck('notice_id')
                ->toArray();

            // Convert read notices to hash for fast lookup
            $readNoticesHash = array_flip($readNotices);

            // 7️⃣ Map public notices
            $publicNotifications = $publicNotices->map(function ($notice) use ($readNoticesHash) {
                return [
                    'notification_id' => $notice->notice_id,
                    'notification_type' => $notice->type,
                    'title' => $notice->title,
                    'notification_desc' => $notice->desc,
                    'is_read' => isset($readNoticesHash[$notice->notice_id]) ? 1 : 0,
                    'messagetype' => 'public',
                    'status' => $notice->status,
                    'delete' => $notice->delete,
                    'created_on' => $notice->created_on,
                    'updated_on' => $notice->updated_on,
                ];
            })->toArray();

            // 8️⃣ Merge private + public notifications
            $tbl_notification = array_merge($privateNotifications, $publicNotifications);

            // 🔟 Sort: unread first, then created_on
            usort($tbl_notification, function ($a, $b) {

                // 1️⃣ unread first
                if ($a['is_read'] !== $b['is_read']) {
                    return $a['is_read'] - $b['is_read'];
                }

                // 2️⃣ created_on DESC
                return strtotime($b['created_on']) - strtotime($a['created_on']);
            });

            // Count unread notifications
            $total_unread = collect($tbl_notification)
                ->where('is_read', 0)
                ->count();

            // 9️⃣ Return response
            return sendEncryptedJsonResponse([
                'status' => true,
                'message' => __('messages.list_success'),
                'error' => "",
                'data' => $tbl_notification,
                'total_unread' => $total_unread,
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
     * View Manager tbl_notification.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function listmanager(Request $request)
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
            $lang = $request->getPreferredLanguage();
            $manager_id = $request->input('manager_id');
            $tbl_shop = Shop::where('delete', 0)
                            ->where('manager_id', $manager_id)
                            ->get();
            $shopids = $tbl_shop->pluck('shop_id')->toArray();
            $tbl_notification = Notifications::where('delete', 0)
                            ->where('agent_id', $tbl_manager->agent_id)
                            ->where('recipient_type', 'manager')
                            ->where(function ($query) use ($manager_id) {
                                $query->whereNull('recipient_id')
                                      ->orWhere('recipient_id', $manager_id);
                            })
                            ->whereIn('sender_id', $shopids )
                            ->orderBy('is_read', 'asc')
                            ->orderBy('created_on', 'desc')
                            ->get();
            $tbl_notification->map(function ($notification) {
                $notification->title = __($notification->title);
                $notification->notification_desc = NotificationDescDetail($notification->notification_desc);
                return $notification;
            });
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_notification,
                ],
                200
            );
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
     * Member Read tbl_notification.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function memberread(Request $request)
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
            'member_id' => 'required|integer',
            'notification_id' => 'required|integer',
            'messagetype' =>  'required|string|in:private,public',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $messagetype = $request->input('messagetype');
            switch ($messagetype) {
                case 'private':
                    $tbl_notification = Notifications::where('status', 1)
                                                    ->where('delete', 0)
                                                    ->where('notification_id', $request->input('notification_id'))
                                                    ->first();
                    if (!$tbl_notification) {
                        return sendEncryptedJsonResponse(
                            [
                                'status' => false,
                                'message' => __('notification.no_data_found'),
                                'error' => __('notification.no_data_found'),
                            ],
                            400
                        );
                    }
                    $tbl_notification->update([
                        'is_read' => 1,
                        'updated_on' => now(),
                    ]);
                    break;
                case 'public':
                    $tbl_notification = Noticepublic::where('status', 1)
                                                    ->where('delete', 0)
                                                    ->where('notice_id', $request->input('notification_id'))
                                                    ->first();
                    if (!$tbl_notification) {
                        return sendEncryptedJsonResponse(
                            [
                                'status' => false,
                                'message' => __('notification.no_data_found'),
                                'error' => __('notification.no_data_found'),
                            ],
                            400
                        );
                    }
                    // $tbl_noticeread = Noticeread::where('status', 1)
                    //                             ->where('delete', 0)
                    //                             ->where('notice_id', $request->input('notification_id'))
                    //                             ->where('member_id', $request->input('member_id'))
                    //                             ->first();
                    // if (!$tbl_noticeread) {
                    //     $tbl_noticeread = Noticeread::create([
                    //         'notice_id' => $request->input('notification_id'),
                    //         'member_id' => $request->input('member_id'),
                    //         'read_on' => now(),
                    //         'agent_id' => $tbl_member->agent_id,
                    //         'status' => 1,
                    //         'delete' => 0,
                    //         'created_on' => now(),
                    //         'updated_on' => now(),
                    //     ]);
                    // }
                    // Fast: only 1 SQL query
                    Noticeread::firstOrCreate(
                        [
                            'notice_id' => $request->input('notification_id'),
                            'member_id' => $request->input('member_id'),
                        ],
                        [
                            'read_on' => now(),
                            'agent_id' => $tbl_member->agent_id,
                            'status' => 1,
                            'delete' => 0,
                            'created_on' => now(),
                            'updated_on' => now(),
                        ]
                    );
                    $tbl_notification->is_read = 1;
                    break;
                default:
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('notification.no_data_found'),
                            'error' => __('notification.no_data_found'),
                        ],
                        400
                    );
                    break;
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('notification.read'),
                    'error' => "",
                    'data' => $tbl_notification->fresh(),
                ],
                200
            );
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
     * Manager Read tbl_notification.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function managerread(Request $request)
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
            'notification_id' => 'required|integer',
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
            $manager_id = $request->input('manager_id');
            $tbl_notification = Notifications::where('status', 1)
                                            ->where('delete', 0)
                                            ->where('notification_id', $request->input('notification_id'))
                                            ->first();
            if (!$tbl_notification) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('notification.no_data_found'),
                        'error' => __('notification.no_data_found'),
                    ],
                    400
                );
            }
            $tbl_notification->update([
                'is_read' => 1,
                'updated_on' => now(),
            ]);
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('notification.read'),
                    'error' => "",
                    'data' => $tbl_notification->fresh(),
                ],
                200
            );
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
     * View tbl_notification.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(Request $request, string $type) {
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
            'manager_id' => 'nullable|integer',
            'shop_id' => 'nullable|integer',
            'member_id' => 'nullable|integer',
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
            if ($request->filled('manager_id') || 
                $request->filled('shop_id') || 
                $request->filled('member_id') 
            ) {
                $checkuser = CheckAvailabilityUser( $request->input("{$type}_id"), $type);
                if ( $checkuser ) {
                    return sendEncryptedJsonResponse(
                        $checkuser,
                        401
                    );
                }
                $tbl_table = DB::table("tbl_{$type}")
                            ->where("{$type}_id", $request->input("{$type}_id"))
                            ->first();
            } else {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.unvalidation'),
                        'error' => __('messages.unvalidation'),
                    ],
                    422
                );
            }
            $lang = $request->getPreferredLanguage();

            $privateNotifications = Notifications::where('recipient_id', $request->input("{$type}_id") )
                ->where('recipient_type', $type)
                ->orderBy('is_read', 'asc')
                ->orderBy('created_on', 'desc')
                ->get()
                ->map(function ($notification) {
                    $notification->title = __($notification->title);
                    $notification->notification_desc = NotificationDescDetail($notification->notification_desc);
                    return [
                        'notification_id' => $notification->notification_id,
                        'notification_type' => $notification->notification_type,
                        'title' => $notification->title,
                        'notification_desc' => $notification->notification_desc,
                        'is_read' => $notification->is_read,
                        'messagetype' => 'private',
                        'status' => $notification->status,
                        'delete' => $notification->delete,
                        'created_on' => $notification->created_on,
                        'updated_on' => $notification->updated_on,
                    ];
                })
                ->toArray();

            $publicNotices = Noticepublic::whereIn('recipient_type', ['all',$type] )
                ->where('lang', $lang)
                ->where('status', 1)
                ->where('delete', 0)
                ->where(function ($q) {
                    $q->whereNull('start_on')->orWhere('start_on', '<=', now());
                })
                ->where(function ($q) {
                    $q->whereNull('end_on')->orWhere('end_on', '>=', now());
                })
                ->orderBy('created_on', 'desc')
                ->get();

            $readNotices = Noticeread::where( "{$type}_id", $request->input("{$type}_id") )
                ->where('status', 1)
                ->where('delete', 0)
                ->pluck('notice_id')
                ->toArray();

            $readNoticesHash = array_flip($readNotices);

            $publicNotifications = $publicNotices->map(function ($notice) use ($readNoticesHash) {
                return [
                    'notification_id' => $notice->notice_id,
                    'notification_type' => $notice->type,
                    'title' => $notice->title,
                    'notification_desc' => $notice->desc,
                    'is_read' => isset($readNoticesHash[$notice->notice_id]) ? 1 : 0,
                    'messagetype' => 'public',
                    'status' => $notice->status,
                    'delete' => $notice->delete,
                    'created_on' => $notice->created_on,
                    'updated_on' => $notice->updated_on,
                ];
            })->toArray();

            $tbl_notification = array_merge($privateNotifications, $publicNotifications);

            usort($tbl_notification, function ($a, $b) {
                if ($a['is_read'] !== $b['is_read']) {
                    return $a['is_read'] - $b['is_read'];
                }
                return strtotime($b['created_on']) - strtotime($a['created_on']);
            });

            // Count unread notifications
            $total_unread = collect($tbl_notification)
                ->where('is_read', 0)
                ->count();

            return sendEncryptedJsonResponse([
                'status' => true,
                'message' => __('messages.list_success'),
                'error' => "",
                'data' => $tbl_notification,
                'total_unread' => $total_unread,
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
