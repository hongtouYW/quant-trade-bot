import en from "@/locales/en.json";
import zh from "@/locales/zh.json";
import ms from "@/locales/ms.json";
import IntlProvider from "@/components/IntlProvider";
import LoadingDialog from "@/components/LoadingDialog";
import ConfirmDialog from "@/components/ConfirmDialog";
import { UIProvider } from "@/contexts/UIProvider";
import { Providers } from "@/store/provider";
import { Toaster } from "react-hot-toast";
import PrivacyHeader from "./components/PrivacyHeader";
import PrivacyFooter from "./components/PrivacyFooter";

const bundles = { en, zh, ms };

export default async function IntroLayout({ children, params }) {
  const { locale } = await params; // ⭐ FIX
  const messages = bundles[locale];

  return (
    <Providers>
      <IntlProvider locale={locale} messages={messages}>
        <UIProvider>
          <div className="min-h-dvh w-full bg-black text-white flex flex-col">
            <PrivacyHeader />
            <main className="flex-1">{children}</main>
            <PrivacyFooter />
          </div>

          <Toaster />
          <LoadingDialog />
          <ConfirmDialog />
        </UIProvider>
      </IntlProvider>
    </Providers>
  );
}
