"use client";

import { createContext, useContext, useRef } from "react";

const WukongContext = createContext(null);

export function WukongProvider({ children }) {
  const wkRef = useRef(null);
  return (
    <WukongContext.Provider value={wkRef}>{children}</WukongContext.Provider>
  );
}

export function useWukongRef() {
  return useContext(WukongContext);
}
