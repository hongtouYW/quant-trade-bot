import { createContext, type ReactNode, useContext } from "react";
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

  const getImageByKey = (key: string): GlobalImage | undefined => {
    return globalImages?.find(image => image.key === key);
  };

  return (
    <GlobalImageContext.Provider
      value={{
        globalImages,
        isLoading: isPending,
        isError,
        getImageByKey,
      }}
    >
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