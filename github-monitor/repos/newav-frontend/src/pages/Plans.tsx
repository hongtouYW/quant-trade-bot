import { useState } from "react";
import { useTranslation } from "react-i18next";
import PlanCard from "@/components/plan-card";
import PlanStackedAccordion from "@/components/PlanStackedAccordion";
import vipCard from "@/assets/vip-card.png";
import pointsCard from "@/assets/points-card.png";
import diamondCard from "@/assets/diamond-card.svg";
import { useGlobalVip } from "@/hooks/plan/useGlobalVip.ts";
import { InlineError } from "@/components/error-states";
import { useUser } from "@/contexts/UserContext.tsx";
import { formattedVipTime } from "@/utils/common.ts";
import PaymentMethodDialog from "@/components/PaymentMethodDialog";
import { useAuthAction } from "@/hooks/auth/useAuthAction";
import { useGlobalImageWithFallback } from "@/hooks/useGlobalImageWithFallback";
import { useLocation } from "react-router";
import type { PlanStackedSectionId } from "@/constants/planSections.ts";

export default function Plans() {
  const { t } = useTranslation();
  const location = useLocation();
  const { data, isPending, isError, refetch } = useGlobalVip();
  const { user } = useUser();
  const { executeWithAuth } = useAuthAction();
  const vipBackgroundImage = useGlobalImageWithFallback({
    imageKey: "vipBg",
    fallbackImage: vipCard,
  });
  const vipMobileImage = useGlobalImageWithFallback({
    imageKey: "vipBg",
    fallbackImage: vipCard,
    useMobileVersion: true,
  });
  const diamondBackgroundImage = useGlobalImageWithFallback({
    imageKey: "diamondBg",
    fallbackImage: diamondCard,
  });
  const diamondMobileImage = useGlobalImageWithFallback({
    imageKey: "diamondBg",
    fallbackImage: diamondCard,
    useMobileVersion: true,
  });
  const pointsBackgroundImage = useGlobalImageWithFallback({
    imageKey: "voucherBg",
    fallbackImage: pointsCard,
  });
  const pointsMobileImage = useGlobalImageWithFallback({
    imageKey: "voucherBg",
    fallbackImage: pointsCard,
    useMobileVersion: true,
  });
  const [paymentDialogOpen, setPaymentDialogOpen] = useState(false);
  const [selectedPackage, setSelectedPackage] = useState<
    | {
        id: number;
        name: string;
        price: string;
        type: 1 | 2 | 3; // 1=vip, 2=diamond, 3=point
      }
    | undefined
  >(undefined);
  const locationState = location.state as
    | { focusPlanId?: PlanStackedSectionId | null }
    | null
    | undefined;
  const focusPlanId = locationState?.focusPlanId;

  const handleVipPackageClick = (pkg: any) => {
    executeWithAuth(() => {
      setSelectedPackage({
        id: pkg.id,
        name: pkg.name,
        price: pkg.price,
        type: 1, // 1=vip
      });
      setPaymentDialogOpen(true);
    });
  };

  const handlePointPackageClick = (pkg: any) => {
    executeWithAuth(() => {
      setSelectedPackage({
        id: pkg.id,
        name: pkg.name,
        price: pkg.price,
        type: 2, // 2=point
      });
      setPaymentDialogOpen(true);
    });
  };

  const handleDiamondPackageClick = (pkg: any) => {
    executeWithAuth(() => {
      setSelectedPackage({
        id: pkg.id,
        name: pkg.name,
        price: pkg.price,
        type: 3, // 3=diamond
      });
      setPaymentDialogOpen(true);
    });
  };

  if (isPending) {
    return (
      <div className="">
        <header className="border-b px-4">
          <div className="flex h-14 items-center justify-between gap-4">
            <div className="flex flex-1 items-center gap-2 text-base font-bold">
              <span className="">{t("plans.current_vip_level")}</span>
            </div>
          </div>
        </header>
        <div className="p-4 w-full overflow-hidden">
          <div className="space-y-4 w-full">
            {/* Loading Skeleton */}
            {Array.from({ length: 3 }).map((_, index) => (
              <div
                key={index}
                className="bg-white dark:bg-[#1E0223] border min-h-[280px] md:h-[316px] shadow-lg opacity-100 gap-2.5 flex flex-col rounded-2xl p-4"
              >
                {/* Header Section */}
                <div>
                  <div className="flex items-center gap-2 mb-2">
                    <div className="w-[3px] h-5 rounded-full bg-accent animate-pulse"></div>
                    <div className="h-4 bg-accent animate-pulse rounded w-32"></div>
                  </div>
                  <div className="w-full h-[0.5px] bg-accent animate-pulse"></div>
                </div>

                {/* Content Section */}
                <div className="flex flex-col md:flex-row h-full gap-3">
                  {/* Image Section */}
                  <div className="shrink-0 relative">
                    <div className="w-full h-full object-contain aspect-video bg-accent animate-pulse rounded"></div>
                    {/* Status text overlay skeleton */}
                    <div className="absolute left-2 top-3/5 -translate-y-1/2">
                      <div className="bg-accent animate-pulse rounded px-3 py-1.5 w-24 h-8"></div>
                      <div className="bg-accent animate-pulse rounded px-3 py-1 w-20 h-6 mt-1"></div>
                    </div>
                  </div>

                  {/* Package Options Section */}
                  <div className="flex flex-col flex-1 min-w-0 overflow-hidden">
                    <div className="grid grid-cols-2 md:flex md:gap-3 md:overflow-x-auto scrollbar-thin h-full gap-2">
                      {Array.from({ length: 2 }).map((_, pkgIndex) => (
                        <div
                          key={pkgIndex}
                          className="bg-accent animate-pulse w-full md:w-[220px] flex-shrink-0 rounded-3xl h-full"
                        ></div>
                      ))}
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  if (isError) {
    return (
      <div className="">
        <header className="border-b px-4">
          <div className="flex h-14 items-center justify-between gap-4">
            <div className="flex flex-1 items-center gap-2 text-base font-bold">
              <span className="">{t("plans.current_vip_level")}</span>
            </div>
          </div>
        </header>
        <div className="p-4 w-full overflow-hidden">
          <InlineError onRetry={() => refetch()} />
        </div>
      </div>
    );
  }

  // Prepare plan data for stacked accordion (mobile)
  const stackedPlans = [];
  if (data?.vip && data.vip.length > 0) {
    stackedPlans.push({
      id: 1,
      title: t("plans.exchange_vip"),
      image: vipMobileImage,
      compactImage: "/compact_vip.svg",
      compactStatusText: (
        <div className="text-[#674617] py-1.5 text-sm font-light">
          <div>{t("plans.total_count", { count: data.vip.length })}</div>
        </div>
      ),
      statusText: user?.vip_end_time ? (
        <div className="text-[#674617] px-3 py-1.5 text-sm">
          {t("plans.vip_until", {
            date: formattedVipTime(user?.vip_end_time, "yyyy.MM.dd"),
          })}
        </div>
      ) : undefined,
      packages: data.vip
        .sort((a, b) => Number(a.money) - Number(b.money))
        .map((vipPlan) => ({
          ...vipPlan,
          name: vipPlan.title,
          price: vipPlan.money.toString(),
        })),
      onPackageClick: handleVipPackageClick,
    });
  }

  if (data?.point && data.point.length > 0) {
    stackedPlans.push({
      id: 2,
      title: t("plans.exchange_points"),
      image: pointsMobileImage,
      compactImage: "/compact_point.svg",
      compactStatusText: (
        <div className="text-[#463576] py-1.5 text-sm font-light">
          <div>{t("plans.total_count", { count: data.point.length })}</div>
        </div>
      ),
      statusText: (
        <div className="text-[#463576] px-3 py-1.5 text-sm">
          <div>{t("plans.current_total")}</div>
          <div>{t("plans.point_count", { count: user?.point })}</div>
        </div>
      ),
      packages: data.point
        .sort((a, b) => Number(a.money) - Number(b.money))
        .map((pointPlan) => ({
          ...pointPlan,
          name: pointPlan.title,
          price: pointPlan.money.toString(),
        })),
      onPackageClick: handlePointPackageClick,
    });
  }

  if (data?.coin && data.coin.length > 0) {
    stackedPlans.push({
      id: 3,
      title: t("plans.exchange_diamonds"),
      image: diamondMobileImage,
      compactImage: "/compact_diamond.svg",
      compactStatusText: (
        <div className="text-[#CF3B6F] py-1.5 text-sm font-light">
          <div>{t("plans.total_count", { count: data.coin.length })}</div>
        </div>
      ),
      statusText: user?.coin ? (
        <div className="text-[#CF3B6F] px-3 py-1.5 text-sm">
          <div>{t("plans.current_total")}</div>
          <div>{t("plans.diamond_count", { count: user?.coin })}</div>
        </div>
      ) : undefined,
      packages: data.coin
        .sort((a, b) => Number(a.money) - Number(b.money))
        .map((coinPlan) => ({
          ...coinPlan,
          name: coinPlan.title,
          price: coinPlan.money.toString(),
        })),
      onPackageClick: handleDiamondPackageClick,
    });
  }

  return (
    <div className="">
      <header className="border-b px-4">
        <div className="flex h-14 items-center justify-between gap-4">
          <div className="flex flex-1 items-center gap-2 text-base font-bold">
            <span className="">{t("plans.current_vip_level")}</span>
          </div>
        </div>
      </header>

      {/* Mobile View - Stacked Accordion */}
      <div className="md:hidden">
        <PlanStackedAccordion
          plans={stackedPlans}
          initialActivePlanId={focusPlanId}
        />
      </div>

      {/* Desktop View - Original Layout */}
      <div className="hidden md:block p-4 w-full overflow-hidden">
        <div className="space-y-4 w-full">
          {/* VIP Plans */}
          {data?.vip && data.vip.length > 0 && (
            <PlanCard
              title={t("plans.exchange_vip")}
              image={vipBackgroundImage}
              statusText={
                user?.vip_end_time ? (
                  <div className="px-3 py-1.5 text-xl text-[#674617]">
                    {t("plans.vip_until", {
                      date: formattedVipTime(user?.vip_end_time, "yyyy.MM.dd"),
                    })}
                  </div>
                ) : null
              }
              packages={data.vip
                .sort((a, b) => Number(a.money) - Number(b.money))
                .map((vipPlan) => ({
                  ...vipPlan,
                  name: vipPlan.title,
                  price: vipPlan.money.toString(),
                }))}
              onPackageClick={handleVipPackageClick}
            />
          )}

          {/* Points Plans */}
          {data?.point && data.point.length > 0 && (
            <PlanCard
              title={t("plans.exchange_points")}
              image={pointsBackgroundImage}
              statusText={
                user?.point ? (
                  <div className="px-3 py-1.5 text-xl text-[#463576]">
                    <div>{t("plans.current_total")}</div>
                    <div>{t("plans.point_count", { count: user?.point })}</div>
                  </div>
                ) : null
              }
              packages={data.point
                .sort((a, b) => Number(a.money) - Number(b.money))
                .map((pointPlan) => ({
                  ...pointPlan,
                  name: pointPlan.title,
                  price: pointPlan.money.toString(),
                }))}
              onPackageClick={handlePointPackageClick}
            />
          )}

          {/* Diamond Plans (from coin array) */}
          {data?.coin && data.coin.length > 0 && (
            <PlanCard
              title={t("plans.exchange_diamonds")}
              image={diamondBackgroundImage}
              statusText={
                user?.coin ? (
                  <div className="px-3 py-1.5 text-xl text-[#CF3B6F]">
                    <div>{t("plans.current_total")}</div>
                    <div>{t("plans.diamond_count", { count: user?.coin })}</div>
                  </div>
                ) : null
              }
              packages={data.coin
                .sort((a, b) => Number(a.money) - Number(b.money))
                .map((coinPlan) => ({
                  ...coinPlan,
                  name: coinPlan.title,
                  price: coinPlan.money.toString(),
                }))}
              onPackageClick={handleDiamondPackageClick}
            />
          )}
        </div>
      </div>

      <PaymentMethodDialog
        open={paymentDialogOpen}
        onOpenChange={setPaymentDialogOpen}
        packageData={selectedPackage}
      />
    </div>
  );
}
