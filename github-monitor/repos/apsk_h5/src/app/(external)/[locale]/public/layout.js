import en from "@/locales/en.json";
import zh from "@/locales/zh.json";
import ms from "@/locales/ms.json";
import IntlProvider from "@/components/IntlProvider";
import LoadingDialog from "@/components/LoadingDialog";
import ConfirmDialog from "@/components/ConfirmDialog";
import { UIProvider } from "@/contexts/UIProvider";
import { Providers } from "@/store/provider";
import { Toaster } from "react-hot-toast";
import PublicHeader from "./components/PublicHeader";
import PublicFooter from "./components/PublicFooter";

const bundles = { en, zh, ms };

export default async function IntroLayout({ children, params }) {
  const { locale } = await params; // ⭐ FIX
  const messages = bundles[locale];

  return (
    <Providers>
      <IntlProvider locale={locale} messages={messages}>
        <UIProvider>
          <div className="min-h-dvh w-full bg-black text-white flex flex-col">
            <PublicHeader /> {/* 🔥 add here */}
            <main className="flex-1">{children}</main>
            <PublicFooter /> {/* 🔥 add here */}
          </div>

          <Toaster />
          <LoadingDialog />
          <ConfirmDialog />
        </UIProvider>
      </IntlProvider>
    </Providers>
  );
}
