from flask import Flask
from .extensions import db, login_manager
from config import Config


def create_app(config_class=Config):
    app = Flask(__name__)
    app.config.from_object(config_class)

    # Init extensions
    db.init_app(app)
    login_manager.init_app(app)
    login_manager.login_view = 'auth.login'

    # Register blueprints
    from .api.channels import channels_bp
    from .api.signals import signals_bp
    from .api.strategies import strategies_bp
    from .api.trades import trades_bp
    from .api.admin import admin_bp
    from .api.auth import auth_bp
    from .api.dashboard import dashboard_bp

    app.register_blueprint(auth_bp)
    app.register_blueprint(dashboard_bp)
    app.register_blueprint(channels_bp, url_prefix='/api/channels')
    app.register_blueprint(signals_bp, url_prefix='/api/signals')
    app.register_blueprint(strategies_bp, url_prefix='/api/strategies')
    app.register_blueprint(trades_bp, url_prefix='/api/trades')
    app.register_blueprint(admin_bp, url_prefix='/admin/api')

    # Create tables
    with app.app_context():
        from . import models  # noqa
        db.create_all()
        _ensure_admin(app)

    return app


def _ensure_admin(app):
    """Create default admin user if not exists."""
    from .models.user import User
    with app.app_context():
        admin = User.query.filter_by(username='admin').first()
        if not admin:
            admin = User(username='admin', is_admin=True)
            admin.set_password('Admin123!')
            db.session.add(admin)
            db.session.commit()
