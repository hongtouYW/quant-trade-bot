"use client";
import { useEffect, useRef } from "react";
import { usePathname, useRouter } from "next/navigation";

export default function DetectBackToHome({ onBackToHome }) {
  const pathname = usePathname();
  const router = useRouter();
  const prevPath = useRef(pathname);

  useEffect(() => {
    // Run only when the path actually changes
    if (prevPath.current !== pathname) {
      // ✅ If user went back from /game-start → /home
      if (prevPath.current === "/game-start" && pathname === "/home") {
        alert("⬅️ User clicked Back to Home");
        if (onBackToHome) onBackToHome(); // trigger your logic
      }

      // Store the new path
      prevPath.current = pathname;
    }
  }, [pathname, onBackToHome]);

  return null; // invisible component
}
