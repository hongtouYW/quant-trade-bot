"use client";
import { useEffect, useRef, useState } from "react";

export default function MarqueeTitle({
  title,
  selected = false,
  maxWidth = 60,
  speedSec = 7,
  stop = false, // default = scroll on
  fontSize = "12px", // 👈 NEW (string or number)
}) {
  const containerRef = useRef(null);
  const measureRef = useRef(null);
  const [isOverflow, setIsOverflow] = useState(false);

  const styleMaxWidth =
    typeof maxWidth === "number" ? `${maxWidth}px` : maxWidth;

  const resolvedFontSize =
    typeof fontSize === "number" ? `${fontSize}px` : fontSize;

  useEffect(() => {
    const box = containerRef.current;
    const text = measureRef.current;
    if (!box || !text) return;

    const checkOverflow = () => {
      const boxW = box.clientWidth;
      const textW = text.offsetWidth;
      setIsOverflow(textW > boxW);
    };

    checkOverflow();

    const ro = new ResizeObserver(checkOverflow);
    ro.observe(box);

    return () => ro.disconnect();
  }, [title, maxWidth, fontSize]);

  return (
    <div
      ref={containerRef}
      className="inline-block"
      style={{
        maxWidth: styleMaxWidth,
        overflow: "hidden",
        whiteSpace: "nowrap",
        color: selected ? "#FFC000" : "rgba(255,255,255,0.8)",
        position: "relative",
        fontSize: resolvedFontSize, // 👈 container fallback
      }}
    >
      {/* Hidden accurate width measure */}
      <span
        ref={measureRef}
        style={{
          position: "absolute",
          visibility: "hidden",
          whiteSpace: "nowrap",
          fontSize: resolvedFontSize, // 👈 MUST match
        }}
      >
        {title}
      </span>

      {isOverflow && !stop ? (
        <div
          className="flex animate-marquee"
          style={{
            width: "max-content",
            animationDuration: `${speedSec}s`,
            animationTimingFunction: "linear",
            animationIterationCount: "infinite",
            fontSize: resolvedFontSize,
          }}
        >
          <span className="pr-8">{title}</span>
          <span className="pr-8">{title}</span>
        </div>
      ) : (
        <span style={{ fontSize: resolvedFontSize }}>{title}</span>
      )}

      <style jsx>{`
        @keyframes marquee {
          from {
            transform: translateX(0);
          }
          to {
            transform: translateX(-50%);
          }
        }
        .animate-marquee {
          animation-name: marquee;
        }
      `}</style>
    </div>
  );
}
