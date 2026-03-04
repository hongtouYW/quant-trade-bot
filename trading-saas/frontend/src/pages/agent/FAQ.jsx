import { useState } from 'react';
import { Card } from '../../components/common/Card';
import { HelpCircle, ChevronDown, ChevronUp, Bot, Key, Receipt, TrendingUp, AlertTriangle } from 'lucide-react';

const faqData = [
  {
    category: 'Bot',
    icon: Bot,
    items: [
      {
        q: 'Why is the bot not opening positions?',
        a: `There are several reasons:
- **Signal score too low**: The bot requires a minimum score (default 60) to open. Most coins don't meet the threshold at any given time.
- **85+ LONG filter**: Backtesting showed that extreme bullish signals (85+ score, LONG direction) consistently lose money. These are automatically skipped.
- **MA slope filter**: If the trend direction (based on MA20 vs MA50) conflicts with the signal direction, the trade is filtered out.
- **Max positions reached**: The bot has a configurable limit (default 15). When reached, no new positions open until existing ones close.
- **Cooldown period**: After closing a position on a symbol, the bot waits (default 30 min) before re-entering.
- **Risk limit**: If the daily loss limit or max drawdown is hit, the bot pauses new entries.

Check the **Signal Scanner** panel on the Bot page to see which signals are being analyzed and why they're being filtered.`,
      },
      {
        q: 'Bot shows "error" status — what should I do?',
        a: `The bot watchdog automatically restarts crashed bots. If it crashes more than 3 times in 5 minutes, it stops trying and marks the bot as "error".

Common causes:
- **Binance API rate limit**: Too many requests. Wait a few minutes and restart.
- **API key expired or revoked**: Check your API key status in Settings.
- **Network issues**: Temporary connection problems to Binance servers.

To fix: Go to Bot Control page and click "Start Bot". If it keeps crashing, check your API key configuration.`,
      },
      {
        q: 'What happens if the server restarts?',
        a: `All bots that were running before the restart will automatically resume. Open positions are stored in the database and reloaded when the bot restarts. No trades are lost.`,
      },
    ],
  },
  {
    category: 'API Keys',
    icon: Key,
    items: [
      {
        q: 'How do I create a Binance API key?',
        a: `Go to Settings > Binance API Keys and follow the step-by-step wizard:
1. Log in to Binance and go to API Management
2. Create a new API key (System generated)
3. Set IP whitelist to our server IP: \`139.162.41.38\`
4. Enable "Futures" permission
5. Copy the key and secret, paste them in Settings

**Important**: Never enable "Withdrawals" permission. Our bot only needs trading access.`,
      },
      {
        q: 'API key verification failed — what\'s wrong?',
        a: `Common issues:
- **IP not whitelisted**: Add \`139.162.41.38\` to your API key's IP restriction list.
- **Futures not enabled**: The bot trades Futures contracts — this permission must be enabled.
- **Key expired**: Binance keys can expire. Create a new one if needed.
- **Testnet mismatch**: If you created a testnet key, make sure "Testnet mode" is checked in Settings.`,
      },
      {
        q: 'Is my API key safe?',
        a: `Yes. Your API keys are:
- **Encrypted** with AES-256 before storage (industry standard)
- **Never stored in plaintext** — not in the database, not in logs
- **IP-restricted** — even if someone obtained your encrypted key, it only works from our server
- **No withdrawal permission** — the bot cannot move funds out of your account

The encryption master key is stored separately in the server environment, not in the database.`,
      },
    ],
  },
  {
    category: 'Billing',
    icon: Receipt,
    items: [
      {
        q: 'How is profit sharing calculated?',
        a: `We use the **High Water Mark (HWM)** method:
- You only pay commission on **new profits above your previous highest point**
- If you lose money, you pay nothing until you recover to the previous high
- Commission rate: **20% of net new profit** (after fees)

**Example**:
- Month 1: You profit +500U → Commission: 100U (20% of 500)
- Month 2: You lose -200U → Commission: 0U (no profit)
- Month 3: You profit +300U → Commission: 20U (only 20% of the 100U above the old high)

This ensures you never pay commission on the same profit twice.`,
      },
      {
        q: 'When is billing calculated?',
        a: `Billing periods run monthly (1st to last day of month). On the 1st of each month, the system automatically closes the previous period and calculates any commission due.`,
      },
    ],
  },
  {
    category: 'Trading',
    icon: TrendingUp,
    items: [
      {
        q: 'What trading pairs are supported?',
        a: `The bot monitors approximately **150 USDT perpetual futures pairs** on Binance. These include major coins (BTC, ETH, BNB), mid-caps, and selected small-caps.

Certain coins that consistently lose money in backtesting are excluded (SKIP_COINS list). The system automatically updates this list based on performance data.`,
      },
      {
        q: 'What strategy does the bot use?',
        a: `The bot uses a **multi-factor signal scoring system** (v4.2):
- Technical indicators: RSI, MACD, Bollinger Bands, volume
- Trend analysis: MA20/MA50 slope comparison
- Each signal gets a score from 0-100
- Only signals above the minimum threshold trigger trades
- Position size is scaled by score and risk level

Key rules:
- **SHORT bias (1.05x)**: Slightly favors short positions based on backtest data
- **85+ LONG skip**: Extreme bullish signals are contrarian indicators
- **MA slope filter**: Ensures trend alignment
- **Trailing stop**: Locks in profits as they grow`,
      },
      {
        q: 'What are the risk controls?',
        a: `Multiple layers of protection:
- **Position size limits**: Max per-trade size (default 500U)
- **Max leverage**: Capped at 3x (configurable)
- **Max concurrent positions**: Default 15
- **ROI stop-loss**: Auto-close at -10% ROI per trade
- **Trailing stop**: Activates at +6% ROI, trails by 3%
- **Daily loss limit**: Pauses trading if daily loss exceeds threshold
- **Max drawdown**: Pauses at 20% drawdown from peak capital
- **Min hold time**: 3-hour minimum prevents panic selling (unless loss > 15%)
- **Max hold time**: Forces close after 48 hours`,
      },
    ],
  },
];

