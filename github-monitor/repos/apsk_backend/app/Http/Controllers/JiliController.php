<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\Credit;
use App\Models\Member;
use App\Models\Game;
use App\Models\Gametype;
use App\Models\Gamemember;
use App\Models\Gamepoint;
use App\Models\Jilicallback;
use Carbon\Carbon;

class JiliController extends Controller
{
    public function callback(Request $request)
    {
        try {
            $response = $request->all();
            $tbl_jilicallback = Jilicallback::create([
                'response' => json_encode($response),
                'error' => isset($response['error']) ? 1 : 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            return [
                'success'   => 1,
                'message'   => 'success',
                'errorCode' => 200,
            ];
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::info('API Jili CallBack Request', $e->getMessage() );
            return [
                'success'   => 0,
                'message'   => $e->getMessage(),
                'errorCode' => 500,
            ];
        }
    }
}
