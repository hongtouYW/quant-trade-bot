import { createContext, type ReactNode, useContext, useEffect } from "react";
import { useConfig } from "@/hooks/config/useConfig";
import type { ConfigData } from "@/types/config.types";

interface ConfigContextType {
  configList: ConfigData | null;
  isLoading: boolean;
  isError: boolean;
}

const ConfigContext = createContext<ConfigContextType | null>(null);

interface ConfigProviderProps {
  children: ReactNode;
}

/**
 * Initialize Baidu Analytics with the tracking token from config
 */
function initializeBaiduTracking(baiduToken: string) {
  if (!baiduToken) return;

  // Avoid duplicate initialization
  if ((window as any)._baiduTracking_initialized) return;

  (window as any)._hmt = (window as any)._hmt || [];

  const script = document.createElement("script");
  script.src = `https://hm.baidu.com/hm.js?${baiduToken}`;
  script.async = true;

  const firstScript = document.getElementsByTagName("script")[0];
  firstScript?.parentNode?.insertBefore(script, firstScript);

  (window as any)._baiduTracking_initialized = true;
}

export function ConfigProvider({ children }: ConfigProviderProps) {
  const { data, isPending, isError } = useConfig();
  const configList = data?.data || null;

  // Initialize Baidu tracking once config is loaded
  useEffect(() => {
    if (configList?.baidu_token) {
      console.log(configList.baidu_token);
      initializeBaiduTracking(configList.baidu_token);
    }
  }, [configList?.baidu_token]);

  return (
    <ConfigContext.Provider
      value={{
        configList,
        isLoading: isPending,
        isError,
      }}
    >
      {children}
    </ConfigContext.Provider>
  );
}

export function useConfigContext() {
  const context = useContext(ConfigContext);
  if (!context) {
    throw new Error("useConfigContext must be used within a ConfigProvider");
  }
  return context;
}
