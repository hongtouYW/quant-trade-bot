<?php

namespace App\Http\Controllers;

use App\Models\Paymentgateway;
use App\Models\Credit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;

class PaymentgatewayController extends Controller
{
    /**
     * Search tbl_paymentgateway.
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
            'user_id' => 'required|integer',
            'type' => 'nullable|string|in:online,currency,ewallet,qr',
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
            $checkuser = CheckAvailabilityUser( $request->input('user_id'), $type);
            if ( $checkuser ) {
                return sendEncryptedJsonResponse(
                    $checkuser,
                    401
                );
            }
            $tbl_paymentgateway = Paymentgateway::where('status', 1)
                                                ->where('delete', 0);
            if ( $request->filled('type') )
            {
                $tbl_paymentgateway = $tbl_paymentgateway->where('type', $request->input('type') );
            }
            $tbl_paymentgateway = $tbl_paymentgateway->get();
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_paymentgateway,
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

    public function depositstatus(Request $request, string $type)
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
            'credit_id' => 'required|integer',
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
            $checkuser = CheckAvailabilityUser( $request->input('user_id'), $type);
            if ( $checkuser ) {
                return sendEncryptedJsonResponse(
                    $checkuser,
                    401
                );
            }
            $tbl_credit = Credit::where( 'credit_id', $request->input('credit_id') )->first();
            if ( !$tbl_credit ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('credit.no_data_found'),
                        'error' => __('credit.no_data_found'),
                    ],
                    400
                );
            }
            $response = null;
            switch ( $tbl_credit->payment_id ) {
                case 1: //Cash
                    return [
                        'status' => true,
                        'message' => __('messages.list_success'),
                        'error' => "",
                        'code' => 200,
                        'credit' => $tbl_credit->fresh(),
                    ];
                    break;
                case 2: //Bank
                    return [
                        'status' => true,
                        'message' => __('messages.list_success'),
                        'error' => "",
                        'code' => 200,
                        'credit' => $tbl_credit->fresh(),
                    ];
                    break;
                case 3: //FPay
                    $response = \Fpay::detail( $tbl_credit );
                    break;
                case 4: //Super Pay
                    $response = \Superpay::detail( $tbl_credit, 1);
                    break;
                case 5: //FPay Duitnow
                    $response = \Fpay::detailduitnow( $tbl_credit );
                    break;
                default:
                    return [
                        'status' => false,
                        'message' => __('paymentgateway.no_data_found'),
                        'error' => "",
                        'code' => 400,
                        'credit' => $tbl_credit->fresh(),
                    ];
                    break;
            }
            return sendEncryptedJsonResponse(
                $response,
                $response['code'],
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

    public function withdrawstatus(Request $request, string $type)
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
            'credit_id' => 'required|integer',
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
            $checkuser = CheckAvailabilityUser( $request->input('user_id'), $type);
            if ( $checkuser ) {
                return sendEncryptedJsonResponse(
                    $checkuser,
                    401
                );
            }
            $tbl_credit = Credit::where('credit_id', $request->input('credit_id') )
                                ->first();
            if ( !$tbl_credit )
            {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('credit.no_data_found'),
                        'error' => __('credit.no_data_found'),
                    ],
                    200
                );
            }
            $response = null;
            switch ( $tbl_credit->payment_id ) {
                case 3: //FPay
                    $response = \Fpay::withdrawstatus( $request, $tbl_credit );
                    break;
                case 4: //Super Pay
                    $response = \Superpay::detail( $tbl_credit->transactionId, 2);
                    break;
                case 5: //FPay
                    $response = \Fpay::withdrawstatusduitnow( $request, $tbl_credit );
                    break;
                default:
                    break;
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'credit' => $tbl_credit->fresh(),
                    'response' => $response,
                    'message' => __('messages.list_success'),
                    'error' => "",
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
    
    public function withdrawredirect(Request $request, $payload)
    {
        return view('payment.redirect', compact('payload'));
    }
    public function paymentstatus(Request $request, $payload)
    {
        try {
            $data = json_decode(Crypt::decryptString($payload), true);
            $credit_id = $data['credit_id'];
            $tbl_credit = Credit::where( 'credit_id', $credit_id )->first();
            if ( !$tbl_credit ) {
                return [
                    'status' => false,
                    'message' => __('credit.no_data_found'),
                    'error' => __('credit.no_data_found'),
                    'code' => 400,
                ];
            }
            // if ( (int) $tbl_credit->status !== 0 ) {
            //     $response['status'] = true;
            //     $response['message'] = __($tbl_credit->reason);
            //     $response['error'] = "";
            //     $response['code'] = 200;
            //     $response['credit'] = $tbl_credit;
            //     return $response;
            // }
            // switch ( $tbl_credit->payment_id ) {
            //     case 1: //Cash
            //         return [
            //             'status' => true,
            //             'message' => __('messages.list_success'),
            //             'error' => "",
            //             'code' => 200,
            //             'credit' => $tbl_credit->fresh(),
            //         ];
            //         break;
            //     case 2: //Bank
            //         return [
            //             'status' => true,
            //             'message' => __('messages.list_success'),
            //             'error' => "",
            //             'code' => 200,
            //             'credit' => $tbl_credit->fresh(),
            //         ];
            //         break;
            //     case 3: //FPay
            //         $response = \Fpay::detail( $tbl_credit );
            //         break;
            //     case 4: //Super Pay
            //         $response = \Superpay::detail( $tbl_credit, 1);
            //         break;
            //     default:
            //         return [
            //             'status' => false,
            //             'message' => __('paymentgateway.no_data_found'),
            //             'error' => "",
            //             'code' => 400,
            //             'credit' => $tbl_credit->fresh(),
            //         ];
            //         break;
            // }
            // $tbl_credit = $tbl_credit->fresh();
            // if ( (int) $tbl_credit->status === 0 ) {
            //     $tbl_credit->reason = __('credit.pending');
            //     $response['credit'] = $tbl_credit;
            //     return $response;
            // }
            // $tbl_credit->reason = $tbl_credit->reason ? __($tbl_credit->reason) : __('credit.active');
            $response['status'] = true;
            $response['message'] = $tbl_credit->reason ? __($tbl_credit->reason) : __('credit.active');
            $response['error'] = "";
            $response['code'] = 200;
            $response['credit'] = $tbl_credit;
            return $response;
        } catch (\Illuminate\Database\QueryException $e) {
            return [
                'status' => false,
                'message' => __('messages.unexpected_error'),
                'error' => $e->getMessage(),
            ];
        }
    }
}
