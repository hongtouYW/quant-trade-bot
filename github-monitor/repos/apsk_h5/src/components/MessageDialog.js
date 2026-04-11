"use client";

import { useContext, useEffect } from "react";
import { toast } from "react-hot-toast";
import { UIContext } from "@/contexts/UIProvider";

export default function MessageDialog() {
  const { message, type, messageVisible } = useContext(UIContext);

  useEffect(() => {
    if (messageVisible && message) {
      if (type === "success") {
        toast.success(message, {
          style: {
            background: "linear-gradient(180deg,#156B2D 0%,#0F4E22 100%)",
            color: "#fff",
            boxShadow:
              "0 0 0 1px rgba(39,225,109,0.6) inset, 0 8px 24px rgba(39,225,109,0.25)",
          },
        });
      } else {
        toast.error(message, {
          style: {
            background: "linear-gradient(180deg,#7A0E0E 0%,#520909 100%)",
            color: "#fff",
            boxShadow:
              "0 0 0 1px rgba(255,92,92,0.55) inset, 0 8px 24px rgba(255,92,92,0.25)",
          },
        });
      }
    }
  }, [message, type, messageVisible]);

  return null; // nothing to render
}
