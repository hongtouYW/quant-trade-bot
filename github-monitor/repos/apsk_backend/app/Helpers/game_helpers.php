<?php

use App\Models\Game;
use App\Models\Gametype;
use App\Models\Gamebookmark;
use App\Models\Gamemember;
use App\Models\Gamepoint;
use App\Models\Gamelog;
use App\Models\Gameplatform;
use App\Models\Member;
use App\Models\Credit;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

if (!function_exists('GetGameName')) {
    /**
     * Retrieves the game Name for a given game name, using caching for performance.
     *
     * @param int $game_id The name of the game.
     * @return ?instringt The ID of the game, or null if not found.
     */
    function GetGameName(int $game_id): ?string
    {
        if (empty($game_id)) {
            return null;
        }
        try {
            $tbl_game = Game::where('game_id', $game_id)->first();
            return $tbl_game ? (string) $tbl_game->game_name : null;
        } catch (\Exception $e) {
            Log::error("GetGameName - Failed to fetch game Name for {$game_id}: {$e->getMessage()}");
            return null;
        }
    }
}

if (!function_exists('GetGameTypeID')) {
    /**
     * Retrieves the game type ID for a given game type name, using caching for performance.
     *
     * @param string $type_name The name of the game type.
     * @return ?int The ID of the game type, or null if not found.
     */
    function GetGameTypeID(string $type_name): ?int
    {
        if (empty($type_name)) {
            return null;
        }
        try {
            $tbl_gametype = Gametype::where('type_name', $type_name)->first();
            return $tbl_gametype ? (int) $tbl_gametype->gametype_id : null;
        } catch (\Exception $e) {
            Log::error("GetGameTypeID - Failed to fetch game type ID for {$type_name}: {$e->getMessage()}");
            return null;
        }
    }
}

if (!function_exists('GetGameTypeName')) {
    /**
     * Retrieves the game type Name for a given game type name, using caching for performance.
     *
     * @param int $gametype_id The name of the game type.
     * @return ?string The Name of the game type, or null if not found.
     */
    function GetGameTypeName(int $gametype_id): ?string
    {
        if (empty($gametype_id)) {
            return null;
        }
        try {
            $tbl_gametype = Gametype::where('gametype_id', $gametype_id)->first();
            return $tbl_gametype ? (string) $tbl_gametype->type_name : null;
        } catch (\Exception $e) {
            Log::error("GetGameTypeName - Failed to fetch game type ID for {$gametype_id}: {$e->getMessage()}");
            return null;
        }
    }
}

if (!function_exists('GetGameMemberID')) {
    /**
     * Retrieves the Member ID for a given game member name, using caching for performance.
     *
     * @param int $gamemember_id The name of the game member.
     * @return ?int The Member ID of the game , or null if not found.
     */
    function GetGameMemberID(int $gamemember_id): ?int
    {
        if (empty($gamemember_id)) {
            return null;
        }
        try {
            $tbl_gamemember = Gamemember::where('gamemember_id', $gamemember_id)->first();
            return $tbl_gamemember ? (int) $tbl_gamemember->member_id : null;
        } catch (\Exception $e) {
            Log::error("GetGameMemberID - Failed to fetch Member ID for {$gamemember_id}: {$e->getMessage()}");
            return null;
        }
    }
}

if (!function_exists('GetGameMemberIDBalance')) {
    /**
     * Retrieves the Member Balance for a given shop name, using caching for performance.
     *
     * @param string $gamemember_id The id of the shop.
     * @return ?float The Balance of the shop, or null if not found.
     */
    function GetGameMemberIDBalance(string $gamemember_id): ?float
    {
        if (empty($gamemember_id)) {
            return null;
        }
        try {
            $tbl_gamemember = Gamemember::where('gamemember_id', $gamemember_id)->first();
            return $tbl_gamemember ? (float) $tbl_gamemember->balance : null;
        } catch (\Exception $e) {
            Log::error("GetGameMemberIDBalance - Failed to fetch balance for {$gamemember_id}: {$e->getMessage()}");
            return null;
        }
    }

}

if (!function_exists('CountGameMemberBalance')) {
    function CountGameMemberBalance( $gamemember_id, $amount)
    {
        try {
            $updateData = [
                'balance' =>  GetGameMemberIDBalance($gamemember_id) + $amount,
                'updated_on' => now(),
            ];
            $tbl_gamemember = Gamemember::where('gamemember_id', $gamemember_id)->first();
            $tbl_gamemember->update($updateData);
        } catch (\Exception $e) {
            Log::error("CountGameMemberBalance - Failed to count balance: {$e->getMessage()}");
            return null;
        }
    }
}

