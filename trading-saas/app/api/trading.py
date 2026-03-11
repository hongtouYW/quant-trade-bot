"""Trading Data API - Positions, History, Stats, Real-time PnL"""
import csv
import io
import logging
import requests as req
from datetime import datetime, timezone
from flask import Blueprint, request, jsonify, Response
from sqlalchemy import func, case

from ..middleware.auth_middleware import agent_required, admin_required, get_current_user_id
from ..models.trade import Trade, DailyStat
from ..models.agent import Agent
from ..extensions import db
from ..engine.signal_analyzer import exchange_symbol

logger = logging.getLogger(__name__)
trading_bp = Blueprint('trading', __name__)

# Exchange price endpoints
_PRICE_ENDPOINTS = {
    'binance': 'https://fapi.binance.com/fapi/v1/ticker/price',
    'bitget':  'https://api.bitget.com/api/v2/mix/market/tickers',
}


def _get_agent_exchange(agent_id: int) -> str:
    """Get exchange name from agent's API key config."""
    try:
        from ..models.agent_config import AgentApiKey
        record = AgentApiKey.query.filter_by(agent_id=agent_id).first()
        if record:
            return record.exchange or 'binance'
    except Exception:
        pass
    return 'binance'


def _fetch_all_prices(exchange: str = 'binance') -> dict:
    """Fetch all futures ticker prices in one call."""
    try:
        if exchange == 'bitget':
            resp = req.get(
                _PRICE_ENDPOINTS['bitget'],
                params={'productType': 'USDT-FUTURES'},
                timeout=5,
            )
            data = resp.json().get('data', [])
            return {item['symbol']: float(item['lastPr']) for item in data}
        else:
            resp = req.get(_PRICE_ENDPOINTS['binance'], timeout=5)
            return {item['symbol']: float(item['price']) for item in resp.json()}
    except Exception:
        logger.warning(f"Failed to fetch {exchange} prices for PnL enrichment")
        return {}


@trading_bp.route('/positions', methods=['GET'])
@agent_required
def get_positions():
    """Get open positions with real-time PnL from BotManager."""
    agent_id = get_current_user_id()
    trades = Trade.query.filter_by(
        agent_id=agent_id, status='OPEN'
    ).order_by(Trade.entry_time.desc()).all()

    # Batch-fetch all futures prices (single HTTP call)
    ex = _get_agent_exchange(agent_id)
    all_prices = _fetch_all_prices(ex) if trades else {}

    positions = []
    for t in trades:
        d = t.to_dict()
        d['unrealized_pnl'] = None
        d['current_roi'] = None
        d['current_price'] = None

        # Calculate unrealized PnL from current price
        binance_sym = exchange_symbol(t.symbol, ex)
        current_price = all_prices.get(binance_sym)
        if current_price and t.entry_price:
            entry = float(t.entry_price)
            if entry <= 0:
                positions.append(d)
                continue
            amount = float(t.amount) if t.amount else 0
            leverage = int(t.leverage or 1)

            if t.direction == 'LONG':
                price_change_pct = (current_price - entry) / entry
            else:
                price_change_pct = (entry - current_price) / entry

            d['current_price'] = current_price
            d['current_roi'] = round(price_change_pct * leverage * 100, 2)
            open_fee = float(t.fee or 0)
            d['unrealized_pnl'] = round(price_change_pct * leverage * amount - open_fee, 2)

        # peak_roi from DB (works across processes, unlike BotManager in-memory)
        d['peak_roi'] = float(t.peak_roi) if t.peak_roi else 0
        positions.append(d)

    return jsonify({'positions': positions})


