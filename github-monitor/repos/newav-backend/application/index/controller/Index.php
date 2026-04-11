<?php
namespace app\index\controller;
use think\facade\App;
use app\index\model\Order as VipOrder;
use app\index\model\Video;
use app\index\model\User;
use think\Db;
class Index extends Base
{
    //主页
    public function index(){
        return $this->fetch("index");
    }


    /**
     * Notes:欢迎页
     * User: joker
     * Date: 2022/1/15
     * Time: 16:55
     * @return mixed
     *
     */
    public function welcome()
    {
        $now = time();
        $startOfToday     = strtotime(date('Y-m-d 00:00:00', $now));
        $endOfToday       = strtotime(date('Y-m-d 23:59:59', $now));
        $startOfYesterday = strtotime(date('Y-m-d 00:00:00', strtotime('-1 day', $now)));
        $endOfYesterday   = strtotime(date('Y-m-d 23:59:59', strtotime('-1 day', $now)));
        $startOfMonth     = strtotime(date('Y-m-01 00:00:00', $now));
        $startOfQuarter   = strtotime(date('Y-'.(floor((date('n',$now)-1)/3)*3+1).'-01 00:00:00', $now));
        $startOfYear      = strtotime(date('Y-01-01 00:00:00', $now));

        // TOTAL ORDERS (all statuses)
        $totalOrdersToday     = VipOrder::whereBetween('add_time', [$startOfToday, $endOfToday])->count();
        $totalOrdersYesterday = VipOrder::whereBetween('add_time', [$startOfYesterday, $endOfYesterday])->count();

        // TOTAL ORDERS (status = 1 only)
        $totalPaidOrdersToday     = VipOrder::where('status', 1)->whereBetween('add_time', [$startOfToday, $endOfToday])->count();
        $totalPaidOrdersYesterday = VipOrder::where('status', 1)->whereBetween('add_time', [$startOfYesterday, $endOfYesterday])->count();

        // Total Profit
        $totalMoneyToday     = VipOrder::where('status', 1)->whereBetween('add_time', [$startOfToday, $endOfToday])->sum('money');
        $totalMoneyYesterday = VipOrder::where('status', 1)->whereBetween('add_time', [$startOfYesterday, $endOfYesterday])->sum('money');
        $totalMoneyToday     = round($totalMoneyToday, 2);
        $totalMoneyYesterday = round($totalMoneyYesterday, 2);
        
        // Total User info
        $totalUsers         = User::count();
        $todayUsers         = User::where('reg_time', '>=', $startOfToday)->count();
        $yesterdayUsers     = User::whereBetween('reg_time', [$startOfYesterday, $endOfYesterday])->count();
        $todayLoginUser     = Db::name('login_log')->where('created_at', '>=', $startOfToday)->distinct(true)->count('ip');
        $yesterdayLoginUser = Db::name('login_log')->whereBetween('created_at', [$startOfYesterday, $endOfYesterday])->distinct(true)->count('ip');

        // Returning buyer login: users who paid in last 7 days and logged in today/yesterday
        $last7days = $now - 7 * 86400;
        $buyerUids = Db::name('vip_order')->where('status', 1)->where('pay_time', '>=', $last7days)->column('uid');
        $buyerUids = array_values(array_unique($buyerUids));
        if (!empty($buyerUids)) {
            $todayBuyerLogin     = Db::name('login_log')->whereIn('uid', $buyerUids)->where('created_at', '>=', $startOfToday)->distinct(true)->count('uid');
            $yesterdayBuyerLogin = Db::name('login_log')->whereIn('uid', $buyerUids)->whereBetween('created_at', [$startOfYesterday, $endOfYesterday])->distinct(true)->count('uid');
        } else {
            $todayBuyerLogin     = 0;
            $yesterdayBuyerLogin = 0;
        }


        // --- Top-level totals (include all orders even inactive) ---
        $totalSales   = round(VipOrder::where('status',1)->sum('money'), 2);
        $totalRecords = VipOrder::where('status',1)->count();
        $vipSales     = round(VipOrder::where('product_type', 1)->where('status',1)->sum('money'), 2);
        $vipCount     = VipOrder::where('product_type', 1)->where('status',1)->count();
        $pointSales   = round(VipOrder::where('product_type', 2)->where('status',1)->sum('money'), 2);
        $pointCount   = VipOrder::where('product_type', 2)->where('status',1)->count();
        $coinSales    = round(VipOrder::where('product_type', 3)->where('status',1)->sum('money'), 2);
        $coinCount    = VipOrder::where('product_type', 3)->where('status',1)->count();

        // --- Time-based totals (only active products) ---
        $getTotalsByType = function($type, $table) use ($startOfToday, $startOfYesterday, $endOfYesterday, $startOfMonth, $startOfQuarter, $startOfYear) {
        $baseQuery = VipOrder::where('product_type', $type)
            ->where('status', 1);

            return [
                'today' => [
                    'sales' => round((clone $baseQuery)->where('add_time', '>=', $startOfToday)->sum('money'), 2),
                    'count' => (clone $baseQuery)->where('add_time', '>=', $startOfToday)->count(),
                ],
                'yesterday' => [
                    'sales' => round((clone $baseQuery)->whereBetween('add_time', [$startOfYesterday, $endOfYesterday])->sum('money'), 2),
                    'count' => (clone $baseQuery)->whereBetween('add_time', [$startOfYesterday, $endOfYesterday])->count(),
                ],
                'month' => [
                    'sales' => round((clone $baseQuery)->where('add_time', '>=', $startOfMonth)->sum('money'), 2),
                    'count' => (clone $baseQuery)->where('add_time', '>=', $startOfMonth)->count(),
                ],
                'quarter' => [
                    'sales' => round((clone $baseQuery)->where('add_time', '>=', $startOfQuarter)->sum('money'), 2),
                    'count' => (clone $baseQuery)->where('add_time', '>=', $startOfQuarter)->count(),
                ],
                'year' => [
                    'sales' => round((clone $baseQuery)->where('add_time', '>=', $startOfYear)->sum('money'), 2),
                    'count' => (clone $baseQuery)->where('add_time', '>=', $startOfYear)->count(),
                ],
            ];
        };


        // --- Breakdowns by type (only active products) ---
        $getBreakdownsByType = function($type, $table) {
            $rows = VipOrder::alias('o')
                ->join($table.' p', 'o.product_id=p.id')
                ->where('o.product_type', $type)
                ->where('p.status', 1)
                ->where('o.status', 1)
                ->field('p.title, SUM(o.money) as sales, COUNT(*) as count')
                ->group('o.product_id')
                ->select();

            $result = [];
            foreach ($rows as $item) {
                $result[] = [
                    'title' => $item['title'],
                    'sales' => round($item['sales'], 2),
                    'count' => (int)$item['count'],
                ];
            }
            return $result;
        };

        // --- Video statistics ---
        $totalVideos     = Video::count();
        $todayVideos     = Video::where('insert_time', '>=', $startOfToday)->count();
        $yesterdayVideos = Video::whereBetween('insert_time', [$startOfYesterday, $endOfYesterday])->count();
        // $monthVideos = Video::where('insert_time', '>=', $startOfMonth)->count();

        // --- Zimu statistics (from zimu_log table) ---
        // type: 1=transcribe, 2=translate | event: 1=request, 2=success, 3=failed
        $transcribeStats = [
            'requested' => Db::name('zimu_log')->where('type', 1)->where('event', 1)->where('created_at', '>=', $startOfToday)->count(),
            'success'   => Db::name('zimu_log')->where('type', 1)->where('event', 2)->where('created_at', '>=', $startOfToday)->count(),
            'pending'   => Db::name('video')->where('zimu_status', 1)->count(),
            'failed'    => Db::name('zimu_log')->where('type', 1)->where('event', 3)->where('created_at', '>=', $startOfToday)->count(),
        ];
        $translateStats = [
            'requested' => Db::name('zimu_log')->where('type', 2)->where('event', 1)->where('created_at', '>=', $startOfToday)->count(),
            'success'   => Db::name('zimu_log')->where('type', 2)->where('event', 2)->where('created_at', '>=', $startOfToday)->count(),
            'pending'   => Db::name('video')->where('zimu_status', 3)->count(),
            'failed'    => Db::name('zimu_log')->where('type', 2)->where('event', 3)->where('created_at', '>=', $startOfToday)->count(),
        ];

        $this->assign([
            'total_sales'   => $totalSales,
            'total_records' => $totalRecords,
            'vip'   => ['sales' => $vipSales, 'count' => $vipCount],
            'coin'  => ['sales' => $coinSales, 'count' => $coinCount],
            'point' => ['sales' => $pointSales, 'count' => $pointCount],
            'totals' => [
                'vip'   => $getTotalsByType(1, 'vip'),
                'point' => $getTotalsByType(2, 'point'),
                'coin'  => $getTotalsByType(3, 'coin'),
            ],
            'breakdowns' => [
                'vip'   => $getBreakdownsByType(1, 'vip'),
                'point' => $getBreakdownsByType(2, 'point'),
                'coin'  => $getBreakdownsByType(3, 'coin'),
            ],
            'videos' => [
                'total'     => $totalVideos,
                'today'     => $todayVideos,
                'yesterday' => $yesterdayVideos,
                // 'month' => $monthVideos,
            ],
            'order_stats' => [
                'today' => [
                    'total_order'  => $totalOrdersToday,
                    'paid_order'   => $totalPaidOrdersToday,
                    'money'        => $totalMoneyToday,
                ],
                'yesterday' => [
                    'total_order'  => $totalOrdersYesterday,
                    'paid_order'   => $totalPaidOrdersYesterday,
                    'money'        => $totalMoneyYesterday,
                ],
            ],
            'transcribe_stats' => $transcribeStats,
            'translate_stats'  => $translateStats,
            'user_stats' => [
                'total'                => $totalUsers,
                'today'                => $todayUsers,
                'yesterday'            => $yesterdayUsers,
                'today_login'          => $todayLoginUser,
                'yesterday_login'      => $yesterdayLoginUser,
                'today_buyer_login'    => $todayBuyerLogin,
                'yesterday_buyer_login'=> $yesterdayBuyerLogin,
            ],
        ]);
        return $this->fetch('welcome');
    }
}