class Gamehelper
{
    protected static $expireSeconds = 3;   // Duplicate window in seconds
    protected static $retentionDays = 2;    // Keep last X days of files
    protected static $storagePath = null;

    /**
     * Get storage folder path
     */
    protected static function getStoragePath(): string
    {
        if (!self::$storagePath) {
            self::$storagePath = storage_path('app/game/token');
        }

        // Ensure directory exists
        if (!is_dir(self::$storagePath)) {
            mkdir(self::$storagePath, 0755, true);
        }

        return self::$storagePath;
    }

    /**
     * Get today's token file path
     */
    protected static function getFilePath(): string
    {
        $date = Carbon::now()->format('Y-m-d');
        return self::getStoragePath() . "/request_tokens_{$date}.json";
    }

    /**
     * Delete old daily token files beyond retention period
     */
    public static function cleanOldFiles()
    {
        $files = File::files(self::getStoragePath());
        $now = Carbon::now();

        foreach ($files as $file) {
            $filename = $file->getFilename();

            if (preg_match('/^request_tokens_(\d{4}-\d{2}-\d{2})\.json$/', $filename, $matches)) {
                $fileDate = Carbon::createFromFormat('Y-m-d', $matches[1]);

                if ($fileDate->diffInDays($now) > self::$retentionDays) {
                    File::delete($file->getRealPath());
                }
            }
        }
    }

    /**
     * Check if a request token is duplicate
     * Returns true = not duplicate, false = duplicate
     *
     * @param Request $request
     * @param array $extra Optional extra parameters to include in token
     */
    public static function duplicaterequest( $id, Request $request, array $extra = []): bool
    {
        // Deterministic token: user + API route + optional extra params
        $tokenString = (string) $id . '|' . $request->method() . '|' . $request->path();
        if (!empty($extra)) {
            $tokenString .= json_encode($extra);
        }
        $token = md5($tokenString);

        $now = Carbon::now()->timestamp;

        // Auto-clean old daily files
        self::cleanOldFiles();

        $filePath = self::getFilePath();
        $data = [];

        // Open file with exclusive lock (create if not exists)
        $file = fopen($filePath, 'c+');
        if (!$file) {
            return false; // Unsafe to proceed
        }

        try {
            if (flock($file, LOCK_EX)) {

                // Read existing tokens safely
                $contents = stream_get_contents($file);
                $data = $contents ? json_decode($contents, true) : [];
                if (!is_array($data)) $data = [];

                // Remove expired tokens
                $data = array_filter($data, fn($timestamp) => ($now - $timestamp) < self::$expireSeconds);

                // Check for duplicate
                if (isset($data[$token])) {
                    return false; // Duplicate found
                }

                // Add new token
                $data[$token] = $now;

                // Rewind file and write updated data
                ftruncate($file, 0);
                rewind($file);
                fwrite($file, json_encode($data));

            } else {
                return false; // Could not acquire lock
            }
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }

        return true; // Not duplicate
    }

    private static function neworderid(int $length = 15)
    {
        do {
            $orderid = '';
            $orderid .= random_int(1, 9);
            for ($i = 1; $i < $length; $i++) {
                $orderid .= random_int(0, 9);
            }
        } while (Gamepoint::where('orderid', $orderid)->exists());
        return $orderid;
    }

    public static function providerlist( $gameplatform_id )
    {
        switch ($gameplatform_id) {
            case 1004: //Mega
                $response = \Mega::providerlist();
                break;
            case 1004: //Jili
                $response = [
                    'status'  => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'code' => 200,
                ];
                break;
            case 1005: //Onehubx
                $response = \Onehubx::providerlist();
                break;
            default:
                $response = [
                    'status'  => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'code' => 200,
                ];
                break;
        }
        return $response;
    }

    public static function gamelist( $tbl_provider )
    {
        switch ($tbl_provider->gameplatform_id) {
            case 1003: //Mega
                return [
                    'status'  => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => [],
                    'code' => 200
                ];
            case 1004: //Jili
                return \Jili::gamelist( $tbl_provider );
                break;
            case 1005: //Onehubx
                return \Onehubx::gamelist( $tbl_provider );
                break;
            default:
                return [
                    'status' => false,
                    'message' => __('provider.no_data_found'),
                    'error' => __('provider.no_data_found'),
                    'code'    => 400
                ];
                break;
        }
    }

    public static function create($tbl_provider, $player_name)
    {
        $player_pass = generateRandomPasswordSimple();
        switch ($tbl_provider->gameplatform_id) {
            case 1003: //Mega
                return \Mega::register( $player_name, $player_pass );
                break;
            case 1004: //Jili
                return \Jili::register();
                break;
            case 1005: //Onehubx
                return \Onehubx::register( $tbl_provider );
                break;
            default:
                return [
                    'status'  => false,
                    'message' => __('game.no_platform_found'),
                    'error' => __('game.no_platform_found'),
                    'code'    => 500
                ];
                break;
        }
    }

