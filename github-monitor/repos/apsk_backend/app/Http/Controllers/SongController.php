<?php

namespace App\Http\Controllers;

use App\Models\Song;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class SongController extends Controller
{
    /**
     * Search tbl_song.
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
            'artist_id' => 'nullable|integer',
            'creator' => 'nullable|integer',
            'album' => 'nullable|string',
            'genre_id' => 'nullable|integer',
            'year' => 'nullable|integer',
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
            $query = Song::where('status', 1)
                        ->where('delete', 0)
                        ->with('Artist.Countries', 'Creator.Countries','Genre')
                        ->orderBy('published_on', 'desc');
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('song_name', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            if ($request->filled('artist_name')) {
                $query->where('artist_id', $request->input('artist_id'));
            }
            if ($request->filled('creator_name')) {
                $query->where('creator', $request->input('creator'));
            }
            if ($request->filled('genre_id')) {
                $query->where('genre_id', $request->input('genre_id'));
            }
            if ($request->filled('album')) {
                $query->where('album', $request->input('album'));
            }
            $query = queryBetweenYear($query, $request, 'published_on');
            $tbl_song = $query->get();
            if (!$tbl_song) {
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
                    'data' => $tbl_song,
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
     * encrypt tbl_song.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function encrypt(Request $request)
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
            'url' => 'required|string', //song path
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
            $timestamp = now()->timestamp;
            $encrypt = encryptPassword( $timestamp.$request->input('url') );
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('song.song_updated_successfully',["songname"=>$request->input('url')]),
                    'error' => "",
                    'data' => $encrypt,
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
}
