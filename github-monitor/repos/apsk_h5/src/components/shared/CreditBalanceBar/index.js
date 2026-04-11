"use client";
import {
  forwardRef,
  useImperativeHandle,
  useEffect,
  useState,
  useContext,
} from "react";
import Image from "next/image";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";
import { getMemberInfo } from "@/utils/utility";
import { UIContext } from "@/contexts/UIProvider";
import { useBalanceStore } from "@/store/zustand/balanceStore";
import { useGetMemberViewQuery } from "@/services/authApi";

const CreditBalanceBar = forwardRef((props, ref) => {
  const t = useTranslations();
  const [info, setInfo] = useState(null);
  const { setLoading } = useContext(UIContext);

  // read cookies on client
  useEffect(() => {
    const member = getMemberInfo();
    setInfo(member);
  }, []);

  // member API
  const {
    data: user,
    isLoading,
    isFetching,
    refetch: refetchMember,
  } = useGetMemberViewQuery(info ? { member_id: info.member_id } : undefined, {
    skip: !info?.member_id,
  });

  const setCredits = useBalanceStore((s) => s.setCredits);
  const credits = useBalanceStore((s) => s.credits);

  // 🔁 expose refresh to parent
  const handleRefresh = async () => {
    if (!info?.member_id) return;
    try {
      setLoading(true);
      await refetchMember();
    } finally {
      setLoading(false);
    }
  };

  useImperativeHandle(ref, () => ({
    handleRefresh,
  }));

  // update balance
  useEffect(() => {
    if (user?.data) {
      setCredits(Number(user.data.balance || 0));
    }
  }, [user, setCredits]);

  return (
    <div className="flex items-center justify-center gap-2">
      {/* Main credit pill */}
      <div className="flex items-center rounded-full bg-[#4F6BDE] px-3 py-1.5">
        <div
          className="flex h-7 w-7 items-center justify-center rounded-full shrink-0"
          style={{
            background: "linear-gradient(180deg, #F8AF07 0%, #FFFC86 100%)",
          }}
        >
          <Image
            src={IMAGES.withdraw.buttonmoney}
            alt="money"
            width={16}
            height={16}
            className="object-contain"
          />
        </div>

        <span className="mx-3 text-sm font-semibold text-white leading-none">
          {credits.toFixed(2)}
        </span>
      </div>

      {/* Refresh icon */}
      <div className="w-[15%] flex items-end justify-center ">
        <button
          onClick={handleRefresh}
          className="flex h-9 w-9 items-center justify-center"
          disabled={!info?.member_id || isLoading || isFetching}
        >
          <Image
            src={IMAGES.withdraw.iconRefresh}
            alt="refresh"
            width={30}
            height={30}
          />
        </button>
      </div>
    </div>
  );
});

CreditBalanceBar.displayName = "CreditBalanceBar";
export default CreditBalanceBar;