@trading_bp.route('/history', methods=['GET'])
@agent_required
def get_history():
    """Get closed trade history with pagination and filters."""
    agent_id = get_current_user_id()
    page = request.args.get('page', 1, type=int)
    per_page = request.args.get('per_page', 20, type=int)
    per_page = min(per_page, 100)

    # Optional filters
    symbol = request.args.get('symbol')
    direction = request.args.get('direction')
    date_from = request.args.get('from')  # YYYY-MM-DD
    date_to = request.args.get('to')

    query = Trade.query.filter_by(agent_id=agent_id, status='CLOSED')

    if symbol:
        query = query.filter(Trade.symbol == symbol.upper())
    if direction:
        query = query.filter(Trade.direction == direction.upper())
    if date_from:
        try:
            date_from_dt = datetime.strptime(date_from, '%Y-%m-%d')
            query = query.filter(Trade.exit_time >= date_from_dt)
        except ValueError:
            pass
    if date_to:
        try:
            date_to_dt = datetime.strptime(date_to, '%Y-%m-%d').replace(hour=23, minute=59, second=59)
            query = query.filter(Trade.exit_time <= date_to_dt)
        except ValueError:
            pass

    query = query.order_by(Trade.exit_time.desc())
    pagination = query.paginate(page=page, per_page=per_page, error_out=False)

    return jsonify({
        'trades': [t.to_dict() for t in pagination.items],
        'total': pagination.total,
        'page': pagination.page,
        'pages': pagination.pages,
    })


@trading_bp.route('/<int:trade_id>', methods=['GET'])
@agent_required
def get_trade(trade_id):
    agent_id = get_current_user_id()
    trade = Trade.query.filter_by(id=trade_id, agent_id=agent_id).first()
    if not trade:
        return jsonify({'error': 'Trade not found'}), 404
    return jsonify(trade.to_dict())


@trading_bp.route('/stats', methods=['GET'])
@agent_required
def get_stats():
    """Comprehensive trade statistics."""
    agent_id = get_current_user_id()

    stats = db.session.query(
        func.count(Trade.id),
        func.sum(Trade.pnl),
        func.sum(Trade.fee),
        func.sum(Trade.funding_fee),
        func.max(Trade.pnl),
        func.min(Trade.pnl),
        func.sum(case((Trade.pnl > 0, 1), else_=0)),
        func.avg(Trade.pnl),
    ).filter(
        Trade.agent_id == agent_id, Trade.status == 'CLOSED'
    ).first()

    total_trades = stats[0] or 0
    if total_trades == 0:
        return jsonify({
            'total_trades': 0, 'win_trades': 0, 'loss_trades': 0,
            'win_rate': 0, 'total_pnl': 0, 'total_fees': 0,
            'avg_pnl': 0, 'best_trade': 0, 'worst_trade': 0,
            'profit_factor': 0, 'max_drawdown': 0,
        })

    total_pnl = float(stats[1]) if stats[1] else 0
    total_fees = float(stats[2] or 0) + float(stats[3] or 0)
    best = float(stats[4]) if stats[4] else 0
    worst = float(stats[5]) if stats[5] else 0
    wins = int(stats[6]) if stats[6] else 0
    avg_pnl = float(stats[7]) if stats[7] else 0

    # Profit factor
    gross_profit = db.session.query(
        func.sum(Trade.pnl)
    ).filter(
        Trade.agent_id == agent_id, Trade.status == 'CLOSED', Trade.pnl > 0
    ).scalar() or 0
    gross_loss = abs(db.session.query(
        func.sum(Trade.pnl)
    ).filter(
        Trade.agent_id == agent_id, Trade.status == 'CLOSED', Trade.pnl < 0
    ).scalar() or 0)
    profit_factor = float(gross_profit) / float(gross_loss) if gross_loss > 0 else (
        999.99 if gross_profit > 0 else 0)

    # Max drawdown from trade history
    closed_trades = Trade.query.filter_by(
        agent_id=agent_id, status='CLOSED'
    ).order_by(Trade.exit_time).all()

    from ..models.agent_config import AgentTradingConfig
    tc = AgentTradingConfig.query.filter_by(agent_id=agent_id).first()
    initial_capital = float(tc.initial_capital) if tc else 2000

    peak = initial_capital
    max_dd = 0
    cumulative = initial_capital
    for t in closed_trades:
        cumulative += float(t.pnl) if t.pnl else 0
        if cumulative > peak:
            peak = cumulative
        dd = (peak - cumulative) / peak * 100 if peak > 0 else 0
        max_dd = max(max_dd, dd)

    # Open positions count and unrealized
    open_count = Trade.query.filter_by(agent_id=agent_id, status='OPEN').count()

    return jsonify({
        'total_trades': total_trades,
        'win_trades': wins,
        'loss_trades': total_trades - wins,
        'win_rate': round(wins / total_trades * 100, 1),
        'total_pnl': round(total_pnl, 2),
        'total_fees': round(total_fees, 4),
        'avg_pnl': round(avg_pnl, 2),
        'best_trade': round(best, 2),
        'worst_trade': round(worst, 2),
        'profit_factor': round(profit_factor, 2),
        'max_drawdown': round(max_dd, 2),
        'current_capital': round(cumulative, 2),
        'open_positions': open_count,
        'strategy_version': tc.strategy_version if tc else None,
    })