    public static function view( $gamemember_id )
    {
        $tbl_player = Gamemember::where('gamemember_id', $gamemember_id )
                                ->first();
        if (!$tbl_player) {
            return [
                'status'  => false,
                'message' => __('gamemember.player_no_found'),
                'error' => __('gamemember.player_no_found'),
                'code'    => 400
            ];
        }
        switch ($tbl_player->gameplatform_id) {
            case 1003: //Mega
                return \Mega::view( $tbl_player );
                break;
            case 1004: //Jili
                return \Jili::view( $tbl_player );
                break;
            case 1005: //Onehubx
                return \Onehubx::view( $tbl_player );
                break;
            default:
                return [
                    'status'  => false,
                    'message' => __('game.no_platform_found'),
                    'error' => __('game.no_platform_found'),
                    'code'    => 500
                ];
                break;
        }
    }

    public static function login($request, $tbl_player, $tbl_game = null)
    {
        switch ($tbl_player->gameplatform_id) {
            case 1003: //Mega
                return [
                    'status' => true,
                    'message' => __('messages.login_success'),
                    'error' => '',
                    'code' => 200,
                    'url' => 'https://m.mega57.com',
                ];
                break;
            case 1004: //Jili
                if ( is_null($tbl_game) ) {
                    return [
                        'status'  => false,
                        'message' => __('game.no_data_found'),
                        'error' => __('game.no_data_found'),
                        'code'    => 500
                    ];
                }
                return \Jili::login($request, $tbl_player, $tbl_game);
                break;
            case 1005: //Onehubx
                return \Onehubx::login($tbl_player, $tbl_game);
                break;
            default:
                return [
                    'status'  => false,
                    'message' => __('game.no_platform_found'),
                    'error' => __('game.no_platform_found'),
                    'code'    => 500
                ];
                break;
        }
    }

    public static function changepassword( $tbl_player, $password )
    {
        switch ($tbl_player->gameplatform_id) {
            case 1003: //Mega
                break;
            case 1004: //Jili
                break;
            case 1005: //Onehubx
                $response = \Onehubx::resetpassword($tbl_player);
                if ( !$response['status'] ) {
                    return $response;
                }
                $password = $response['password'];
                break;
            default:
                return [
                    'status'  => false,
                    'message' => __('game.no_platform_found'),
                    'error' => __('game.no_platform_found'),
                    'code'    => 500
                ];
                break;
        }
        $tbl_player->update([
            'pass' => encryptPassword($password),
            'updated_on' => now(),
        ]);
        return $tbl_player->fresh();
    }

    public static function deposit( $gamemember_id, $amount, $ip )
    {
        $response = self::view( $gamemember_id );
        $type = 'reload';
        if ( !$response['status'] ) {
            return $response;
        }
        $tbl_player = $response['data'];
        switch ($tbl_player->gameplatform_id) {
            case 1003: //Mega
                return \Mega::transfer( $tbl_player, $amount, $ip );
                break;
            case 1004: //Jili
                return \Jili::transfer( $tbl_player, $amount, 2 );
                break;
            case 1005: //Onehubx
                return \Onehubx::transfer($tbl_player, $amount, $type);
                break;
            default:
                return [
                    'status'  => false,
                    'message' => __('game.no_platform_found'),
                    'error' => __('game.no_platform_found'),
                    'code'    => 500
                ];
                break;
        }
    }

    public static function withdraw( $gamemember_id, $amount, $ip )
    {
        $response = self::view( $gamemember_id );
        $type = 'withdraw';
        if ( !$response['status'] ) {
            return $response;
        }
        $tbl_player = $response['data'];
        if ( $tbl_player->balance - $amount < 0 ) {
            return [
                'status' => false,
                'message' => __('role.member')." ".__('messages.insufficient'),
                'error' => __('role.member')." ".__('messages.insufficient'),
                'code'    => 403
            ];
        }
        switch ($tbl_player->gameplatform_id) {
            case 1003: //Mega
                return \Mega::transfer( $tbl_player, ReverseDecimal($amount), $ip );
                break;
            case 1004: //Jili
                \Jili::kick($tbl_player);
                return \Jili::transfer( $tbl_player, $amount, 3 );
                break;
            case 1005: //Onehubx
                return \Onehubx::transfer($tbl_player, $amount, $type );
                break;
            default:
                return [
                    'status'  => false,
                    'message' => __('game.no_platform_found'),
                    'error' => __('game.no_platform_found'),
                    'code'    => 500
                ];
                break;
        }
    }

