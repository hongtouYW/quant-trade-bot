import { useTranslation } from "react-i18next";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import forgotPasswordBg from "@/assets/forgot-password-bg.png";
import { useGlobalImageWithFallback } from "@/hooks/useGlobalImageWithFallback";
import { Headset } from "lucide-react";
import { useConfigContext } from "@/contexts/ConfigContext";

interface ForgotPasswordDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export default function ForgotPasswordDialog({
  open,
  onOpenChange,
}: ForgotPasswordDialogProps) {
  const { t } = useTranslation();
  const { configList } = useConfigContext();
  const backgroundImage = useGlobalImageWithFallback({
    imageKey: "forgotPasswordBg",
    fallbackImage: forgotPasswordBg
  });

  const handleContactSupport = () => {
    if (configList?.contact_us) {
      window.open(
        configList.contact_us,
        "_blank",
        "noopener,noreferrer",
      );
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="p-0">
        <img
          className="w-full object-cover max-h-[220px]"
          src={backgroundImage}
          alt="Forgot Password Background"
          width={500}
          height={252}
        />
        <div className="flex flex-col gap-4 w-full px-8 pb-8 pt-4">
          <DialogHeader className="text-left">
            <DialogTitle className="text-3xl">{t("auth.forgot_password")}</DialogTitle>
            <DialogDescription className="mt-2 text-base text-[#969696]">
              {t("auth.contact_support")}
            </DialogDescription>
          </DialogHeader>

          <Button
            variant="default"
            size="lg"
            className="w-full bg-[#EEEEEE] text-black hover:bg-[#EEEEEE]/80"
            onClick={handleContactSupport}
          >
            <Headset />
            {t("auth.contact_support")}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}
