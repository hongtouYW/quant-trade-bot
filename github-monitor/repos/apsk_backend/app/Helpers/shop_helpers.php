<?php

use Illuminate\Http\Request;
use App\Models\Shop;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;


if (!function_exists('GetShopName')) {
    /**
     * Retrieves the shop ID for a given shop name, using caching for performance.
     *
     * @param int $shop_id The name of the shop.
     * @return ?string The Name of the shop name, or null if not found.
     */
    function GetShopName(int $shop_id): ?string
    {
        if (empty($shop_id)) {
            return null;
        }
        try {
            $tbl_shop = Shop::where('shop_id', $shop_id)->first();
            return $tbl_shop ? (int) $tbl_shop->shop_name : null;
        } catch (\Exception $e) {
            Log::error("GetShopName - Failed to fetch shop Name for {$shop_id}: {$e->getMessage()}");
            return null;
        }
    }
}

if (!function_exists('GetShopIDBalance')) {
    /**
     * Retrieves the Shop Balance for a given shop name, using caching for performance.
     *
     * @param string $shop_id The id of the shop.
     * @return ?float The Balance of the shop, or null if not found.
     */
    function GetShopIDBalance(string $shop_id): ?float
    {
        if (empty($shop_id)) {
            return null;
        }
        try {
            $tbl_shop = Shop::where('shop_id', $shop_id)->first();
            return $tbl_shop ? (float) $tbl_shop->balance : null;
        } catch (\Exception $e) {
            Log::error("GetShopIDBalance - Failed to fetch balance for {$shop_id}: {$e->getMessage()}");
            return null;
        }
    }

}

if (!function_exists('GetShopBalance')) {
    /**
     * Retrieves the Shop Balance for a given shop name, using caching for performance.
     *
     * @param ?int $shop_id The id of the shop.
     * @return ?float The Balance of the shop, or null if not found.
     */
    function GetShopBalance(?int $shop_id): ?float
    {
        if (empty($shop_id)) {
            return null;
        }
        try {
            $tbl_shop = Shop::where('shop_id', $shop_id)->first();
            return $tbl_shop ? (float) $tbl_shop->balance : null;
        } catch (\Exception $e) {
            Log::error("GetShopBalance - Failed to fetch balance for {$shop_id}: {$e->getMessage()}");
            return null;
        }
    }

}

if (!function_exists('CountShopBalance')) {
    function CountShopBalance( $shop_id, $amount)
    {
        try {
            $updateData = [
                'balance' => GetShopBalance($shop_id) + $amount,
                'updated_on' => now(),
            ];
            $tbl_shop = Shop::where('shop_id', $shop_id)->first();
            $tbl_shop->update($updateData);
        } catch (\Exception $e) {
            Log::error("CountShopBalance - Failed to count balance: {$e->getMessage()}");
            return null;
        }
    }
}