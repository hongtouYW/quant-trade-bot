"""Trade persistence - save/load trades and positions from SQLite"""
import logging
from datetime import datetime
from app.db.database import get_connection
from app.models.market import Position, TradeRecord

log = logging.getLogger(__name__)


def save_trade(record: TradeRecord):
    """Save a closed trade to database"""
    conn = get_connection()
    conn.execute("""
        INSERT INTO trades (symbol, direction, setup_type, entry_price, exit_price,
                           size, margin, pnl, pnl_pct, fees, funding_fees, net_pnl,
                           close_reason, opened_at, closed_at, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'closed')
    """, (
        record.symbol, record.direction, record.setup_type,
        record.entry_price, record.exit_price,
        record.size, 0, record.pnl, record.pnl_pct,
        record.fees, record.funding_fees, record.net_pnl,
        record.close_reason,
        record.opened_at.strftime('%Y-%m-%d %H:%M:%S'),
        record.closed_at.strftime('%Y-%m-%d %H:%M:%S'),
    ))
    conn.commit()
    log.info(f"交易已保存: {record.symbol} PnL={record.pnl:+.2f} 手续费={record.fees:.4f} 资金费={record.funding_fees:.4f} 净PnL={record.net_pnl:+.2f}")


def save_position(pos: Position):
    """Save an open position to database"""
    conn = get_connection()
    conn.execute("""
        INSERT INTO positions (symbol, direction, entry_price, size, margin,
                              stop_loss, tp1, tp2, tp1_done, original_size,
                              strategy_tag, opened_at, order_id, entry_fee, funding_fees, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open')
    """, (
        pos.symbol, pos.direction, pos.entry_price, pos.size, pos.margin,
        pos.stop_loss, pos.tp1, pos.tp2, int(pos.tp1_done), pos.original_size,
        pos.strategy_tag, pos.opened_at.strftime('%Y-%m-%d %H:%M:%S'), pos.order_id,
        pos.entry_fee, pos.funding_fees,
    ))
    conn.commit()


def update_position_funding(symbol, direction, funding_fees):
    """Update accumulated funding fees for an open position"""
    conn = get_connection()
    conn.execute("""
        UPDATE positions SET funding_fees = ? WHERE symbol = ? AND direction = ? AND status = 'open'
    """, (funding_fees, symbol, direction))
    conn.commit()


def close_position_in_db(symbol, direction):
    """Mark a position as closed in database"""
    conn = get_connection()
    conn.execute("""
        UPDATE positions SET status = 'closed' WHERE symbol = ? AND direction = ? AND status = 'open'
    """, (symbol, direction))
    conn.commit()


def load_open_positions():
    """Load open positions from database (for restart recovery)"""
    conn = get_connection()
    rows = conn.execute("SELECT * FROM positions WHERE status = 'open'").fetchall()
    positions = []
    for row in rows:
        pos = Position(
            symbol=row['symbol'],
            direction=row['direction'],
            entry_price=row['entry_price'],
            size=row['size'],
            margin=row['margin'],
            stop_loss=row['stop_loss'],
            tp1=row['tp1'],
            tp2=row['tp2'],
            opened_at=datetime.strptime(row['opened_at'], '%Y-%m-%d %H:%M:%S'),
            strategy_tag=row['strategy_tag'] or '',
            tp1_done=bool(row['tp1_done']),
            original_size=row['original_size'],
            order_id=row['order_id'] or '',
            entry_fee=row['entry_fee'] if 'entry_fee' in row.keys() else 0,
            funding_fees=row['funding_fees'] if 'funding_fees' in row.keys() else 0,
        )
        positions.append(pos)
    return positions


def load_trade_history(limit=200):
    """Load recent trade history from database"""
    conn = get_connection()
    rows = conn.execute(
        "SELECT * FROM trades WHERE status = 'closed' ORDER BY closed_at DESC LIMIT ?",
        (limit,)
    ).fetchall()
    keys = rows[0].keys() if rows else []
    records = []
    for row in rows:
        record = TradeRecord(
            symbol=row['symbol'],
            direction=row['direction'],
            entry_price=row['entry_price'],
            exit_price=row['exit_price'] or 0,
            size=row['size'],
            pnl=row['pnl'],
            pnl_pct=row['pnl_pct'],
            setup_type=row['setup_type'] or '',
            close_reason=row['close_reason'] or '',
            opened_at=datetime.strptime(row['opened_at'], '%Y-%m-%d %H:%M:%S'),
            closed_at=datetime.strptime(row['closed_at'], '%Y-%m-%d %H:%M:%S') if row['closed_at'] else datetime.utcnow(),
            fees=row['fees'],
            funding_fees=row['funding_fees'] if 'funding_fees' in keys else 0,
            net_pnl=row['net_pnl'] if 'net_pnl' in keys else row['pnl'] - row['fees'],
        )
        records.append(record)
    records.reverse()
    return records


def save_daily_stat(date_str, trades, wins, losses, pnl, pnl_pct, equity_start, equity_end):
    """Save or update daily statistics"""
    conn = get_connection()
    conn.execute("""
        INSERT INTO daily_stats (date, total_trades, wins, losses, pnl, pnl_pct, equity_start, equity_end)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON CONFLICT(date) DO UPDATE SET
            total_trades = excluded.total_trades,
            wins = excluded.wins,
            losses = excluded.losses,
            pnl = excluded.pnl,
            pnl_pct = excluded.pnl_pct,
            equity_end = excluded.equity_end
    """, (date_str, trades, wins, losses, pnl, pnl_pct, equity_start, equity_end))
    conn.commit()


def get_daily_stats(days=30):
    """Get recent daily stats"""
    conn = get_connection()
    rows = conn.execute(
        "SELECT * FROM daily_stats ORDER BY date DESC LIMIT ?", (days,)
    ).fetchall()
    return [dict(row) for row in rows]


def get_trade_count():
    """Get total trade count"""
    conn = get_connection()
    row = conn.execute("SELECT COUNT(*) as cnt FROM trades WHERE status='closed'").fetchone()
    return row['cnt'] if row else 0


def get_fee_summary():
    """Get total fees and funding fees summary"""
    conn = get_connection()
    row = conn.execute("""
        SELECT
            COUNT(*) as total_trades,
            COALESCE(SUM(fees), 0) as total_fees,
            COALESCE(SUM(funding_fees), 0) as total_funding_fees,
            COALESCE(SUM(pnl), 0) as total_pnl,
            COALESCE(SUM(net_pnl), 0) as total_net_pnl
        FROM trades WHERE status='closed'
    """).fetchone()
    return dict(row) if row else {}
