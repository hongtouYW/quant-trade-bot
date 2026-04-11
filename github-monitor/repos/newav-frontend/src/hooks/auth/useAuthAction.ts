import { useUser } from "@/contexts/UserContext";
import { useAuthDialog } from "@/contexts/AuthDialogContext";

interface AuthActionOptions {
  onLoginRequired?: () => void;
  requireAuth?: boolean;
}

export const useAuthAction = () => {
  const { user } = useUser();
  const { showLogin } = useAuthDialog();
  
  const executeWithAuth = (
    action: () => void, 
    options?: AuthActionOptions
  ) => {
    // If auth is explicitly not required, execute directly
    if (options?.requireAuth === false) {
      action();
      return;
    }
    
    // Check if user is authenticated
    if (!user) {
      // Use custom login handler or default to showing global login dialog
      if (options?.onLoginRequired) {
        options.onLoginRequired();
      } else {
        showLogin();
      }
      return;
    }
    
    // User is authenticated, execute the action
    action();
  };

  return {
    executeWithAuth,
    isAuthenticated: !!user,
    user
  };
};