"use client";

import { useContext, useEffect, useMemo, useState } from "react";

import { useTranslations } from "next-intl";
import { UIContext } from "@/contexts/UIProvider";

// --- tiny formatter (supports: yyyy, MM, dd, HH, mm) ---
function pad(n) {
  return String(n).padStart(2, "0");
}

function formatDate(d, fmt) {
  if (!(d instanceof Date) || isNaN(d)) return "";
  const map = {
    yyyy: String(d.getFullYear()), // 2025
    MM: pad(d.getMonth() + 1), // 01..12
    dd: pad(d.getDate()), // 01..31
    HH: pad(d.getHours()), // 00..23
    mm: pad(d.getMinutes()), // 00..59
  };
  return fmt.replace(/yyyy|MM|dd|HH|mm/g, (k) => map[k]);
}

// input helpers
function toInputValue(d, type) {
  const yyyy = d.getFullYear();
  const mm = pad(d.getMonth() + 1);
  const dd = pad(d.getDate());
  if (type === "datetime") {
    const HH = pad(d.getHours());
    const min = pad(d.getMinutes());
    return `${yyyy}-${mm}-${dd}T${HH}:${min}`;
  }
  return `${yyyy}-${mm}-${dd}`;
}
function fromInputValue(v, type) {
  if (!v) return new Date();
  if (type === "datetime") {
    // v: "YYYY-MM-DDTHH:mm"
    const [datePart, timePart = "00:00"] = v.split("T");
    const [y, m, d] = datePart.split("-").map(Number);
    const [H, M] = timePart.split(":").map(Number);
    return new Date(y, m - 1, d, H ?? 0, M ?? 0, 0, 0);
  }
  // date only: "YYYY-MM-DD"
  const [y, m, d] = v.split("-").map(Number);
  const dt = new Date(y, m - 1, d);
  dt.setHours(0, 0, 0, 0);
  return dt;
}

export default function DatePickerModal() {
  const { datePickerConfig, closeDatePicker } = useContext(UIContext);
  const t = useTranslations();

  const cfg = datePickerConfig;
  const isOpen = !!cfg;

  // local temp state mirrors the config while editing
  const [tmpSingle, setTmpSingle] = useState(new Date());
  const [tmpStart, setTmpStart] = useState(new Date());
  const [tmpEnd, setTmpEnd] = useState(new Date());

  useEffect(() => {
    if (!cfg) return;
    if (cfg.mode === "single") {
      setTmpSingle(cfg.value ?? new Date());
    } else {
      setTmpStart(cfg.start ?? new Date());
      setTmpEnd(cfg.end ?? new Date());
    }
  }, [cfg]);

  const type = cfg?.type ?? "date"; // "date" | "datetime"
  const mode = cfg?.mode ?? "range"; // "single" | "range"
  const fmt = useMemo(() => {
    // default formats if not provided
    if (cfg?.format) return cfg.format;
    return type === "datetime" ? "yyyy-MM-dd HH:mm" : "dd/MM/yyyy";
  }, [cfg?.format, type]);

  if (!isOpen) return null;

  return (
    <div
      className="fixed inset-0 z-[100] bg-black/60 backdrop-blur-[1px]"
      onClick={() => {
        cfg?.onCancel?.();
        closeDatePicker();
      }}
    >
      <div
        className="absolute inset-x-0 bottom-0 mx-auto max-w-[480px] rounded-t-2xl bg-[#0B1D48] text-white p-4 shadow-2xl"
        onClick={(e) => e.stopPropagation()}
        role="dialog"
        aria-modal="true"
        aria-label={t("transaction.filter.searchByDate")}
      >
        <div className="mx-auto mb-3 h-1.5 w-12 rounded-full bg-white/30" />
        <h3 className="mb-3 text-base font-semibold">
          {t("transaction.filter.searchByDate")}
        </h3>

        {/* Single or Range inputs */}
        {mode === "single" ? (
          <div className="space-y-4">
            <label className="block text-sm">
              <span className="mb-1 block text-white/80">
                {t("transaction.filter.date")}
              </span>
              <input
                type={type === "datetime" ? "datetime-local" : "date"}
                value={toInputValue(tmpSingle, type)}
                onChange={(e) =>
                  setTmpSingle(fromInputValue(e.target.value, type))
                }
                className="w-full rounded-md border border-white/30 bg-transparent px-3 py-2 text-white outline-none"
              />
            </label>
            <div className="text-xs text-white/60">
              {t("transaction.filter.preview")}:{" "}
              <span className="text-white/90">
                {formatDate(tmpSingle, fmt)}
              </span>
            </div>
          </div>
        ) : (
          <div className="space-y-4">
            {/* From */}
            <label className="block text-sm">
              <span className="mb-1 block text-white/80">
                {t("transaction.filter.dateFrom")}
              </span>
              <input
                type={type === "datetime" ? "datetime-local" : "date"}
                value={toInputValue(tmpStart, type)}
                max={toInputValue(tmpEnd, type)}
                onChange={(e) =>
                  setTmpStart(fromInputValue(e.target.value, type))
                }
                style={{
                  // 👇 force white calendar icon
                  WebkitAppearance: "none",
                }}
                className="w-full rounded-md border border-white/30 bg-transparent px-3 py-2 text-white outline-none"
              />
            </label>

            {/* To */}
            <label className="block text-sm">
              <span className="mb-1 block text-white/80">
                {t("transaction.filter.dateTo")}
              </span>
              <input
                type={type === "datetime" ? "datetime-local" : "date"}
                value={toInputValue(tmpEnd, type)}
                min={toInputValue(tmpStart, type)}
                onChange={(e) =>
                  setTmpEnd(fromInputValue(e.target.value, type))
                }
                style={{
                  // 👇 force white calendar icon
                  WebkitAppearance: "none",
                }}
                className="w-full rounded-md border border-white/30 bg-transparent px-3 py-2 text-white outline-none"
              />
            </label>

            <div className="text-xs text-white/60">
              {t("transaction.filter.preview")}:{" "}
              <span className="text-white/90">
                {formatDate(tmpStart, fmt)} – {formatDate(tmpEnd, fmt)}
              </span>
            </div>
          </div>
        )}

        {/* Actions */}
        <div className="mt-5 flex items-center justify-end gap-3">
          <button
            type="button"
            onClick={() => {
              cfg?.onCancel?.();
              closeDatePicker();
            }}
            className="px-4 py-2 text-sm text-white/80"
          >
            {t("transaction.filter.cancel")}
          </button>
          <button
            type="button"
            onClick={() => {
              if (mode === "single") {
                cfg?.onApply?.({
                  value: tmpSingle,
                  formatted: formatDate(tmpSingle, fmt),
                });
              } else {
                cfg?.onApply?.({
                  start: tmpStart,
                  end: tmpEnd,
                  formattedStart: formatDate(tmpStart, fmt),
                  formattedEnd: formatDate(tmpEnd, fmt),
                });
              }
              closeDatePicker();
            }}
            className="rounded-md px-5 py-2 text-sm font-medium text-[#00143D]
                       bg-[linear-gradient(262.63deg,#FFC000_0%,#FE9121_100%)]"
          >
            {t("transaction.filter.apply")}
          </button>
        </div>
      </div>
    </div>
  );
}
