"""Trading SaaS - Flask App Factory"""
from flask import Flask
from .config import Config
from .extensions import db, jwt, migrate, cors


def create_app(config_class=Config):
    app = Flask(__name__)
    app.config.from_object(config_class)

    # Initialize extensions
    db.init_app(app)
    jwt.init_app(app)
    migrate.init_app(app, db)
    cors.init_app(app)

    # Register blueprints
    from .api.auth import auth_bp
    from .api.admin import admin_bp
    from .api.agent import agent_bp
    from .api.trading import trading_bp
    from .api.bot_control import bot_bp, bot_admin_bp
    from .api.billing import billing_bp, billing_admin_bp
    from .api.market import market_bp
    from .api.notifications import notification_bp

    app.register_blueprint(auth_bp, url_prefix='/api/auth')
    app.register_blueprint(admin_bp, url_prefix='/api/admin')
    app.register_blueprint(agent_bp, url_prefix='/api/agent')
    app.register_blueprint(trading_bp, url_prefix='/api/agent/trades')
    app.register_blueprint(bot_bp, url_prefix='/api/agent/bot')
    app.register_blueprint(bot_admin_bp, url_prefix='/api/admin/bots')
    app.register_blueprint(billing_bp, url_prefix='/api/agent/billing')
    app.register_blueprint(billing_admin_bp, url_prefix='/api/admin/billing')
    app.register_blueprint(market_bp, url_prefix='/api/market')
    app.register_blueprint(notification_bp, url_prefix='/api/agent/notifications')

    # Register error handlers
    from .middleware.error_handler import register_error_handlers
    register_error_handlers(app)

    # Health check endpoint
    @app.route('/health')
    @app.route('/api/health')
    def health_check():
        from flask import jsonify as _jsonify
        import time as _time

        # DB check
        try:
            db.session.execute(db.text('SELECT 1'))
            db_ok = True
        except Exception:
            db_ok = False

        # Bot count
        running_bots = 0
        try:
            from .engine.bot_manager import BotManager
            manager = BotManager.get_instance()
            running_bots = sum(1 for b in manager._bots.values() if b.is_running)
        except Exception:
            pass

        # Agent/trade counts
        agent_count = 0
        try:
            from .models.agent import Agent
            agent_count = Agent.query.filter_by(is_active=True).count()
        except Exception:
            pass

        return _jsonify({
            'status': 'ok' if db_ok else 'degraded',
            'database': db_ok,
            'running_bots': running_bots,
            'active_agents': agent_count,
            'version': '1.0.0',
            'timestamp': int(_time.time()),
        }), 200 if db_ok else 503

    # Create tables if needed
    with app.app_context():
        from . import models  # noqa: F401
        db.create_all()

    # Start bot watchdog (auto-restart crashed bots)
    from .engine.bot_manager import BotManager
    manager = BotManager.get_instance(app)
    manager.start_watchdog()

    return app
