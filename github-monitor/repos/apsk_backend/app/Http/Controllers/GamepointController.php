<?php

namespace App\Http\Controllers;

use App\Models\Gamepoint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class GamepointController extends Controller
{

    /**
     * latest score tbl_gamepoint.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function latestscore(Request $request)
    {

    }

}
