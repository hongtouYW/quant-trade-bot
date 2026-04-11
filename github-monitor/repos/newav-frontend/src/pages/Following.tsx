import { UserRoundCheck } from "lucide-react";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group.tsx";
import { useId, useState, useEffect } from "react";
import { MyPublisherList } from "@/components/my-publisher-list.tsx";
import { MyActorList } from "@/components/my-actor-list.tsx";
import { useTranslation } from "react-i18next";
import { useSearchParams, useNavigate } from "react-router";
import { RecommendedHorizontalList } from "@/components/recommended-horizontal-list.tsx";

export default function Following() {
  const { t } = useTranslation();
  const id = useId();
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const tabParam = searchParams.get("tab");

  // Map tab parameter to radio value
  const getInitialTab = () => {
    if (tabParam === "actors") return "2";
    if (tabParam === "publishers") return "4";
    if (tabParam === "all") return "1";
    return "1";
  };

  const [selected, setSelected] = useState(getInitialTab);

  // Update selected tab when URL param changes
  useEffect(() => {
    const newTab = getInitialTab();
    setSelected(newTab);
  }, [tabParam]);

  // Set URL parameter when component mounts if no tab param exists
  useEffect(() => {
    if (!tabParam) {
      navigate("/following?tab=all", { replace: true });
    }
  }, []);

  // Handle tab change and update URL
  const handleTabChange = (value: string) => {
    setSelected(value);
    const tabMap: Record<string, string> = {
      "1": "all",
      "2": "actors",
      "4": "publishers",
    };
    navigate(`/following?tab=${tabMap[value]}`, { replace: true });
  };

  const items = [
    { value: "1", label: t("following.all") },
    { value: "2", label: t("following.actresses") },
    // { value: "3", label: t("following.channels") },
    { value: "4", label: t("following.publishers"), disabled: false },
  ];

  return (
    <div>
      <div className="sticky top-[112px] md:top-0 z-40 bg-background">
        {/* Header - desktop only */}
        <header className="hidden md:block border-b px-4">
          <div className="flex h-14 items-center justify-between gap-4">
            <div className="flex flex-1 items-center gap-2 text-base font-bold">
              <UserRoundCheck
                className="sm:-ms-1"
                size={20}
                aria-hidden="true"
              />
              <span className="">{t("following.my_following")}</span>
            </div>
          </div>
        </header>

        {/* Filter section - always visible */}
        <div className="border-b px-4">
          <div className="flex py-3 items-center justify-between gap-4">
            <div className="flex flex-1 items-center gap-2 text-base font-bold">
              <fieldset className="space-y-4">
                <RadioGroup
                  className="grid grid-cols-4 gap-2 sm:grid-flow-col sm:gap-3 sm:grid-cols-none"
                  value={selected}
                  onValueChange={handleTabChange}
                >
                  {items.map((item) => (
                    <label
                      key={`${id}-${item.value}`}
                      className="border-input has-data-[state=checked]:bg-primary/50 has-data-[state=checked]:text-white has-focus-visible:border-ring has-focus-visible:ring-ring/50 relative flex cursor-pointer flex-col items-center gap-3 rounded-full border px-2 py-3 text-center shadow-xs transition-[color,box-shadow] outline-none has-focus-visible:ring-[3px] has-data-disabled:cursor-not-allowed has-data-disabled:opacity-50"
                    >
                      <RadioGroupItem
                        id={`${id}-${item.value}`}
                        value={item.value}
                        className="sr-only after:absolute after:inset-0"
                        disabled={item.disabled}
                      />
                      <p className="px-2 sm:px-8.5 text-xs sm:text-sm leading-none font-medium">
                        {item.label}
                      </p>
                    </label>
                  ))}
                </RadioGroup>
              </fieldset>
            </div>
          </div>
        </div>
      </div>

      <div className="p-4 space-y-4">
        <div className={selected === "1" || selected === "2" ? "" : "hidden"}>
          <MyActorList isAllTab={selected === "1"} />
        </div>
        {/*<div className={selected === "1" || selected === "3" ? "" : "hidden"}>*/}
        {/*  <ChannelList />*/}
        {/*</div>*/}
        <div className={selected === "1" || selected === "4" ? "" : "hidden"}>
          <MyPublisherList isAllTab={selected === "1"} />
        </div>
      </div>

      <div className="py-4 px-4">
        <RecommendedHorizontalList />
      </div>
    </div>
  );
}
