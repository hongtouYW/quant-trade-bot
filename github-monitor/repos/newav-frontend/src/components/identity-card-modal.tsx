import { useTranslation } from "react-i18next";
import { useIdentityCard } from "@/contexts/IdentityCardContext";
import { useUser } from "@/contexts/UserContext";
import { cn } from "@/lib/utils";
import { QRCode } from "react-qrcode-logo";
import { Dialog, DialogContent } from "@/components/ui/dialog";
import { X } from "lucide-react";

export function IdentityCardModal() {
  const { t } = useTranslation();
  const { isOpen, closeIdentityCard } = useIdentityCard();
  const { user } = useUser();

  return (
    <Dialog open={isOpen} onOpenChange={closeIdentityCard}>
      <DialogContent
        className={cn(
          "p-0 border-0 bg-transparent shadow-none",
          "max-w-[320px] w-[320px] [&>button]:hidden",
        )}
      >
        {/* Main Card Container */}
        <div
          className={cn(
            "relative w-[320px] h-[650px] rounded-[20px] overflow-hidden",
            "bg-cover bg-center bg-no-repeat",
            "shadow-2xl",
          )}
          style={{ background: "var(--color-brand-gradient)" }}
        >
          {/* Header Section with Close Button */}
          <div className="relative pl-2 pr-6 pt-2 pb-2">
            <button
              onClick={() => closeIdentityCard()}
              className={cn(
                "absolute top-6 right-6",
                "text-gray-500 transition-colors duration-200",
                "flex items-center justify-center w-6 h-6 rounded-full",
                "hover:bg-white/10",
              )}
              aria-label="Close identity card"
            >
              <X />
            </button>
          </div>

          {/* Content Card */}
          <div className="mx-3 sm:mx-4 space-y-2">
            {/* URL Section with Gradient Border */}
            <div
              className="rounded-2xl p-px"
              style={{ background: "var(--color-brand-gradient)" }}
            >
              <div className="bg-white rounded-[calc(1rem-1px)] p-2 space-y-2">
                <div className="rounded-xl px-3">
                  <div className="space-y-1">
                    <h2
                      className={cn(
                        "bg-clip-text text-transparent font-bold mb-3 text-xl tracking-wide",
                        "min-w-0 whitespace-nowrap",
                      )}
                      style={{
                        backgroundImage:
                          "linear-gradient(90deg, #FA7154 0%, #F9176D 49.01%, #F308CF 100.01%)",
                      }}
                    >
                      {t("identity_card.title")}
                    </h2>
                    <p className="text-gray-800 font-semibold text-sm sm:text-base text-center">
                      — {t("identity_card.anti_loss_certificate")} —
                    </p>
                  </div>

                  {/* QR Code Section */}
                  <div className="flex justify-center py-2">
                    <div
                      className={cn(
                        "w-48 h-48 sm:w-48 sm:h-48 border-4 border-[#F308CF] rounded-2xl",
                        "flex items-center justify-center bg-gray-50",
                        "relative p-2",
                      )}
                    >
                      {/* Dynamic QR Code */}
                      <div className="w-full h-full rounded-lg flex items-center justify-center overflow-hidden">
                        <QRCode
                          size={165}
                          value={
                            user?.username && user?.ori_password
                              ? `${window.location.origin}/auth_login?username=${user.username}&password=${user.ori_password}`
                              : ""
                          }
                          logoImage="/logo-only.svg"
                          logoWidth={30}
                          logoHeight={30}
                          removeQrCodeBehindLogo={true}
                          logoPadding={2}
                          logoPaddingStyle="circle"
                        />
                      </div>
                    </div>
                  </div>

                  {/* Credentials & Backup URL Section */}
                  <div className="space-y-2">
                    <div className="grid grid-cols-[minmax(auto,_80px)_1fr] gap-2 items-start">
                      <span className="text-black text-sm font-medium text-right whitespace-nowrap">
                        {t("identity_card.account_label")}:
                      </span>
                      <span
                        className="text-sm font-medium text-transparent bg-clip-text break-all"
                        style={{
                          backgroundImage:
                            "linear-gradient(90deg, #FA7154 0%, #F9176D 49.01%, #F308CF 100.01%)",
                        }}
                      >
                        {user?.username || ""}
                      </span>
                    </div>
                    <div className="grid grid-cols-[minmax(auto,_80px)_1fr] gap-2 items-start">
                      <span className="text-black text-sm font-medium text-right whitespace-nowrap">
                        {t("identity_card.password_label")}:
                      </span>
                      <span
                        className="text-sm font-medium text-transparent bg-clip-text break-all"
                        style={{
                          backgroundImage:
                            "linear-gradient(90deg, #FA7154 0%, #F9176D 49.01%, #F308CF 100.01%)",
                        }}
                      >
                        {user?.ori_password || ""}
                      </span>
                    </div>
                    <div className="grid grid-cols-[minmax(auto,_80px)_1fr] gap-2 items-start">
                      <span className="text-black text-sm font-medium text-right whitespace-nowrap">
                        {t("identity_card.backup_address")}:
                      </span>
                      <span
                        className="text-sm font-medium text-transparent bg-clip-text break-all"
                        style={{
                          backgroundImage:
                            "linear-gradient(90deg, #FA7154 0%, #F9176D 49.01%, #F308CF 100.01%)",
                        }}
                      >
                        {window.location.origin}
                      </span>
                    </div>
                    <div className="grid grid-cols-[minmax(auto,_80px)_1fr] gap-2 items-start">
                      <span className="text-black text-sm font-medium text-right whitespace-nowrap">
                        {t("identity_card.overseas_address")}:
                      </span>
                      <div className="flex flex-col gap-1">
                        <span
                          className="text-sm font-medium text-transparent bg-clip-text"
                          style={{
                            backgroundImage:
                              "linear-gradient(90deg, #FA7154 0%, #F9176D 49.01%, #F308CF 100.01%)",
                          }}
                        >
                          https://aijav.cc/
                        </span>
                        <span
                          className="text-sm font-medium text-transparent bg-clip-text"
                          style={{
                            backgroundImage:
                              "linear-gradient(90deg, #FA7154 0%, #F9176D 49.01%, #F308CF 100.01%)",
                          }}
                        >
                          https://aijav.com/
                        </span>
                        <span
                          className="text-sm font-medium text-transparent bg-clip-text"
                          style={{
                            backgroundImage:
                              "linear-gradient(90deg, #FA7154 0%, #F9176D 49.01%, #F308CF 100.01%)",
                          }}
                        >
                          https://aijav.vip/
                        </span>
                        <span
                          className="text-sm font-medium text-transparent bg-clip-text"
                          style={{
                            backgroundImage:
                              "linear-gradient(90deg, #FA7154 0%, #F9176D 49.01%, #F308CF 100.01%)",
                          }}
                        >
                          https://aijav.top/
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {/* Instructions Section */}
            <div className="space-y-2 sm:space-y-2 text-xs sm:text-sm">
              <div>
                <h3 className="font-bold text-gray-800 mb-1">
                  {t("identity_card.usage_method")}
                </h3>
                <p className="text-gray-700 text-[10px] leading-relaxed">
                  {t("identity_card.usage_instructions")}
                </p>
              </div>

              {/* Warning Section */}
              <div>
                <h3 className="font-bold text-gray-800 mb-1">
                  {t("identity_card.warning_title")}
                </h3>
                <p className="text-gray-700 text-[10px] leading-relaxed mb-2">
                  {t("identity_card.warning_message")}
                </p>
              </div>

              {/* Save Warning */}
              <p className="text-red-500 text-xs font-medium">
                {t("identity_card.save_warning")}
              </p>
            </div>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}

export default IdentityCardModal;
