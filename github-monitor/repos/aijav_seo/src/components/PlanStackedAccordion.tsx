import { useEffect, useMemo, useRef, useState } from "react";
import { AnimatePresence, motion } from "framer-motion";
import { useTranslation } from "react-i18next";
import { cn } from "@/lib/utils.ts";
import { X, Flame, ThumbsUp } from "lucide-react";

interface PackageOption {
  id: number;
  name: string;
  price: string;
  title?: string;
  money?: string | number;
  cost?: string | number;
  hot?: number;
  recommended?: number;
  recommend?: number;
  [key: string]: unknown;
}

interface PlanCardData {
  id: number;
  title: string;
  image: string;
  compactImage?: string;
  compactStatusText?: React.ReactNode;
  statusText?: React.ReactNode;
  packages: PackageOption[];
  onPackageClick: (pkg: PackageOption) => void;
}

interface PlanStackedAccordionProps {
  plans: PlanCardData[];
  initialActivePlanId?: number | null;
}

const CARD_HEADER_HEIGHT = 128;
const STACK_GAP = 32;

function PackageCard({
  pkg,
  onClick,
}: {
  pkg: PackageOption;
  onClick?: () => void;
}) {
  const { t } = useTranslation();

  return (
    <div className="relative border border-[#DAC7A0] bg-[#FFFCEF] w-full aspect-square rounded-xl transition-all hover:shadow flex flex-col items-center justify-center overflow-hidden">
      {/* Badge */}
      {(pkg.hot === 1 || pkg.recommend === 1) && (
        <div className="absolute top-0.5 right-0.5 flex flex-col items-center gap-0.5 z-20">
          {pkg.hot === 1 && (
            <div
              className="flex items-center gap-0.5 px-1 py-1 rounded-full text-white text-[8px] font-medium"
              style={{
                background:
                  "linear-gradient(90deg, #FA7154 0%, #F9176D 49.01%, #F308CF 100.01%)",
              }}
            >
              <Flame size={10} className="flex-shrink-0 fill-white" />
              {/*<span className="truncate">{t("plans.badge_hot")}</span>*/}
            </div>
          )}
          {pkg.recommend === 1 && (
            <div
              className="flex items-center gap-0.5 px-1 py-1 rounded-full text-white text-[8px] font-medium"
              style={{
                background:
                  "linear-gradient(90deg, #FA7154 0%, #F9176D 49.01%, #F308CF 100.01%)",
              }}
            >
              <ThumbsUp size={8} className="flex-shrink-0 fill-white" />
              {/*<span className="truncate">{t("plans.badge_recommended")}</span>*/}
            </div>
          )}
        </div>
      )}

      {/* Cutout section */}
      <div className="z-10">
        <div className="absolute rounded-full border-[#DAC7A0] border w-3 h-3 bg-white top-1/2 -translate-y-1/2 -left-1.5"></div>
        <div className="absolute rounded-full border-[#DAC7A0] border w-3 h-3 bg-white top-1/2 -translate-y-1/2 -right-1.5"></div>
      </div>

      <div
        className="text-center w-full h-full flex flex-col justify-center cursor-pointer"
        onClick={onClick}
      >
        <div className="flex-1 flex items-center justify-center px-1">
          <div className="">
            <div className="flex flex-col items-center">
              {pkg.is_sale === 1 && pkg.cost && (
                <span className="text-xs sm:text-sm font-semibold text-[#917457] line-through leading-none">
                  {pkg.cost}
                </span>
              )}
              <div className="flex items-baseline justify-center -space-x-0.5 -mt-1">
                <span className="text-lg sm:text-2xl font-bold text-[#6E340D] leading-tight">
                  {pkg.price}
                </span>
                <span className="ml-0.5 text-[10px] sm:text-xs font-semibold text-[#6E340D]">
                  {t("plans.currency_yuan")}
                </span>
              </div>
            </div>
            <div className="text-[10px] font-normal  text-[#917457] text-center line-clamp-1">
              {pkg.name}
            </div>
          </div>
        </div>
        {/* Purchase Button Section */}
        <div className="relative bg-[#FCF0CE] h-6 grid place-items-center w-full flex-shrink-0">
          <svg
            className="absolute top-0 left-0 h-1.5 w-full"
            viewBox="0 0 100 10"
            preserveAspectRatio="none"
          >
            <polygon
              points="0,0 5,10 10,0 15,10 20,0 25,10 30,0 35,10 40,0 45,10 50,0 55,10 60,0 65,10 70,0 75,10 80,0 85,10 90,0 95,10 100,0"
              fill="#FFFCEF"
            />
          </svg>
          <p className="text-[10px] font-medium text-[#6E340D] mt-0.5">
            {t("plans.buy_button")}
          </p>
        </div>
      </div>
    </div>
  );
}

