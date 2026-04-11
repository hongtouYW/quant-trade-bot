import { useState, useEffect } from "react";
import SignIn from "./sign-in";
import SignUp from "./sign-up";

interface AuthDialogManagerProps {
  initialDialog?: "signIn" | "signUp" | null;
  onClose?: () => void;
  showTrigger?: boolean;
}

export default function AuthDialogManager({
  initialDialog = null,
  onClose,
  showTrigger = false,
}: AuthDialogManagerProps) {
  const [openDialog, setOpenDialog] = useState<"signIn" | "signUp" | null>(
    initialDialog,
  );

  useEffect(() => {
    setOpenDialog(initialDialog);
  }, [initialDialog]);

  const handleDialogClose = (isOpen: boolean) => {
    if (!isOpen) {
      setOpenDialog(null);
      onClose?.();
    }
  };

  return (
    <>
      <SignIn
        open={openDialog === "signIn"}
        onOpenChange={(open: boolean) => {
          if (open) {
            setOpenDialog("signIn");
          } else {
            handleDialogClose(false);
          }
        }}
        onSwitchToSignUp={() => setOpenDialog("signUp")}
        showTrigger={showTrigger}
      />
      <SignUp
        open={openDialog === "signUp"}
        onOpenChange={(open: boolean) => {
          if (open) {
            setOpenDialog("signUp");
          } else {
            handleDialogClose(false);
          }
        }}
        onSwitchToSignIn={() => setOpenDialog("signIn")}
      />
    </>
  );
}
