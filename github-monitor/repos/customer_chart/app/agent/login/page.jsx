"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { WUKONG_API_BASE } from "@/utils/constants";
import {
  unlockAudio,
  startRingtone,
  stopRingtone,
} from "@/utils/notificationAudio";
import { APSKY_API } from "../../../utils/constants";
import toast from "react-hot-toast";
const DEFAULT_AVATAR = "/user.png";

export default function SupportLogin() {
  const router = useRouter();

  // -----------------------------
  // CUSTOMER STATE
  // -----------------------------
  const [username, setUsername] = useState("");

  // -----------------------------
  // AGENT STATE
  // -----------------------------
  const [agentUsername, setAgentUsername] = useState("");
  const [agentPassword, setAgentPassword] = useState("");
  // const AGENT_UID = process.env.NEXT_PUBLIC_AGENT_UID;
  // ------------------------------------------------------------
  // HELPERS
  // ------------------------------------------------------------
  const generateId = () => {
    return (
      Date.now().toString(36) + "-" + Math.random().toString(36).slice(2, 10)
    );
  };

  // ------------------------------------------------------------
  // SHARED LOGIN FUNCTION
  // ------------------------------------------------------------
  const login = async (agent, toastId) => {
    const agentUid = agent.platform.toLowerCase();
    const token = agent.name; // browser-unique is enough
    const agentId = agent.name; // browser-unique is enough

    try {
      await fetch(`${WUKONG_API_BASE}/user/token`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          uid: agentUid,
          token,
          device_flag: 1,
          device_level: 1,
        }),
      });
    } catch {
      toast.error("Unable to connect to chat server", { id: toastId });
      return;
    }

    // save session (SOURCE = ABP)
    localStorage.setItem(
      `im_agent_${agentUid}`,
      JSON.stringify({
        uid: agentUid,
        platformId: agentUid,
        role: "agent",
        token,
        agentName: agent.name,
        agentId: agent.agentId,
        avatar: agent.avatarFullUrl, // ✅ REAL avatar
      }),
    );

    toast.success(`Welcome ${agent.name}`, { id: toastId });
    router.push("/agent/chat/");
  };

  // ------------------------------------------------------------
  // AGENT LOGIN (HARDCODED)
  // ------------------------------------------------------------
  function getAgentAvatar(seed) {
    return `https://api.dicebear.com/7.x/personas/svg?seed=${encodeURIComponent(
      seed,
    )}`;
  }

  const handleAgentLogin = async () => {
    const toastId = toast.loading("Signing in...");

    try {
      const res = await fetch(`${APSKY_API}/api/app/agent-auth/login`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          username: agentUsername,
          password: agentPassword,
        }),
      });

      if (!res.ok) {
        toast.error("Invalid username or password", { id: toastId });
        return;
      }

      const agent = await res.json();
      const agentUid = agent.platform.toLowerCase();
      // clear old sessions
      Object.keys(localStorage)
        .filter((k) => k.startsWith("im_agent_" + agentUid))
        .forEach((k) => localStorage.removeItem(k));

      Object.keys(localStorage)
        .filter((k) => k.startsWith("im_read_agent_" + agentUid))
        .forEach((k) => localStorage.removeItem(k));

      await login(agent, toastId);
    } catch {
      toast.error("Login failed", { id: toastId });
    }
  };

  useEffect(() => {
    Object.keys(localStorage)
      .filter((k) => k.startsWith("im_agent_"))
      .forEach((k) => localStorage.removeItem(k));

    Object.keys(localStorage)
      .filter((k) => k.startsWith("im_read_agent_"))
      .forEach((k) => localStorage.removeItem(k));
  }, []);

  // ------------------------------------------------------------
  // UI
  // ------------------------------------------------------------
  return (
    <div
      className="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-600 to-indigo-700 px-4"
      onClick={unlockAudio}
    >
      <div className="w-full max-w-4xl grid grid-cols-1 lg:grid-cols-2 rounded-3xl overflow-hidden shadow-2xl bg-white">
        {/* LEFT BRAND */}
        <div className="hidden lg:flex flex-col justify-between p-10 bg-gradient-to-br from-[#1672f3] to-indigo-600 text-white">
          <div className="space-y-6">
            <div className="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center text-lg font-bold">
              CS
            </div>

            <h1 className="text-3xl font-semibold leading-snug">
              Customer Support
              <br />
              Made Simple
            </h1>

            <p className="text-white/80 text-sm leading-relaxed">
              Start a conversation instantly or log in as a support agent to
              assist customers in real time.
            </p>
          </div>

          <p className="text-xs text-white/60">
            Secure • Fast • Real-time messaging
          </p>
        </div>

        {/* RIGHT LOGIN */}
        <div className="p-8 sm:p-10 space-y-8">
          {/* CUSTOMER
          <div className="space-y-4">
            <h2 className="text-lg font-semibold text-gray-800">
              Customer Access
            </h2>

            <input
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              placeholder="Enter your display name"
              className="w-full rounded-xl border border-gray-300 px-4 py-3 text-black focus:ring-2 focus:ring-blue-500 focus:outline-none"
            />

            <button
              onClick={() => {
                if (!username.trim()) return alert("Enter your name");
                login(username.trim().toLowerCase(), "customer");
              }}
              className="w-full h-12 rounded-xl bg-[#1672f3] text-white font-medium hover:bg-blue-700 transition"
            >
              Enter Chat
            </button>
          </div> */}

          {/* DIVIDER */}
          <div className="flex items-center gap-3">
            <div className="flex-1 h-px bg-gray-200" />
            <span className="text-xs text-gray-400 tracking-widest">AGENT</span>
            <div className="flex-1 h-px bg-gray-200" />
          </div>

          {/* AGENT */}
          <div className="space-y-4">
            <h2 className="text-lg font-semibold text-gray-800">Agent Login</h2>

            <input
              value={agentUsername}
              onChange={(e) => setAgentUsername(e.target.value)}
              placeholder="Agent username"
              className="w-full rounded-xl border border-gray-300 px-4 py-3 text-black focus:ring-2 focus:ring-blue-500 focus:outline-none"
            />

            <input
              type="password"
              value={agentPassword}
              onChange={(e) => setAgentPassword(e.target.value)}
              placeholder="Agent password"
              className="w-full rounded-xl border border-gray-300 px-4 py-3 text-black focus:ring-2 focus:ring-blue-500 focus:outline-none"
            />

            <button
              onClick={handleAgentLogin}
              className="w-full h-12 rounded-xl border-2 border-[#1672f3] text-[#1672f3] font-medium hover:bg-blue-50 transition"
            >
              Login as Agent
            </button>
          </div>

          <p className="text-center text-xs text-gray-400">
            Customers require no password
          </p>
        </div>
      </div>
    </div>
  );
}
