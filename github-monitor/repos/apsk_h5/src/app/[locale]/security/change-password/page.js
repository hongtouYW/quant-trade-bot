"use client";

import { useState, useMemo, useContext, useEffect } from "react";
import Image from "next/image";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { useDispatch } from "react-redux";
import toast from "react-hot-toast";

import { IMAGES } from "@/constants/images";
import SubmitButton from "@/components/shared/SubmitButton";
import { EyeWhiteIcon, EyeWhiteSlashIcon } from "@/components/shared/EyeIcon";
import {
  useChangeNewPasswordMutation,
  useGetMemberViewQuery,
} from "@/services/authApi";
import { UIContext } from "@/contexts/UIProvider";
import { extractError, getMemberInfo, PasswordChecking } from "@/utils/utility";
import { setChangePwdPayload } from "@/store/slice/changePwdSlice";

export default function ChangePassword() {
  const t = useTranslations();
  const router = useRouter();
  const { setLoading } = useContext(UIContext);

  const dispatch = useDispatch();

  const [info, setInfo] = useState(null);
  useEffect(() => {
    setInfo(getMemberInfo());
  }, []);

  // ✅ background fetch of memberView
  const { data: user } = useGetMemberViewQuery(
    info ? { member_id: info.member_id } : undefined,
    { skip: !info?.member_id }
  );

  const [changeNewPassword] = useChangeNewPasswordMutation();

  const [oldPwd, setOldPwd] = useState("");
  const [newPwd, setNewPwd] = useState("");
  const [confirmPwd, setConfirmPwd] = useState("");

  const [show, setShow] = useState({ old: false, nw: false, cf: false });
  const [touched, setTouched] = useState({ old: false, nw: false, cf: false });

  const toggle = (k) => setShow((s) => ({ ...s, [k]: !s[k] }));

  // strength
  const strength = useMemo(() => {
    let s = 0;
    if (newPwd.length >= 6) s++;
    if (/[0-9]/.test(newPwd)) s++;
    if (/[A-Za-z]/.test(newPwd)) s++;
    if (/[^A-Za-z0-9]/.test(newPwd)) s++;
    return s;
  }, [newPwd]);

  // validation
  const errors = useMemo(() => {
    const e = { old: "", nw: "", cf: "" };
    if (touched.nw) {
      const valid = PasswordChecking(newPwd); // ✅ call your function

      if (!valid) {
        e.nw = t("pwd.errors.format", { min: 6, max: 12 });
      }
    }
    if (touched.cf) {
      if (confirmPwd !== newPwd) e.cf = t("pwd.errors.mismatch");
    }
    if (touched.old) {
      if (!oldPwd) e.old = t("pwd.errors.required");
    }
    return e;
  }, [touched, oldPwd, newPwd, confirmPwd, t]);

  const hasErrors = !!(errors.old || errors.nw || errors.cf);

  const handleNext = async () => {
    setTouched({ old: true, nw: true, cf: true });
    if (hasErrors || !oldPwd || !newPwd || !confirmPwd) return;

    if (!info?.member_id) {
      toast.error(t("pwd.errors.failed"));
      return;
    }

    setLoading(true);
    try {
      // 1. Change password
      const result = await changeNewPassword({
        member_id: info.member_id,
        newpassword: newPwd,
        oldpassword: oldPwd,
      }).unwrap();

      if (result?.status) {
        // toast.success(result.message);

        // 2. Save background fetched binds
        if (user?.data) {
          dispatch(
            setChangePwdPayload({
              memberId: user.data.member_id,
              bindphone: user.data.bindphone,
              bindemail: user.data.bindemail,
              bindgoogle: user.data.bindgoogle,
              phone: user.data.phone,
              newPw: newPwd,
              email: user.data.email,
            })
          );
        }

        // 3. Redirect
        router.push("/security/change-password/verify");
      } else {
        toast.error(result?.message || t("pwd.errors.failed"));
      }
    } catch (err) {
      const result = extractError(err);
      if (result.type === "validation") {
        toast.error(Object.values(result.fieldErrors).join(", "));
      } else {
        toast.error(result.message);
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#01133C] text-white">
      {/* Header */}
      <div className="relative flex items-center h-14 px-4 ">
        <button onClick={() => router.back()} className="z-10 cursor-pointer">
          <Image src={IMAGES.arrowLeft} alt="back" width={22} height={22} />
        </button>
        <h1 className="absolute inset-x-0 text-lg font-semibold text-center">
          {t("pwd.title")}
        </h1>
      </div>

      <div className="px-4 pb-10">
        <h2 className="mt-1 mb-4 text-[22px] font-bold">{t("pwd.enterPwd")}</h2>

        {/* Old password */}
        <label className="mb-1 block text-sm font-semibold">
          {t("pwd.old")}
        </label>
        <div className="group flex items-center rounded-md border border-white/30 px-3 mb-3">
          <input
            type={show.old ? "text" : "password"}
            value={oldPwd}
            onChange={(e) => setOldPwd(e.target.value)}
            onBlur={() => setTouched((s) => ({ ...s, old: true }))}
            placeholder="********"
            className="flex-1 bg-transparent py-3 text-white placeholder-white/40 outline-none"
          />
          <button onClick={() => toggle("old")} className="p-1">
            {show.old ? <EyeWhiteSlashIcon /> : <EyeWhiteIcon />}
          </button>
        </div>
        {errors.old && (
          <p className="mt-1 text-xs text-[#FF6B6B]">{errors.old}</p>
        )}

        {/* New password */}
        <label className="mb-1 block text-sm font-semibold">
          {t("pwd.new")}
        </label>
        <div className="group flex items-center rounded-md border border-white/30 px-3 mb-3">
          <input
            type={show.nw ? "text" : "password"}
            value={newPwd}
            onChange={(e) => setNewPwd(e.target.value)}
            onBlur={() => setTouched((s) => ({ ...s, nw: true }))}
            placeholder="********"
            className="flex-1 bg-transparent py-3 text-white placeholder-white/40 outline-none"
          />
          <button onClick={() => toggle("nw")} className="p-1">
            {show.nw ? <EyeWhiteSlashIcon /> : <EyeWhiteIcon />}
          </button>
        </div>
        <div className="mt-2">
          <div className="mb-1 text-xs text-white/70">{t("pwd.strength")}</div>
          <div className="flex gap-2">
            {[0, 1, 2, 3].map((i) => (
              <div
                key={i}
                className={`h-2 flex-1 rounded-sm ${
                  i < strength ? "bg-[#21C25E]" : "bg-white/30"
                }`}
              />
            ))}
          </div>
        </div>
        {errors.nw && (
          <p className="mt-1 text-xs text-[#FF6B6B]">{errors.nw}</p>
        )}

        {/* Confirm password */}
        <label className="mb-1 block text-sm font-semibold">
          {t("pwd.confirm")}
        </label>
        <div className="group flex items-center rounded-md border border-white/30 px-3 mb-3">
          <input
            type={show.cf ? "text" : "password"}
            value={confirmPwd}
            onChange={(e) => setConfirmPwd(e.target.value)}
            onBlur={() => setTouched((s) => ({ ...s, cf: true }))}
            placeholder="********"
            className="flex-1 bg-transparent py-3 text-white placeholder-white/40 outline-none"
          />
          <button onClick={() => toggle("cf")} className="p-1">
            {show.cf ? <EyeWhiteSlashIcon /> : <EyeWhiteIcon />}
          </button>
        </div>
        {errors.cf && (
          <p className="mt-1 text-xs text-[#FF6B6B]">{errors.cf}</p>
        )}

        <SubmitButton onClick={handleNext} className="mt-6">
          {t("pwd.next")}
        </SubmitButton>
      </div>
    </div>
  );
}