    public static function delete( $gamemember_id, $ip )
    {
        $response = self::view( $gamemember_id );
        if ( !$response['status'] ) {
            return $response;
        }
        $tbl_player = $response['data'];
        if ( $tbl_player->status !== 1 ) {
            return [
                'status'  => true,
                'message' => __(
                    'gamemember.gamemember_deleted_successfully', 
                    ['gamemember_id' => $gamemember_id] 
                ),
                'error'   => "",
                'data' => $tbl_player,
                'code'    => 200
            ];
        }
        // 余额必须少过一块以下
        if ( $tbl_player->balance >= 1 ) {
            $response['status'] = false;
            $response['message'] = __('gamemember.balance_must_below_1');
            $response['error'] = __('gamemember.balance_must_below_1');
            $response['code'] = 400;
            return $response;
        }
        switch ($tbl_player->gameplatform_id) {
            case 1003: //Mega
                $delete = \Mega::enable($tbl_player, 0);
                if ( !$delete['status'] ) {
                    return $delete;
                }
                break;
            case 1004: //Jili
                break;
            case 1005: //Onehubx
                // Provider does not support disabling player
                // $delete = \Onehubx::enable($tbl_player, 0);
                // if ( !$delete['status'] ) {
                //     return $delete;
                // }
                break;
            default:
                return [
                    'status'  => false,
                    'message' => __('game.no_platform_found'),
                    'error' => __('game.no_platform_found'),
                    'code'    => 500,
                ];
                break;
        }
        return [
            'status'  => true,
            'message' => __(
                'gamemember.gamemember_deleted_successfully', 
                ['gamemember_id' => $gamemember_id] 
            ),
            'error'   => "",
            'data' => $tbl_player,
            'code'    => 200
        ];
    }

    public static function gamelog( $request, $gamemember_id )
    {
        $tbl_player = Gamemember::where('gamemember_id', $gamemember_id )
                                ->with('Member')
                                ->first();
        if (!$tbl_player) {
            return [
                'status'  => false,
                'message' => __('gamemember.player_no_found'),
                'error' => __('gamemember.player_no_found'),
                'code'    => 400
            ];
        }
        switch ($tbl_player->gameplatform_id) {
            case 1003: //Mega
                return \Mega::gameorder( $tbl_player );
                break;
            case 1004: //Jili
                return \Jili::gamelog( $tbl_player );
                break;
            case 1005: //Onehubx
                return \Onehubx::gamelog( $tbl_player );
                break;
            default:
                return [
                    'status'  => false,
                    'message' => __('game.no_platform_found'),
                    'error' => __('game.no_platform_found'),
                    'code'    => 500
                ];
                break;
        }
    }

    // Gamemember close account balance
    public static function block( $tbl_member, $request )
    {
        $ip = $request->filled('ip')
            ? $request->input('ip')
            : $request->ip();
        $tbl_player = Gamemember::where('member_id', $request->input('member_id') )
                                ->where('status', 1)
                                ->where('delete', 0)
                                ->where('has_balance', 1)
                                ->get();
        $viewresponses = [];
        $withdrawresponses = [];
        foreach ($tbl_player as $player) {
            switch ($player->gameplatform_id) {
                case 1003: //Mega
                    $viewresponses[] = \Mega::view( $player );
                    break;
                case 1004: //Jili
                    $viewresponses[] = \Jili::view( $player );
                    break;
                case 1005: //Onehubx
                    $viewresponses[] = \Onehubx::view( $player );
                    break;
                default:
                    break;
            }
        }
        if ( empty( $viewresponses ) ) {
            return $withdrawresponses;
        }
        foreach ($viewresponses as $viewresponse) {
            if ( !$viewresponse['status'] ) {
                continue;
            }
            if ( empty( $viewresponse['data'] ) ) {
                continue;
            }
            if ($viewresponse['data']->balance <= 0) {
                continue;
            }
            $player = $viewresponse['data'];
            switch ($player->gameplatform_id) {
                case 1003: //Mega
                    $withdrawresponses[] = \Mega::transfer( 
                        $player, 
                        ReverseDecimal($player->balance), 
                        $ip, 
                    );
                    break;
                case 1004: //Jili
                    $withdrawresponses[] = \Jili::transfer( 
                        $player, 
                        $player->balance, 
                        3, 
                    );
                    break;
                case 1005: //Onehubx
                    $withdrawresponses[] = \Onehubx::transfer(
                        $player, 
                        $player->balance, 
                        'withdraw', 
                    );
                    break;
                default:
                    break;
            }
        }
        return $withdrawresponses;
    }
}