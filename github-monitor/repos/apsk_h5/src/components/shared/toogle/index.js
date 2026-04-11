"use client";

import { useState, memo } from "react";

function FastToggleComponent({
  value,
  onChange,
  defaultOn = true,
  className = "",
  ariaLabel = "fast-toggle",
}) {
  const [internalOn, setInternalOn] = useState(defaultOn);
  const isControlled = value !== undefined;
  const on = isControlled ? value : internalOn;

  const toggle = (e) => {
    e.stopPropagation();
    const next = !on;
    if (!isControlled) setInternalOn(next);
    onChange?.(next);
  };

  return (
    <button
      onClick={toggle}
      aria-pressed={on}
      aria-label={ariaLabel}
      className={`relative h-7 w-12 rounded-full transition-colors ${
        on ? "bg-emerald-400" : "bg-white/20"
      } ${className}`}
    >
      <span
        className={`absolute top-0.5 h-6 w-6 rounded-full bg-white transition-all ${
          on ? "left-6" : "left-0.5"
        }`}
      />
    </button>
  );
}

const FastToggle = memo(FastToggleComponent);
export default FastToggle;
