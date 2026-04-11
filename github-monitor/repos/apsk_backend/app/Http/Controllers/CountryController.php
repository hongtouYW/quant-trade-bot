<?php

namespace App\Http\Controllers;

use App\Models\Countries;
use App\Models\States;
use App\Models\Areas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CountryController extends Controller
{

    /**
     * Select tbl_countries.
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
            'country_code' => 'required|string',
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
            $tbl_countries = Countries::where( 'country_code', $request->input('country_code') )
                        ->where('status', 1)
                        ->where('delete', 0)
                        ->first();
            if (!$tbl_countries) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('country.no_data_found'),
                        'error' => __('country.no_data_found'),
                    ],
                    400
                );
            }
            $tbl_areas = Areas::where( 'country_code', $request->input('country_code') )
                        ->where('status', 1)
                        ->where('delete', 0)
                        ->get();
            $state_codes = [];
            foreach ($tbl_areas as $key => $area) {
                $state_codes[] = $area->state_code;
            }
            $tbl_states = States::wherein( 'state_code', $state_codes )
                        ->where('status', 1)
                        ->where('delete', 0)
                        ->get();
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'tbl_states' => $tbl_states,
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
     * Search tbl_countries.
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
            $tbl_countries = Countries::where('status', 1)
                        ->where('delete', 0);
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $tbl_countries->where(function ($q) use ($searchTerm) {
                    $q->where('country_code', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('country_name', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('phone_code', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            $tbl_countries = $tbl_countries->get();
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_countries,
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
     * login list tbl_countries.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function phonelist(Request $request)
    {
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
            $tbl_countries = Countries::where('status', 1)
                        ->where('delete', 0);
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $tbl_countries->where(function ($q) use ($searchTerm) {
                    $q->where('country_code', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('country_name', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('phone_code', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            $tbl_countries = $tbl_countries->get();
            $searchTerm = strtolower($request->input('search', ''));
            // ------------------ Expro99 row ------------------
            $expro = (object) [
                'country_code' => \Prefix::phonecode(),
                'country_name' => 'Expro99',
                'phone_code'   => \Prefix::phonecode(),
                'status'       => 1,
                'delete'       => 0,
                'created_on'   => '2025-05-01 00:00:00',
                'updated_on'   => '2025-05-01 00:00:00',
            ];
            // Check if Expro matches search
            $includeExpro = true;
            if ($searchTerm) {
                $includeExpro =
                    str_contains(strtolower($expro->country_code), $searchTerm) ||
                    str_contains(strtolower($expro->country_name), $searchTerm) ||
                    str_contains(strtolower($expro->phone_code), $searchTerm);
            }
            // ------------------ Malaysia ------------------
            $malaysia = $tbl_countries->first(function ($country) {
                return $country->phone_code == '60';
            });
            // Remove Malaysia from original list
            if ($malaysia) {
                $tbl_countries = $tbl_countries->reject(function ($country) use ($malaysia) {
                    return $country->country_code === $malaysia->country_code;
                });
            }
            // Check if Malaysia matches search
            $includeMalaysia = true;
            if ($searchTerm && $malaysia) {
                $includeMalaysia =
                    str_contains(strtolower($malaysia->country_code), $searchTerm) ||
                    str_contains(strtolower($malaysia->country_name), $searchTerm) ||
                    str_contains(strtolower($malaysia->phone_code), $searchTerm);
            }
            // ------------------ Rebuild Order ------------------
            $ordered = collect();
            if ($includeExpro) {
                $ordered->push($expro);
            }
            if ($malaysia && $includeMalaysia) {
                $ordered->push($malaysia);
            }
            $tbl_countries = $ordered->merge($tbl_countries->values());
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_countries,
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
