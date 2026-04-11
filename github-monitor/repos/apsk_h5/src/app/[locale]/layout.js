// app/[locale]/layout.js
import en from "@/locales/en.json";
import zh from "@/locales/zh.json";
import ms from "@/locales/ms.json";
import IntlProvider from "@/components/IntlProvider";
import LoadingDialog from "@/components/LoadingDialog";

import { UIProvider } from "@/contexts/UIProvider";
import { Providers } from "@/store/provider";
import ConfirmDialog from "@/components/ConfirmDialog";
import DatePickerModal from "@/components/DatePickerModal";
import { Toaster } from "react-hot-toast";

export const dynamicParams = false;
export function generateStaticParams() {
  return ["zh", "en", "ms"].map((locale) => ({ locale }));
}
const bundles = { zh, en, ms };
// ←– BUILD-TIME metadata that’s **static** but knows the locale

export default async function LocaleLayout({ children, params }) {
  const locale = (await params).locale;
  const messages = bundles[locale];
  // Pass locale + messages down, but no client hooks here
  return (
    <Providers>
      <IntlProvider locale={locale} messages={messages} defaultNS={locale}>
        <UIProvider>
          <div className="mx-auto max-w-[480px] min-h-dvh bg-[#01133C] shadow">
            {children}
          </div>

          <Toaster
            position="top-center"
            toastOptions={{
              duration: 3000, // default for all (important!)
              // default (info/warning)

              style: {
                borderRadius: "16px",
                padding: "12px 16px",
                fontSize: "14px",
                fontWeight: 500,
              },

              // override for specific types
              success: {
                duration: 3000, // success: short
              },
              error: {
                duration: 5000, // error: longer
              },
            }}
          />

          {/* <MessageDialog /> */}
          <LoadingDialog />
          <ConfirmDialog />
        </UIProvider>
      </IntlProvider>
    </Providers>
  );
}
