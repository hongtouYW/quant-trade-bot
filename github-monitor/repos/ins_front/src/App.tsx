import { RouterProvider } from "react-router-dom";
import { greenRouter, router } from "./router";

import { THEME_COLOR } from "./utils/constant";
import u from "./utils/utils";

function App() {
  const siteType = u.siteType();
  return (
    <RouterProvider
      router={!siteType.theme || siteType.theme === THEME_COLOR.GREEN ? greenRouter : router}
    />
  );
}

export default App;
