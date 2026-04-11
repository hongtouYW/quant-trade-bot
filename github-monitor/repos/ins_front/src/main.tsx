import React from "react";
import ReactDOM from "react-dom/client";

import Hls from "hls.js";
import i18n from "./utils/i18n/index.ts";
import { I18nextProvider } from "react-i18next";

import { UserProvider } from "./contexts/user.context.tsx";
import { ConfigProvider } from "./contexts/config.context.tsx";
import { ModalProvider } from "./contexts/modal.context.tsx";

import App from "./App.tsx";
import "./index.css";

const w = window as any;
w.Hls = Hls;

const ignoreDebugger = localStorage.getItem("devTool");
if (ignoreDebugger !== "123456") {
  // eslint-disable-next-line no-inner-declarations
  function ctrlShiftKey(e: any, keyCode: any) {
    return e.ctrlKey && e.shiftKey && e.keyCode === keyCode.charCodeAt(0);
  }

  // Disable right-click
  document.addEventListener("contextmenu", (e) => e.preventDefault());

  document.onkeydown = (e) => {
    // Disable F12, Ctrl + Shift + I, Ctrl + Shift + J, Ctrl + U
    if (
      e.keyCode === 123 ||
      ctrlShiftKey(e, "I") ||
      ctrlShiftKey(e, "J") ||
      ctrlShiftKey(e, "C") ||
      (e.altKey && e.keyCode === 73) ||
      (e.ctrlKey && e.keyCode === "U".charCodeAt(0))
    )
      return false;
  };
}
const appRoot = document.getElementById('root')!
appRoot.setAttribute('notranslate', "true")
appRoot.setAttribute('translate', "no")

ReactDOM.createRoot(appRoot).render(
  <React.StrictMode>
    <I18nextProvider i18n={i18n} defaultNS={"translation"}>
      <ConfigProvider>
        <UserProvider>
          <ModalProvider>
            <App />
          </ModalProvider>
        </UserProvider>
      </ConfigProvider>
    </I18nextProvider>
  </React.StrictMode>
);
