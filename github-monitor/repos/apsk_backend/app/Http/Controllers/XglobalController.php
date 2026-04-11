<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Xglobalcallback;
use Carbon\Carbon;

class XglobalController extends Controller
{
    public function callback(Request $request)
    {
        try {
            $jsonpayload = preg_replace('/^json=/', '', $request->getContent() );
            // $jsonpayload = json_encode($request->all());
            $tbl_xglobalcallback = Xglobalcallback::create([
                // 'response' => json_encode($request->all()),
                'response' => $jsonpayload,
                'error' => $request->has('error') ? 1 : 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $datarequest = json_decode( $jsonpayload );

            return [
                'success'   => 1,
                'message' => __('xglobal.callback_success'),
            ];
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::info('API FPay CallBack Request', $e->getMessage() );
            return [
                'success'   => 0,
                'message' => $e->getMessage(),
            ];
        }
    }
}
