"use client";

import Image from "next/image";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";
import { useGetDownlineListQuery } from "@/services/referralApi";
import { getMemberInfo } from "@/utils/utility";
import { useEffect, useState } from "react";
import { skipToken } from "@reduxjs/toolkit/query";
import SharedLoading from "@/components/shared/LoadingBar/ShareLoading";
import EmptyRecord from "@/components/shared/EmptyRecord";

export default function SubordinatesTab() {
  const t = useTranslations();
  const [info, setInfo] = useState(null);

  // Load member info once (client-side)
  useEffect(() => {
    const member = getMemberInfo();
    setInfo(member);
  }, []);

  // Prepare query args
  const queryArgs = info?.member_id ? { member_id: info.member_id } : skipToken;

  const { data, isLoading, isFetching } = useGetDownlineListQuery(
    queryArgs,
    {}
  );

  // Extract list
  const list = data?.data || [];

  // Compute flags
  const showLoading = isLoading || (isFetching && list.length === 0);
  const showEmpty = !showLoading && list.length === 0;

  return (
    <div className="divide-y divide-[#2E3D6B]">
      {/* Loading (first load OR refetch with no data) */}
      {showLoading && <SharedLoading />}

      {/* Empty (only when not loading & not fetching) */}
      {showEmpty && <EmptyRecord />}

      {/* Real list */}
      {list.map((u) => {
        const joinDate = u.registered_on ? formatDate(u.registered_on) : "—";

        return (
          <div key={u.member_id} className="bg-[#122346]">
            <div className="flex items-start gap-3 py-3 px-3">
              {/* Avatar */}
              <Image
                src={IMAGES.iconUser}
                alt="avatar"
                width={26}
                height={26}
                className="mt-1 shrink-0 opacity-70"
              />

              {/* Text block */}
              <div className="flex-1 text-xs leading-5">
                {/* Name + ID */}
                <div className="flex flex-wrap items-center gap-x-2">
                  <span className="text-[#FFC000] font-medium">
                    {u.member_login}
                  </span>
                  <span className="text-white/70">
                    {t("subInfo.id")}: {u.member_id}
                  </span>
                </div>

                {/* Joined time */}
                <div className="text-white/70">
                  {t("subInfo.joined")}: {joinDate}
                </div>

                {/* Inviter code */}
                <div className="text-white/60">
                  {t("subInfo.inviteCode")}: {u.invitecode || ""}
                </div>
              </div>
            </div>
          </div>
        );
      })}
    </div>
  );
}
