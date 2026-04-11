"use client";
import { useTranslations } from "next-intl";
import { useState, useRef, useEffect } from "react";

export default function UISelect({
  options = [],
  value,
  onChange,
  placeholder,
  disabled = false,
  className = "",
  renderOption,
}) {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  const t = useTranslations();

  const selected = options.find((o) => o.value === value);

  // --- Click outside -> close dropdown
  useEffect(() => {
    const handler = (e) => {
      if (ref.current && !ref.current.contains(e.target)) {
        setOpen(false);
      }
    };
    document.addEventListener("click", handler);
    return () => document.removeEventListener("click", handler);
  }, []);

  return (
    <div ref={ref} className={`relative w-full ${className}`}>
      {/* Trigger */}
      <button
        type="button"
        disabled={disabled}
        onClick={() => !disabled && setOpen(!open)}
        className={`h-11 w-full flex items-center justify-between rounded-lg px-3 text-sm transition
          ${
            disabled
              ? "bg-[#0A1F58]/40 border border-white/10 text-white/40 cursor-not-allowed"
              : "bg-[#0C1F46] border border-white/20 text-white hover:border-white/40"
          }
        `}
        style={{
          appearance: "none",
          WebkitAppearance: "none",
          MozAppearance: "none",
        }}
      >
        <span>
          {selected ? (
            selected.label
          ) : (
            <span className="text-white/40">{placeholder}</span>
          )}
        </span>

        {/* ▼ */}
        <span
          className={`text-xs transition ${
            open ? "rotate-180 text-white" : "text-white/60"
          }`}
        >
          ▼
        </span>
      </button>

      {/* Dropdown List */}
      {open && !disabled && (
        <div className="absolute left-0 right-0 mt-1 z-[999] max-h-56 overflow-auto rounded-lg bg-[#0C1F46] border border-white/20 shadow-xl backdrop-blur-sm animate-fadeIn">
          {options.length === 0 && (
            <div className="px-3 py-3 text-sm text-white/50 text-center">
              {t("common.noData")}
            </div>
          )}

          {options.map((opt) => (
            <div
              key={opt.value}
              onClick={() => {
                onChange(opt.value, opt);
                setOpen(false);
              }}
              className="px-3 py-2 text-sm text-white hover:bg-[#152B70] cursor-pointer transition"
            >
              {renderOption ? renderOption(opt) : opt.label}
            </div>
          ))}
        </div>
      )}

      {/* CSS Animation */}
      <style jsx>{`
        .animate-fadeIn {
          animation: fadeIn 0.15s ease-out;
        }
        @keyframes fadeIn {
          from {
            opacity: 0;
            transform: translateY(-4px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }
      `}</style>
    </div>
  );
}
