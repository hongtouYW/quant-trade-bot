"use client";

import Image from "next/image";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";
import SubmitButton from "@/components/shared/SubmitButton";
import CreateInviteModal from "../../components/createInviteModal";
import { toast } from "react-hot-toast";
import { useState, useContext, useEffect, useMemo } from "react";
import Link from "next/link";
import { UIContext } from "@/contexts/UIProvider";
import {
  useCreateNewInviteCodeMutation,
  useEditDefaultInviteCodeMutation,
  useEditInviteNameMutation,
  useGetInviteListQuery,
} from "@/services/referralApi";
import SharedLoading from "@/components/shared/LoadingBar/ShareLoading";
import EmptyRecord from "@/components/shared/EmptyRecord";
import { extractError, getMemberInfo } from "@/utils/utility";
import MarqueeTitle from "@/components/shared/MarqueeTitle";
import ShareDialog from "@/components/shared/ShareDialog";
import { useGetReferralQrQuery } from "@/services/authApi";
import { skipToken } from "@reduxjs/toolkit/query";

export default function InviteCodesPage() {
  const router = useRouter();
  const t = useTranslations();
  const { setLoading } = useContext(UIContext);

  const [createNewInviteCode] = useCreateNewInviteCodeMutation();
  const [editDefaultInviteCode] = useEditDefaultInviteCodeMutation();
  const [shouldSyncFromApi, setShouldSyncFromApi] = useState(false);
  const [showShare, setShowShare] = useState(false);
  const [shareUrl, setShareUrl] = useState("");
  const [editingId, setEditingId] = useState(null);
  const [editValue, setEditValue] = useState("");
  const [openAdd, setOpenAdd] = useState(false);
  const [editInviteName] = useEditInviteNameMutation();

  // for preventing UI flicker
  const [firstLoaded, setFirstLoaded] = useState(false);
  const startEdit = (item) => {
    setEditingId(item.invitation_id); // enter edit mode
    setEditValue(item.invitecode_name); // preload current code
  };

  // member info
  const info = getMemberInfo();
  const memberId = info?.member_id;

  // fetch invite list
  const {
    data: inviteData,
    isLoading,
    isFetching,
    refetch,
  } = useGetInviteListQuery({ member_id: memberId }, { skip: !memberId });

  const { refetch: refetchReferral } = useGetReferralQrQuery(
    info?.member_id ? { member_id: info.member_id } : skipToken,
    {}
  );

  // only treat first load as loading
  const showFirstLoading = !firstLoaded && (isLoading || isFetching);

  // convert backend data
  const items = useMemo(() => {
    return (
      inviteData?.data?.map((it) => ({
        default: it.default === 1,
        invitation_id: it.invitation_id,
        code: it.referralCode,
        link: it.qr,
      })) || []
    );
  }, [inviteData]);

  // freeze UI after first sync
  const [localItems, setLocalItems] = useState([]);
  const [selectedDefault, setSelectedDefault] = useState(null);

  // mark first data loaded
  useEffect(() => {
    if (inviteData && !firstLoaded) {
      setFirstLoaded(true);
    }
  }, [inviteData, firstLoaded]);

  // sync items once
  // Sync from API on first load OR after create
  useEffect(() => {
    if (!inviteData) return;

    const list =
      inviteData?.data?.map((it) => ({
        default: it.default === 1,
        invitation_id: it.invitation_id,
        code: it.referralCode,
        link: it.qr,
        invitecode_name: it.invitecode_name || "", // ⭐ ADD THIS
      })) || [];

    // 1) First load → sync
    if (localItems.length === 0 && !shouldSyncFromApi) {
      setLocalItems(list);

      const def = list.find((x) => x.default);
      setSelectedDefault(def?.invitation_id || null);
      return;
    }

    // 2) After create → sync
    if (shouldSyncFromApi) {
      setLocalItems(list);

      const def = list.find((x) => x.default);
      setSelectedDefault(def?.invitation_id || null);

      setShouldSyncFromApi(false);
    }
  }, [inviteData, shouldSyncFromApi]);

  // copy helper
  const copy = async (text) => {
    try {
      await navigator.clipboard.writeText(text);
      toast.success(t("common.copySuccess"));
    } catch {
      toast.error(t("common.copyFailed"));
    }
  };

  // create new invite code
  const handleCreate = async () => {
    setOpenAdd(false);
    setLoading(true);

    const isDefault = localItems.length === 0 ? 1 : 0;

    try {
      const resp = await createNewInviteCode({
        member_id: memberId,
        invitecode: null,
        default: isDefault ? 1 : 0,
      }).unwrap();

      if (resp?.status) {
        toast.success(t("invite.createSuccess"));

        // ⭐ Clear UI and force full refresh
        setLocalItems([]); // remove all cards
        setSelectedDefault(null); // reset radio
        setFirstLoaded(false); // show loading bar again

        // ⭐ Fetch clean list from backend
        refetch();
        refetchReferral?.();
      }
    } catch (err) {
      toast.error(extractError(err).message);
    } finally {
      setLoading(false);
    }
  };

  const saveEdit = async (item) => {
    const prevName = item.invitecode_name;
    const newName = editValue.trim() || null;

    // 1️⃣ Optimistic UI update
    setLocalItems((list) =>
      list.map((it) =>
        it.invitation_id === item.invitation_id
          ? { ...it, invitecode_name: newName }
          : it
      )
    );

    // close edit mode instantly
    setEditingId(null);

    try {
      // 2️⃣ API call
      const resp = await editInviteName({
        member_id: memberId,
        invitation_id: item.invitation_id,
        invitecode_name: newName,
      }).unwrap();

      toast.success(resp?.message || t("common.success"));

      // 3️⃣ Refresh backend data (optional)
      refetch();
    } catch (err) {
      toast.error("Update failed");

      // 4️⃣ Revert UI
      setLocalItems((list) =>
        list.map((it) =>
          it.invitation_id === item.invitation_id
            ? { ...it, invitecode_name: prevName }
            : it
        )
      );
    }
  };

  // toggle default
  const toggleDefault = async (invitation_id) => {
    if (selectedDefault === invitation_id) return;

    const previous = selectedDefault;

    // instant UI update
    setSelectedDefault(invitation_id);
    setLocalItems((list) =>
      list.map((it) => ({
        ...it,
        default: it.invitation_id === invitation_id,
      }))
    );

    try {
      const resp = await editDefaultInviteCode({
        member_id: memberId,
        invitation_id,
      }).unwrap();

      if (resp?.status) {
        toast.success(t("invite.defaultUpdated"));

        // backend sync but UI will not refresh
        refetchReferral();
        refetch();
      }
    } catch (err) {
      toast.error(extractError(err).message);

      // revert
      setSelectedDefault(previous);
      setLocalItems((list) =>
        list.map((it) => ({
          ...it,
          default: it.invitation_id === previous,
        }))
      );
    }
  };

  const showEmpty = firstLoaded && !isFetching && localItems.length === 0;

  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 pb-8">
      {/* Header */}
      <div className="relative flex items-center h-14">
        <button onClick={() => router.back()} className="z-10 cursor-pointer">
          <Image src={IMAGES.arrowLeft} alt="back" width={22} height={22} />
        </button>

        <h1 className="absolute inset-x-0 text-lg font-semibold text-center">
          {t("invite.myCode")}
        </h1>

        <button className="ml-auto z-10 pr-1" onClick={() => handleCreate()}>
          <span className="text-[#F8AF07] text-2xl leading-none">+</span>
        </button>
      </div>

      {/* FIRST LOADING ONLY */}
      {showFirstLoading && <SharedLoading />}

      {/* EMPTY */}
      {showEmpty && <EmptyRecord />}

      {/* LIST */}
      {firstLoaded &&
        !showEmpty &&
        localItems.map((it, idx) => {
          const isDefault = it.default;

          return (
            <div
              key={idx}
              className="mt-4 rounded-xl border border-white/40 p-4 space-y-4"
            >
              {/* Row 1 */}
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-1 text-[15px]">
                  {/* LABEL OR INPUT */}
                  {editingId === it.invitation_id ? (
                    <input
                      type="text"
                      value={editValue}
                      placeholder={
                        it.invitecode_name?.trim()
                          ? it.invitecode_name // placeholder = name
                          : t("invite.myCode") // fallback placeholder
                      }
                      onChange={(e) => setEditValue(e.target.value)}
                      className="
                  bg-transparent
                  border-b border-[#FFC000]
                  text-[#FFC000]
                  text-sm
                  outline-none
                  min-w-[140px]
      "
                    />
                  ) : (
                    <span>
                      {it.invitecode_name?.trim()
                        ? it.invitecode_name
                        : t("invite.myCode")}
                    </span>
                  )}

                  {/* EDIT / SAVE TOGGLE */}
                  {editingId === it.invitation_id ? (
                    // SAVE — use your iconCheckmark
                    <button onClick={() => saveEdit(it)}>
                      <Image
                        src={IMAGES.iconCheckmark}
                        alt="save"
                        width={18}
                        height={18}
                        className="object-contain"
                      />
                    </button>
                  ) : (
                    // EDIT
                    <button onClick={() => startEdit(it)}>
                      <Image
                        src={IMAGES.iconPencil}
                        alt="edit"
                        width={16}
                        height={16}
                      />
                    </button>
                  )}
                </div>

                {/* toggle default */}
                <button
                  onClick={() => toggleDefault(it.invitation_id)}
                  className="flex items-center gap-2 active:scale-95"
                >
                  {isDefault ? (
                    <>
                      <span className="text-xs text-white/70">
                        {t("invite.default")}
                      </span>
                      <div className="h-5 w-5 rounded-full bg-[#35D06E] flex items-center justify-center">
                        <svg
                          viewBox="0 0 24 24"
                          className="h-3 w-3"
                          fill="none"
                          stroke="white"
                          strokeWidth="3"
                          strokeLinecap="round"
                          strokeLinejoin="round"
                        >
                          <path d="M5 12l4 4 10-10" />
                        </svg>
                      </div>
                    </>
                  ) : (
                    <>
                      <span className="text-xs text-white/70">
                        {t("invite.setAsDefault")}
                      </span>
                      <div className="h-5 w-5 rounded-full border-2 border-[#FFC000]"></div>
                    </>
                  )}
                </button>
              </div>

              <div className="h-px bg-[#354B9C]" />

              {/* Invite Code */}
              <div className="flex items-center justify-between text-sm">
                <span>{t("invite.inviteCode")}</span>
                <div className="flex items-center gap-2">
                  <span className="tracking-wider text-[#FFFC8680]">
                    {it.code}
                  </span>
                  <button onClick={() => copy(it.code)}>
                    <Image
                      src={IMAGES.iconCopy}
                      alt="copy"
                      width={16}
                      height={16}
                    />
                  </button>
                </div>
              </div>

              <div className="h-px bg-[#354B9C]" />

              {/* Invite Link */}
              <div className="flex items-center justify-between gap-2 text-sm">
                <span>{t("invite.inviteLink")}</span>
                <div className="flex items-center gap-2 overflow-hidden">
                  <MarqueeTitle
                    stop={showShare}
                    speedSec={25}
                    title={it.link}
                    selected={false}
                    maxWidth={300}
                  />

                  <button
                    onClick={() => copy(it.link)}
                    className="shrink-0 p-1"
                  >
                    <Image
                      src={IMAGES.iconCopy}
                      alt="copy"
                      width={16}
                      height={16}
                    />
                  </button>
                </div>
              </div>

              <SubmitButton
                onClick={() => {
                  setShareUrl(it.link);
                  setShowShare(true);
                }}
              >
                {t("invite.inviteNow")}
              </SubmitButton>
            </div>
          );
        })}

      {/* Create Modal */}
      {/* <CreateInviteModal
        open={openAdd}
        onCancel={() => setOpenAdd(false)}
        onConfirm={handleCreate}
      /> */}

      <ShareDialog
        open={showShare}
        onClose={() => setShowShare(false)}
        text={t("earn.share.shareTitle")}
        url={shareUrl}
      />
    </div>
  );
}
