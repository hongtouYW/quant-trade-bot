"use client";

import { useTranslations } from "next-intl";

export default function HelpContent() {
  const t = useTranslations("helps");

  return (
    <div className="min-h-dvh w-full bg-white text-black">
      {/* ====== HEADER ====== */}
      <section className="w-full bg-white">
        <div className="mx-auto max-w-4xl px-4 py-10">
          <h1 className="text-4xl font-bold mb-6">{t("title")}</h1>
        </div>
      </section>

      {/* ====== SECTION 1 ====== */}
      <section id="help-faq" className="w-full bg-white">
        <div className="mx-auto max-w-4xl px-4 py-6">
          <h2 className="text-lg font-semibold mb-2">{t("section1.title")}</h2>
          <p className="text-sm leading-relaxed text-black/80">
            {t("section1.desc")}
          </p>
        </div>
      </section>

      {/* ====== SECTION 2 ====== */}
      <section id="help-deposit" className="w-full bg-white">
        <div className="mx-auto max-w-4xl px-4 py-6">
          <h2 className="text-lg font-semibold mb-2">{t("section2.title")}</h2>
          <p className="text-sm leading-relaxed text-black/80">
            {t("section2.desc")}
          </p>
        </div>
      </section>

      {/* ====== SECTION 3 ====== */}
      <section id="help-withdraw" className="w-full bg-white">
        <div className="mx-auto max-w-4xl px-4 py-6">
          <h2 className="text-lg font-semibold mb-2">{t("section3.title")}</h2>
          <p className="text-sm leading-relaxed text-black/80">
            {t("section3.desc")}
          </p>
        </div>
      </section>

      {/* ====== SECTION 4 ====== */}
      <section id="help-account" className="w-full bg-white">
        <div className="mx-auto max-w-4xl px-4 py-6">
          <h2 className="text-lg font-semibold mb-2">{t("section4.title")}</h2>
          <p className="text-sm leading-relaxed text-black/80">
            {t("section4.desc")}
          </p>
        </div>
      </section>

      {/* ====== SECTION 5 ====== */}
      <section id="help-contact" className="w-full bg-white">
        <div className="mx-auto max-w-4xl px-4 py-6">
          <h2 className="text-lg font-semibold mb-2">{t("section5.title")}</h2>
          <p className="text-sm leading-relaxed text-black/80">
            {t("section5.desc")}
          </p>
        </div>
      </section>

      {/* ====== FOOTER ====== */}
      <section className="w-full bg-white">
        <div className="mx-auto max-w-4xl px-4 py-10">
          <div className="text-xs text-black/50">
            © {new Date().getFullYear()} Expro99. All rights reserved.
          </div>
        </div>
      </section>
    </div>
  );
}
