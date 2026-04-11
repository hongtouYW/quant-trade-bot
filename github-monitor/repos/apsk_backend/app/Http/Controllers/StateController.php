<?php

namespace App\Http\Controllers;

use App\Models\States;
use App\Models\Areas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StateController extends Controller
{
    
    /**
     * Select tbl_states.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function select(Request $request, string $type = "member")
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
            'user_id' => 'required|integer',
            'state_code' => 'required|string',
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
            $tbl_table = DB::table('tbl_'.$type)
                            ->where($type.'_id', $request->input('user_id') )
                            ->where('status', 1)
                            ->where('delete', 0)
                            ->first();
            if (!$tbl_table) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __($type.'.no_data_found'),
                        'error' => __($type.'.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_table->status !== 1 || $tbl_table->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            if ( in_array( $type, ['member','shop'] ) ) {
                if ($tbl_table->alarm === 1 ) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('messages.profileinactive'),
                            'error' => __('messages.profileinactive'),
                        ],
                        401
                    );
                }
            }
            $tbl_states = States::where( 'state_code', $request->input('state_code') )
                        ->where('status', 1)
                        ->where('delete', 0)
                        ->first();
            if (!$tbl_states) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('state.no_data_found'),
                        'error' => __('state.no_data_found'),
                    ],
                    400
                );
            }
            $tbl_areas = Areas::where( 'state_code', $request->input('state_code') )
                        ->where('status', 1)
                        ->where('delete', 0)
                        ->get();
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'tbl_areas' => $tbl_areas,
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
     * Search tbl_states.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(Request $request, string $type = "member")
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
            'user_id' => 'required|integer',
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
            $tbl_table = DB::table('tbl_'.$type)
                            ->where($type.'_id', $request->input('user_id') )
                            ->where('status', 1)
                            ->where('delete', 0)
                            ->first();
            if (!$tbl_table) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __($type.'.no_data_found'),
                        'error' => __($type.'.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_table->status !== 1 || $tbl_table->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            if ( in_array( $type, ['member','shop'] ) ) {
                if ($tbl_table->alarm === 1 ) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('messages.profileinactive'),
                            'error' => __('messages.profileinactive'),
                        ],
                        401
                    );
                }
            }
            $tbl_states = States::where('status', 1)
                        ->where('delete', 0)
                        ->get();
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_states,
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
     * Add tbl_states.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function add(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return response()->json([
                'data' => [],
                'datetime' => now(),
                'status' => false,
                'message' => __('messages.Unauthorized'),
                'error' => __('messages.Unauthorized'),
                'code' => 403
            ], 403);
        }
        if (!CheckAuthorizedEdit($authorizedUser->currentAccessToken()->tokenable_id, GetModuleID('state_management') )) {
            return response()->json([
                'data' => [],
                'datetime' => now(),
                'status' => false,
                'message' => __('messages.Unauthorized'),
                'error' => __('messages.Unauthorized'),
                'code' => 403
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'state_code' => 'required|string|max:10|unique:tbl_states,state_code',
            'state_name' => 'required|string|max:100',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'data' => [],
                'datetime' => now(),
                'status' => false,
                'message' => __('messages.unvalidation'),
                'error' => $validator->errors(),
                'code' => 422
            ], 422);
        }
        try {
            $newdata = [
                'state_code' => $request->input('state_code'),
                'state_name' => $request->input('state_name'),
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ];
            $id = DB::table('tbl_states')->insertGetId($newdata);
            $insertedRow = DB::table('tbl_states')->where('state_code', $id)->first();
            if (!$insertedRow) {
                return response()->json([
                    'data' => [],
                    'datetime' => now(),
                    'status' => false,
                    'message' => __('messages.noexist'),
                    'error' => __('messages.noexist'),
                    'code' => 500
                ], 500);
            }
            return response()->json([
                'data' => (array) $insertedRow,
                'datetime' => now(),
                'status' => true,
                'message' => __('messages.add_success'),
                'error' => '',
                'code' => 201
            ], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'data' => [],
                'datetime' => now(),
                'status' => false,
                'message' => __('messages.add_fail'),
                'error' => $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }

    /**
     * Edit tbl_states.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return response()->json([
                'data' => [],
                'datetime' => now(),
                'status' => false,
                'message' => __('messages.Unauthorized'),
                'error' => __('messages.Unauthorized'),
                'code' => 403
            ], 403);
        }
        if (!CheckAuthorizedEdit($authorizedUser->currentAccessToken()->tokenable_id, GetModuleID('state_management') )) {
            return response()->json([
                'data' => [],
                'datetime' => now(),
                'status' => false,
                'message' => __('messages.Unauthorized'),
                'error' => __('messages.Unauthorized'),
                'code' => 403
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'state_code' => 'required|string|max:10|unique:tbl_states,state_code',
            'state_name' => 'required|string|max:100',
            'status' => 'nullable|in:1,0',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'data' => [],
                'datetime' => now(),
                'status' => false,
                'message' => __('messages.unvalidation'),
                'error' => $validator->errors(),
                'code' => 422
            ], 422);
        }
        try {
            $tbl_table = DB::table('tbl_states')->where('state_code', $request->input('state_code'))->first();
            if (!$tbl_table) {
                return response()->json([
                    'data' => [],
                    'datetime' => now(),
                    'status' => false,
                    'message' => __('messages.noexist'),
                    'error' => __('messages.noexist'),
                    'code' => 500
                ], 500);
            }
            $tbl_table->update([
                'state_code' => $request->input('state_code'),
                'state_name' => $request->input('state_name'),
                'status' => $request->input('status'),
                'updated_on' => now(),
            ]);
            $updatedRow = $tbl_table->fresh();
            return response()->json([
                'data' => $updatedRow,
                'datetime' => now(),
                'status' => false,
                'message' => __('messages.edit_success'),
                'error' => '',
                'code' => 200
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'data' => [],
                'datetime' => now(),
                'status' => false,
                'message' => __('messages.edit_fail'),
                'error' => $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }

    /**
     * Delete tbl_states.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return response()->json([
                'delete' => false,
                'datetime' => now(),
                'status' => false,
                'message' => __('messages.Unauthorized'),
                'error' => __('messages.Unauthorized'),
                'code' => 403
            ], 403);
        }
        if (!CheckAuthorizedDelete($authorizedUser->currentAccessToken()->tokenable_id, GetModuleID('state_management') )) {
            return response()->json([
                'delete' => false,
                'datetime' => now(),
                'status' => false,
                'message' => __('messages.Unauthorized'),
                'error' => __('messages.Unauthorized'),
                'code' => 403
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'state_code' => 'required|string|max:10|unique:tbl_states,state_code',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'delete' => false,
                'datetime' => now(),
                'status' => false,
                'message' => __('messages.unvalidation'),
                'error' => $validator->errors(),
                'code' => 422
            ], 422);
        }
        try {
            $tbl_table = DB::table('tbl_states')->where('state_code', $request->input('state_code'))->first();
            if (!$tbl_table) {
                return response()->json([
                    'delete' => false,
                    'datetime' => now(),
                    'status' => false,
                    'message' => __('messages.noexist'),
                    'error' => __('messages.noexist'),
                    'code' => 500
                ], 500);
            }
            $tbl_table->update([
                'status' => 0,
                'delete' => 1,
                'updated_on' => now(),
            ]);
            return response()->json([
                'delete' => true,
                'datetime' => now(),
                'status' => false,
                'message' => __('messages.delete_success'),
                'error' => '',
                'code' => 200
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'delete' => false,
                'datetime' => now(),
                'status' => false,
                'message' => __('messages.delete_fail'),
                'error' => e->getMessage(),
                'code' => 500
            ], 500);
        }
    }

}