@trading_bp.route('/daily', methods=['GET'])
@agent_required
def get_daily():
    agent_id = get_current_user_id()
    days = request.args.get('days', 30, type=int)
    days = min(days, 365)

    stats = (
        DailyStat.query
        .filter_by(agent_id=agent_id)
        .order_by(DailyStat.date.desc())
        .limit(days)
        .all()
    )

    # Add cumulative PnL
    result = [s.to_dict() for s in reversed(stats)]
    cumulative = 0
    for d in result:
        cumulative += d['total_pnl']
        d['cumulative_pnl'] = round(cumulative, 2)

    return jsonify({'daily': result})


@trading_bp.route('/export/csv', methods=['GET'])
@agent_required
def export_csv():
    """Export closed trades as CSV file."""
    agent_id = get_current_user_id()

    symbol = request.args.get('symbol')
    direction = request.args.get('direction')
    date_from = request.args.get('from')
    date_to = request.args.get('to')

    query = Trade.query.filter_by(agent_id=agent_id, status='CLOSED')
    if symbol:
        query = query.filter(Trade.symbol == symbol.upper())
    if direction:
        query = query.filter(Trade.direction == direction.upper())
    if date_from:
        try:
            date_from_dt = datetime.strptime(date_from, '%Y-%m-%d')
            query = query.filter(Trade.exit_time >= date_from_dt)
        except ValueError:
            pass
    if date_to:
        try:
            date_to_dt = datetime.strptime(date_to, '%Y-%m-%d').replace(hour=23, minute=59, second=59)
            query = query.filter(Trade.exit_time <= date_to_dt)
        except ValueError:
            pass

    trades = query.order_by(Trade.exit_time.desc()).all()

    output = io.StringIO()
    writer = csv.writer(output)
    writer.writerow([
        'Symbol', 'Direction', 'Entry Price', 'Exit Price', 'Amount (U)',
        'Leverage', 'PnL (U)', 'ROI (%)', 'Fee', 'Funding Fee',
        'Score', 'Close Reason', 'Entry Time', 'Exit Time', 'Strategy',
    ])
    for t in trades:
        writer.writerow([
            t.symbol, t.direction,
            f'{float(t.entry_price):.8f}' if t.entry_price else '',
            f'{float(t.exit_price):.8f}' if t.exit_price else '',
            float(t.amount) if t.amount else '',
            t.leverage,
            f'{float(t.pnl):.4f}' if t.pnl else '0',
            f'{float(t.roi):.4f}' if t.roi else '0',
            f'{float(t.fee):.6f}' if t.fee else '0',
            f'{float(t.funding_fee):.6f}' if t.funding_fee else '0',
            t.score or '',
            t.close_reason or '',
            t.entry_time.strftime('%Y-%m-%d %H:%M:%S') if t.entry_time else '',
            t.exit_time.strftime('%Y-%m-%d %H:%M:%S') if t.exit_time else '',
            t.strategy_version or '',
        ])

    csv_data = output.getvalue()
    today = datetime.now().strftime('%Y%m%d')
    filename = f'trades_{today}.csv'

    return Response(
        csv_data,
        mimetype='text/csv',
        headers={'Content-Disposition': f'attachment; filename={filename}'},
    )


@trading_bp.route('/symbols', methods=['GET'])
@agent_required
def get_traded_symbols():
    """Get list of all symbols this agent has traded."""
    agent_id = get_current_user_id()
    symbols = db.session.query(
        Trade.symbol
    ).filter(
        Trade.agent_id == agent_id
    ).distinct().all()
    return jsonify({'symbols': [s[0] for s in symbols]})
