#!/usr/bin/env python3
# Make trade cards more compact

file_path = "/opt/trading-bot/quant-trade-bot/xmr_monitor/trading_assistant_dashboard.py"

with open(file_path, "r") as f:
    content = f.read()

# Current CSS (beautified version)
old_css = """        .trade-cards {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .trade-card {
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px;
            transition: all 0.25s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }

        .trade-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }

        .trade-card.closed {
            border-left: 4px solid #10b981;
            background: linear-gradient(145deg, #f0fdf4 0%, #ffffff 100%);
        }

        .trade-card.closed.loss {
            border-left: 4px solid #ef4444;
            background: linear-gradient(145deg, #fef2f2 0%, #ffffff 100%);
        }

        .trade-card.open {
            border-left: 4px solid #3b82f6;
            background: linear-gradient(145deg, #eff6ff 0%, #ffffff 100%);
        }

        .trade-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f1f5f9;
        }

        .trade-card-title {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .trade-card-symbol {
            font-size: 1.1em;
            font-weight: 700;
            color: #1e293b;
            letter-spacing: 0.5px;
        }

        .trade-card-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding: 8px 0;
        }

        .trade-card-pnl {
            font-size: 1.35em;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .trade-card-roi {
            font-size: 1em;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            background: rgba(0, 0, 0, 0.04);
        }

        .trade-card-details {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            font-size: 0.8em;
            padding-top: 10px;
            border-top: 1px dashed #e2e8f0;
        }

        .trade-card-detail {
            display: flex;
            flex-direction: column;
            padding: 6px 8px;
            background: #f8fafc;
            border-radius: 6px;
        }

        .trade-card-detail-label {
            color: #94a3b8;
            font-size: 0.85em;
            margin-bottom: 3px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .trade-card-detail-value {
            color: #334155;
            font-weight: 600;
            font-size: 0.95em;
        }"""

# Compact version
new_css = """        .trade-cards {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .trade-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 8px 10px;
            transition: all 0.2s;
        }

        .trade-card:hover {
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
        }

        .trade-card.closed {
            border-left: 3px solid #10b981;
            background: #fafffe;
        }

        .trade-card.closed.loss {
            border-left: 3px solid #ef4444;
            background: #fffafa;
        }

        .trade-card.open {
            border-left: 3px solid #3b82f6;
            background: #fafbff;
        }

        .trade-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .trade-card-title {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .trade-card-symbol {
            font-size: 0.95em;
            font-weight: 700;
            color: #1e293b;
        }

        .trade-card-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .trade-card-pnl {
            font-size: 1.15em;
            font-weight: 700;
        }

        .trade-card-roi {
            font-size: 0.9em;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
            background: rgba(0, 0, 0, 0.04);
        }

        .trade-card-details {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 4px;
            font-size: 0.72em;
            padding-top: 6px;
            border-top: 1px solid #f0f0f0;
        }

        .trade-card-detail {
            display: flex;
            flex-direction: column;
            padding: 3px 5px;
            background: #f9fafb;
            border-radius: 4px;
        }

        .trade-card-detail-label {
            color: #9ca3af;
            font-size: 0.9em;
            margin-bottom: 1px;
        }

        .trade-card-detail-value {
            color: #374151;
            font-weight: 600;
        }"""

if old_css in content:
    content = content.replace(old_css, new_css)
    with open(file_path, "w") as f:
        f.write(content)
    print("Success: Made cards compact")
else:
    print("Error: Could not find CSS pattern")