export default function PlanStackedAccordion({
  plans,
  initialActivePlanId,
}: PlanStackedAccordionProps) {
  const [activeCard, setActiveCard] = useState<number | null>(null);
  const [expandedHeight, setExpandedHeight] = useState(0);

  const initialActiveIndex = useMemo(() => {
    if (initialActivePlanId == null) {
      return null;
    }

    const index = plans.findIndex((plan) => plan.id === initialActivePlanId);
    return index >= 0 ? index : null;
  }, [initialActivePlanId, plans]);

  useEffect(() => {
    if (initialActivePlanId === undefined) {
      return;
    }

    if (initialActivePlanId === null || initialActiveIndex === null) {
      setExpandedHeight(0);
      setActiveCard(null);
      return;
    }

    setExpandedHeight(0);
    setActiveCard(initialActiveIndex);
  }, [initialActiveIndex, initialActivePlanId]);

  const handleToggle = (index: number) => {
    setActiveCard((prev) => {
      // Reset height when switching cards or closing
      setExpandedHeight(0);
      return prev === index ? null : index;
    });
  };

  // Callback to receive the actual expanded height from CardItem
  const handleHeightChange = (height: number) => {
    setExpandedHeight(height);
  };

  // Calculate dynamic top position based on active state
  const getCardTop = (index: number): number => {
    const baseTop = index * (CARD_HEADER_HEIGHT + STACK_GAP);

    if (activeCard === null) {
      return baseTop;
    }

    const gap = 16;

    // CASE 1: Top card active
    if (activeCard === 0) {
      if (index === 0) return baseTop;
      if (index === 1) return CARD_HEADER_HEIGHT + expandedHeight + gap + 20;
      if (index === 2)
        return CARD_HEADER_HEIGHT + expandedHeight + gap + STACK_GAP + 40;
    }

    // CASE 2: Middle card active
    if (activeCard === 1) {
      if (index === 0) return baseTop;
      if (index === 1) return baseTop + 8;
      if (index === 2) return baseTop + 8 + expandedHeight + gap;
    }

    // CASE 3: Bottom card active
    if (activeCard === 2) {
      if (index === 0) return baseTop;
      if (index === 1) return baseTop - 80;
      return baseTop - 160;
    }

    return baseTop;
  };

  // Compute scale and zIndex
  const getCardTransform = (
    index: number,
  ): { scale: number; zIndex: number; opacity?: number } => {
    const total = plans.length;

    if (activeCard === null) {
      return {
        scale: 1,
        zIndex: total - index,
      };
    }

    // CASE 1: Top card active
    if (activeCard === 0) {
      if (index === 0) return { scale: 1, zIndex: 10 };
      return {
        scale: 0.95,
        zIndex: total - index,
        opacity: index === 2 ? 0.2 : 0.4,
      };
    }

    // CASE 2: Middle card active
    if (activeCard === 1) {
      if (index === 0) return { scale: 0.95, zIndex: 0, opacity: 0.4 };
      if (index === 1) return { scale: 1, zIndex: 10 };
      return { scale: 0.95, zIndex: total - index, opacity: 0.4 };
    }

    // CASE 3: Bottom card active
    if (activeCard === 2) {
      if (index === 0) return { scale: 1, zIndex: 5, opacity: 0.2 };
      if (index === 1) return { scale: 1, zIndex: 6, opacity: 0.4 };
      if (index === 2) return { scale: 1, zIndex: 10 };
    }

    return { scale: 1, zIndex: index };
  };

  return (
    <div className="p-4 w-full overflow-hidden">
      <div className="relative h-screen">
        {plans.map((plan, index) => {
          const isActive = activeCard === index;
          const transform = getCardTransform(index);
          const top = getCardTop(index);

          return (
            <motion.div
              key={plan.id}
              layout
              animate={transform}
              transition={{
                layout: { type: "spring", stiffness: 300, damping: 30 },
                scale: { type: "spring", stiffness: 300, damping: 30 },
                opacity: { duration: 0.2 },
              }}
              className="absolute w-full"
              style={{
                top: `${top}px`,
              }}
            >
              <PlanCardItem
                plan={plan}
                isActive={isActive}
                onClick={() => handleToggle(index)}
                onHeightChange={(height) => {
                  if (isActive) {
                    handleHeightChange(height);
                  }
                }}
              />
            </motion.div>
          );
        })}
      </div>
    </div>
  );
}

