"use client";
import { useContext, useEffect, useState } from "react";
import Image from "next/image";
import Link from "next/link";
import { IMAGES } from "@/constants/images";
import { UIContext } from "@/contexts/UIProvider";
import {
  useGetQuestionListQuery,
  useGetSupportListQuery,
} from "@/services/commonApi";
import { fixUrl, getMemberInfo } from "@/utils/utility";
import { toast } from "react-hot-toast";
import { useDispatch } from "react-redux";
import { useRouter } from "next/navigation";
import { setSelectedQuestion } from "@/store/slice/helpSlice";
import EmptyRecord from "@/components/shared/EmptyRecord";
import { useGetMemberViewQuery } from "@/services/authApi";
import SharedLoading from "@/components/shared/LoadingBar/ShareLoading";

export default function HelpContent({ t, type = "all" }) {
  const tabs =
    type === "all"
      ? [
          { key: "commonquestion", label: t("help.tab.faq") },
          // { key: "transactioncontrol", label: t("help.tab.trade") },
          // { key: "transfer", label: t("help.tab.transfer") },
          { key: "vip", label: t("help.tab.vip") },
        ]
      : type === "cs"
        ? [
            { key: "commonquestion", label: t("help.tab.faq") },

            // { key: "transactioncontrol", label: t("help.tab.trade") },
          ]
        : [];
  const [activeTab, setActiveTab] = useState("commonquestion");
  const { setConfirmConfig } = useContext(UIContext);
  const dispatch = useDispatch();
  const info = getMemberInfo();
  const memberId = info?.member_id;
  const router = useRouter();
  const { loading, setLoading } = useContext(UIContext);
  const { data: user } = useGetMemberViewQuery(
    info ? { member_id: info.member_id } : undefined, // body passed only when info exists
    {
      skip: !info?.member_id,
      refetchOnMountOrArgChange: false, // (default)// avoid running with empty info
    },
  );

  // useEffect(() => {
  //   if (user != null) {
  //     setUsername(user?.data?.member_login);
  //     setUserId(user?.data?.member_id);
  //     setSocialId(user?.data?.whatsapp);
  //     if (user?.data?.avatar != null) {
  //       setAvatarSrc(user?.data?.avatar);
  //     }

  //     if (user?.data?.dob) {
  //       // API format: "2007-09-06 00:00:00"
  //       const formatted = user.data.dob.split(" ")[0]; // take only "2007-09-06"
  //       setBirthday(formatted);
  //     }
  //   }
  // }, [user]);

  const {
    data: questionList,
    isLoading: isQuestionLoading,
    refetch,
    isFetching,
  } = useGetQuestionListQuery({
    member_id: memberId,
    question_type: activeTab,
  });

  const [openGroups, setOpenGroups] = useState({});

  const toggleGroup = (key) => {
    setOpenGroups((prev) => ({
      ...prev,
      [key]: !prev[key],
    }));
  };

  const { data: supportList, isLoading: isSupportLoading } =
    useGetSupportListQuery(
      {
        member_id: info?.member_id, // pass your user ID
      },
      {
        skip: !info?.member_id, // prevent calling before member_id ready
      },
    );
  const rawSupportUrl = supportList?.support;
  const supportUrl = fixUrl(rawSupportUrl);
  const isSupportAvailable = supportUrl !== "";

  const rawTelegramUrl = supportList?.telegramsupport;
  const telegramUrl = fixUrl(rawTelegramUrl);
  const isTelegramAvailable = telegramUrl !== "";

  const rawWhatsappUrl = supportList?.whatsappsupport;
  const whatsappUrl = fixUrl(rawWhatsappUrl);
  const isWhatsappAvailable = whatsappUrl !== "";

  useEffect(() => {
    if (questionList?.data?.length > 0) {
      const first = questionList.data[0];
      setOpenGroups({ [first.question_id]: true }); // only first open
    }
  }, [questionList]);

  const handleShowNotice = () => {
    // 🔔 show notice modal
    setConfirmConfig({
      titleKey: "common.underMaintenanceTitle",
      messageKey: "common.underMaintenanceMessage",
      confirmKey: "common.ok",
      displayMode: "center", // ✅ center modal
      showCancel: true, // ✅ single button
    });
  };

  // useEffect(() => {
  //   // 🔔 show notice on first render
  //   setConfirmConfig({
  //     titleKey: "common.underMaintenanceTitle",
  //     messageKey: "common.underMaintenanceMessage",
  //     confirmKey: "common.ok",
  //     displayMode: "center", // ✅ <— show in center
  //     showCancel: true, // ✅ one-button mode
  //   });
  // }, [setConfirmConfig]);
  useEffect(() => {
    setLoading(isSupportLoading);
  }, [isSupportLoading, setLoading]);

  const avatars = [
    "https://api.apsk.cc/assets/img/member/avatar_10.webp",
    "https://api.apsk.cc/assets/img/member/avatar_2.webp",
    "https://api.apsk.cc/assets/img/member/avatar_4.webp",
  ];

  const [avatar, setAvatar] = useState(null);

  useEffect(() => {
    const r = avatars[Math.floor(Math.random() * avatars.length)];
    setAvatar(r);
  }, []);

  // const randomAvatar = avatars[Math.floor(Math.random() * avatars.length)];

  return (
    <>
      {/* 🔹 Top Card */}
      <div className="flex items-center gap-3">
        <div className="mt-5 h-24 w-24 shrink-0 rounded-full border-2 border-[#F8AF07]">
          <div className="relative h-full w-full rounded-full overflow-hidden">
            {avatar && (
              <Image
                src={avatar}
                alt="avatar"
                fill
                className="object-cover object-[center_30%]"
                sizes="96px"
                priority
              />
            )}
          </div>
        </div>
        <div className="flex-1">
          <div className="text-sm font-semibold mb-1">
            {t("help.card.title")}
          </div>
          <div className="text-xs text-white/80">{t("help.card.subtitle")}</div>
        </div>
      </div>

      {/* 🔹 Two main CS buttons */}
      <div className="mt-4 grid grid-cols-3 gap-3">
        <button
          onClick={() => {
            if (isSupportAvailable) {
              window.open(supportUrl, "_blank");
            } else {
              toast.error(t("help.noSupportLink"));
            }
          }}
          className="w-full flex items-center justify-center h-12 rounded-full border border-[#F8AF07] bg-[#0C121E] active:scale-95"
        >
          <img
            src={IMAGES.setting.iconCs}
            alt="cs"
            className="w-6 h-6 object-contain"
          />
        </button>

        <button
          onClick={() => {
            if (isTelegramAvailable) {
              window.open(telegramUrl, "_blank");
            } else {
              toast.error(t("help.noSupportLink"));
            }
          }}
          className="w-full flex items-center justify-center h-12 rounded-full border border-[#F8AF07] bg-[#0C121E] active:scale-95"
        >
          <img
            src={IMAGES.setting.iconTelegram}
            alt="telegram"
            className="w-6 h-6 object-contain"
          />
        </button>

        <button
          onClick={() => {
            if (isWhatsappAvailable) {
              window.open(whatsappUrl, "_blank");
            } else {
              toast.error(t("help.noSupportLink"));
            }
          }}
          className="w-full flex items-center justify-center h-12 rounded-full border border-[#F8AF07] bg-[#0C121E] active:scale-95"
        >
          <img
            src={IMAGES.setting.iconWatapps}
            alt="whatsapp"
            className="w-6 h-6 object-contain"
          />
        </button>
      </div>

      {/* 🔹 Tabs */}
      <div className="mt-5 flex items-center gap-6 overflow-x-auto no-scrollbar">
        {tabs.map((tab) => {
          const isActive = activeTab === tab.key;
          return (
            <button
              key={tab.key}
              onClick={() => setActiveTab(tab.key)}
              className={`relative pb-2 text-sm whitespace-nowrap ${
                isActive ? "text-[#F8AF07]" : "text-[#F8AF0780]"
              }`}
            >
              {tab.label}
              {isActive && (
                <span className="absolute left-0 -bottom-[2px] h-[3px] w-full rounded-sm bg-[#F8AF07]" />
              )}
            </button>
          );
        })}
      </div>

      {/* 🔹 Accordion Groups */}
      <div className="mt-3 divide-y divide-white/5 rounded-xl overflow-hidden bg-[#0F214F]/50 border border-white/10">
        {!loading && (isQuestionLoading || isFetching) ? (
          <SharedLoading />
        ) : questionList?.data?.length > 0 ? (
          questionList.data.map((group) => (
            <div key={group.question_id} className="mb-3">
              <AccordionHeader
                title={group.title}
                open={openGroups[group.question_id]}
                onClick={() => toggleGroup(group.question_id)}
              />

              {openGroups[group.question_id] && (
                <div className="bg-[#0F214F]">
                  {group.children?.length > 0 ? (
                    group.children.map((child) => (
                      <button
                        key={`${child.question_id}-${child.title}`}
                        onClick={() => {
                          dispatch(setSelectedQuestion(child));
                          router.push("/help/detail");
                        }}
                        className="w-full flex items-center justify-between px-4 py-3 active:opacity-80 text-left"
                      >
                        <span className="text-sm text-white/90">
                          {child.title}
                        </span>
                        <ChevronRight />
                      </button>
                    ))
                  ) : (
                    <div className="p-3 text-sm text-white/70">
                      {t("help.noChild")}
                    </div>
                  )}
                </div>
              )}
            </div>
          ))
        ) : !isFetching ? (
          <EmptyRecord />
        ) : null}
      </div>
    </>
  );
}

/* ---------- tiny subcomponents ---------- */
function AccordionHeader({ title, open, onClick }) {
  return (
    <button
      onClick={onClick}
      className="w-full flex items-center justify-between px-4 py-3 bg-[#13255B] text-left"
    >
      <span className="text-sm">{title}</span>
      <Chevron open={open} />
    </button>
  );
}

function FaqRow({ text, href = "#" }) {
  return (
    <Link
      href={href}
      className="flex items-center justify-between px-4 py-3 active:opacity-80"
    >
      <span className="text-sm text-white/90">{text}</span>
      <ChevronRight />
    </Link>
  );
}

function Chevron({ open }) {
  return (
    <svg
      className={`h-5 w-5 transition-transform ${open ? "rotate-180" : ""}`}
      viewBox="0 0 24 24"
      fill="none"
    >
      <path d="M6 9l6 6 6-6" stroke="currentColor" strokeWidth="2" />
    </svg>
  );
}

function ChevronRight() {
  return (
    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none">
      <path d="M9 6l6 6-6 6" stroke="currentColor" strokeWidth="2" />
    </svg>
  );
}
