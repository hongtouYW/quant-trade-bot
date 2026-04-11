import { createContext, type ReactNode, useCallback, useContext, useMemo } from "react";
import { useGlobalImage } from "@/hooks/index/useGlobalImage";
import type { GlobalImage } from "@/types/global-image.types";

interface GlobalImageContextType {
  globalImages: GlobalImage[] | null;
  isLoading: boolean;
  isError: boolean;
  getImageByKey: (key: string) => GlobalImage | undefined;
}

const GlobalImageContext = createContext<GlobalImageContextType | null>(null);

interface GlobalImageProviderProps {
  children: ReactNode;
}

export function GlobalImageProvider({ children }: GlobalImageProviderProps) {
  const { data, isPending, isError } = useGlobalImage();
  const globalImages = data || null;

  const getImageByKey = useCallback((key: string): GlobalImage | undefined => {
    return globalImages?.find(image => image.key === key);
  }, [globalImages]);

  const value = useMemo(() => ({
    globalImages,
    isLoading: isPending,
    isError,
    getImageByKey,
  }), [globalImages, isPending, isError, getImageByKey]);

  return (
    <GlobalImageContext.Provider value={value}>
      {children}
    </GlobalImageContext.Provider>
  );
}

export function useGlobalImageContext() {
  const context = useContext(GlobalImageContext);
  if (!context) {
    throw new Error("useGlobalImageContext must be used within a GlobalImageProvider");
  }
  return context;
}