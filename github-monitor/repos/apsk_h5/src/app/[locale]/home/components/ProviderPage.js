import React, { memo } from "react";
import Image from "next/image";
import { IMAGES } from "@/constants/images";
import GameTile from "@/components/shared/GameTile";
import { isFullHttpUrl } from "@/utils/utility";
import SharedLoading from "@/components/shared/LoadingBar/ShareLoading";

const ProviderPage = memo(function ProviderPage({
  t,
  isProviderLoading,
  isProviderFetching,
  visibleGames,
  onSelectProvider,
  activePid,
}) {
  if ((isProviderLoading || isProviderFetching) && visibleGames.length === 0) {
    return <SharedLoading />;
  }

  if (
    !isProviderLoading &&
    !isProviderFetching &&
    (!visibleGames || visibleGames.length === 0)
  ) {
    return (
      <div className="flex flex-col items-center justify-center py-12 text-white/70">
        <Image
          src={IMAGES.empty}
          alt="empty"
          width={120}
          height={120}
          className="mb-3 object-contain"
        />
        <p className="text-sm">{t("common.noRecords")}</p>
      </div>
    );
  }

  return (
    <div
      className="overflow-x-auto no-scrollbar px-0 py-2 pb-[calc(env(safe-area-inset-bottom)+110px)]"
      style={{ WebkitOverflowScrolling: "touch" }}
    >
      <div className="grid w-full grid-cols-4 gap-1">
        {visibleGames.map((g) => (
          <div
            className="w-full"
            key={`provider-${g.provider_id}`}
            data-id={g.provider_id}
          >
            <GameTile
              type="P"
              isBookMark={false}
              src={
                g.icon
                  ? g.icon === "assets/img/game/WL.webp"
                    ? IMAGES.home.rightbar.provider
                    : isFullHttpUrl(g.icon)
                      ? g.icon
                      : `${process.env.NEXT_PUBLIC_IMAGE_URL}/${g.icon}`
                  : IMAGES.home.rightbar.provider
              }
              title={""}
              alt={g.provider_name}
              // title={g.provider_name}
              selected={activePid != null && activePid === g.provider_id}
              onClick={() => onSelectProvider(g)}
            />
          </div>
        ))}
      </div>
      <div className="flex min-h-[150px] items-center justify-center pb-[env(safe-area-inset-bottom)]">
        <p className="text-center text-[10px] text-white/55">
          {t("common.noMore")}
        </p>
      </div>
    </div>
  );
});

export default ProviderPage;
