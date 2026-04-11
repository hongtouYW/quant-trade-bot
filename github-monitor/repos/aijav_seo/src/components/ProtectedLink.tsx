import React from "react";
import { Link } from "react-router";
import { useAuth } from "@/hooks/auth/useAuth";
import { useAuthDialog } from "@/contexts/AuthDialogContext";

interface ProtectedLinkProps {
  to: string;
  children: React.ReactNode;
  className?: string;
  onClick?: () => void;
}

export const ProtectedLink: React.FC<ProtectedLinkProps> = ({
  to,
  children,
  className,
  onClick,
}) => {
  const { isAuthenticated } = useAuth();
  const { showLogin } = useAuthDialog();

  const handleClick = (e: React.MouseEvent) => {
    if (!isAuthenticated) {
      e.preventDefault(); // Prevent navigation

      // Store the intended destination for post-login redirect
      localStorage.setItem("intendedRoute", to);
      showLogin();
    } else {
      // User is authenticated, allow normal navigation
      onClick?.();
    }
  };

  return (
    <Link to={to} className={className} onClick={handleClick}>
      {children}
    </Link>
  );
};
