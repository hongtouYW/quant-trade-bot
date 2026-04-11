import {
  Avatar,
  AvatarFallback,
  AvatarImage,
} from "@/components/ui/avatar.tsx";
import { Heart, History, UserRoundCheck, UserRoundIcon } from "lucide-react";
import vipBadge from "@/assets/vip-badge.svg";
import userAvatar from "@/assets/user-avatar.png";
import CollectionCardItem from "@/components/collection-card-item.tsx";
import { useTranslation } from "react-i18next";
import { useNavigate } from "react-router";
import { useUserInfo } from "@/hooks/user/useUserInfo.ts";
import { useMyFollowingCount } from "@/hooks/user/useMyFollowingCount.ts";
import { useMyCollectedVideos } from "@/hooks/video/useMyCollectedVideos.ts";
import { usePlayLog } from "@/hooks/video/usePlayLog.ts";
import { RecommendedHorizontalList } from "@/components/recommended-horizontal-list.tsx";

export default function MyChannels() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { data: userInfo, isLoading, isError } = useUserInfo();

  // Fetch dynamic counts
  const { data: collectedVideos } = useMyCollectedVideos();
  const { data: playLogData } = usePlayLog();
  const { data: followingCountData } = useMyFollowingCount();

  // Loading state
  if (isLoading) {
    return <div>{t("my_channels.loading")}</div>;
  }

  // Error state
  if (isError || !userInfo) {
    return <div>{t("my_channels.error_loading_user")}</div>;
  }

  const channelData = {
    name: userInfo.username,
    title: "S1 NO.1 STYLE",
    followerCount: 7000,
    videoCount: 4000,
    statusCount: 10,
    avatar: userAvatar,
    banner: "/path/to/banner.jpg", // Replace with actual banner
    isVip: userInfo.is_vip === 1,
    isFollowing: false,
  };

  // Calculate dynamic counts
  const favoriteCount = collectedVideos?.data?.data?.length || 0;
  const watchHistoryCount = playLogData?.data?.data?.length || 0;
  const followingCount = followingCountData?.totalCount ?? 0;

  // Navigation handlers
  const handleNavigateToFavorites = () => {
    navigate("/my-favorites");
  };

  const handleNavigateToFollowing = () => {
    navigate("/following");
  };

  const handleNavigateToWatchHistory = () => {
    navigate("/watch-history");
  };

  const collections = [
    {
      title: t("my_channels.favorite_videos"),
      count: favoriteCount,
      icon: <Heart className="size-8 md:size-16" fill="currentColor" />,
      bgColor: "bg-[#E126FC]",
      onClick: handleNavigateToFavorites,
    },
    {
      title: t("my_channels.my_following"),
      count: followingCount,
      countSuffix: t("my_channels.following_count_suffix"),
      icon: <UserRoundCheck className="size-8 md:size-16" />,
      onClick: handleNavigateToFollowing,
    },
    {
      title: t("my_channels.watch_history"),
      count: watchHistoryCount,
      icon: <History className="size-8 md:size-16" />,
      bgColor: "bg-[#E126FC]",
      onClick: handleNavigateToWatchHistory,
    },
  ];

  return (
    <>
      <div className="p-5 md:p-0">
        {/* Banner */}
        {/*<div className="h-[100px] md:h-[180px] rounded-xl md:rounded-t-none md:rounded-b-2xl overflow-hidden bg-gray-300"></div>*/}

        {/* Profile Section */}
        <div className="px-4 md:my-6 flex items-start gap-4 flex-nowrap flex-row py-6 pt-0 border-b border-gray-200">
          <Avatar className="ring-white  rounded-full ring-6 size-[90px] md:size-[150px]">
            <AvatarImage src={channelData.avatar} alt={channelData.name} />
            <AvatarFallback>
              <UserRoundIcon
                size={16}
                className="opacity-60"
                aria-hidden="true"
              />
            </AvatarFallback>
          </Avatar>

          <div className="flex items-start gap-4 w-full flex-wrap flex-col md:flex-row">
            <div className="flex flex-col gap-2 justify-between">
              <div className="flex items-center pt-2 md:pt-0 gap-2">
                <p className="font-bold text-xl md:text-3xl leading-none">
                  {channelData.name}
                </p>
                {userInfo.is_vip === 1 && (
                  <img src={vipBadge} alt="VIP Badge" className="h-8 w-auto" />
                )}
              </div>
              {/*<span className="text-[#757575] text-sm font-medium">*/}
              {/*  {t("my_channels.follower_count", { count: channelData.followerCount })}*/}
              {/*</span>*/}
            </div>

            {/*<div className="flex flex-col gap-4 text-end flex-1">*/}
            {/*  <div className="flex flex-col gap-2 md:items-end">*/}
            {/*    <div className="space-x-2">*/}
            {/*      <Button*/}
            {/*        className={cn("rounded-full has-[>svg]:px-4")}*/}
            {/*        size="sm"*/}
            {/*        onClick={handleFollow}*/}
            {/*      >*/}
            {/*        <UserPlus />*/}
            {/*        {channelData.isFollowing ? t("common.followed") : t("common.follow")}*/}
            {/*      </Button>*/}
            {/*      <Button*/}
            {/*        className="rounded-full bg-[#F4A8FF]/20 text-[#EC67FF] border-[#EC67FF] hover:text-[#EC67FF] has-[>svg]:px-4"*/}
            {/*        variant="outline"*/}
            {/*        size="sm"*/}
            {/*        onClick={handleShare}*/}
            {/*      >*/}
            {/*        <Share2 /> {t("my_channels.share_channel")}*/}
            {/*      </Button>*/}
            {/*    </div>*/}
            {/*    <div className="relative">*/}
            {/*      <Button*/}
            {/*        className="rounded-full bg-[#F4A8FF]/20 text-[#EC67FF] border-[#EC67FF] hover:text-[#EC67FF] has-[>svg]:px-4"*/}
            {/*        variant="outline"*/}
            {/*        size="sm"*/}
            {/*        onClick={handleEdit}*/}
            {/*      >*/}
            {/*        <Plus /> {t("my_channels.edit_series")}*/}
            {/*      </Button>*/}
            {/*      <img*/}
            {/*        src={inDevelopment}*/}
            {/*        alt="In Development"*/}
            {/*        className="absolute -top-2 -right-2 h-6 w-auto"*/}
            {/*      />*/}
            {/*    </div>*/}
            {/*  </div>*/}
            {/*</div>*/}
          </div>
        </div>

        {/* Collections Section */}
        <div className="pt-5 px-0 md:px-5">
          <div className="grid grid-cols-3 sm:flex sm:gap-4 gap-3">
            {collections.map((collection, index) => (
              <CollectionCardItem
                key={index}
                title={collection.title}
                count={collection.count}
                countSuffix={collection.countSuffix}
                icon={collection.icon}
                bgColor={collection.bgColor}
                onClick={collection.onClick}
                className="w-full aspect-square"
              />
            ))}
          </div>
        </div>

        <div className="my-5 px-0 md:px-4">
          <RecommendedHorizontalList />
        </div>
      </div>
    </>
  );
}
