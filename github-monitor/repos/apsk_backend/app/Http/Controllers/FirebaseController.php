<?php

namespace App\Http\Controllers;

// use App\Models\Manager;
// use App\Models\Shop;
// use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FirebaseController extends Controller
{
    /**
     * device key.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function device(Request $request, string $type)
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
            'devicekey' => 'nullable|string',
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
            if ( !$request->input("{$type}_id") ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => $type.'_id'.__('messages.unvalidation'),
                        'error' => $type.'_id'.__('messages.unvalidation'),
                    ],
                    422
                );
            }
            $checkuser = CheckAvailabilityUser( $request->input("{$type}_id"), $type);
            if ( $checkuser ) {
                return sendEncryptedJsonResponse(
                    $checkuser,
                    401
                );
            }
            DB::table("tbl_{$type}")
                ->where("{$type}_id", $request->input("{$type}_id"))
                ->update([
                    'devicekey'  => $request->input('devicekey') ?? null,
                    'updated_on' => now(),
                ]);
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'data' => DB::table("tbl_{$type}")
                            ->where("{$type}_id", $request->input("{$type}_id"))
                            ->first(),
                    'message' => $request->filled('devicekey') ? 
                                __('firebase.devicekey_updated') : 
                                __('firebase.devicekey_deleted'),
                    'error' => ""
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
