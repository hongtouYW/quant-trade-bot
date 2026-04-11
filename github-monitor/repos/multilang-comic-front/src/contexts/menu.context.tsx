import { createContext, useContext, useState } from "react";

interface ReactNodeProps {
  children: React.ReactNode;
}

type MenuContextType = {
  isShowChildrenList: number[];
  setIsShowChildrenList: React.Dispatch<React.SetStateAction<number[]>>;
  prevRoute: string;
  setPrevRoute: React.Dispatch<React.SetStateAction<string>>;
};

const MenuContext = createContext<MenuContextType | null>({
  isShowChildrenList: [],
  setIsShowChildrenList: () => {},
  prevRoute: "",
  setPrevRoute: () => {},
});

export const MenuProvider: React.FC<ReactNodeProps> = ({ children }) => {
  const [isShowChildrenList, setIsShowChildrenList] = useState<number[]>([]);
  const [prevRoute, setPrevRoute] = useState<string>("");

  const value = { isShowChildrenList, setIsShowChildrenList, prevRoute, setPrevRoute };
  return <MenuContext.Provider value={value}>{children}</MenuContext.Provider>;
};

// eslint-disable-next-line react-refresh/only-export-components
export const useMenu = () => {
  return useContext(MenuContext) as MenuContextType;
};
