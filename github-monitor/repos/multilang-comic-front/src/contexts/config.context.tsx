import { createContext, useContext, useEffect, useState } from "react";
import { API_ENDPOINTS } from "../api/api-endpoint";
import { http } from "../api";
import type { APIResponseType } from "../api/type";

interface ReactNodeProps {
  children: React.ReactNode;
}

type ConfigContextType = {
  config: any;
};

const ConfigContext = createContext<ConfigContextType | null>({
  config: {},
});

export const ConfigProvider: React.FC<ReactNodeProps> = ({ children }) => {
  const [config, setConfig] = useState<any>({});

  const handleGetConfig = async () => {
    try {
      const res = await http.post<APIResponseType>(API_ENDPOINTS.config);
      // console.log("res-config", res);
      if (res?.data?.code === 1) {
        setConfig(res?.data?.data);
      }
    } catch (error) {
      console.error("error", error);
    }
  };

  useEffect(() => {
    handleGetConfig();
  }, []);

  const value = { config, setConfig };
  return (
    <ConfigContext.Provider value={value}>{children}</ConfigContext.Provider>
  );
};

// eslint-disable-next-line react-refresh/only-export-components
export const useConfig = () => {
  return useContext(ConfigContext) as ConfigContextType;
};
