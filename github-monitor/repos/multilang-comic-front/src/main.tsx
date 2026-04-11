import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import "./index.css";
import App from "./App.tsx";
import { MenuProvider } from "./contexts/menu.context.tsx";
import { UserProvider } from "./contexts/user.context.tsx";
import { I18nextProvider } from "react-i18next";
import i18n from "./utils/i18n/index.ts";
import { ConfigProvider } from "./contexts/config.context.tsx";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { ResourceProvider } from "./contexts/resource.context.tsx";

export const queryClient = new QueryClient();

createRoot(document.getElementById("root")!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <I18nextProvider i18n={i18n}>
        <ConfigProvider>
          <UserProvider>
            <ResourceProvider>
              <MenuProvider>
                <App />
              </MenuProvider>
            </ResourceProvider>
          </UserProvider>
        </ConfigProvider>
      </I18nextProvider>
    </QueryClientProvider>
  </StrictMode>
);
