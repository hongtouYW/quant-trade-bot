import { useApi } from '../../hooks/useApi';
import { Card } from '../common/Card';
import { CheckCircle, Circle, Key, MessageSquare, Sliders, Play, ArrowRight } from 'lucide-react';
import { useNavigate } from 'react-router-dom';

export default function SetupChecklist() {
  const { data: keyStatus } = useApi('/agent/api-keys/status');
  const { data: configData } = useApi('/agent/trading/config');
  const { data: botData } = useApi('/agent/bot/status');
  const navigate = useNavigate();

  const steps = [
    {
      key: 'api_key',
      label: 'Set Binance API Key',
      done: !!keyStatus?.has_api_key,
      icon: Key,
      link: '/agent/settings',
    },
    {
      key: 'verify',
      label: 'Verify API Key',
      done: !!keyStatus?.permissions_verified,
      icon: Key,
      link: '/agent/settings',
    },
    {
      key: 'config',
      label: 'Configure Trading Strategy',
      done: !!configData?.config?.strategy_version,
      icon: Sliders,
      link: '/agent/settings',
    },
    {
      key: 'bot',
      label: 'Start Bot',
      done: botData?.status === 'running',
      icon: Play,
      link: '/agent/bot',
    },
  ];

  const completed = steps.filter(s => s.done).length;
  const allDone = completed === steps.length;

  if (allDone) return null;

  const nextStep = steps.find(s => !s.done);

  return (
    <Card className="border border-primary/30 bg-primary/5">
      <div className="flex items-center justify-between mb-3">
        <h3 className="text-sm font-semibold">Setup Your Trading Bot</h3>
        <span className="text-xs text-text-secondary">{completed}/{steps.length} completed</span>
      </div>

      <div className="w-full bg-bg-input rounded-full h-1.5 mb-4">
        <div
          className="bg-primary h-1.5 rounded-full transition-all"
          style={{ width: `${(completed / steps.length) * 100}%` }}
        />
      </div>

      <div className="space-y-2">
        {steps.map((step) => {
          const Icon = step.icon;
          const isNext = step === nextStep;
          return (
            <button
              key={step.key}
              onClick={() => navigate(step.link)}
              className={`w-full flex items-center gap-3 px-3 py-2 rounded-lg text-left text-sm transition-colors ${
                step.done
                  ? 'text-text-tertiary'
                  : isNext
                    ? 'bg-primary/10 text-primary hover:bg-primary/15'
                    : 'text-text-secondary hover:bg-white/5'
              }`}
            >
              {step.done
                ? <CheckCircle size={16} className="text-success shrink-0" />
                : <Circle size={16} className="shrink-0" />
              }
              <Icon size={14} className="shrink-0" />
              <span className={step.done ? 'line-through' : ''}>{step.label}</span>
              {isNext && <ArrowRight size={14} className="ml-auto shrink-0" />}
            </button>
          );
        })}
      </div>
    </Card>
  );
}
