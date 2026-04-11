"use client";

import { useContext, useEffect, useMemo, useState } from "react";
import { useTranslations } from "next-intl";

import { UIContext } from "@/contexts/UIProvider";
import { useLazyGetCountryPhoneQuery } from "@/services/commonApi";

export default function PhoneCodePickerModal() {
  const t = useTranslations();

  const [cachedCodes, setCachedCodes] = useState([]);
  const [hasCache, setHasCache] = useState(false);
  const [cacheChecked, setCacheChecked] = useState(false); // ✅ NEW
  const [q, setQ] = useState("");

  const { phonePickerOpen, setPhonePickerOpen, phonePickerConfig } =
    useContext(UIContext);

  const [triggerGetCountryPhone, { isLoading }] = useLazyGetCountryPhoneQuery();

  useEffect(() => {
    if (!phonePickerOpen) return;

    // reset when open
    setCacheChecked(false);

    // 1️⃣ load cache first
    const cached = localStorage.getItem("phone_country_codes");
    if (cached) {
      try {
        const parsed = JSON.parse(cached);
        if (Array.isArray(parsed) && parsed.length) {
          setCachedCodes(parsed);
          setHasCache(true);
        } else {
          setHasCache(false);
        }
      } catch {
        setHasCache(false);
      }
    } else {
      setHasCache(false);
    }

    // ✅ mark cache checked BEFORE calling API
    setCacheChecked(true);

    // 2️⃣ refresh API in background
    triggerGetCountryPhone(undefined, true)
      .unwrap()
      .then((res) => {
        const formatted = res.data.map((c) => ({
          code: c.country_code,
          name: c.country_name,
          dial: `+${c.phone_code}`,
        }));

        setCachedCodes(formatted);
        setHasCache(true);
        localStorage.setItem("phone_country_codes", JSON.stringify(formatted));
      })
      .catch(() => {
        // ignore error
      });
  }, [phonePickerOpen, triggerGetCountryPhone]);

  const phoneCodes = useMemo(() => cachedCodes, [cachedCodes]);

  const list = useMemo(() => {
    const query = q.trim().toLowerCase();
    return phoneCodes.filter(
      (c) =>
        c.name.toLowerCase().includes(query) ||
        c.dial.toLowerCase().includes(query) ||
        c.code.toLowerCase().includes(query),
    );
  }, [q, phoneCodes]);

  const currentDial = phonePickerConfig?.value ?? "+60";

  const handleCancel = () => setPhonePickerOpen(false);
  const handleConfirm = () => setPhonePickerOpen(false);

  const onPick = (item) => {
    phonePickerConfig?.onSelect?.(item);
    setPhonePickerOpen(false);
  };

  if (!phonePickerOpen) return null;

  return (
    <div
      className="fixed inset-0 z-[100] bg-black/60 backdrop-blur-[1px]"
      onClick={handleCancel}
      aria-hidden
    >
      <div
        className="absolute inset-x-0 bottom-0 mx-auto max-w-[480px] rounded-t-2xl bg-[#1D1D1D] text-white p-4 shadow-2xl"
        onClick={(e) => e.stopPropagation()}
        role="dialog"
        aria-modal="true"
        aria-label={t("login.phonePickerTitle")}
      >
        {/* Handle */}
        <div className="mx-auto mb-3 h-1.5 w-12 rounded-full bg-white/30" />

        {/* Title */}
        <h3 className="mb-3 text-base font-semibold text-center">
          {t("login.phonePickerTitle")}
        </h3>

        {/* Search */}
        <div className="mb-3 rounded-lg border border-white/15 bg-black/30 px-3 py-2">
          <input
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder={t("login.searchCountry")}
            className="w-full bg-transparent text-sm outline-none placeholder:text-white/50"
          />
        </div>

        {/* List */}
        <div className="max-h-[48vh] overflow-y-auto -mx-4 px-4">
          {/* ✅ Loading only if: cache checked + no cache */}
          {cacheChecked && isLoading && !hasCache && (
            <div className="py-6 text-center text-sm text-white/70">
              {t("common.loading")}...
            </div>
          )}

          {list.map((c) => {
            const active = c.dial === currentDial;
            return (
              <button
                key={c.code}
                onClick={() => onPick(c)}
                className="flex w-full items-center justify-between py-3 text-left border-b border-white/10"
              >
                <span className="text-sm text-[#DB9F27]">
                  {c.name} <span>{c.dial}</span>
                </span>

                <span
                  className={[
                    "grid h-5 w-5 place-items-center rounded-full border",
                    active
                      ? "border-transparent p-[2px]"
                      : "border-[#FFC000]/70",
                  ].join(" ")}
                  style={
                    active
                      ? {
                          background:
                            "linear-gradient(180deg, #F8AF07 0%, #FFFC86 100%)",
                        }
                      : undefined
                  }
                >
                  {active ? (
                    <span className="block h-full w-full rounded-full bg-[#0B1D48]" />
                  ) : (
                    <span className="block h-2 w-2 rounded-full" />
                  )}
                </span>
              </button>
            );
          })}
        </div>

        {/* Actions */}
        <div className="mt-4 flex items-center gap-4 px-2 pb-2">
          <button
            onClick={handleCancel}
            className="flex-1 rounded-full border border-[#FFC000] py-3 text-sm font-medium text-[#FFC000] active:scale-95"
          >
            {t("common.cancel")}
          </button>

          <button
            onClick={handleConfirm}
            className="flex-1 rounded-full py-3 text-sm font-medium text-black active:scale-95"
            style={{
              background: "linear-gradient(180deg, #F8AF07 0%, #FFFC86 100%)",
            }}
          >
            {t("common.confirm")}
          </button>
        </div>
      </div>
    </div>
  );
}
