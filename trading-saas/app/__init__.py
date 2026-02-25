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

    app.register_blueprint(auth_bp, url_prefix='/api/auth')
    app.register_blueprint(admin_bp, url_prefix='/api/admin')
    app.register_blueprint(agent_bp, url_prefix='/api/agent')
    app.register_blueprint(trading_bp, url_prefix='/api/agent/trades')
    app.register_blueprint(bot_bp, url_prefix='/api/agent/bot')
    app.register_blueprint(bot_admin_bp, url_prefix='/api/admin/bots')
    app.register_blueprint(billing_bp, url_prefix='/api/agent/billing')
    app.register_blueprint(billing_admin_bp, url_prefix='/api/admin/billing')
    app.register_blueprint(market_bp, url_prefix='/api/market')

    # Register error handlers
    from .middleware.error_handler import register_error_handlers
    register_error_handlers(app)

    # Health check endpoint
    @app.route('/health')
    def health_check():
        from flask import jsonify as _jsonify
        try:
            db.session.execute(db.text('SELECT 1'))
            db_ok = True
        except Exception:
            db_ok = False
        return _jsonify({
            'status': 'ok' if db_ok else 'degraded',
            'database': db_ok,
        }), 200 if db_ok else 503

    # Create tables if needed
    with app.app_context():
        from . import models  # noqa: F401
        db.create_all()

    return app
