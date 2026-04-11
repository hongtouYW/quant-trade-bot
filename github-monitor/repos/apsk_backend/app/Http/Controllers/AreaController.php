<?php

namespace App\Http\Controllers;

use App\Models\Areas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AreaController extends Controller
{

    /**
     * Search tbl_areas.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(Request $request)
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
            $tbl_areas = Areas::where('status', 1)
                        ->where('delete', 0)
                        ->with('Countries', 'States')
                        ->get();
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_areas,
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
     * Add tbl_areas.
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
        if (!CheckAuthorizedEdit($authorizedUser->currentAccessToken()->tokenable_id, GetModuleID('area_management') )) {
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
            'area_code' => 'required|string|max:10|unique:tbl_areas,area_code',
            'state_code' => 'required|string|max:10|unique:tbl_states,state_code',
            'country_code' => 'required|string|max:3|unique:tbl_countries,country_code',
            'area_name' => 'required|string|max:100',
            'area_type' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
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
                'area_code' => $request->input('area_code'),
                'state_code' => $request->input('state_code'),
                'country_code' => $request->input('country_code'),
                'area_name' => $request->input('area_name'),
                'area_type' => $request->input('area_type'),
                'postal_code' => $request->input('postal_code'),
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ];
            $id = DB::table('tbl_areas')->insertGetId($newdata);
            $insertedRow = DB::table('tbl_areas')->where('area_code', $id)->first();
            if (!$insertedRow) {
                return response()->json([
                    'data' => [],
                    'datetime' => now(),
                    'status' => false,
                    'message' => __('messages.add_fail'),
                    'error' => __('messages.add_fail'),
                    'code' => 500
                ], 500);
            }
            return response()->json([
                'data' => (array) $insertedRow,
                'datetime' => now(),
                'status' => true,
                'message' => $request->input('area_code') . __('messages.add_success'),
                'error' => '',
                'code' => 201
            ], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'data' => [],
                'datetime' => now(),
                'status' => false,
                'message' => __('messages.add_fail').$request->input('areaarea_codecode'),
                'error' => $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }

    /**
     * Edit tbl_areas.
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
        if (!CheckAuthorizedEdit($authorizedUser->currentAccessToken()->tokenable_id, GetModuleID('area_management') )) {
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
            'area_code' => 'required|string|max:10|unique:tbl_areas,area_code',
            'state_code' => 'required|string|max:10|unique:tbl_states,state_code',
            'country_code' => 'required|string|max:3|unique:tbl_countries,country_code',
            'area_name' => 'required|string|max:100',
            'area_type' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
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
            $tbl_table = DB::table('tbl_areas')->where('area_code', $request->input('area_code'))->first();
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
                'area_code' => $request->input('area_code'),
                'state_code' => $request->input('state_code'),
                'country_code' => $request->input('country_code'),
                'area_name' => $request->input('area_name'),
                'area_type' => $request->input('area_type'),
                'postal_code' => $request->input('postal_code'),
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
     * Delete tbl_areas.
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
        if (!CheckAuthorizedDelete($authorizedUser->currentAccessToken()->tokenable_id, GetModuleID('area_management') )) {
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
            'area_code' => 'required|string|max:10|unique:tbl_areas,area_code',
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
            $tbl_table = DB::table('tbl_areas')->where('area_code', $request->input('area_code'))->first();
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
