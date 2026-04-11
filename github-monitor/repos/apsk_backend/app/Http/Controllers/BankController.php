<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BankController extends Controller
{
    /**
     * Search tbl_bank.
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
            'search' => 'nullable|string',
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
            $query = DB::table('tbl_bank');
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('status', 1)
                      ->where('delete', 0)
                      ->where('bank_name', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('api', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            $tbl_table = $query->get();
            if (!$tbl_table) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_table,
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
     * Add tbl_bank.
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
        if (!CheckAuthorizedEdit($authorizedUser->currentAccessToken()->tokenable_id, GetModuleID('bank_management') )) {
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
            'bank_name' => 'required|string|max:255|unique:tbl_bank,bank_name',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'api' => 'nullable|string|max:255',
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
            $iconPath = null;
            if ($request->hasFile('icon') && $request->file('icon')->isValid()) {
                $sanitizedName = Str::slug($validator->validated()['bank_name'], '_');
                $extension = $request->file('icon')->getClientOriginalExtension();
                $filename = $sanitizedName.'_' . time() . '.' . $extension;
                $iconPath = $request->file('icon')->storeAs('assets/img/bank', $filename, 'public');
            }
            $newdata = [
                'bank_name' => $request->input('bank_name'),
                'icon' => $iconPath,
                'api' => $request->input('api') ?? null,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ];
            $id = DB::table('tbl_bank')->insertGetId($newdata);
            $insertedRow = DB::table('tbl_bank')->where('bank_id', $id)->first();
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
     * Edit tbl_bank.
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
        if (!CheckAuthorizedEdit($authorizedUser->currentAccessToken()->tokenable_id, GetModuleID('bank_management') )) {
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
            'bank_id' => 'required|string|max:255|unique:tbl_bank,bank_id',
            'bank_name' => 'required|string|max:255',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'api' => 'nullable|string|max:255',
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
            $tbl_table = DB::table('tbl_bank')->where('bank_id', $request->input('bank_id'))->first();
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
            $iconPath = $bank->icon;
            if ($request->hasFile('icon') && $request->file('icon')->isValid()) {
                if ($bank->icon && Storage::disk('public')->exists($bank->icon)) {
                    Storage::disk('public')->delete($bank->icon);
                }
                $sanitizedName = Str::slug($validator->validated()['bank_name'], '_');
                $extension = $request->file('icon')->getClientOriginalExtension();
                $filename = $sanitizedName.'_' . time() . '.' . $extension;
                $iconPath = $request->file('icon')->storeAs('assets/img/bank', $filename, 'public');
            }
            $tbl_table->update([
                'bank_name' => $request->input('bank_name'),
                'icon' => $iconPath,
                'api' => $request->input('api') ?? null,
                'status' => $request->input('status'),
                'updated_on' => now(),
            ]);
            $updatedRow = $tbl_table->fresh();
            return response()->json([
                'data' => $updatedRow,
                'datetime' => now(),
                'status' => true,
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
     * Delete tbl_bank.
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
        if (!CheckAuthorizedDelete($authorizedUser->currentAccessToken()->tokenable_id, GetModuleID('bank_management') )) {
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
            'bank_id' => 'required|string|max:255|unique:tbl_bank,bank_id',
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
            $tbl_table = DB::table('tbl_bank')->where('bank_id', $request->input('bank_id'))->first();
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
