"""
日统计自动计算
每日自动汇总当天交易数据到 daily_stats 表
"""
import asyncio
from datetime import datetime, timezone, timedelta
from models.db_ops import get_closed_trades, upsert_daily_stat
from core.logger import get_logger

logger = get_logger('daily_stats')

MYT = timezone(timedelta(hours=8))


async def daily_stats_updater(interval: int = 300):
    """每 5 分钟更新一次日统计"""
    logger.info("daily_stats_updater.started")
    while True:
        try:
            today = datetime.now(MYT).date()
            trades = get_closed_trades(days=1)

            # 只统计今天的
            today_trades = [
                t for t in trades
                if t.get('close_time') and (
                    t['close_time'].date() == today
                    if hasattr(t['close_time'], 'date')
                    else str(t['close_time'])[:10] == str(today)
                )
            ]

            if not today_trades:
                await asyncio.sleep(interval)
                continue

            wins = [t for t in today_trades if (t.get('pnl') or 0) > 0]
            losses = [t for t in today_trades if (t.get('pnl') or 0) <= 0]
            total_pnl = sum(t.get('pnl') or 0 for t in today_trades)
            total_fee = sum(t.get('fee') or 0 for t in today_trades)

            win_rate = len(wins) / len(today_trades) if today_trades else 0
            avg_win = sum(t['pnl'] for t in wins) / len(wins) if wins else 0
            avg_loss = sum(t['pnl'] for t in losses) / len(losses) if losses else 0
            pf = abs(avg_win / avg_loss) if avg_loss != 0 else 0

            tier1 = len([t for t in today_trades if t.get('tier') == 'tier1'])
            tier2 = len([t for t in today_trades if t.get('tier') == 'tier2'])
            tier3 = len([t for t in today_trades if t.get('tier') == 'tier3'])

            upsert_daily_stat(today, {
                'starting_balance': 10000,  # TODO: 从前一天 ending 取
                'ending_balance': 10000 + total_pnl,
                'total_trades': len(today_trades),
                'winning_trades': len(wins),
                'losing_trades': len(losses),
                'total_pnl': round(total_pnl, 2),
                'total_fee': round(total_fee, 2),
                'win_rate': round(win_rate, 4),
                'profit_factor': round(pf, 2),
                'tier1_trades': tier1,
                'tier2_trades': tier2,
                'tier3_trades': tier3,
            })

            logger.info("daily_stats.updated",
                       date=str(today), trades=len(today_trades),
                       pnl=round(total_pnl, 2))

        except Exception as e:
            logger.error("daily_stats.error", error=str(e))

        await asyncio.sleep(interval)
