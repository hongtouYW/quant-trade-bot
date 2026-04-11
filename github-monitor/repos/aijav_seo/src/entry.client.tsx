import { startTransition, StrictMode } from "react";
import { hydrateRoot } from "react-dom/client";
import { HydratedRouter } from "react-router/dom";

// Suppress known third-party errors that are not actionable
if (process.env.NODE_ENV === "production") {
  window.addEventListener(
    "error",
    (e) => {
      if (e.message?.includes("google-analytics.com")) e.preventDefault();
    },
    true,
  );
}

startTransition(() => {
  hydrateRoot(
    document,
    <StrictMode>
      <HydratedRouter />
    </StrictMode>,
  );
});
