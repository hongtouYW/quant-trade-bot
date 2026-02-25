"""Order Executor - Real Binance Futures trading via ccxt.

Handles order creation, position closing, and exchange API interactions.
Each AgentBot gets its own OrderExecutor with isolated API credentials.
"""
import time
import ccxt
from typing import Optional


class OrderExecutor:
    """Executes real orders on Binance Futures via ccxt."""

    def __init__(self, api_key: str, api_secret: str,
                 is_testnet: bool = False):
        """Initialize ccxt exchange connection.

        Args:
            api_key: Binance API key (plaintext, already decrypted)
            api_secret: Binance API secret (plaintext, already decrypted)
            is_testnet: Use Binance testnet if True
        """
        config = {
            'apiKey': api_key,
            'secret': api_secret,
            'enableRateLimit': True,
            'options': {'defaultType': 'future'},
        }

        if is_testnet:
            config['sandbox'] = True

        self.exchange = ccxt.binance(config)

        if is_testnet:
            self.exchange.set_sandbox_mode(True)

    def get_price(self, symbol: str) -> Optional[float]:
        """Get current price for a symbol."""
        try:
            ticker = self.exchange.fetch_ticker(symbol)
            return ticker['last']
        except Exception as e:
            print(f"[OrderExecutor] Price fetch failed {symbol}: {e}")
            return None

    def get_balance(self) -> Optional[dict]:
        """Get futures account balance.

        Returns: {'total': float, 'free': float, 'used': float} or None
        """
        try:
            balance = self.exchange.fetch_balance()
            usdt = balance.get('USDT', {})
            return {
                'total': float(usdt.get('total', 0)),
                'free': float(usdt.get('free', 0)),
                'used': float(usdt.get('used', 0)),
            }
        except Exception as e:
            print(f"[OrderExecutor] Balance fetch failed: {e}")
            return None

    def set_leverage(self, symbol: str, leverage: int) -> bool:
        """Set leverage for a symbol."""
        try:
            self.exchange.set_leverage(leverage, symbol)
            return True
        except Exception as e:
            print(f"[OrderExecutor] Set leverage failed {symbol}: {e}")
            return False

    def set_margin_mode(self, symbol: str, mode: str = 'isolated') -> bool:
        """Set margin mode (isolated or cross)."""
        try:
            self.exchange.set_margin_mode(mode, symbol)
            return True
        except ccxt.ExchangeError as e:
            # "No need to change margin type" is not an error
            if 'No need to change' in str(e):
                return True
            print(f"[OrderExecutor] Set margin mode failed {symbol}: {e}")
            return False

    def open_position(self, symbol: str, direction: str,
                      amount_usdt: float, leverage: int) -> Optional[dict]:
        """Open a futures position.

        Args:
            symbol: e.g. 'BTC/USDT'
            direction: 'LONG' or 'SHORT'
            amount_usdt: Position size in USDT (margin)
            leverage: Leverage multiplier

        Returns:
            Order info dict or None on failure
        """
        try:
            # Set leverage and margin mode
            self.set_margin_mode(symbol, 'isolated')
            self.set_leverage(symbol, leverage)

            # Calculate quantity
            price = self.get_price(symbol)
            if not price:
                return None

            notional = amount_usdt * leverage
            quantity = notional / price

            # Get market info for precision
            market = self.exchange.market(symbol)
            quantity = self.exchange.amount_to_precision(symbol, quantity)
            quantity = float(quantity)

            if quantity <= 0:
                print(f"[OrderExecutor] Quantity too small for {symbol}")
                return None

            # Place market order
            side = 'buy' if direction == 'LONG' else 'sell'
            order = self.exchange.create_market_order(
                symbol, side, quantity,
                params={'reduceOnly': False}
            )

            return {
                'order_id': order.get('id'),
                'symbol': symbol,
                'direction': direction,
                'side': side,
                'quantity': quantity,
                'price': float(order.get('average', price)),
                'amount_usdt': amount_usdt,
                'leverage': leverage,
                'status': order.get('status'),
                'raw': order,
            }

        except ccxt.InsufficientFunds as e:
            print(f"[OrderExecutor] Insufficient funds for {symbol}: {e}")
            return None
        except ccxt.ExchangeError as e:
            print(f"[OrderExecutor] Exchange error opening {symbol}: {e}")
            return None
        except Exception as e:
            print(f"[OrderExecutor] Open position failed {symbol}: {e}")
            return None

    def close_position(self, symbol: str, direction: str,
                       quantity: float = None) -> Optional[dict]:
        """Close a futures position.

        Args:
            symbol: Trading pair
            direction: 'LONG' or 'SHORT' (the position direction to close)
            quantity: Specific quantity to close. If None, closes entire position.

        Returns:
            Order info dict or None
        """
        try:
            if quantity is None:
                # Fetch open position to get quantity
                positions = self.exchange.fetch_positions([symbol])
                for pos in positions:
                    if pos['symbol'] == symbol and float(pos.get('contracts', 0)) > 0:
                        quantity = float(pos['contracts'])
                        break
                if not quantity:
                    print(f"[OrderExecutor] No open position found for {symbol}")
                    return None

            # Close = reverse order
            side = 'sell' if direction == 'LONG' else 'buy'
            order = self.exchange.create_market_order(
                symbol, side, quantity,
                params={'reduceOnly': True}
            )

            return {
                'order_id': order.get('id'),
                'symbol': symbol,
                'side': side,
                'quantity': quantity,
                'price': float(order.get('average', 0)),
                'status': order.get('status'),
                'raw': order,
            }

        except Exception as e:
            print(f"[OrderExecutor] Close position failed {symbol}: {e}")
            return None

    def get_open_positions(self) -> list:
        """Get all open futures positions."""
        try:
            positions = self.exchange.fetch_positions()
            return [
                {
                    'symbol': pos['symbol'],
                    'side': pos['side'],
                    'contracts': float(pos.get('contracts', 0)),
                    'notional': float(pos.get('notional', 0)),
                    'unrealized_pnl': float(pos.get('unrealizedPnl', 0)),
                    'entry_price': float(pos.get('entryPrice', 0)),
                    'leverage': int(pos.get('leverage', 1)),
                    'margin': float(pos.get('initialMargin', 0)),
                }
                for pos in positions
                if float(pos.get('contracts', 0)) > 0
            ]
        except Exception as e:
            print(f"[OrderExecutor] Fetch positions failed: {e}")
            return []

    def verify_api_key(self) -> tuple:
        """Verify API key connectivity and permissions.

        Returns:
            (success: bool, message: str)
        """
        try:
            balance = self.exchange.fetch_balance()
            # Check if futures trading is available
            usdt_balance = balance.get('USDT', {})
            total = float(usdt_balance.get('total', 0))
            return True, f"Connected. USDT balance: {total:.2f}"
        except ccxt.AuthenticationError:
            return False, "Authentication failed: invalid API key or secret"
        except ccxt.PermissionDenied:
            return False, "Permission denied: API key lacks futures trading permission"
        except ccxt.ExchangeNotAvailable:
            return False, "Exchange not available, try again later"
        except Exception as e:
            return False, f"Connection failed: {str(e)}"
