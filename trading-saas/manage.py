#!/usr/bin/env python3
"""CLI Management Commands"""
import json
import sys
import os

from app import create_app
from app.extensions import db
from app.services.auth_service import hash_password
from app.models.admin import Admin
from app.models.strategy_preset import StrategyPreset


app = create_app()


def create_admin(username, email, password):
    """Create a new admin user."""
    with app.app_context():
        if Admin.query.filter_by(username=username).first():
            print(f"Admin '{username}' already exists")
            return
        admin = Admin(
            username=username,
            email=email,
            password_hash=hash_password(password),
        )
        db.session.add(admin)
        db.session.commit()
        print(f"Admin '{username}' created (id={admin.id})")


def seed_strategies():
    """Seed strategy presets from existing STRATEGY_PRESETS."""
    presets = {
        'v4.1': {
            'label': 'v4.1 防守反击',
            'description': 'LONG≥70/3x杠杆/4h冷却/严格趋势过滤',
            'config': {
                'min_score': 60, 'long_min_score': 70, 'cooldown': 4,
                'max_leverage': 3, 'enable_trend_filter': True,
                'long_ma_slope_threshold': 0.02,
                'roi_stop_loss': -10, 'roi_trailing_start': 6,
                'roi_trailing_distance': 3,
            }
        },
        'v4.2': {
            'label': 'v4.2 扩容版 (推荐)',
            'description': 'v4.1基础 + 30m冷却 + 15仓位 + SHORT偏置1.05',
            'config': {
                'min_score': 60, 'long_min_score': 70, 'cooldown': 1,
                'max_leverage': 3, 'max_positions': 15,
                'short_bias': 1.05,
                'enable_trend_filter': True, 'enable_btc_filter': True,
                'long_ma_slope_threshold': 0.02,
                'roi_stop_loss': -10, 'roi_trailing_start': 6,
                'roi_trailing_distance': 3,
            }
        },
        'v4.3': {
            'label': 'v4.3 动态版',
            'description': '动态杠杆(3-10x) + 移动止盈(按趋势调整)',
            'config': {
                'min_score': 60, 'long_min_score': 70, 'cooldown': 1,
                'max_leverage': 10, 'max_positions': 15,
                'short_bias': 1.05,
                'enable_trend_filter': True, 'enable_btc_filter': True,
                'dynamic_leverage': True, 'dynamic_tpsl': True,
            }
        },
    }

    with app.app_context():
        for version, data in presets.items():
            existing = StrategyPreset.query.filter_by(version=version).first()
            if existing:
                existing.label = data['label']
                existing.description = data['description']
                existing.config = json.dumps(data['config'])
                print(f"Updated strategy {version}")
            else:
                preset = StrategyPreset(
                    version=version,
                    label=data['label'],
                    description=data['description'],
                    config=json.dumps(data['config']),
                )
                db.session.add(preset)
                print(f"Created strategy {version}")
        db.session.commit()
        print("Strategy presets seeded.")


def init_db():
    """Initialize database tables."""
    with app.app_context():
        db.create_all()
        print("Database tables created.")


if __name__ == '__main__':
    if len(sys.argv) < 2:
        print("Usage:")
        print("  python manage.py init_db                    - Create all tables")
        print("  python manage.py create_admin <user> <email> <pass>  - Create admin")
        print("  python manage.py seed_strategies             - Seed strategy presets")
        sys.exit(1)

    cmd = sys.argv[1]
    if cmd == 'init_db':
        init_db()
    elif cmd == 'create_admin':
        if len(sys.argv) < 5:
            print("Usage: python manage.py create_admin <username> <email> <password>")
            sys.exit(1)
        create_admin(sys.argv[2], sys.argv[3], sys.argv[4])
    elif cmd == 'seed_strategies':
        seed_strategies()
    else:
        print(f"Unknown command: {cmd}")
        sys.exit(1)
