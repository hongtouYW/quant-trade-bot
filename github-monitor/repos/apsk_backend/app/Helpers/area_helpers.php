<?php

use App\Models\Countries;
use App\Models\States;
use App\Models\Areas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

if (!function_exists('GetCountryName')) {
    /**
     * Retrieves the Country Name.
     *
     * @param string $country_code.
     * @return ?string $country_name.
     */
    function GetCountryName(string $country_code): ?string
    {
        if (empty($country_code)) {
            return null;
        }
        try {
            $tbl_countries = Countries::where('country_code', $country_code)->first();
            return $tbl_countries ? (string) $tbl_countries->country_name : null;
        } catch (\Exception $e) {
            Log::error("GetCountryName - Failed to fetch Country Name for {$country_code}: {$e->getMessage()}");
            return null;
        }
    }
}

if (!function_exists('GetCountryID')) {
    /**
     * Retrieves the Country ID.
     *
     * @param string $country_name.
     * @return ?string $country_code.
     */
    function GetCountryID(string $country_name): ?string
    {
        if (empty($country_name)) {
            return null;
        }
        try {
            $tbl_countries = Countries::where('country_name', $country_name)->first();
            return $tbl_countries ? (string) $tbl_countries->country_code : null;
        } catch (\Exception $e) {
            Log::error("GetCountryID - Failed to fetch Country ID for {$country_name}: {$e->getMessage()}");
            return null;
        }
    }
}

if (!function_exists('GetStateName')) {
    /**
     * Retrieves the State Name.
     *
     * @param string $state_code.
     * @return ?string $state_name.
     */
    function GetStateName(string $state_code): ?string
    {
        if (empty($state_code)) {
            return null;
        }
        try {
            $tbl_states = States::where('state_code', $state_code)->first();
            return $tbl_states ? (string) $tbl_states->state_name : null;
        } catch (\Exception $e) {
            Log::error("GetStateName - Failed to fetch State Name for {$state_code}: {$e->getMessage()}");
            return null;
        }
    }
}

if (!function_exists('GetStateID')) {
    /**
     * Retrieves the State Name.
     *
     * @param string $state_code.
     * @return ?string $state_name.
     */
    function GetStateID(string $state_name): ?string
    {
        if (empty($state_name)) {
            return null;
        }
        try {
            $tbl_states = States::where('state_name', $state_name)->first();
            return $tbl_states ? (string) $tbl_states->state_code : null;
        } catch (\Exception $e) {
            Log::error("GetStateID - Failed to fetch State ID for {$state_name}: {$e->getMessage()}");
            return null;
        }
    }
}