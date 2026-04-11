<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use Carbon\Carbon;

if (!function_exists('queryDateTimeNow')) {
    function queryDateTimeNow()
    {
        return Carbon::now()->format('Y-m-d H:i:s');
    }
}

if (!function_exists('queryDateTimeNowStart')) {
    function queryDateTimeNowStart()
    {
        return Carbon::now()->startOfDay();
    }
}

if (!function_exists('queryDateTimeNowEnd')) {
    function queryDateTimeNowEnd()
    {
        return Carbon::now()->endOfDay();
    }
}

if (!function_exists('queryBetweenDate')) {
    /**
     * Apply date range filtering to a query.
     *
     * @param Builder $query The query builder instance
     * @param Request $request The HTTP request containing startdate and enddate
     * @param string $column The database column to filter on (default: 'created_on')
     * @return Builder
     */
    function queryBetweenDate(Builder $query, Request $request, string $column = 'created_on')
    {
        try {
            if ($request->filled('startdate') && $request->filled('enddate')) {
                $query->whereBetween($column, [
                    Carbon::parse($request->input('startdate'))->startOfDay(),
                    Carbon::parse($request->input('enddate'))->endOfDay(),
                ]);
            } elseif ($request->filled('startdate')) {
                $query->where($column, '>=', Carbon::parse($request->input('startdate'))->startOfDay());
            } elseif ($request->filled('enddate')) {
                $query->where($column, '<=', Carbon::parse($request->input('enddate'))->endOfDay());
            }
        } catch (\Exception $e) {
            // Log the error and return the unmodified query to avoid breaking the query
            \Log::warning('Invalid date format in QueryBetweenDate', [
                'startdate' => $request->input('startdate'),
                'enddate' => $request->input('enddate'),
                'error' => $e->getMessage(),
            ]);
        }
        return $query;
    }
}

if (!function_exists('queryBetweenDateEloquent')) {
    /**
     * Apply date range filtering to a query.
     *
     * @param Illuminate\Database\Eloquent\Builder $query The query builder instance
     * @param Request $request The HTTP request containing startdate and enddate
     * @param string $column The database column to filter on (default: 'created_on')
     * @return Illuminate\Database\Eloquent\Builder
     */
    function queryBetweenDateEloquent(Illuminate\Database\Eloquent\Builder $query, Request $request, string $column = 'created_on')
    {
        try {
            if ($request->filled('startdate') && $request->filled('enddate')) {
                $query->whereBetween($column, [
                    Carbon::parse($request->input('startdate'))->startOfDay(),
                    Carbon::parse($request->input('enddate'))->endOfDay(),
                ]);
            } elseif ($request->filled('startdate')) {
                $query->where($column, '>=', Carbon::parse($request->input('startdate'))->startOfDay());
            } elseif ($request->filled('enddate')) {
                $query->where($column, '<=', Carbon::parse($request->input('enddate'))->endOfDay());
            }
        } catch (\Exception $e) {
            // Log the error and return the unmodified query to avoid breaking the query
            \Log::warning('Invalid date format in QueryBetweenDate', [
                'startdate' => $request->input('startdate'),
                'enddate' => $request->input('enddate'),
                'error' => $e->getMessage(),
            ]);
        }
        return $query;
    }
}

if (!function_exists('queryBetweenYear')) {
    /**
     * Apply date range filtering to a query.
     *
     * @param Builder $query The query builder instance
     * @param Request $request The HTTP request containing startdate and enddate
     * @param string $column The database column to filter on (default: 'created_on')
     * @return Builder
     */
    function queryBetweenYear(Builder $query, Request $request, string $column = 'created_on')
    {
        try {
            if ($request->filled('year')) {
                $year = $request->input('year');
                $start = Carbon::createFromDate($year, 1, 1)->startOfDay();
                $end   = Carbon::createFromDate($year, 12, 31)->endOfDay();
                $query->whereBetween($column, [
                    $start,
                    $end,
                ]);
            }
        } catch (\Exception $e) {
            // Log the error and return the unmodified query to avoid breaking the query
            \Log::warning('Invalid date format in queryBetweenYear', [
                'year' => $request->input('year'),
                'error' => $e->getMessage(),
            ]);
        }
        return $query;
    }
}