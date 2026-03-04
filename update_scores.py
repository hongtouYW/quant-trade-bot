#!/usr/bin/env python3
"""更新交易记录的评分字段"""
import sqlite3
import re

db_path = '/opt/trading-bot/quant-trade-bot/data/db/trading_assistant.db'

conn = sqlite3.connect(db_path)
conn.row_factory = sqlite3.Row
cursor = conn.cursor()

# 获取所有有reason字段的记录
cursor.execute("SELECT id, reason FROM real_trades WHERE reason IS NOT NULL")
rows = cursor.fetchall()

updated = 0
for row in rows:
    reason = row['reason'] or ''
    # 尝试匹配 "信号评分XX分" 或 "评分XX分"
    match = re.search(r'评分(\d+)分', reason)
    if match:
        score = int(match.group(1))
        cursor.execute("UPDATE real_trades SET score = ? WHERE id = ?", (score, row['id']))
        updated += 1
        print(f"ID {row['id']}: score = {score}")

conn.commit()
print(f"\n✅ Updated {updated} records")

# 验证结果
cursor.execute("SELECT symbol, score, reason FROM real_trades WHERE score > 0 LIMIT 10")
for row in cursor.fetchall():
    print(f"{row['symbol']}: {row['score']}分 - {row['reason'][:30]}...")

conn.close()
