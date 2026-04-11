"use client";

import { useEffect } from "react";
import { usePathname } from "next/navigation";
import { unlockAudio } from "@/utils/notificationAudio";

export default function AudioGate() {
  const pathname = usePathname();

  // ONLY agent pages unlock audio
  const isAgent = pathname.startsWith("/agent");

  useEffect(() => {
    if (!isAgent) return;

    const handler = () => unlockAudio();

    // any user interaction unlocks audio
    window.addEventListener("click", handler, { once: true });
    window.addEventListener("touchstart", handler, { once: true });
    window.addEventListener("keydown", handler, { once: true });

    return () => {
      window.removeEventListener("click", handler);
      window.removeEventListener("touchstart", handler);
      window.removeEventListener("keydown", handler);
    };
  }, [isAgent]);

  return null; // 👈 NO UI
}
