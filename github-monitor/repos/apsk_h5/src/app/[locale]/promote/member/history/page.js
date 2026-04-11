"use client";

import { useEffect, useState } from "react";
import Image from "next/image";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";
import { getMemberInfo } from "@/utils/utility";
import {
  useGetReferralFriendCommissionQuery,
  useGetReferralFriendQuery,
} from "@/services/referralApi";
import SharedLoading from "@/components/shared/LoadingBar/ShareLoading";
import EmptyRecord from "@/components/shared/EmptyRecord";

export default function InviteRecordsPage() {
  const router = useRouter();
  const t = useTranslations();
  const [tab, setTab] = useState("friends"); // friends | rebate
  const [info, setInfo] = useState(null);

  // read member from cookies
  useEffect(() => {
    const member = getMemberInfo();
    setInfo(member);
  }, []);

  const {
    data,
    error,
    isLoading: friendLoading,
  } = useGetReferralFriendQuery(
    { member_id: info?.member_id },
    { skip: !info?.member_id }
  );

  const friends = data?.data || [];

  const { data: commissions, isLoading: rebateLoading } =
    useGetReferralFriendCommissionQuery(
      { member_id: info?.member_id },
      { skip: !info?.member_id }
    );

  const rebateList = commissions?.data || [];

  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 pb-8">
      {/* Header */}
      <div className="relative flex items-center h-14">
        <button onClick={() => router.back()} className="z-10 cursor-pointer">
          <Image
            src={IMAGES.arrowLeft}
            alt="back"
            width={22}
            height={22}
            className="object-contain"
          />
        </button>
        <h1 className="absolute inset-x-0 text-lg font-semibold text-center">
          {t("inviteRecords.title")}
        </h1>
      </div>

      {/* Tabs */}
      <div className="flex ">
        <button
          onClick={() => setTab("friends")}
          className={`flex-1 py-3 text-sm ${
            tab === "friends" ? "text-[#FFC000] font-medium" : "text-white/70"
          }`}
        >
          {t("inviteRecords.tabs.friends")}
          {tab === "friends" && (
            <div className="mx-auto mt-1 h-0.5 w-12 bg-[#FFC000]" />
          )}
        </button>
        <button
          onClick={() => setTab("rebate")}
          className={`flex-1 py-3 text-sm ${
            tab === "rebate" ? "text-[#FFC000] font-medium" : "text-white/70"
          }`}
        >
          {t("inviteRecords.tabs.rebate")}
          {tab === "rebate" && (
            <div className="mx-auto mt-1 h-0.5 w-12 bg-[#FFC000]" />
          )}
        </button>
      </div>

      {/* Tab Content */}
      <div className="mt-4 space-y-4">
        {/* ================= FRIEND LIST TAB ================= */}
        {tab === "friends" && (
          <>
            {friendLoading && <SharedLoading />}

            {!friendLoading && friends.length === 0 && <EmptyRecord />}

            {!friendLoading &&
              friends.length > 0 &&
              friends.map((item, idx) => (
                <div
                  key={idx}
                  className="rounded-lg border border-white/30 p-4 space-y-2"
                >
                  <Row
                    label={t("inviteRecords.invitedId")}
                    value={item.member_id}
                  />

                  <Row
                    label={t("inviteRecords.code")}
                    value={item.referralCode}
                  />

                  {/* API has no time → omit */}
                  {/* <Row label={t("inviteRecords.time")} value={"-"} /> */}

                  <Row
                    label={t("inviteRecords.invitedCount")}
                    value={item.invitecount}
                  />

                  <Row
                    label={t("inviteRecords.tradedCount")}
                    value={item.creditcount}
                  />

                  <Row
                    label={t("inviteRecords.totalAmount")}
                    value={
                      <span className="text-[#FFC000]">
                        {item.creditamount}
                      </span>
                    }
                  />
                </div>
              ))}
          </>
        )}

        {/* ================= REBATE TAB ================= */}
        {tab === "rebate" && (
          <>
            {rebateLoading && <SharedLoading />}

            {!rebateLoading && rebateList.length === 0 && <EmptyRecord />}

            {!rebateLoading &&
              rebateList.length > 0 &&
              rebateList.map((item, idx) => (
                <div
                  key={idx}
                  className="rounded-lg border border-white/30 p-4 space-y-2"
                >
                  <Row
                    label={t("inviteRecords.time")}
                    value={item.created_on}
                  />

                  <Row
                    label={t("inviteRecords.invitedId")}
                    value={item.member_id}
                  />

                  <Row
                    label={t("inviteRecords.rebateAmount")}
                    value={
                      <span className="text-[#FFC000]">{item.amount}</span>
                    }
                  />
                </div>
              ))}
          </>
        )}
      </div>
    </div>
  );
}

function Row({ label, value }) {
  return (
    <div className="flex items-center justify-between text-sm">
      <span className="text-white/80">{label}</span>
      <span>{value}</span>
    </div>
  );
}
