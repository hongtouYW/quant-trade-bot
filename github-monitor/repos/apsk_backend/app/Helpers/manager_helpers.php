<?php

use Illuminate\Http\Request;
use App\Models\Manager;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

if (!function_exists('GetManagerID')) {
    /**
     * Retrieves the manager ID for a given manager name, using caching for performance.
     *
     * @param string $manager_name The name of the manager.
     * @return ?int The ID of the manager, or null if not found.
     */
    function GetManagerID(string $manager_name): ?int
    {
        if (empty($manager_name)) {
            return null;
        }
        try {
            $tbl_manager = Manager::where('manager_name', $manager_name)->first();
            return $tbl_manager ? (int) $tbl_manager->manager_id : null;
        } catch (\Exception $e) {
            Log::error("GetManagerID - Failed to fetch manager ID for {$manager_name}: {$e->getMessage()}");
            return null;
        }
    }
}

if (!function_exists('GetManagerName')) {
    /**
     * Retrieves the manager ID for a given manager name, using caching for performance.
     *
     * @param int $manager_id The name of the manager.
     * @return ?string The Name of the manager name, or null if not found.
     */
    function GetManagerName(int $manager_id): ?string
    {
        if (empty($manager_id)) {
            return null;
        }
        try {
            $tbl_manager = Manager::where('manager_id', $manager_id)->first();
            return $tbl_manager ? (int) $tbl_manager->manager_name : null;
        } catch (\Exception $e) {
            Log::error("GetManagerName - Failed to fetch Manager Name for {$manager_id}: {$e->getMessage()}");
            return null;
        }
    }
}

if (!function_exists('GetManagerIDBalance')) {
    /**
     * Retrieves the Manager Balance for a given shop name, using caching for performance.
     *
     * @param int $manager_id The id of the shop.
     * @return ?float The Balance of the shop, or null if not found.
     */
    function GetManagerIDBalance(int $manager_id): ?float
    {
        if (empty($manager_id)) {
            return null;
        }
        try {
            $tbl_manager = Manager::where('manager_id', $manager_id)->first();
            return $tbl_manager ? (float) $tbl_manager->balance : null;
        } catch (\Exception $e) {
            Log::error("GetManagerIDBalance - Failed to fetch balance for {$manager_id}: {$e->getMessage()}");
            return null;
        }
    }

}

if (!function_exists('CountManagerBalance')) {
    function CountManagerBalance( $manager_id, $amount)
    {
        try {
            $updateData = [
                'balance' =>  GetManagerIDBalance($manager_id) + $amount,
                'updated_on' => now(),
            ];
            $tbl_manager = Manager::where('manager_id', $manager_id)->first();
            $tbl_manager->update($updateData);
        } catch (\Exception $e) {
            Log::error("CountManagerBalance - Failed to count balance: {$e->getMessage()}");
            return null;
        }
    }
}