export default function FAQ() {
  return (
    <div className="space-y-6">
      <div className="flex items-center gap-2">
        <HelpCircle size={22} className="text-primary" />
        <h2 className="text-xl font-bold">FAQ & Help</h2>
      </div>

      {faqData.map((section) => (
        <div key={section.category} className="space-y-3">
          <div className="flex items-center gap-2">
            <section.icon size={16} className="text-primary" />
            <h3 className="text-sm font-semibold text-text-secondary uppercase tracking-wider">{section.category}</h3>
          </div>
          {section.items.map((item, i) => (
            <FaqItem key={i} question={item.q} answer={item.a} />
          ))}
        </div>
      ))}
    </div>
  );
}

function FaqItem({ question, answer }) {
  const [open, setOpen] = useState(false);

  return (
    <Card className="cursor-pointer" onClick={() => setOpen(!open)}>
      <div className="flex items-center justify-between gap-2">
        <h4 className="text-sm font-medium text-text">{question}</h4>
        {open ? (
          <ChevronUp size={16} className="text-text-secondary shrink-0" />
        ) : (
          <ChevronDown size={16} className="text-text-secondary shrink-0" />
        )}
      </div>
      {open && (
        <div className="mt-3 pt-3 border-t border-border/30 text-sm text-text-secondary leading-relaxed whitespace-pre-line prose-sm">
          {answer.split('\n').map((line, i) => {
            // Handle markdown-like bold
            const parts = line.split(/\*\*(.*?)\*\*/g);
            return (
              <p key={i} className={line.startsWith('-') ? 'ml-2' : ''}>
                {parts.map((part, j) =>
                  j % 2 === 1 ? <b key={j} className="text-text">{part}</b> : part
                )}
              </p>
            );
          })}
        </div>
      )}
    </Card>
  );
}
