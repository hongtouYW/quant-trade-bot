"""
Flash Quant - 数据库操作函数
所有 DB 读写都在这里
"""
import json
from datetime import datetime, timezone, timedelta
from sqlalchemy import select, insert, update, delete, func, desc, and_, text
from models.base import get_engine
from models.signal import signals
from models.trade import trades
from models.position import positions
from models.daily_stat import daily_stats
from models.circuit_breaker import circuit_breakers
from models.audit_log import audit_logs
from core.logger import get_logger

logger = get_logger('db_ops')

MYT = timezone(timedelta(hours=8))


def _exec(stmt):
    """执行语句并提交"""
    engine = get_engine()
    with engine.connect() as conn:
        result = conn.execute(stmt)
        conn.commit()
        return result


def _query(stmt):
    """查询并返回字典列表"""
    engine = get_engine()
    with engine.connect() as conn:
        result = conn.execute(stmt)
        rows = result.fetchall()
        if not rows:
            return []
        keys = result.keys()
        return [dict(zip(keys, row)) for row in rows]


# ============================================================
# Signals
# ============================================================

def save_signal(data: dict) -> int:
    """保存信号记录"""
    data['timestamp'] = data.get('timestamp', datetime.now(MYT))
    if isinstance(data.get('raw_data'), dict):
        data['raw_data'] = json.dumps(data['raw_data'], default=str)
    stmt = insert(signals).values(**{k: v for k, v in data.items() if k in signals.c})
    result = _exec(stmt)
    return result.inserted_primary_key[0]


def query_signals(limit=100, decision=None, tier=None, symbol=None):
    """查询信号"""
    stmt = select(signals).order_by(desc(signals.c.created_at)).limit(limit)
    if decision:
        stmt = stmt.where(signals.c.final_decision == decision)
    if tier:
        stmt = stmt.where(signals.c.tier == tier)
    if symbol:
        stmt = stmt.where(signals.c.symbol == symbol)
    return _query(stmt)


def count_signals_today():
    """今日信号数"""
    today = datetime.now(MYT).date()
    stmt = select(func.count()).select_from(signals).where(
        func.date(signals.c.timestamp) == today
    )
    engine = get_engine()
    with engine.connect() as conn:
        return conn.execute(stmt).scalar() or 0


# ============================================================
# Trades
# ============================================================

def insert_trade(data: dict) -> int:
    """创建交易记录"""
    if isinstance(data.get('take_profit_data'), (dict, list)):
        data['take_profit_data'] = json.dumps(data['take_profit_data'], default=str)
    stmt = insert(trades).values(**{k: v for k, v in data.items() if k in trades.c})
    result = _exec(stmt)
    return result.inserted_primary_key[0]


def update_trade(trade_id: int, data: dict):
    """更新交易记录"""
    stmt = update(trades).where(trades.c.id == trade_id).values(**data)
    _exec(stmt)


def query_trades(limit=100, mode=None, status=None, symbol=None):
    """查询交易"""
    stmt = select(trades).order_by(desc(trades.c.open_time)).limit(limit)
    if mode:
        stmt = stmt.where(trades.c.mode == mode)
    if status:
        stmt = stmt.where(trades.c.status == status)
    if symbol:
        stmt = stmt.where(trades.c.symbol == symbol)
    return _query(stmt)


def get_closed_trades(days=30):
    """获取最近N天已平仓交易"""
    cutoff = datetime.now(MYT) - timedelta(days=days)
    stmt = (select(trades)
            .where(and_(trades.c.status == 'closed', trades.c.close_time >= cutoff))
            .order_by(desc(trades.c.close_time)))
    return _query(stmt)


def get_trade_by_id(trade_id: int):
    """获取单笔交易"""
    rows = _query(select(trades).where(trades.c.id == trade_id))
    return rows[0] if rows else None


def get_open_trades():
    """获取所有未平仓交易"""
    return _query(select(trades).where(trades.c.status == 'open'))


def count_open_trades():
    engine = get_engine()
    with engine.connect() as conn:
        return conn.execute(
            select(func.count()).select_from(trades).where(trades.c.status == 'open')
        ).scalar() or 0


def get_open_symbols():
    rows = _query(select(trades.c.symbol).where(trades.c.status == 'open'))
    return {r['symbol'] for r in rows}


