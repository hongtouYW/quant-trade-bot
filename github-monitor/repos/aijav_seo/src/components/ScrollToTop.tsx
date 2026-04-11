import { useEffect } from "react";
import { useLocation } from "react-router";

export function ScrollToTop() {
  const { pathname } = useLocation();

  useEffect(() => {
    // Scroll window (for mobile)
    window.scrollTo(0, 0);

    // Scroll the SidebarInset container (for desktop)
    // The main element with data-slot="sidebar-inset" is the scrollable container on desktop
    const sidebarInset = document.querySelector('main[data-slot="sidebar-inset"]');
    if (sidebarInset) {
      sidebarInset.scrollTo(0, 0);
    }
  }, [pathname]);

  return null;
}
