"use client";

import { useEffect, useState } from "react";

import {
  PLATFORM_UID,
  AGENT_TOKEN,
  WUKONG_WS_ADDR,
  WUKONG_API_BASE,
} from "@/utils/constants";

import { ConnectStatus } from "wukongimjssdk";
import { initWukong } from "../../../utils/wukong-customer";

export default function AgentChat({ params }) {
  const customerUid = params.uid;

  const [status, setStatus] = useState("Connecting...");
  const [messages, setMessages] = useState([]);
  const [input, setInput] = useState("");
  const [client, setClient] = useState(null);

  useEffect(() => {
    async function start() {
      // ----------------------------------------
      // 1) Register agent (MUST)
      // ----------------------------------------
      try {
        await fetch(`${WUKONG_API_BASE}/user/token`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            uid: PLATFORM_UID,
            token: AGENT_TOKEN,
            device_flag: 1,
            device_level: 1,
          }),
        });
      } catch (err) {
        console.error("Agent registration failed:", err);
        setStatus("Register failed");
        return;
      }

      // ----------------------------------------
      // 2) Connect to IM
      // ----------------------------------------
      const c = initWukong({
        uid: PLATFORM_UID,
        token: AGENT_TOKEN,
        // addr: WUKONG_WS_ADDR,
        connectAddrCallback: () => process.env.NEXT_PUBLIC_WUKONG_WS_ADDR,
        onStatusChange: (s, reason) => {
          if (s === ConnectStatus.Connected) {
            setStatus("Connected");
          } else {
            setStatus("Disconnected (" + (reason || "-") + ")");
          }
        },

        onMessage: (msg) => {
          const text = msg.content?.content || "";
          setMessages((prev) => [
            ...prev,
            {
              id: msg.messageSeq || Date.now(),
              fromUID: msg.fromUID,
              text,
            },
          ]);
        },
      });

      setClient({ sendText: c.sendText });
    }

    start();
  }, []);

  // ----------------------------------------
  // 3) Send message
  // ----------------------------------------
  const handleSend = async () => {
    if (!client || !input.trim()) return;

    const text = input.trim();

    setMessages((prev) => [
      ...prev,
      { id: Date.now(), fromUID: PLATFORM_UID, text },
    ]);
    setInput("");

    try {
      await client.sendText(customerUid, text);
    } catch (err) {
      console.error("Send failed", err);
    }
  };

  return (
    <div className="min-h-dvh bg-white flex flex-col">
      <header className="p-3 border-b flex justify-between">
        <span className="font-semibold">Chat with {customerUid}</span>
        <span className="text-xs text-gray-500">{status}</span>
      </header>

      <div className="flex-1 overflow-y-auto p-3 space-y-2 bg-gray-50">
        {messages.map((m) => (
          <div
            key={m.id}
            className={
              "max-w-[70%] px-3 py-2 rounded text-sm " +
              (m.fromUID === PLATFORM_UID
                ? "ml-auto bg-blue-500 text-white"
                : "mr-auto bg-gray-200 text-gray-900")
            }
          >
            {m.text}
          </div>
        ))}
      </div>

      <div className="p-3 border-t flex gap-2">
        <input
          className="flex-1 border rounded px-2 py-1 text-sm"
          value={input}
          onChange={(e) => setInput(e.target.value)}
          placeholder="Type a message…"
        />

        <button
          onClick={handleSend}
          className="px-4 py-1 rounded bg-blue-500 text-white text-sm"
        >
          Send
        </button>
      </div>
    </div>
  );
}
