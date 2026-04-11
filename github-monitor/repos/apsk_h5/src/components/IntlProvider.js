// components/IntlProvider.js
"use client";

import { NextIntlClientProvider } from "next-intl";
import FooterNav from "./shared/FooterNav";

// import Header from "./Header";
// import Footer from "./Footer";
// import HeaderM from "./HeaderM";
// import FooterM from "./FooterM";

export default function IntlProvider({
  locale,
  defaultNS,
  messages,
  children,
}) {
  const timeZone = "Europe/Vienna";

  return (
    <NextIntlClientProvider
      timeZone={timeZone}
      locale={locale}
      defaultNS={defaultNS}
      messages={messages}
    >
      <main className="flex-1">{children}</main>
      <FooterNav />
    </NextIntlClientProvider>
  );
}
