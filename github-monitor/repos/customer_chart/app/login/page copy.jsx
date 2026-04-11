"use client";

import { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import { WUKONG_API_BASE } from "@/utils/constants";
import { APSKY_API } from "../../utils/constants";
import toast from "react-hot-toast";
const DEFAULT_AVATAR = "/user.png";

export default function SupportLogin() {
  const router = useRouter();

  const [username, setUsername] = useState("");
  const [avatarFile, setAvatarFile] = useState(null);
  const [avatarPreview, setAvatarPreview] = useState("");
  const [autoLogging, setAutoLogging] = useState(false);

  function getCustomerAvatar(seed) {
    return `https://api.dicebear.com/7.x/adventurer-neutral/svg?seed=${encodeURIComponent(
      seed,
    )}`;
  }

  // ------------------------------------------------------------
  // AUTO LOGIN USING URL PARAMS (customer)
  // ------------------------------------------------------------
  useEffect(() => {
    if (typeof window === "undefined") return;

    const params = new URLSearchParams(window.location.search);
    const rawUid = params.get("uid");
    const platformId = params.get("pid");

    // ❌ Case 3: no pid and no uid
    if (!platformId && !rawUid) {
      alert("Missing parameters. Please try again.");
      return;
    }

    // ❌ Case: has uid but no pid (invalid)
    if (rawUid && !platformId) {
      alert("Platform ID is missing. Please try again.");
      return;
    }

    // ✅ Case 1: pid + uid
    if (rawUid && platformId) {
      setAutoLogging(true);

      const base = platformId.includes("_") ? platformId.split("_")[0] : "";

      const username = base + "_" + rawUid.trim().toLowerCase();

      const combinedUid = `${platformId}:${username}`;
      const avatar = getCustomerAvatar(combinedUid);

      autoLogin(combinedUid, username, avatar, "customer", platformId);

      return;
    }

    // ✅ Case 2: pid only
    if (platformId && !rawUid) {
      const registerByPid = async () => {
        try {
          const url = `${APSKY_API}/api/app/agent-account/register-by-pid?pid=${encodeURIComponent(platformId)}`;
          const res = await fetch(url, { method: "GET" });

          if (res.ok) {
            alert("Registration successful.");
          } else {
            alert("Registration failed. Please try again.");
          }
        } catch (error) {
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

  // ------------------------------------------------------------
  // SHARED LOGIN FUNCTION
  // ------------------------------------------------------------

  const generateId = () => {
    return (
      Date.now().toString(36) + "-" + Math.random().toString(36).slice(2, 10)
    );
  };
  const toFullAvatarUrl = (avatar) => {
    if (!avatar) return `${window.location.origin}${DEFAULT_AVATAR}`;

    if (avatar.startsWith("http://") || avatar.startsWith("https://")) {
      return avatar;
    }

    if (avatar.startsWith("/")) {
      return `${window.location.origin}${avatar}`;
    }

    return `${window.location.origin}${DEFAULT_AVATAR}`;
  };

  const autoLogin = async (uid, username, avatarUrl, role, platformId) => {
    setAutoLogging(true);
    toast.loading("Connecting to support…", { id: "login" });
    platformId = platformId.toLowerCase();
    uid = uid.toLowerCase();

    const token = generateId();

    // DiceBear already full https URL
    let finalAvatar = avatarUrl;

    // 1️⃣ WuKong token
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

    // 2️⃣ Ensure Visitor (ABP = source of truth)
    try {
      const res = await fetch(`${APSKY_API}/api/app/visitor/ensure`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          uid,
          name: username,
          avatar: avatarUrl, // DiceBear candidate
          platform: platformId,
        }),
      });

      if (res.ok) {
        const visitor = await res.json();
        if (visitor?.avatar) {
          finalAvatar = visitor.avatar; // ✅ ABP wins
        }
      }
    } catch {
      toast("Visitor sync failed", { icon: "⚠️" });
    }
    try {
      const url = `${APSKY_API}/api/app/agent-account/register-by-pid?pid=${encodeURIComponent(platformId)}`;
      await fetch(url, { method: "GET" });
    } catch {}

    Object.keys(localStorage)
      .filter((k) => k.startsWith(`im_read_customer_${platformId}`))
      .forEach((k) => localStorage.removeItem(k));

    // 3️⃣ Save session (ONLY final avatar)
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

    // 4️⃣ Enter chat
    router.push(`/chat?uid=${encodeURIComponent(uid)}`);
  };

  // ------------------------------------------------------------
  // AUTO LOGIN LOADING
  // ------------------------------------------------------------
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

  // ------------------------------------------------------------
  // UI
  // ------------------------------------------------------------
  return (
    
    <div className="min-h-screen flex flex-col items-center justify-center bg-gray-50 px-4">
      {/* Logo / Title (optional) */}
      <div className="mb-6 text-center">
        <div className="text-lg font-semibold text-gray-800">
          Customer Support
        </div>
        <div className="text-sm text-gray-500 mt-1">Initializing chat…</div>
      </div>

      {/* Loading bar */}
      <div className="w-full max-w-sm">
        <div className="h-2 w-full bg-gray-200 rounded-full overflow-hidden">
          <div className="h-full w-1/2 bg-[#1672f3] animate-loading-bar rounded-full" />
        </div>
      </div>
    </div>

    // <div className="min-h-screen grid grid-cols-1 lg:grid-cols-2">
    //   {/* LEFT BRAND PANEL */}
    //   <div className="hidden lg:flex flex-col justify-center px-16 bg-[#1672f3] text-white">
    //     <div className="max-w-md space-y-6">
    //       <div className="flex items-center gap-3">
    //         <div className="w-10 h-10 rounded-lg bg-white/20 flex items-center justify-center font-bold">
    //           CS
    //         </div>
    //         <span className="text-xl font-semibold">Customer Support</span>
    //       </div>

    //       <h2 className="text-3xl font-semibold leading-snug">
    //         Support that’s fast,
    //         <br />
    //         friendly, and human.
    //       </h2>

    //       <p className="text-white/80">
    //         Chat instantly with our support team or sign in as an agent to
    //         assist customers.
    //       </p>
    //     </div>
    //   </div>

    //   {/* RIGHT LOGIN CARD */}
    //   <div className="flex items-center justify-center bg-gray-50 px-4">
    //     <div className="w-full max-w-md bg-white rounded-2xl shadow-xl p-8 space-y-6">
    //       <div className="space-y-1">
    //         <h1 className="text-2xl font-semibold text-gray-800">
    //           Customer Support
    //         </h1>
    //         <p className="text-sm text-gray-500">
    //           Enter chat as a customer or agent
    //         </p>
    //       </div>

    //       {/* Avatar */}
    //       <div className="flex justify-center">
    //         <label className="relative cursor-pointer group">

    //           <div className="w-24 h-24 rounded-full bg-gray-200 overflow-hidden flex items-center justify-center ring-4 ring-blue-100 group-hover:ring-blue-300 transition">
    //             <img
    //               src={avatarPreview || DEFAULT_AVATAR}
    //               alt="avatar"
    //               className="w-full h-full object-cover"
    //             />
    //           </div>
    //         </label>
    //       </div>

    //       {/* Name */}
    //       <div className="space-y-1">
    //         <label className="text-sm text-gray-600">Display name</label>
    //         <input
    //           value={username}
    //           onChange={(e) => setUsername(e.target.value)}
    //           placeholder="Enter your name"
    //           className="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-black focus:ring-2 focus:ring-blue-500 focus:outline-none"
    //         />
    //       </div>

    //       {/* Buttons */}
    //       <div className="space-y-3">
    //         {/* Customer */}
    //         <button
    //           onClick={async () => {
    //             if (!username.trim()) return alert("Enter your name");
    //             const uid = username.trim().toLowerCase();
    //             const avatarUrl = await uploadAvatar(avatarFile);
    //             autoLogin(uid, avatarUrl, "customer");
    //           }}
    //           className="w-full h-11 rounded-lg bg-[#1672f3] text-white font-medium hover:bg-blue-700 transition"
    //         >
    //           Start Chat (Customer)
    //         </button>

    //         {/* Agent */}
    //         <button
    //           onClick={() => {
    //             router.push("http://72.61.148.252:8888/agent/chat/list");
    //           }}
    //           className="w-full h-11 rounded-lg border border-[#1672f3] text-[#1672f3] font-medium hover:bg-blue-50 transition"
    //         >
    //           Agent Login
    //         </button>
    //       </div>

    //       <p className="text-center text-xs text-gray-400">
    //         No password • Instant access
    //       </p>
    //     </div>
    //   </div>
    // </div>
  );
}
