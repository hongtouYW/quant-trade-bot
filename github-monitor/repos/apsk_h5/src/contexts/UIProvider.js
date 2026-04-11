// /contexts/UIContext.js
"use client";

import React, {
  createContext,
  useState,
  useCallback,
  useMemo,
  useEffect,
} from "react";
import { useTranslations } from "next-intl";
import { toast } from "react-hot-toast";
import ConfirmDialog from "@/components/ConfirmDialog";
import DatePickerModal from "@/components/DatePickerModal";
import { IMAGES } from "@/constants/images";
import TransactionDialog from "@/components/TransactionDialog";
import PhoneCodePickerModal from "@/components/PhoneCodePickerModal";
import AvatarPicker from "@/components/AvatarPicker";
import WalletTransferModal from "@/components/WalletTransferModal";
import LogoutDialog from "@/components/LogoutDialog";

export const UIContext = createContext();

// central bank list (icons from IMAGES.bank)
const BANKS = [
  { id: "maybank", name: "Maybank2u", icon: IMAGES.bank.maybank },
  { id: "ambank", name: "AmBank", icon: IMAGES.bank.ambank },
  { id: "hongleong", name: "Hong Leong Bank", icon: IMAGES.bank.hongleong },
  { id: "affin", name: "Affin Bank", icon: IMAGES.bank.affin },
  { id: "hsbc", name: "HSBC Bank", icon: IMAGES.bank.hsbc },
  { id: "public", name: "Public Bank", icon: IMAGES.bank.public },
  { id: "rhb", name: "RHB Bank", icon: IMAGES.bank.rhb },
  { id: "muamalat", name: "Bank Muamalat", icon: IMAGES.bank.muamalat },
];

export function UIProvider({ children }) {
  const t = useTranslations();
  const [loading, setLoading] = useState(false);
  const [isGlobalLoading, setIsGlobalLoading] = useState(false);

  // toast
  const [message, setMessage] = useState("");
  const [type, setType] = useState("success");
  const [messageVisible, setMessageVisible] = useState(false);

  // const showMessage = useCallback(
  //   (msgKey, msgType = "success", timeout = 3000) => {
  //     setMessage(msgKey);
  //     setType(msgType);
  //     setMessageVisible(true);
  //     setTimeout(() => setMessageVisible(false), timeout);
  //   },
  //   []
  // );

  // ------------------------- LOGOUT DIALOG -------------------------
  const [logoutConfig, setLogoutConfig] = useState(null);
  useEffect(() => {
    function onAuthEvent(e) {
      const { type } = e?.detail ?? {};

      if (type === "force_logout") {
        // SHOW YOUR LOGOUT DIALOG
        setLogoutConfig({
          titleKey: "common.sessionExpired",
          messageKey: "common.sessionExpiredMessage",
          confirmKey: "common.ok",
        });
        return;
      }

      if (type === "network_error") {
        // SHOW NORMAL TOAST
        toast.error(t("common.networkError"));
        return;
      }
    }

    window.addEventListener("AUTH_EVENT", onAuthEvent);
    return () => window.removeEventListener("AUTH_EVENT", onAuthEvent);
  }, [t]);

  // confirm
  const [confirmConfig, setConfirmConfig] = useState(null);
  const showConfirm = useCallback((config) => setConfirmConfig(config), []);

  // date picker
  const [datePickerConfig, setDatePickerConfig] = useState(null);
  const showDatePicker = useCallback((config) => {
    setDatePickerConfig({
      mode: config?.mode ?? "range",
      type: config?.type ?? "date",
      format: config?.format,
      value: config?.value ?? new Date(),
      start: config?.start ?? new Date(),
      end: config?.end ?? new Date(),
      onApply: config?.onApply,
      onCancel: config?.onCancel,
    });
  }, []);
  const closeDatePicker = useCallback(() => setDatePickerConfig(null), []);

  /** ---- bank selection slice (NEW) ---- **/
  const [bankQuery, setBankQuery] = useState("");
  const [selectedBankId, setSelectedBankId] = useState(null);

  const filteredBanks = useMemo(() => {
    const q = bankQuery.trim().toLowerCase();
    if (!q) return BANKS;
    return BANKS.filter(
      (b) => b.name.toLowerCase().includes(q) || b.id.toLowerCase().includes(q)
    );
  }, [bankQuery]);

  const getSelectedBank = useCallback(
    () => BANKS.find((b) => b.id === selectedBankId) ?? null,
    [selectedBankId]
  );
  /** ----------------------------------- **/

  // success sheet state
  const [successSheet, setSuccessSheet] = useState({
    visible: false,
    titleKey: "",
    descKey: "",
    amount: null,
    imageSrc: null,
    onClose: undefined, // 👈 optional callback
    redirectTo: undefined, // 👈 optional route
  });

  /** ---- phone code picker ---- **/
  const [phonePickerOpen, setPhonePickerOpen] = useState(false);
  const [phonePickerConfig, setPhonePickerConfig] = useState(null);
  const showPhonePicker = useCallback((config) => {
    setPhonePickerConfig({
      value: config?.value ?? "+60",
      onSelect: config?.onSelect,
    });
    setPhonePickerOpen(true);
  }, []);
  /** --------------------------- **/

  /** ---- avatar picker ---- **/
  // const [avatarConfig, setAvatarConfig] = useState(null);
  // const showAvatarPicker = useCallback((config) => {
  //   setAvatarConfig({
  //     onSelect: config?.onSelect,
  //   });
  // }, []);
  // const closeAvatarPicker = useCallback(() => setAvatarConfig(null), []);
  /** ------------------------ **/

  /** ---- wallet transfer modal ---- **/
  const [transferProps, setTransferProps] = useState(null);
  const showTransfer = useCallback(
    (props) => setTransferProps({ open: true, ...props }),
    []
  );
  const hideTransfer = useCallback(() => setTransferProps(null), []);

  return (
    <UIContext.Provider
      value={{
        // loading
        loading,
        setLoading,

        //global loading
        isGlobalLoading,
        setIsGlobalLoading,

        logoutConfig,
        setLogoutConfig,
        // // toast
        // message,
        // type,
        // messageVisible,
        // showMessage,

        // confirm
        confirmConfig,
        showConfirm,
        setConfirmConfig,

        // date picker
        datePickerConfig,
        showDatePicker,
        closeDatePicker,

        // bank selection (NEW)
        banks: BANKS,
        filteredBanks,
        bankQuery,
        setBankQuery,
        selectedBankId,
        setSelectedBankId,
        getSelectedBank,

        // success sheet
        successSheet,
        setSuccessSheet,

        phonePickerOpen,
        setPhonePickerOpen,
        phonePickerConfig,
        showPhonePicker,

        showTransfer,
        hideTransfer,

        //avatar picker
        // avatar picker
        // avatarConfig,
        // setAvatarConfig,
        // showAvatarPicker,
        // closeAvatarPicker,
      }}
    >
      {children}

      {/* overlays */}
      <ConfirmDialog />
      <DatePickerModal />
      <TransactionDialog />
      <PhoneCodePickerModal />
      <WalletTransferModal
        open={!!transferProps?.open}
        gmemberId={transferProps?.gmemberId}
        providerName={transferProps?.providerName}
        providerLoginId={transferProps?.providerLoginId}
        url={transferProps?.url}
        gameId={transferProps?.gameId}
        providerCategory={transferProps?.providerCategory}
        onClose={hideTransfer}
      />
      <LogoutDialog></LogoutDialog>
      {/* <AvatarPicker /> */}
    </UIContext.Provider>
  );
}
