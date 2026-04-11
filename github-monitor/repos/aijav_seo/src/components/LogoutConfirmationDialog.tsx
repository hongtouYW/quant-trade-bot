import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { useTranslation } from "react-i18next";
import signOutBg from "@/assets/log-out-bg.png";
import { useGlobalImageWithFallback } from "@/hooks/useGlobalImageWithFallback";

interface LogoutConfirmationDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onConfirm: () => void;
}

export function LogoutConfirmationDialog({
  open,
  onOpenChange,
  onConfirm,
}: LogoutConfirmationDialogProps) {
  const { t } = useTranslation();
  const backgroundImage = useGlobalImageWithFallback({
    imageKey: "logoutBg",
    fallbackImage: signOutBg,
  });

  const handleConfirm = () => {
    onConfirm();
    onOpenChange(false);
  };

  const handleCancel = () => {
    onOpenChange(false);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="p-0 w-[300px] md:w-full" forceMount>
        <img
          className="w-full object-cover max-h-[220px]"
          src={backgroundImage}
          alt="Sign Out Background"
          width={500}
          height={252}
        />
        <div className="flex flex-col gap-4 w-full px-8 pb-8 pt-4">
          <DialogHeader>
            <DialogTitle className="text-center">
              {t("sidebar.logout_confirmation_title")}
            </DialogTitle>
          </DialogHeader>
          <div className="pb-4 flex gap-4 flex-col">
            <p className="text-center text-[#969696]">
              {t("sidebar.logout_confirmation_subtitle")}
            </p>
            <p className="text-center font-semibold">
              <span>{t("sidebar.logout_confirmation_promo")}</span>
              {/*<span className="text-[#969696] mx-1">*/}
              {/*  {t("sidebar.logout_confirmation_and")}*/}
              {/*</span>*/}
              {/*<span>{t("sidebar.logout_confirmation_messages")}</span>*/}
            </p>
          </div>
          <DialogFooter className="grid grid-cols-2 gap-2 sm:justify-center">
            <Button
              variant="outline"
              onClick={handleCancel}
              className="flex-1 rounded-full"
            >
              {t("sidebar.logout_cancel")}
            </Button>
            <Button onClick={handleConfirm} className="flex-1 rounded-full">
              {t("sidebar.logout_confirm")}
            </Button>
          </DialogFooter>
        </div>
      </DialogContent>
    </Dialog>
  );
}
