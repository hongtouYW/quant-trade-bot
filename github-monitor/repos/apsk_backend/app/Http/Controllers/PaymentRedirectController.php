<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Credit;

class PaymentRedirectController extends Controller
{
    public function handle(Request $request)
    {
        $creditId = $request->cookie('credit_id');
        $userAgent = strtolower($request->header('User-Agent'));

        $device = 'web';

        if (strpos($userAgent, 'android') !== false) {
            $device = 'android';
        }

        if (!$creditId) {
            // No cookie: show failed result
            return redirect()->route('payment.result')->with([
                'status' => 'failed',
                'message' => 'Credit ID not found.'
            ]);
        }

        $credit = Credit::find($creditId);

        if (!$credit) {
            return view('payment.result')->with([
                'status' => 'failed',
                'message' => 'Credit record not found.'
            ]);
        }

        // Redirect to your app download/redirect URL
        return view('payment.result')->with([
            'status' => $credit->status,
            'credit' => $credit,
            'device' => $device
        ]);
    }
}
