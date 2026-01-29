    def evaluate_position_health(self, pos, current_price):
        """智能评估持仓健康度，决定是否提前止损"""
        symbol = pos['symbol']
        direction = pos['direction']
        entry_price = pos['entry_price']
        entry_time = datetime.fromisoformat(pos['entry_time'])
        holding_minutes = (datetime.now() - entry_time).total_seconds() / 60
        
        # 计算当前盈亏百分比
        if direction == 'long':
            pnl_pct = (current_price - entry_price) / entry_price * 100
        else:
            pnl_pct = (entry_price - current_price) / entry_price * 100
        
        # 获取当前市场指标
        try:
            response = requests.get(f"http://localhost:5001/api/analysis/{symbol.replace('/', '')}", timeout=10)
            if response.status_code == 200:
                analysis = response.json()
                current_rsi = analysis.get('rsi', 50)
                current_trend = analysis.get('trend', 'neutral')
            else:
                return None, None
        except:
            return None, None
        
        should_exit = False
        reason = ""
        
        # 规则1: 持仓超2小时且亏损
        if holding_minutes > 120 and pnl_pct < 0:
            should_exit = True
            reason = f"持仓{int(holding_minutes)}分钟无盈利"
        
        # 规则2: 趋势反转
        entry_trend = pos.get('entry_trend', 'neutral')
        if direction == 'long' and current_trend == 'bearish' and entry_trend != 'bearish':
            if pnl_pct < 0.5:  # 盈利不足0.5%时趋势反转要跑
                should_exit = True
                reason = f"趋势反转 ({entry_trend}→{current_trend})"
        elif direction == 'short' and current_trend == 'bullish' and entry_trend != 'bullish':
            if pnl_pct < 0.5:
                should_exit = True
                reason = f"趋势反转 ({entry_trend}→{current_trend})"
        
        # 规则3: RSI反向极端
        if direction == 'long' and current_rsi > 75 and pnl_pct > 0:
            should_exit = True
            reason = f"RSI超买({current_rsi:.0f})获利了结"
        elif direction == 'short' and current_rsi < 25 and pnl_pct > 0:
            should_exit = True
            reason = f"RSI超卖({current_rsi:.0f})获利了结"
        
        # 规则4: 浮亏超1%且持仓超30分钟
        if pnl_pct < -1.0 and holding_minutes > 30:
            should_exit = True
            reason = f"浮亏{pnl_pct:.1f}%主动止损"
        
        return should_exit, reason