interface PlanCardItemProps {
  plan: PlanCardData;
  isActive: boolean;
  onClick: () => void;
  onHeightChange: (height: number) => void;
}

function PlanCardItem({
  plan,
  isActive,
  onClick,
  onHeightChange,
}: PlanCardItemProps) {
  const contentRef = useRef<HTMLDivElement>(null);
  const [contentHeight, setContentHeight] = useState(0);

  useEffect(() => {
    if (contentRef.current) {
      const height = contentRef.current.scrollHeight;
      setContentHeight(height);
      onHeightChange(isActive ? height : 0);
    }
  }, [isActive, onHeightChange]);

  return (
    <div
      role="button"
      tabIndex={0}
      onClick={onClick}
      onKeyDown={(e) => e.key === "Enter" && onClick()}
      className={cn(
        `bg-white rounded-2xl overflow-hidden cursor-pointer will-change-transform focus:outline-none transition-all`,
        isActive ? "border-b" : "border-none",
      )}
    >
      {/* Card header - Background Image with Title */}
      <div className="relative overflow-hidden">
        <img
          src={plan.image}
          alt={plan.title}
          className="w-full h-full object-cover"
        />

        {/* Status Text Overlay */}
        {plan.statusText && (
          <div className="absolute left-2 top-4/5 -translate-y-1/2 ">
            {plan.statusText}
          </div>
        )}
      </div>

      {/* Expandable content - Package Grid */}
      <AnimatePresence>
        {isActive && (
          <motion.div
            initial={{ height: 0, opacity: 0 }}
            animate={{ height: contentHeight, opacity: 1 }}
            exit={{ height: 0, opacity: 0 }}
            transition={{
              type: "spring",
              stiffness: 250,
              damping: 28,
            }}
            className="overflow-hidden"
          >
            <div ref={contentRef} className="p-4 px-2 bg-white">
              <div className="mb-4">
                <div className="flex items-center justify-between mb-2">
                  <div className="flex items-center gap-2">
                    <div className="w-[3px] h-5 rounded-full bg-[#F53357]"></div>
                    <h3 className="text-base text-gray-900 font-semibold">
                      {plan.title}
                    </h3>
                  </div>
                  <button
                    onClick={(e) => {
                      e.stopPropagation();
                      onClick();
                    }}
                    className="p-1 hover:bg-gray-100 rounded-full transition-colors"
                    aria-label="Close"
                  >
                    <X className="w-5 h-5 text-gray-600" />
                  </button>
                </div>
                <div className="w-full h-[0.5px] bg-[#E0E0E0]"></div>
              </div>

              <div className="flex gap-1.5 ">
                {/* Compact Image with Status Text Overlay */}
                {plan.compactImage && (
                  <div className="relative flex-shrink-0 h-fit">
                    <img
                      src={plan.compactImage}
                      alt={plan.title}
                      className="w-[80px] h-full contain-contain rounded-xl"
                    />
                    {plan.compactStatusText && (
                      <div className="absolute left-2 top-2/3 -translate-y-1/2 flex items-center justify-center">
                        {plan.compactStatusText}
                      </div>
                    )}
                  </div>
                )}

                {/* Package Cards */}
                <div className="grid grid-cols-3 gap-2 flex-1">
                  {plan.packages.map((pkg, pkgIndex) => (
                    <PackageCard
                      key={pkgIndex}
                      pkg={pkg}
                      onClick={() => plan.onPackageClick(pkg)}
                    />
                  ))}
                </div>
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
