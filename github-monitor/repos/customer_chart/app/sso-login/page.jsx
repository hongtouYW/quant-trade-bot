"use client";

import { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import { WUKONG_API_BASE } from "@/utils/constants";
import { APSKY_API } from "../../utils/constants";
import toast from "react-hot-toast";
import md5 from "md5";

const DEFAULT_AVATAR = "/user.png";

export default function SSOLogin() {
  const router = useRouter();

  const [username, setUsername] = useState("");
  const [avatarFile, setAvatarFile] = useState(null);
  const [avatarPreview, setAvatarPreview] = useState("");
  const [autoLogging, setAutoLogging] = useState(false);
  const [result, setResult] = useState(null);
  const [error, setError] = useState(null);

  function getCustomerAvatar(seed) {
    return `https://api.dicebear.com/7.x/adventurer-neutral/svg?seed=${encodeURIComponent(
      seed,
    )}`;
  }

  useEffect(() => {
    if (typeof window === "undefined") return;

    const params = new URLSearchParams(window.location.search);
    const rawUid = params.get("uid");
    const platformId = params.get("pid");
    const datetime = params.get("datetime");
    const hashkey = params.get("hashkey");

    const SECRET = process.env.NEXT_PUBLIC_CHAT_SECRET_KEY;

    // ⭐ SINGLE HASH VALIDATION
    if (platformId && rawUid && datetime && hashkey) {
      const diff = Math.abs(Date.now() - Number(datetime));

      const localHash = md5(`${platformId}|${rawUid}|${datetime}|${SECRET}`);

      console.log("LOCAL HASH =", localHash);
      console.log("URL HASH =", hashkey);

      if (localHash !== hashkey) {
        alert("Invalid login link");
        return;
      }
    }

    // ⭐ NEW HASH VALIDATION (added only)
    if (platformId && rawUid && datetime && hashkey) {
      const diff = Math.abs(Date.now() - Number(datetime));

      if (diff > 10000) {
        alert("Login link expired");
        return;
      }

      const localHash = md5(`${platformId}|${rawUid}|${datetime}|${SECRET}`);

      if (localHash !== hashkey) {
        alert("Invalid login link");
        return;
      }
    }

    // ------------------------------------------------------------
    // ORIGINAL LOGIC (UNCHANGED)
    // ------------------------------------------------------------

    if (!platformId && !rawUid) {
      alert("Missing parameters. Please try again.");
      return;
    }

    if (rawUid && !platformId) {
      alert("Platform ID is missing. Please try again.");
      return;
    }

    if (rawUid && platformId) {
      setAutoLogging(true);

      const base = platformId.includes("_") ? platformId.split("_")[0] : "";

      const username = base + "_" + rawUid.trim().toLowerCase();

      const combinedUid = `${platformId}:${username}`;
      const avatar = getCustomerAvatar(combinedUid);

      autoLogin(combinedUid, username, avatar, "customer", platformId);

      return;
    }

    if (platformId && !rawUid) {
      const registerByPid = async () => {
        try {
          const url = `${APSKY_API}/api/app/agent-account/register-by-pid?pid=${encodeURIComponent(
            platformId,
          )}`;
          const res = await fetch(url, { method: "GET" });
          const data = await res.json();

          if (res.ok) {
            setResult(data);
          } else {
            alert("Registration failed. Please try again.");
          }
        } catch {
          alert("Something went wrong. Please try again.");
        }
      };

      registerByPid();
    }

    if (!platformId && !rawUid) {
      alert("Something error");
    }
  }, []);

  const uploadAvatar = async (file) => {
    if (!file) return DEFAULT_AVATAR;
    return URL.createObjectURL(file);
  };

  const generateId = () => {
    return (
      Date.now().toString(36) + "-" + Math.random().toString(36).slice(2, 10)
    );
  };

  const autoLogin = async (uid, username, avatarUrl, role, platformId) => {
    setAutoLogging(true);
    toast.loading("Connecting to support…", { id: "login" });

    platformId = platformId.toLowerCase();
    uid = uid.toLowerCase();

    const token = generateId();

    let finalAvatar = avatarUrl;

    try {
      await fetch(`${WUKONG_API_BASE}/user/token`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          uid,
          token,
          device_flag: 1,
          device_level: 1,
        }),
      });
    } catch {
      toast.error("Chat service unavailable", { id: "login" });
      setAutoLogging(false);
      return;
    }

    try {
      const res = await fetch(`${APSKY_API}/api/app/visitor/ensure`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          uid,
          name: username,
          avatar: avatarUrl,
          platform: platformId,
        }),
      });

      if (res.ok) {
        const visitor = await res.json();
        if (visitor?.avatar) {
          finalAvatar = visitor.avatar;
        }
      }
    } catch {
      toast("Visitor sync failed", { icon: "⚠️" });
    }

    try {
      const url = `${APSKY_API}/api/app/agent-account/register-by-pid?pid=${encodeURIComponent(
        platformId,
      )}`;
      await fetch(url, { method: "GET" });
    } catch {}

    Object.keys(localStorage)
      .filter((k) => k.startsWith(`im_read_customer_${platformId}`))
      .forEach((k) => localStorage.removeItem(k));

    const storageKey = role === "agent" ? `im_agent_${uid}` : `im_${uid}`;

    localStorage.setItem(
      storageKey,
      JSON.stringify({
        uid,
        token,
        username,
        avatar: finalAvatar,
        role,
        pid: platformId,
      }),
    );

    toast.success("Connected", { id: "login" });

    router.push(`/chat?uid=${encodeURIComponent(uid)}`);
  };

  if (autoLogging) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-[#1672f3]">
        <div className="w-64 space-y-3">
          <div className="w-full h-2 bg-white/30 rounded-full overflow-hidden">
            <div className="h-full w-1/2 bg-white animate-pulse" />
          </div>
          <p className="text-center text-sm text-white/80">Connecting…</p>
        </div>
      </div>
    );
  }

  return (
    <>
      {result ? (
        <div className="max-w-md mx-auto bg-white shadow-lg rounded-xl p-6 space-y-5">
          <div className="text-center">
            <div className="text-green-600 font-semibold text-xl">
              Register Successful
            </div>
            <p className="text-sm text-gray-500 mt-1">
              Your account has been created successfully.
            </p>
          </div>

          <div className="bg-gray-50 rounded-lg divide-y">
            <div className="flex justify-between py-3 px-4">
              <span className="font-medium text-gray-600">Platform</span>
              <span className="text-gray-800 break-all">
                {result?.platform ?? "-"}
              </span>
            </div>

            <div className="flex justify-between py-3 px-4">
              <span className="font-medium text-gray-600">Username</span>
              <span className="text-gray-800 break-all">
                {result?.username ?? "-"}
              </span>
            </div>

            <div className="py-3 px-4 space-y-2">
              <div className="font-medium text-gray-600">
                Customer Chat Example
              </div>

              <div className="text-sm text-gray-500">
                <span className="font-medium">pid</span> = Platform ID
                <br />
                <span className="font-medium">uid</span> = Customer ID
              </div>

              <div className="text-sm text-blue-600 break-all bg-gray-100 p-2 rounded">
                {`https://chatclient.apsk.cc/login?pid=${result?.platform}&uid=60123456751`}
              </div>
            </div>
          </div>
        </div>
      ) : (
        <div className="min-h-screen flex flex-col items-center justify-center bg-gray-50 px-4">
          <div className="mb-6 text-center">
            <div className="text-lg font-semibold text-gray-800">
              Customer Support
            </div>
            <div className="text-sm text-gray-500 mt-1">Initializing chat…</div>
          </div>

          <div className="w-full max-w-sm">
            <div className="h-2 w-full bg-gray-200 rounded-full overflow-hidden">
              <div className="h-full w-1/2 bg-[#1672f3] animate-loading-bar rounded-full" />
            </div>
          </div>
        </div>
      )}
    </>
  );
}
