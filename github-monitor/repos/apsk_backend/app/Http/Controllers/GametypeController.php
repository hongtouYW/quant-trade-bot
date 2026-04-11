<?php

namespace App\Http\Controllers;

use App\Models\Gametype;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class GametypeController extends Controller
{
    /**
     * Search tbl_gametype.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(Request $request)
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
        if (!CheckAuthorizedView($authorizedUser->currentAccessToken()->tokenable_id, GetModuleID('gametype_management') )) {
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
            'search' => 'nullable|string',
            'status' => 'nullable|in:1,0',
            'delete' => 'nullable|in:1,0',
            'startdate' => 'nullable|date',
            'enddate' => 'nullable|date|after_or_equal:startdate',
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
            $query = DB::table('tbl_gametype');
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('type_name', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('type_desc', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDate($query, $request, 'created_on');
            $data = $query->get();
            if ($data->isEmpty()) {
                return response()->json([
                    'data' => [],
                    'datetime' => now(),
                    'status' => false,
                    'message' => __('messages.nodata'),
                    'error' => __('messages.nodata'),
                    'code' => 404
                ], 200);
            }
            return response()->json([
                'data' => $data,
                'datetime' => now(),
                'status' => true,
                'message' => __('messages.list_success'),
                'code' => 200
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'data' => [],
                'datetime' => now(),
                'status' => false,
                'message' => __('messages.list_fail'),
                'error' => $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }

    /**
     * Add tbl_gametype.
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
        if (!CheckAuthorizedEdit($authorizedUser->currentAccessToken()->tokenable_id, GetModuleID('gametype_management') )) {
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
            'type_name' => 'required|string|max:255|in:slot,hot,fish,sport,live',
            'type_desc' => 'nullable|string|max:10000',
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
                'type_name' => $request->input('type_name'),
                'type_desc' => $request->input('type_desc'),
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ];
            $id = DB::table('tbl_gametype')->insertGetId($newdata);
            $insertedRow = DB::table('tbl_gametype')->where('gametype_id', $id)->first();
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
     * Edit tbl_gametype.
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
        if (!CheckAuthorizedEdit($authorizedUser->currentAccessToken()->tokenable_id, GetModuleID('gametype_management') )) {
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
            'type_name' => 'required|string|max:255|in:slot,hot,fish,sport,live',
            'type_desc' => 'nullable|string|max:10000',
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
            $tbl_table = DB::table('tbl_gametype')->where('type_name', $request->input('type_name'))->first();
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
                'type_name' => $request->input('type_name'),
                'type_desc' => $request->input('type_desc'),
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
     * Delete tbl_gametype.
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
        if (!CheckAuthorizedDelete($authorizedUser->currentAccessToken()->tokenable_id, GetModuleID('gametype_management') )) {
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
            'type_name' => 'required|string|max:255|in:slot,hot,fish,sport,live',
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
            $tbl_table = DB::table('tbl_gametype')->where('type_name', $request->input('type_name'))->first();
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
