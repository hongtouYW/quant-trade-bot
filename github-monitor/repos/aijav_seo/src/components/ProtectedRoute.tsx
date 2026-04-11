import React, { useEffect, useRef } from "react";
import { useAuth } from "@/hooks/auth/useAuth";
import { useAuthDialog } from "@/contexts/AuthDialogContext";
import { Navigate, useLocation } from "react-router";

interface ProtectedRouteProps {
  children: React.ReactNode;
  redirectTo?: string;
  showLoginDialog?: boolean;
}

export const ProtectedRoute: React.FC<ProtectedRouteProps> = ({
  children,
  redirectTo = "/",
  showLoginDialog = true,
}) => {
  const { isAuthenticated } = useAuth();
  const { showLogin } = useAuthDialog();
  const location = useLocation();
  const wasAuthenticated = useRef(isAuthenticated);
  const justLoggedOut = useRef(false);

  useEffect(() => {
    // Check if user just logged out (was authenticated, now not)
    if (wasAuthenticated.current && !isAuthenticated) {
      justLoggedOut.current = true;
    } else {
      justLoggedOut.current = false;
    }

    // If user is not authenticated and we want to show login dialog
    if (!isAuthenticated && showLoginDialog && !justLoggedOut.current) {
      // User is trying to access protected route without being authenticated
      // Store the intended destination for post-login redirect
      const intendedRoute = location.pathname + location.search;
      localStorage.setItem("intendedRoute", intendedRoute);
      showLogin();
    }

    // Update the ref for next render
    wasAuthenticated.current = isAuthenticated;
  }, [
    isAuthenticated,
    showLoginDialog,
    showLogin,
    location.pathname,
    location.search,
  ]);

  if (!isAuthenticated) {
    // For logout scenario, redirect to safe page
    // For initial access attempt, stay on current page with dialog
    return <Navigate to={redirectTo} replace />;
  }

  return <>{children}</>;
};
