<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Providerbookmark;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProviderbookmarkController extends Controller
{
    /**
     * Search tbl_providerbookmark.
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))
                                ->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
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
            $tbl_providerbookmark = Providerbookmark::where('member_id', $request->input('member_id'))
                                            ->with('Provider','Member')
                                            ->get();
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_providerbookmark,
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
     * Add tbl_gamebookmark.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function add(Request $request, string $type)
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
            'member_id' => 'required|integer',
            'provider_id' => 'required|integer',
            'providerbookmark_name' => 'required|string|max:255',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))
                                ->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
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
            $tbl_provider = Provider::where( 'provider_id', $request->input('provider_id') )
                            ->where( 'status', 1 )
                            ->where( 'delete', 0 )
                            ->first();
            if (!$tbl_provider) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('provider.no_data_found'),
                        'error' => __('provider.no_data_found'),
                    ],
                    400
                );
            }
            $tbl_providerbookmark = Providerbookmark::where('member_id', $request->input('member_id'))
                                            ->where('provider_id', $request->input('provider_id'))
                                            ->where( 'status', 1 )
                                            ->where( 'delete', 0 )
                                            ->first();
            if ( $tbl_providerbookmark ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('providerbookmark.data_exist'),
                        'error' => __('providerbookmark.data_exist'),
                    ],
                    400
                );
            }
            $tbl_providerbookmark = Providerbookmark::create([
                'providerbookmark_name' => $request->input('providerbookmark_name'),
                'provider_id' => $request->input('provider_id'),
                'member_id' => $request->input('member_id'),
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            return sendEncryptedJsonResponse(
                [
                    'data' => $tbl_providerbookmark,
                    'status' => true,
                    'message' => __('providerbookmark.provider_added_successfully'),
                    'error' => "",
                ],
                201
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
     * Delete tbl_gamebookmark.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(Request $request, string $type)
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
            'member_id' => 'required|integer',
            'providerbookmark_id' => 'required|integer',
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
            $tbl_providerbookmark = Providerbookmark::where('providerbookmark_id', $request->input('providerbookmark_id'))
                                            ->first();
            if ( !$tbl_providerbookmark ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('providerbookmark.no_data_found'),
                        'error' => __('providerbookmark.no_data_found'),
                    ],
                    400
                );
            }
            $tbl_providerbookmark->update([
                'status' => 0,
                'delete' => 1,
                'updated_on' => now(),
            ]);
            return sendEncryptedJsonResponse(
                [
                    'data' => $tbl_providerbookmark,
                    'status' => true,
                    'message' => __('providerbookmark.provider_deleted_successfully'),
                    'error' => "",
                ],
                201
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
}
