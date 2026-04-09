"""
Flash Quant - 数据库基础
SQLAlchemy Core (不用 ORM 关系映射)
"""
from sqlalchemy import create_engine, MetaData
from sqlalchemy.orm import sessionmaker, scoped_session
from config.settings import settings

metadata = MetaData()

_engine = None
_session_factory = None


def get_engine():
    global _engine
    if _engine is None:
        _engine = create_engine(
            settings.DB_URL,
            pool_size=5,
            max_overflow=10,
            pool_recycle=3600,
            pool_pre_ping=True,
            echo=False,
        )
    return _engine


def get_session():
    global _session_factory
    if _session_factory is None:
        _session_factory = scoped_session(
            sessionmaker(bind=get_engine())
        )
    return _session_factory()


def init_db():
    """创建所有表"""
    # 先 import 所有 model 让 metadata 注册表
    import models.signal  # noqa
    import models.trade  # noqa
    import models.position  # noqa
    import models.daily_stat  # noqa
    import models.circuit_breaker  # noqa
    import models.audit_log  # noqa

    engine = get_engine()
    metadata.create_all(engine)


def drop_all():
    """删除所有表 (测试用)"""
    engine = get_engine()
    metadata.drop_all(engine)
