import React, { createContext, useContext, useState, useCallback, useEffect, useMemo } from 'react';
import { useNavigate } from 'react-router';
import { useAuth } from '@/hooks/auth/useAuth';
import AuthDialogManager from '@/components/AuthDialogManager';

interface AuthDialogContextType {
  showLogin: () => void;
  showSignUp: () => void;
  closeDialog: () => void;
  isDialogOpen: boolean;
}

const AuthDialogContext = createContext<AuthDialogContextType | undefined>(undefined);

export const useAuthDialog = () => {
  const context = useContext(AuthDialogContext);
  if (context === undefined) {
    // During HMR/Fast Refresh, context might temporarily be undefined
    // In development, log a warning but return a no-op implementation
    if (import.meta.env.DEV) {
      console.warn('useAuthDialog: Context is undefined, returning no-op implementation');
      return {
        showLogin: () => {},
        showSignUp: () => {},
        closeDialog: () => {},
        isDialogOpen: false,
      };
    }
    throw new Error('useAuthDialog must be used within an AuthDialogProvider');
  }
  return context;
};

interface AuthDialogProviderProps {
  children: React.ReactNode;
}

export const AuthDialogProvider: React.FC<AuthDialogProviderProps> = ({ children }) => {
  const [currentDialog, setCurrentDialog] = useState<"signIn" | "signUp" | null>(null);
  const navigate = useNavigate();
  const { isAuthenticated } = useAuth();

  // Handle post-login redirect
  useEffect(() => {
    if (isAuthenticated) {
      const intendedRoute = localStorage.getItem('intendedRoute');
      
      // Always close the dialog when user becomes authenticated
      setCurrentDialog(null);
      
      if (intendedRoute && intendedRoute !== window.location.pathname) {
        // Validate that the intended route is safe and valid
        const isValidRoute = intendedRoute.startsWith('/') && !intendedRoute.includes('..') && intendedRoute.length < 200;
        
        // Clean up the stored route
        localStorage.removeItem('intendedRoute');
        
        // Navigate to the intended route if valid
        if (isValidRoute) {
          navigate(intendedRoute);
        }
      }
    }
  }, [isAuthenticated, navigate]);

  // Cleanup stored route only on component unmount
  useEffect(() => {
    return () => {
      // Only cleanup on component unmount, not on dialog state changes
      localStorage.removeItem('intendedRoute');
    };
  }, []);

  const showLogin = useCallback(() => {
    setCurrentDialog("signIn");
  }, []);

  const showSignUp = useCallback(() => {
    setCurrentDialog("signUp");
  }, []);

  const closeDialog = useCallback(() => {
    setCurrentDialog(null);
    // If user manually closes dialog without authenticating, clean up the stored route
    if (!isAuthenticated) {
      localStorage.removeItem('intendedRoute');
    }
  }, [isAuthenticated]);

  const contextValue = useMemo<AuthDialogContextType>(() => ({
    showLogin,
    showSignUp,
    closeDialog,
    isDialogOpen: currentDialog !== null,
  }), [showLogin, showSignUp, closeDialog, currentDialog]);

  return (
    <AuthDialogContext.Provider value={contextValue}>
      {children}
      {/* Global AuthDialogManager - no trigger buttons needed for programmatic use */}
      <AuthDialogManager
        initialDialog={currentDialog}
        onClose={closeDialog}
        showTrigger={false}
      />
    </AuthDialogContext.Provider>
  );
};