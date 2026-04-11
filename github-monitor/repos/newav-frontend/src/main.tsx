import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import "@vidstack/react/player/styles/default/theme.css";
import "@vidstack/react/player/styles/default/theme.css";
import "@vidstack/react/player/styles/default/layouts/video.css";
import App from "./App.tsx";
import { BrowserRouter } from "react-router";
import { ThemeProvider } from "./components/theme-provider";

// Suppress errors in production
if (process.env.NODE_ENV === "production") {
  // Suppress console errors and warnings
  console.error = () => {};
  console.warn = () => {};

  // Suppress GA network errors
  window.addEventListener(
    "error",
    (e) => {
      if (e.message?.includes("google-analytics.com")) e.preventDefault();
    },
    true,
  );
}

createRoot(document.getElementById("root")!).render(
  <StrictMode>
    <ThemeProvider defaultTheme="auto" storageKey="insav-ui-theme">
      <BrowserRouter>
        <App />
      </BrowserRouter>
    </ThemeProvider>
  </StrictMode>,
);
