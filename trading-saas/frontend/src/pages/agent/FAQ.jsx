import { useState } from 'react';
import { Card } from '../../components/common/Card';
import { useLanguage } from '../../contexts/LanguageContext';
import { HelpCircle, ChevronDown, ChevronUp, Bot, Key, Receipt, TrendingUp, AlertTriangle } from 'lucide-react';

function getFaqData(t) {
  return [
    {
      category: t('faq.catBot'),
      icon: Bot,
      items: [
        { q: t('faq.q1'), a: t('faq.a1') },
        { q: t('faq.q2'), a: t('faq.a2') },
        { q: t('faq.q3'), a: t('faq.a3') },
      ],
    },
    {
      category: t('faq.catApiKeys'),
      icon: Key,
      items: [
        { q: t('faq.q4'), a: t('faq.a4') },
        { q: t('faq.q5'), a: t('faq.a5') },
        { q: t('faq.q6'), a: t('faq.a6') },
      ],
    },
    {
      category: t('faq.catBilling'),
      icon: Receipt,
      items: [
        { q: t('faq.q7'), a: t('faq.a7') },
        { q: t('faq.q8'), a: t('faq.a8') },
      ],
    },
    {
      category: t('faq.catTrading'),
      icon: TrendingUp,
      items: [
        { q: t('faq.q9'), a: t('faq.a9') },
        { q: t('faq.q10'), a: t('faq.a10') },
        { q: t('faq.q11'), a: t('faq.a11') },
      ],
    },
  ];
}

export default function FAQ() {
  const { t } = useLanguage();
  const faqData = getFaqData(t);

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-2">
        <HelpCircle size={22} className="text-primary" />
        <h2 className="text-xl font-bold">{t('faq.title')}</h2>
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
