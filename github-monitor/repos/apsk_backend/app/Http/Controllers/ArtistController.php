<?php

namespace App\Http\Controllers;

use App\Models\Artist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ArtistController extends Controller
{

    /**
     * Search tbl_artist.
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
            'search' => 'nullable|string',
            'country_code' => 'nullable|string',
            'genre_id' => 'nullable|integer',
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
            $query = Artist::where('status', 1)
                        ->where('delete', 0)
                        ->with('Countries','Genre');
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('artist_name', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('artist_desc', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            if ($request->filled('country_code')) {
                $query->where('country_code', $request->input('country_code'));
            }
            if ($request->filled('genre_id')) {
                $query->where('genre_id', $request->input('genre_id') );
            }
            $tbl_artist = $query->get();
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_artist,
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
     * Add tbl_artist.
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
        if (!CheckAuthorizedEdit($authorizedUser->currentAccessToken()->tokenable_id, GetModuleID('artist_management') )) {
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
            'artist_name' => 'required|string|max:255|unique:tbl_artist,artist_name',
            'artist_desc' => 'nullable|string|max:10000',
            'genre_id' => 'required|integer',
            'country_code' => 'required|string|max:3',
            'dob' => 'required|date',
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
                'artist_name' => $request->input('artist_name'),
                'artist_desc' => $request->input('artist_desc'),
                'genre_id' => $request->input('genre_id'),
                'country_code' => $request->input('country_code'),
                'dob' => $request->input('dob'),
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ];
            $id = DB::table('tbl_artist')->insertGetId($newdata);
            $insertedRow = DB::table('tbl_artist')->where('artist_id', $id)->first();
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
     * Edit tbl_artist.
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
        if (!CheckAuthorizedEdit($authorizedUser->currentAccessToken()->tokenable_id, GetModuleID('artist_management') )) {
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
            'artist_id' => 'required|integer|unique:tbl_artist,artist_id',
            'artist_name' => 'required|string|max:255|unique:tbl_artist,artist_name',
            'artist_desc' => 'nullable|string|max:10000',
            'genre_id' => 'required|integer',
            'country_code' => 'required|string|max:3',
            'dob' => 'required|date',
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
            $tbl_table = DB::table('tbl_artist')->where('artist_name', $request->input('artist_name'))->first();
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
                'artist_name' => $request->input('artist_name'),
                'artist_desc' => $request->input('artist_desc'),
                'genre_id' => GetGenreID($request->input('genre_id')),
                'country_code' => $request->input('country_code'),
                'dob' => $request->input('dob'),
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
     * Delete tbl_artist.
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
        if (!CheckAuthorizedDelete($authorizedUser->currentAccessToken()->tokenable_id, GetModuleID('artist_management') )) {
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
            'artist_id' => 'required|integer|unique:tbl_artist,artist_id',
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
            $tbl_table = DB::table('tbl_artist')->where('artist_id', $request->input('artist_id'))->first();
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
                'message' => __('messages.delete_fail') ,
                'error' => e->getMessage(),
                'code' => 500
            ], 500);
        }
    }
}
