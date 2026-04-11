"use client";
import Image from "next/image";
import { memo } from "react";

function SubmitButton({ children, onClick, className = "", disabled = false }) {
  return (
    <button
      disabled={disabled}
      onClick={onClick}
      type="submit"
      className={`w-full rounded-full py-3 font-semibold text-black active:scale-95 ${className}`}
      style={{
        background: "linear-gradient(180deg, #F8AF07 0%, #FFFC86 100%)",
      }}
    >
      {children}
    </button>
  );
}
export default memo(SubmitButton);
