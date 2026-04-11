<?php

use App\Models\Game;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Contracts\Auth\Authenticatable;
use App\Enums\TokenAbility;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Config;
use App\Models\Megacallback;

if (!function_exists('safe_json_decode')) {
    function safe_json_decode($response, $assoc = true) {
        // First, try to decode directly
        $decoded = json_decode($response, $assoc);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded; // already valid JSON
        }
        // If decoding failed, try again after stripslashes
        $decoded = json_decode(stripslashes($response), $assoc);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
        // If still invalid, maybe it's double-encoded (wrapped in quotes)
        $decoded = json_decode(json_decode($response), $assoc);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
        // Still not valid JSON
        return null;
    }
}
