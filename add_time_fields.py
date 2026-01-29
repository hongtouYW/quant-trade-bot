#!/usr/bin/env python3
# Add entry_time and exit_time to trade card

file_path = "/opt/trading-bot/quant-trade-bot/xmr_monitor/trading_assistant_dashboard.py"

with open(file_path, "r") as f:
    content = f.read()

old_text = """                                ${trade.reason && trade.status === 'CLOSED' ? `
                                <div class="trade-card-detail" style="grid-column: span 2;">
                                    <span class="trade-card-detail-label">原因</span>
                                    <span class="trade-card-detail-value">${trade.reason}</span>
                                </div>
                                ` : ''}
                                </div>"""

new_text = """                                ${trade.reason && trade.status === 'CLOSED' ? `
                                <div class="trade-card-detail" style="grid-column: span 2;">
                                    <span class="trade-card-detail-label">原因</span>
                                    <span class="trade-card-detail-value">${trade.reason}</span>
                                </div>
                                ` : ''}
                                <div class="trade-card-detail">
                                    <span class="trade-card-detail-label">开仓</span>
                                    <span class="trade-card-detail-value">${formatTime(trade.entry_time)}</span>
                                </div>
                                ${trade.exit_time ? `
                                <div class="trade-card-detail">
                                    <span class="trade-card-detail-label">平仓</span>
                                    <span class="trade-card-detail-value">${formatTime(trade.exit_time)}</span>
                                </div>
                                ` : ''}
                                </div>"""

if old_text in content:
    content = content.replace(old_text, new_text)
    with open(file_path, "w") as f:
        f.write(content)
    print("Success: Added entry_time and exit_time fields")
else:
    print("Warning: Pattern not found, trying alternative...")
    # Try without the reason block (in case it wasn't added yet)
    old_text2 = """                                <div class="trade-card-detail">
                                    <span class="trade-card-detail-label">杠杆</span>
                                    <span class="trade-card-detail-value">${trade.leverage}x</span>
                                </div>
                            </div>
                        </div>
                    \`;"""

    new_text2 = """                                <div class="trade-card-detail">
                                    <span class="trade-card-detail-label">杠杆</span>
                                    <span class="trade-card-detail-value">${trade.leverage}x</span>
                                </div>
                                <div class="trade-card-detail">
                                    <span class="trade-card-detail-label">开仓</span>
                                    <span class="trade-card-detail-value">${formatTime(trade.entry_time)}</span>
                                </div>
                                ${trade.exit_time ? `
                                <div class="trade-card-detail">
                                    <span class="trade-card-detail-label">平仓</span>
                                    <span class="trade-card-detail-value">${formatTime(trade.exit_time)}</span>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    \`;"""

    if old_text2 in content:
        content = content.replace(old_text2, new_text2)
        with open(file_path, "w") as f:
            f.write(content)
        print("Success: Added entry_time and exit_time fields (alternative)")
    else:
        print("Error: Could not find pattern to replace")
