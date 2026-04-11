"use client";

import { useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import toast from "react-hot-toast";
import { WUKONG_API_BASE, APSKY_API, generateTickId } from "@/utils/constants";

export default function CustomerLoginPage() {
  const router = useRouter();
  const didRun = useRef(false);

  const [pid, setPid] = useState("");
  const [identity, setIdentity] = useState("");
  const [loading, setLoading] = useState(true);
  const [connecting, setConnecting] = useState(false);

  // ⭐ avatar generator
  const getAvatar = (seed) =>
    `https://api.dicebear.com/7.x/adventurer-neutral/svg?seed=${encodeURIComponent(
      seed,
    )}`;

  // ⭐ cookie helpers
  const getCookie = (key) => {
    const match = document.cookie.match(new RegExp("(^| )" + key + "=([^;]+)"));
    return match ? decodeURIComponent(match[2]) : null;
  };

  const setCookie = (key, value) => {
    const expires = new Date(Date.now() + 365 * 86400000).toUTCString();
    document.cookie = `${key}=${encodeURIComponent(
      value,
    )}; expires=${expires}; path=/`;
  };

  // ⭐ AUTO LOGIN
  const autoLogin = async (uid, username, avatarUrl, platformId) => {
    setConnecting(true);

    const normalizedPid = platformId.toLowerCase();
    const normalizedUid = uid.toLowerCase();

    const token =
      Date.now().toString(36) + "-" + Math.random().toString(36).slice(2, 10);

    let finalAvatar = avatarUrl;

    toast.loading("Connecting to support…", { id: "login" });

    try {
      await fetch(`${WUKONG_API_BASE}/user/token`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          uid: normalizedUid,
          token,
          device_flag: 1,
          device_level: 1,
        }),
      });
    } catch {
      toast.error("Chat service unavailable", { id: "login" });
      setConnecting(false);
      return;
    }

    try {
      const res = await fetch(`${APSKY_API}/api/app/visitor/ensure`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          uid: normalizedUid,
          name: username,
          avatar: avatarUrl,
          platform: normalizedPid,
        }),
      });

      if (res.ok) {
        const visitor = await res.json();
        if (visitor?.avatar) finalAvatar = visitor.avatar;
      }
    } catch {}

    localStorage.setItem(
      `im_${normalizedUid}`,
      JSON.stringify({
        uid: normalizedUid,
        token,
        username,
        avatar: finalAvatar,
        role: "customer",
        pid: normalizedPid,
      }),
    );

    toast.success("Connected", { id: "login" });

    router.push(`/chat?uid=${encodeURIComponent(normalizedUid)}`);
  };

  // ⭐ INIT (NO useSearchParams)
  useEffect(() => {
    if (didRun.current) return;
    didRun.current = true;

    const sp = new URLSearchParams(window.location.search);
    const platformId = sp.get("pid");

    if (!platformId) {
      toast.error("Missing platform id");
      setLoading(false);
      return;
    }

    setPid(platformId);

    const savedIdentity = getCookie(`chat_identitties_${platformId}`);
    const savedAvatar = getCookie(`chat_avatar_${platformId}`);

    // ⭐ already have cookie → normal auto login
    if (savedIdentity) {
      autoLogin(
        savedIdentity,
        savedIdentity,
        savedAvatar || getAvatar(savedIdentity),
        platformId,
      );
      return;
    }

    // ⭐ NO COOKIE → generate tick id and auto login directly
    const tickId = generateTickId();
    const avatar = getAvatar(tickId);

    setCookie(`chat_identitties_${platformId}`, tickId);
    setCookie(`chat_avatar_${platformId}`, avatar);

    autoLogin(tickId, tickId, avatar, platformId);
  }, []);

  const startChat = () => {
    if (!identity.trim()) {
      toast.error("Enter phone number or email");
      return;
    }

    const avatar = getAvatar(identity);

    setCookie(`chat_identitties_${pid}`, identity);
    setCookie(`chat_avatar_${pid}`, avatar);

    autoLogin(identity, identity, avatar, pid);
  };

  // ⭐ LOADING SCREEN
  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-[#0f4fff] text-white text-sm">
        Preparing support chat…
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-[#0f4fff] via-[#3b82f6] to-[#7dd3fc] flex items-center justify-center px-4">
      <div className="w-full max-w-md">
        <div className="text-center text-white mb-8">
          <div className="text-4xl font-bold tracking-tight">Live Support</div>
          <div className="text-sm opacity-90 mt-3">
            Our team is ready to help you instantly
          </div>
        </div>

        <div className="backdrop-blur-xl bg-white/90 border border-white/40 shadow-2xl rounded-3xl p-8">
          <div className="space-y-6">
            <div>
              <div className="text-sm text-gray-600 mb-2">
                Phone number or Email
              </div>

              <input
                className="w-full rounded-2xl border border-gray-200 px-5 py-4 text-gray-900 placeholder-gray-400 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                placeholder="Enter your contact"
                value={identity}
                onChange={(e) => setIdentity(e.target.value)}
              />
            </div>

            <button
              onClick={startChat}
              disabled={connecting}
              className="w-full rounded-2xl bg-gradient-to-r from-[#1672f3] to-[#4fa3ff] text-white py-4 font-semibold text-sm hover:scale-[1.02] transition active:scale-[0.98] disabled:opacity-60"
            >
              {connecting ? "Connecting…" : "Start Chat"}
            </button>
          </div>

          <div className="text-center text-xs text-gray-400 mt-6">
            Your session will be remembered on this device
          </div>
        </div>
      </div>
    </div>
  );
}
