import { createContext, useContext, useState } from 'react';
import type { ReactNode } from 'react';

interface IdentityCardContextType {
  isOpen: boolean;
  openIdentityCard: () => void;
  closeIdentityCard: () => void;
}

const IdentityCardContext = createContext<IdentityCardContextType | undefined>(undefined);

export function IdentityCardProvider({ children }: { children: ReactNode }) {
  const [isOpen, setIsOpen] = useState(false);

  const openIdentityCard = () => {
    setIsOpen(true);
  };
  const closeIdentityCard = () => {
    setIsOpen(false);
  };

  return (
    <IdentityCardContext.Provider
      value={{
        isOpen,
        openIdentityCard,
        closeIdentityCard,
      }}
    >
      {children}
    </IdentityCardContext.Provider>
  );
}

export function useIdentityCard() {
  const context = useContext(IdentityCardContext);
  if (context === undefined) {
    throw new Error('useIdentityCard must be used within an IdentityCardProvider');
  }
  return context;
}

export default IdentityCardProvider;