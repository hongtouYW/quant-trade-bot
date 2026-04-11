import App from "./App";

// Temporary catchall: wraps the existing SPA App so it runs unchanged
// under React Router framework mode. The framework already provides a
// router context (HydratedRouter), so we don't need BrowserRouter here.
export default function Catchall() {
  return <App />;
}
