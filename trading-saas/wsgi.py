"""WSGI Entry Point"""
import os
import threading
from datetime import datetime, date
from calendar import monthrange
from app import create_app

app = create_app()


def _auto_restart_bots():
    """Restart bots that were running before service restart.

    Uses a file lock so only one Gunicorn worker does this.
    """
    lock_file = '/tmp/trading-saas-bot-autostart.lock'
    try:
        fd = os.open(lock_file, os.O_CREAT | os.O_EXCL | os.O_WRONLY)
        os.write(fd, str(os.getpid()).encode())
        os.close(fd)
    except FileExistsError:
        return  # Another worker already handling this

    try:
        with app.app_context():
            from app.models.bot_state import BotState
            from app.engine.bot_manager import BotManager

            stale = BotState.query.filter(
                BotState.status.in_(['running', 'paused'])
            ).all()

            if not stale:
                return

            manager = BotManager.get_instance(app)
            for state in stale:
                print(f"[AutoRestart] Restarting bot for agent {state.agent_id} "
                      f"(was {state.status})")
                state.status = 'stopped'
                state.pid = None
                from app.extensions import db
                db.session.commit()

                success, msg = manager.start_bot(state.agent_id)
                print(f"[AutoRestart] Agent {state.agent_id}: {msg}")
    except Exception as e:
        print(f"[AutoRestart] Error: {e}")
    finally:
        try:
            os.unlink(lock_file)
        except OSError:
            pass


def _billing_cycle_check():
    """Check and auto-close billing periods on the 1st of each month.

    Runs daily at startup (and via a background timer) to see if any
    open billing periods have ended. If so, close them and create new ones.
    Uses a file lock so only one Gunicorn worker handles this.
    """
    lock_file = '/tmp/trading-saas-billing-cycle.lock'
    try:
        fd = os.open(lock_file, os.O_CREAT | os.O_EXCL | os.O_WRONLY)
        os.write(fd, str(os.getpid()).encode())
        os.close(fd)
    except FileExistsError:
        return

    try:
        with app.app_context():
            from app.extensions import db
            from app.models.billing import BillingPeriod
            from app.services.billing_service import close_billing_period, get_or_create_period

            today = date.today()
            # Find all open periods whose period_end is in the past
            expired = BillingPeriod.query.filter(
                BillingPeriod.status == 'open',
                BillingPeriod.period_end < today,
            ).all()

            for period in expired:
                agent_id = period.agent_id
                print(f"[Billing] Closing expired period for agent {agent_id} "
                      f"({period.period_start} - {period.period_end})")
                result = close_billing_period(agent_id)
                if 'error' in result:
                    print(f"[Billing] Error closing agent {agent_id}: {result['error']}")
                else:
                    print(f"[Billing] Agent {agent_id}: PnL={result['gross_pnl']:.2f}, "
                          f"Commission={result['commission']:.2f}")
                    # Create next month's period
                    get_or_create_period(agent_id)
                    print(f"[Billing] Created new period for agent {agent_id}")
    except Exception as e:
        print(f"[Billing] Error: {e}")
    finally:
        try:
            os.unlink(lock_file)
        except OSError:
            pass


def _schedule_daily_billing_check():
    """Schedule billing check to run every 24 hours."""
    _billing_cycle_check()
    timer = threading.Timer(86400, _schedule_daily_billing_check)
    timer.daemon = True
    timer.start()


# Run auto-restart after app is created
_auto_restart_bots()

# Run billing cycle check (closes expired periods, creates new ones)
_schedule_daily_billing_check()

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5200, debug=True)
