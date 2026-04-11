<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Gametype;
use App\Models\Gamemember;
use App\Models\Gamelog;
use App\Models\Gamebookmark;
use App\Models\Gameplatform;
use App\Models\Gameplatformaccess;
use App\Models\Provider;
use App\Models\Providerbookmark;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ProviderController extends Controller
{
    /**
     * provider view.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function view(Request $request, string $type)
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
            'manager_id' => 'nullable|integer',
            'shop_id' => 'nullable|integer',
            'member_id' => 'nullable|integer',
            'provider_id' => 'required|integer',
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
            if ( !$request->filled($type.'_id') ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => $type.'_id'.__('messages.unvalidation'),
                        'error' => $type.'_id'.__('messages.unvalidation'),
                    ],
                    422
                );
            }
            $checkuser = CheckAvailabilityUser( $request->input($type.'_id'), $type);
            if ( $checkuser ) {
                return sendEncryptedJsonResponse(
                    $checkuser,
                    401
                );
            }
            $tbl_provider = Provider::where('status', 1 )
                                    ->where('delete', 0 )
                                    ->where('provider_id', $request->input('provider_id') )
                                    ->with('Gameplatform')
                                    ->first();
            $tbl_provider = $tbl_provider->fresh();
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_provider,
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
     * provider list.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(Request $request, string $type)
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
            'manager_id' => 'nullable|integer',
            'shop_id' => 'nullable|integer',
            'member_id' => 'nullable|integer',
            'provider_category' => 'nullable|string',
            'isBookmark' => 'nullable|boolean',
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
            if ( !$request->filled($type.'_id') ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => $type.'_id'.__('messages.unvalidation'),
                        'error' => $type.'_id'.__('messages.unvalidation'),
                    ],
                    422
                );
            }
            $checkuser = CheckAvailabilityUser( $request->input($type.'_id'), $type);
            if ( $checkuser ) {
                return sendEncryptedJsonResponse(
                    $checkuser,
                    401
                );
            }
            $tbl_table = DB::table('tbl_'.$type)
                        ->where( $type.'_id', $request->input($type.'_id') )
                        ->first();
            $agent_id = !is_null( $tbl_table->agent_id ) ? $tbl_table->agent_id : 0;
            $gameplatform_ids = Gameplatformaccess::where( 'agent_id', $agent_id )
                    ->where('can_use', 1)
                    ->where('status', 1)
                    ->where('delete', 0)
                    ->pluck('gameplatform_id')
                    ->toArray();
            $tbl_gameplatform = Gameplatform::where( 'status', 1 )
                                            ->where( 'delete', 0 )
                                            ->whereIn('gameplatform_id', $gameplatform_ids)
                                            ->get();
            if (!$tbl_gameplatform) {
                return sendEncryptedJsonResponse(
                    [
                        'status'  => false,
                        'message' => __('game.no_platform_found'),
                        'error' => __('game.no_platform_found'),
                    ],
                    401
                );
            }
            $tbl_provider = Provider::where('status', 1 )
                                    ->where('delete', 0 )
                                    ->with('Gameplatform')
                                    ->whereIn('gameplatform_id', $gameplatform_ids);
            // switch ($type) {
            //     case 'manager':
            //         $tbl_shop = Shop::where('status', 1)
            //                         ->where('delete', 0)
            //                         ->where('manager_id', $request->input('manager_id'))
            //                         ->pluck('shop_id')
            //                         ->toArray();
            //         $tbl_member = Member::where('status', 1)
            //                             ->where('delete', 0)
            //                             ->whereIn('shop_id', $shopids )
            //                             ->first();
            //         break;
            //     case 'shop':
            //         $tbl_member = Member::where('status', 1)
            //                             ->where('delete', 0)
            //                             ->where('shop_id', $request->input('shop_id') )
            //                             ->first();
            //         break;
            //     case 'member':
            //         $tbl_member = Member::where('status', 1)
            //                             ->where('delete', 0)
            //                             ->where('member_id', $request->input('member_id') )
            //                             ->first();
            //         break;
            //     default:
            //         break;
            // }
            // if (!$tbl_member) {
            //     if ( $request->filled('provider_category') ) {
            //         $tbl_provider->where('provider_category', $request->input('provider_category') );
            //     }
            // } else {
            //     $israndomphonecode = \Prefix::israndomphonecode( $tbl_member->phone );
            //     if ( $israndomphonecode ) {
            //         $tbl_provider->where('provider_category', 'app');
            //     } else {
            //         if ( $request->filled('provider_category') ) {
            //             $tbl_provider->where('provider_category', $request->input('provider_category') );
            //         }
            //     }
            // }
            if ( $request->filled('provider_category') ) {
                $tbl_provider->where('provider_category', $request->input('provider_category') );
            }
            $tbl_provider = $tbl_provider->get();
            foreach( $tbl_provider as $provider ) {
                if ( $type === "member" ) {
                    $tbl_providerbookmark = Providerbookmark::where( 'member_id', $request->input('member_id') )
                                                    ->where( 'provider_id', $provider->provider_id)
                                                    ->where( 'status', 1 )
                                                    ->where( 'delete', 0 )
                                                    ->first();
                    $provider->isBookmark = $tbl_providerbookmark ? 1 : 0;
                    $provider->providerbookmark_id = $tbl_providerbookmark ? $tbl_providerbookmark->providerbookmark_id : null;
                }
            }
            if ($request->filled('isBookmark')) {
                $tbl_provider = $tbl_provider->filter(function ($provider) use ($request) {
                    return $provider->isBookmark == $request->input('isBookmark');
                });
                $tbl_provider = $tbl_provider->sortBy([
                    ['isBookmark', 'desc'],
                    ['provider_id', 'asc'],
                ])->values();
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_provider,
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
     * cronjob provider list.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function providerlistcronjob(Request $request)
    {
        try {
            $tbl_gameplatform = Gameplatform::where( 'status', 1 )
                                            ->where( 'delete', 0 )
                                            ->get();
            if (!$tbl_gameplatform) {
                return sendEncryptedJsonResponse(
                    [
                        'status'  => false,
                        'message' => __('game.no_platform_found'),
                        'error' => __('game.no_platform_found'),
                    ],
                    401
                );
            }
            foreach ($tbl_gameplatform as $key => $gameplatform) {
                $response = \Gamehelper::providerlist( $gameplatform->gameplatform_id );
                if (!$response['status']) {
                    return $response;
                }
            }
            return [
                'status' => true,
                'message' => __('messages.list_success'),
                'error' => "",
                'code' => 200,
            ];
        } catch (\Illuminate\Database\QueryException $e) {
            return [
                'status' => false,
                'message' => __('messages.unexpected_error'),
                'error' => $e->getMessage(),
            ];
        }
    }
}
