import { createContext, useContext, useEffect, useState } from "react";
import useAxios from "../hooks/useAxios";
import u from "../utils/utils";
import { INSNAME } from "../utils/constant";

interface ReactNodeProps {
  children: React.ReactNode;
}

export interface IConfig {
  life_domain?: string;
  notice?: string;
  baidu_hm_id?: string;
  txt_thumb_url?: string;
  video_adv?: string;
  server_link?: string;
}

export type ConfigContextType = {
  configList: IConfig;
  asList: any;
  // theme: string;
};

const INITIAL_CONFIG_VALUE = {
  life_domain: "",
  notice: "",
  baidu_hm_id: "",
  txt_thumb_url: "",
  video_adv: "",
  server_link: "",
};

export const ConfigContext = createContext<ConfigContextType | null>({
  configList: INITIAL_CONFIG_VALUE,
  asList: {},
  // theme: "",
});

export const ConfigProvider: React.FC<ReactNodeProps> = ({ children }) => {
  const [configList, setConfigList] = useState<IConfig>(INITIAL_CONFIG_VALUE);
  const [asList, setAsList] = useState({});
  // const [theme, setTheme] = useState("");

  const { req } = useAxios("config/lists", "post");
  const { req: asListsReq } = useAxios("extend/lists", "post");

  const fetchConfigList = async () => {
    try {
      const res = await req();
      setConfigList(res?.data?.data);
    } catch (err) {
      console.log(err);
    }
  };

  const fetchAsList = async () => {
    try {
      const res = await asListsReq({ device: u.isMobile() ? 1 : 2 });

      if (res?.data?.code === 1) {
        setAsList(res?.data?.data);
      }
    } catch (err) {
      console.log(err);
    }
  };

  const addStatisticsScript = () => {
    if (configList && configList?.baidu_hm_id) {
      const hm = document.createElement("script");
      hm.src = `https://hm.baidu.com/hm.js?${configList.baidu_hm_id}`;
      const s: any = document.getElementsByTagName("script")[0];
      s.parentNode.insertBefore(hm, s);
    }
  };

  useEffect(() => {
    fetchConfigList();
  }, []);

  useEffect(() => {
    fetchAsList();
  }, []);

  useEffect(() => {
    addStatisticsScript();
  }, [configList]);

  useEffect(() => {
    const subdomain = u.checkSubdomain();

    switch (subdomain) {
      case INSNAME.WUMA:
        // setTheme(THEME_COLOR.PURPLE);
        document.documentElement.style.setProperty(
          "--primary-color",
          "#6949FF"
        );
        document.documentElement.style.setProperty(
          "--primary-color-light",
          "#A690FF"
        );
        document.documentElement.style.setProperty(
          "--primary-gradient-color-1",
          "#6949FF"
        );
        document.documentElement.style.setProperty(
          "--primary-gradient-color-2",
          "#D821F6"
        );
        document.documentElement.style.setProperty(
          "--secondary-color",
          "#BA49FF"
        );
        document.documentElement.style.setProperty(
          "--identity-border-color",
          "#CFC5FF"
        );
        document.documentElement.style.setProperty(
          "--identity-bg-color",
          "#FEFEFF"
        );
        document.documentElement.style.setProperty(
          "--button-text-color",
          "#FFF"
        );
        return;
      case INSNAME["4K"]:
        // setTheme(THEME_COLOR.BLUE);
        document.documentElement.style.setProperty(
          "--primary-color",
          "#1ec6f6"
        );
        document.documentElement.style.setProperty(
          "--primary-color-light",
          "#90daff"
        );
        document.documentElement.style.setProperty(
          "--primary-gradient-color-1",
          "#1ec6f6"
        );
        document.documentElement.style.setProperty(
          "--primary-gradient-color-2",
          "#1c91e2"
        );
        document.documentElement.style.setProperty(
          "--secondary-color",
          "#4FA9FC"
        );
        document.documentElement.style.setProperty(
          "--identity-border-color",
          "#C5E3FF"
        );
        document.documentElement.style.setProperty(
          "--identity-bg-color",
          "#F0FFFB"
        );
        document.documentElement.style.setProperty(
          "--button-text-color",
          "#FFF"
        );
        return;
      case INSNAME.DM:
        // setTheme(THEME_COLOR.YELLOW);
        document.documentElement.style.setProperty(
          "--primary-color",
          "#f3d07e"
        );
        document.documentElement.style.setProperty(
          "--primary-color-light",
          "#F3E3CD"
        );
        document.documentElement.style.setProperty(
          "--primary-gradient-color-1",
          "#f3d07e"
        );
        document.documentElement.style.setProperty(
          "--primary-gradient-color-2",
          "#f0e6be"
        );
        document.documentElement.style.setProperty(
          "--secondary-color",
          "#F88012"
        );
        document.documentElement.style.setProperty(
          "--identity-border-color",
          "#FFFFF0"
        );
        document.documentElement.style.setProperty(
          "--identity-bg-color",
          "#FEE68F"
        );
        document.documentElement.style.setProperty(
          "--button-text-color",
          "#2f2f2f"
        );
        return;
      default:
        // setTheme(THEME_COLOR.GREEN);
        return;
    }
  }, []);

  const value = { configList, asList };
  return (
    <ConfigContext.Provider value={value}>{children}</ConfigContext.Provider>
  );
};

export const useConfig = () => {
  return useContext(ConfigContext) as ConfigContextType;
};