def get_consecutive_losses(window_hours=24):
    """计算窗口内连续亏损"""
    cutoff = datetime.now(MYT) - timedelta(hours=window_hours)
    rows = _query(
        select(trades.c.pnl)
        .where(and_(trades.c.status == 'closed', trades.c.close_time >= cutoff))
        .order_by(desc(trades.c.close_time))
    )
    count = 0
    for r in rows:
        if (r['pnl'] or 0) < 0:
            count += 1
        else:
            break
    return count


# ============================================================
# Positions
# ============================================================

def insert_position(data: dict) -> int:
    if isinstance(data.get('take_profit_levels'), (dict, list)):
        data['take_profit_levels'] = json.dumps(data['take_profit_levels'], default=str)
    stmt = insert(positions).values(**{k: v for k, v in data.items() if k in positions.c})
    result = _exec(stmt)
    return result.inserted_primary_key[0]


def delete_position(position_id: int):
    _exec(delete(positions).where(positions.c.id == position_id))


def delete_position_by_trade(trade_id: int):
    _exec(delete(positions).where(positions.c.trade_id == trade_id))


def get_open_positions():
    return _query(select(positions).order_by(positions.c.open_time))


def update_position(position_id: int, data: dict):
    stmt = update(positions).where(positions.c.id == position_id).values(**data)
    _exec(stmt)


# ============================================================
# Daily Stats
# ============================================================

def upsert_daily_stat(date, data: dict):
    """更新或插入日统计"""
    engine = get_engine()
    with engine.connect() as conn:
        existing = conn.execute(
            select(daily_stats).where(daily_stats.c.date == date)
        ).fetchone()
        if existing:
            conn.execute(update(daily_stats).where(daily_stats.c.date == date).values(**data))
        else:
            conn.execute(insert(daily_stats).values(date=date, **data))
        conn.commit()


def get_daily_stats(days=30):
    cutoff = datetime.now(MYT).date() - timedelta(days=days)
    return _query(
        select(daily_stats)
        .where(daily_stats.c.date >= cutoff)
        .order_by(desc(daily_stats.c.date))
    )


# ============================================================
# Audit Log
# ============================================================

def write_audit(actor: str, action: str, target: str = None,
                details: dict = None, severity: str = 'info'):
    """写审计日志"""
    _exec(insert(audit_logs).values(
        actor=actor, action=action, target=target,
        details=json.dumps(details or {}, default=str, ensure_ascii=False),
        severity=severity,
    ))


# ============================================================
# Dashboard 查询
# ============================================================

def get_dashboard_stats():
    """Dashboard 首页统计"""
    closed = get_closed_trades(days=30)
    if not closed:
        return {
            'total': 0, 'wins': 0, 'losses': 0, 'win_rate': 0,
            'total_pnl': 0, 'total_fee': 0, 'total_funding': 0, 'avg_hold_hours': 0,
        }

    wins = [t for t in closed if (t.get('pnl') or 0) > 0]
    total_pnl = sum(t.get('pnl') or 0 for t in closed)
    total_fee = sum(t.get('fee') or 0 for t in closed)

    return {
        'total': len(closed),
        'wins': len(wins),
        'losses': len(closed) - len(wins),
        'win_rate': len(wins) / len(closed) if closed else 0,
        'total_pnl': round(total_pnl, 2),
        'total_fee': round(total_fee, 2),
        'total_funding': 0,  # TODO: 从独立字段读取
        'avg_hold_hours': 0,  # TODO: 计算
    }


def get_calendar_data(days=30):
    """日历数据"""
    closed = get_closed_trades(days=days)
    calendar = {}
    for t in closed:
        close_time = t.get('close_time')
        if not close_time:
            continue
        if isinstance(close_time, str):
            d = close_time[:10]
        else:
            d = close_time.strftime('%Y-%m-%d')
        if d not in calendar:
            calendar[d] = {'trades': 0, 'pnl': 0, 'wins': 0, 'losses': 0}
        calendar[d]['trades'] += 1
        pnl = t.get('pnl') or 0
        calendar[d]['pnl'] = round(calendar[d]['pnl'] + pnl, 2)
        if pnl > 0:
            calendar[d]['wins'] += 1
        else:
            calendar[d]['losses'] += 1
    return calendar
