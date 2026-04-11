"use client";

import Image from "next/image";
import { useState, useContext, useEffect } from "react";
import { useTranslations } from "next-intl";
import SubmitButton from "@/components/shared/SubmitButton";
import { UIContext } from "@/contexts/UIProvider";
import { IMAGES } from "@/constants/images";
import { getMemberInfo } from "@/utils/utility";
import { toast } from "react-hot-toast";
import { useCreateDirectDownlineMutation } from "@/services/authApi";
const EyeIcon = ({ className = "w-5 h-5" }) => (
  <svg
    xmlns="http://www.w3.org/2000/svg"
    fill="none"
    viewBox="0 0 24 24"
    stroke="currentColor"
    className={className}
  >
    <path
      strokeLinecap="round"
      strokeLinejoin="round"
      strokeWidth={1.5}
      d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.64 0 8.577 3.01 9.964 7.183.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.64 0-8.577-3.01-9.964-7.178z"
    />
    <path
      strokeLinecap="round"
      strokeLinejoin="round"
      strokeWidth={1.5}
      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
    />
  </svg>
);

const EyeSlashIcon = ({ className = "w-5 h-5" }) => (
  <svg
    xmlns="http://www.w3.org/2000/svg"
    fill="none"
    viewBox="0 0 24 24"
    stroke="currentColor"
    className={className}
  >
    <path
      strokeLinecap="round"
      strokeLinejoin="round"
      strokeWidth={1.5}
      d="M3.98 8.223A10.477 10.477 0 002.036 12c1.387 4.173 5.324 7.183 9.964 7.183 1.73 0 3.356-.41 4.77-1.137M6.228 6.228A9.974 9.974 0 0112 4.5c4.64 0 8.577 3.01 9.964 7.183a10.523 10.523 0 01-4.296 5.038M6.228 6.228L3 3m3.228 3.228l12.544 12.544M9.878 9.878a3 3 0 104.244 4.244"
    />
  </svg>
);

export default function DirectOpenAccountTab() {
  const t = useTranslations();
  const { showPhonePicker } = useContext(UIContext);

  const [dial, setDial] = useState("+60");
  const [phone, setPhone] = useState("");
  const [account, setAccount] = useState(""); // username
  const [password, setPassword] = useState("");
  const [showPwd, setShowPwd] = useState(false);
  const [info, setInfo] = useState(null);
  const [createDownline] = useCreateDirectDownlineMutation();
  const { setLoading } = useContext(UIContext);
  const errors = {}; // placeholder. You can wire validation later.

  // read member from cookies
  useEffect(() => {
    const member = getMemberInfo();
    setInfo(member);
  }, []);

  const handleSubmit = async () => {
    if (!phone.trim() || !password.trim()) {
      toast.error(t("directOpen.errorMissing"));
      return;
    }

    const fullPhone = `${dial.replace("+", "")}${phone}`;

    const me = getMemberInfo(); // your own stored user info
    const memberId = me?.member_id;

    if (!memberId) {
      toast.error("Missing member_id");
      return;
    }

    const payload = {
      member_id: memberId,
      phone: fullPhone,
      password: password,
    };

    try {
      setLoading(true);

      const resp = await createDownline(payload).unwrap();

      if (resp?.status) {
        toast.success(resp.message || "Success!");
        setPhone("");
        setPassword("");
      } else {
        toast.error(resp?.message || "Something went wrong");
      }
    } catch (err) {
      toast.error(err?.data?.message || "Operation failed");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="px-4 pt-4">
      {/* ===== PHONE (same layout as RegisterPage) ===== */}
      <div className="mt-5 grid grid-cols-[110px_1fr] gap-3">
        {/* Country code box */}
        <button
          type="button"
          onClick={() =>
            showPhonePicker({
              value: dial,
              onSelect: (item) => setDial(item.dial),
            })
          }
        >
          <div className="rounded-xl border border-white/30 px-3 py-3">
            <div className="flex items-center justify-between">
              <span className="text-sm">{dial}</span>
              <Image
                src={IMAGES.yellowDown}
                alt="down"
                width={10}
                height={10}
                className="object-contain"
              />
            </div>
          </div>
        </button>

        {/* Phone input */}
        <div className="rounded-xl border border-white/30 px-3 py-3">
          <input
            type="tel"
            value={phone}
            onChange={(e) => setPhone(e.target.value)}
            placeholder={t("login.phone")}
            className="w-full bg-transparent text-sm outline-none placeholder:text-[#F8AF07]/80"
          />
        </div>
      </div>

      {/* ===== PHONE ERROR ROW ===== */}
      <div className="grid grid-cols-[110px_1fr] gap-3">
        <div className="px-3"></div>
        <div className="px-3">
          {errors?.phone && (
            <p className="text-[13px] text-red-400">{errors.phone}</p>
          )}
        </div>
      </div>

      {/* ===== PASSWORD ===== */}
      <div className="mt-4 relative rounded-xl border border-white/30 px-3 ">
        <input
          type={showPwd ? "text" : "password"}
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          placeholder={t("directOpen.passwordPlaceholder")}
          className="h-12 w-full bg-transparent pl-1 pr-11 text-sm text-white placeholder:text-white/55 outline-none"
        />

        <button
          type="button"
          onClick={() => setShowPwd((v) => !v)}
          className="absolute inset-y-0 right-3 my-auto h-8 w-8 grid place-items-center text-[#F8AF07] active:scale-95"
        >
          {showPwd ? <EyeIcon /> : <EyeSlashIcon />}
        </button>
      </div>

      {/* ===== SUBMIT ===== */}
      <div className="mt-8">
        <SubmitButton onClick={handleSubmit}>
          {t("register.submit")}
        </SubmitButton>
      </div>
    </div>
  );
}
