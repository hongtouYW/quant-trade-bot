"use client";

import { useContext, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";
import Image from "next/image";
import Link from "next/link";
import SubmitButton from "@/components/shared/SubmitButton";
import { useDispatch } from "react-redux";
import { setEmailPayload } from "@/store/slice/emailVerifySlice";
import { extractError, getMemberInfo, isValidEmail } from "@/utils/utility";
import { useBindEmailMutation } from "@/services/authApi";
import { UIContext } from "@/contexts/UIProvider";
import { toast } from "react-hot-toast";
import { useGetMemberViewQuery } from "@/services/authApi";

export default function BindEmail() {
  const t = useTranslations();
  const router = useRouter();
  const [bindEmail, { data, error, isLoading, isSuccess }] =
    useBindEmailMutation();
  const [email, setEmail] = useState("");
  const dispatch = useDispatch();
  const { setLoading } = useContext(UIContext);
  const [errors, setErrors] = useState({});

  const [info, setInfo] = useState(null);
  // read cookies on client
  useEffect(() => {
    const member = getMemberInfo();
    setInfo(member);
  }, []);

  const { data: user } = useGetMemberViewQuery(
    info ? { member_id: info.member_id } : undefined, // body passed only when info exists
    {
      skip: !info?.member_id, // avoid running with empty info
    }
  );

  useEffect(() => {
    if (user != null) {
      setEmail(user?.data.email);
    }
  }, [user]);

  const submitReset = async (e) => {
    e.preventDefault();

    const newErrors = {};
    const _email = (email || "").trim().toLowerCase();

    if (!_email) {
      newErrors.email = t("bindEmail.errors.emailRequired");
    } else if (!isValidEmail(_email)) {
      newErrors.email = t("bindEmail.errors.emailInvalid");
    }

    setErrors(newErrors);
    if (Object.keys(newErrors).length === 0) {
      setLoading(true); // always reset
      try {
        const payload = {
          email,
          member_id: info?.member_id,
        };

        const result = await bindEmail(payload).unwrap();

        if (result?.status) {
          dispatch(
            setEmailPayload({
              otpcode: result?.otpcode || "", // optional prefill
              memberId: info?.member_id || "", // or whatever you have
              email: email,
            })
          );

          router.push("/security/email/otp-email"); // go to your OTP verify page

          // localStorage.setItem("token", result.token);
          // router.push("/home");
        }
      } catch (err) {
        const result = extractError(err);

        if (result.type === "validation") {
          // Show under each field
          setErrors(result.fieldErrors);
        } else {
          toast.error(result.message);

          // Toast or global alert
        }
      } finally {
        setLoading(false); // always reset
      }
    } else {
      // validation failed → stop loading too
      setLoading(false);
    }
  };

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
          {t("bindEmail.title")}
        </h1>
      </div>

      {/* Body */}
      <div className="px-6 mt-6 flex-1">
        <h2 className="text-lg font-bold">{t("bindEmail.heading")}</h2>
        {/* <p className="text-sm text-white/60 mt-2">{t("bindEmail.subtitle")}</p> */}

        {/* Form */}
        <div className="mt-6 flex gap-3">
          <input
            type="email"
            inputMode="email"
            autoComplete="email"
            value={email ?? ""}
            onChange={(e) => setEmail(e.target.value)}
            placeholder={t("bindEmail.placeholder")}
            className="w-full px-3 py-2 border border-white/40 rounded-md bg-transparent outline-none text-sm"
          />
        </div>

        <div className="px-3">
          {errors.email && (
            <p className="col-span-2 text-[13px] text-red-400">
              {errors.email}
            </p>
          )}
        </div>

        {/* Button */}
        <div className=" pb-6 pt-6">
          <SubmitButton disabled={isLoading} onClick={submitReset}>
            {t("bindEmail.verify")}
          </SubmitButton>
        </div>
      </div>
    </div>
  );
}
