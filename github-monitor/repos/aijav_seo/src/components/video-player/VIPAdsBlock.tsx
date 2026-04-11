import { Button } from "@/components/ui/button";
import { CircleArrowRight } from "lucide-react";
import { useTranslation } from "react-i18next";
import { useUser } from "@/contexts/UserContext";
import vipIcon from "@/assets/vip-icon.svg";
import { Link } from "react-router";

export function VIPAdsBlock() {
  const { t } = useTranslation();
  const { user } = useUser();

  // Hide the VIP ads block if user is already a VIP member
  if (user?.is_vip === 1) {
    return null;
  }

  return (
    <div className="mb-3 flex justify-between items-center bg-[#252630] rounded-[14px] py-2.5 px-4">
      <div className="flex flex-col gap-1">
        <div className="flex gap-1.5">
          <img loading="lazy" src={vipIcon} alt="vip logo" />
          <span className="text-[#C08B7A] text-base font-medium">AI JAV</span>
        </div>
        <p className="text-xs text-[#AB8449]">
          {t("video_player.vip_member")}｜
          {t("video_player.exclusive_privileges")}
        </p>
      </div>
      <Button
        className="text-[#744C2D] rounded-[20px] bg-gradient-to-r from-[#F0D3BF] to-[#C57B52]"
        asChild
      >
        <Link to="/plans">
          {t("video_player.activate_membership")} <CircleArrowRight />
        </Link>
      </Button>
    </div>
  );
}
