"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { WUKONG_WS_ADDR, WUKONG_API_BASE } from "@/utils/constants";
import { useWukongRef } from "@/utils/WukongContext";
// import { subscribeWukong } from "@/utils/wukong";
// import { initWukong, destroyWukong } from "@/utils/wukong";
// import { ConnectStatus } from "wukongimjssdk";
import {
  unlockAudio,
  startRingtone,
  stopRingtone,
} from "@/utils/notificationAudio";
import { getCustomerDisplayId } from "../../../../utils/constants";
const PROFILE_CACHE_KEY = "im_profile_cache_v1"; // { [uid]: { avatar,name,updatedAt } }
function extractProfileFromMessage(msg) {
  try {
    const raw = msg.content?.text;
    if (!raw) return null;

    const parsed = JSON.parse(raw);
    if (parsed?.avatar || parsed?.name) {
      return {
        avatar: parsed.avatar || null,
        name: parsed.name || null,
      };
    }
  } catch {}
  return null;
}

function safeJsonParse(str, fallback = null) {
  try {
    return JSON.parse(str);
  } catch {
    return fallback;
  }
}

function parseMessagePayload(raw) {
  if (!raw) return { text: "", agent: null, customer: null };

  try {
    const decoded = JSON.parse(raw);

    return {
      text: decoded?.content || decoded?.text || raw,
      agent: decoded?.agent || null,
      // ✅ support both `customer` and flat `avatar/name`
      customer: decoded?.customer
        ? {
            avatar: decoded.customer.avatar || null,
            name: decoded.customer.name || decoded.customer.username || null,
            uid: decoded.customer.uid || null,
          }
        : decoded?.avatar || decoded?.name
          ? {
              avatar: decoded.avatar || null,
              name: decoded.name || decoded.username || null,
              uid: decoded.uid || null,
            }
          : null,
    };
  } catch {
    return { text: raw, agent: null, customer: null };
  }
}
// Decode base64 payload (history)
function decodePayload(base64) {
  if (!base64) return "";
  try {
    const json = atob(base64);
    return JSON.parse(json)?.content || "";
  } catch {
    return "";
  }
}

// Summarize messages (photo, video, caption)
function summarizeMessageText(raw) {
  if (!raw) return "";

  const parts = raw
    .split("\n")
    .map((p) => p.trim())
    .filter(Boolean);

  if (!parts.length) return "";

  const first = parts[0];
  const hasCaption = first.startsWith("[") && first.endsWith("]");
  const caption = hasCaption ? first.slice(1, -1) : "";
  const urls = hasCaption ? parts.slice(1) : parts;

  const images = urls.filter((u) => /\.(jpg|jpeg|png|webp)$/i.test(u));
  const videos = urls.filter((u) => /\.(mp4|mov|webm)$/i.test(u));

  if (images.length && !videos.length) {
    if (caption) return caption;
    return images.length === 1 ? "📷 Photo" : `📷 ${images.length} photos`;
  }

  if (videos.length && !images.length) {
    if (caption) return caption;
    return videos.length === 1 ? "🎥 Video" : `🎥 ${videos.length} videos`;
  }

  if (images.length || videos.length) {
    return caption || "📎 Attachment";
  }

  return raw;
}

// 🔧 KEEP THIS — WuKong UTF-8 FIX
function fixUtf8(str) {
  if (!str) return "";
  if (/[Ãæçð�]/.test(str)) {
    try {
      return decodeURIComponent(escape(str));
    } catch {
      return str;
    }
  }
  return str;
}

export default function AgentChatList({
  onSelectChat,
  activeUid,
  messages,
  connected,
  status,
  rawEvents,
}) {
  const router = useRouter();
  const [mounted, setMounted] = useState(false);
  useEffect(() => {
    setMounted(true);
  }, []);

  const agentSession = useMemo(() => {
    if (typeof window === "undefined") return null;

    const key = Object.keys(localStorage).find((k) =>
      k.startsWith("im_agent_"),
    );

    if (!key) return null;

    try {
      return JSON.parse(localStorage.getItem(key));
    } catch {
      return null;
    }
  }, []);

  const wkRef = useWukongRef();
  const listRef = useRef([]);

  // SAFE constants
  // =============================
  const PLATFORM_UID =
    agentSession?.platformId || process.env.NEXT_PUBLIC_PLATFORM_UID;

  const AGENT_TOKEN = agentSession?.token;

  const AGENT_CHANNEL_ID = process.env.NEXT_PUBLIC_AGENT_CHANNEL_ID;

  const AGENT_NAME =
    agentSession?.agentName || process.env.NEXT_PUBLIC_AGENT_NAME || "Agent";

  const AGENT_AVATAR =
    agentSession?.avatar || process.env.NEXT_PUBLIC_AGENT_AVATAR || "";

  const agentName = mounted
    ? agentSession?.agentName || "Customer Support"
    : "Customer Support";

  // =============================
  // connect only when token exists
  // =============================
  useEffect(() => {
    if (!AGENT_TOKEN) return;

    // ✅ connect websocket / call api here
  }, [AGENT_TOKEN]);

  // const PLATFORM_UID = mounted
  //   ? agentSession?.platformId
  //   : process.env.NEXT_PUBLIC_PLATFORM_UID;
  //   const AGENT_TOKEN = agentSession?.token;
  //   const AGENT_CHANNEL_ID = process.env.NEXT_PUBLIC_AGENT_CHANNEL_ID;

  //   const AGENT_NAME = mounted
  //     ? agentSession?.agentName || process.env.NEXT_PUBLIC_AGENT_NAME || "Agent"
  //     : process.env.NEXT_PUBLIC_AGENT_NAME || "Agent";

  //   const AGENT_AVATAR = mounted
  //     ? agentSession?.avatar || process.env.NEXT_PUBLIC_AGENT_AVATAR || ""
  //     : process.env.NEXT_PUBLIC_AGENT_AVATAR || "";

  // const [status, setStatus] = useState("Connecting...");
  // const [connected, setConnected] = useState(false);
  const [list, setList] = useState([]);
  useEffect(() => {
    listRef.current = list;
  }, [list]);

  const [onlineMap, setOnlineMap] = useState({});

  const profileCacheRef = useRef(null);
  const connectedRef = useRef(false);
  const lastMsgIdMapRef = useRef({}); // ✅ ADD THIS
  const [typingMap, setTypingMap] = useState({});
  // { [customerUid]: true }
  const typingTimerRef = useRef({});

  // 🔔 notification sound
  // const notifyAudioRef = useRef(null);
  // const notifyIntervalRef = useRef(null);
  // const audioUnlockedRef = useRef(false);
  const [notifyEnabled, setNotifyEnabled] = useState(false);
  useEffect(() => {
    if (!PLATFORM_UID) return;
    loadConversationList();
  }, [PLATFORM_UID]);

  // useEffect(() => {
  //   if (!messages) return;
  //   setList(messages);
  // }, [messages]);

  // useEffect(() => {
  //   notifyAudioRef.current = new Audio("/sounds/notification.wav");
  //   notifyAudioRef.current.preload = "auto";
  // }, []);

  // function startRingtone() {
  //   if (!audioUnlockedRef.current) return;
  //   if (!notifyEnabled) return;
  //   if (notifyIntervalRef.current) return;

  //   notifyIntervalRef.current = window.setInterval(() => {
  //     const audio = notifyAudioRef.current;
  //     if (!audio) return;
  //     audio.pause();
  //     audio.currentTime = 0;
  //     audio.play().catch(() => {});
  //   }, 3000);
  // }

  // function stopRingtone() {
  //   if (notifyIntervalRef.current) {
  //     clearInterval(notifyIntervalRef.current);
  //     notifyIntervalRef.current = null;
  //   }
  // }

  // // 🔔 ring while unread exists
  // useEffect(() => {
  //   const hasUnread = list.some((c) => Number(c.unread || 0) > 0);
  //   if (hasUnread) startRingtone();
  //   else stopRingtone();
  //   return () => stopRingtone();
  // }, [list]);

  function toggleNotification() {
    unlockAudio(); // unlocks only on user click

    setNotifyEnabled((prev) => {
      const next = !prev;
      if (!next) stopRingtone();
      return next;
    });
  }
  // -----------------------------
  // Local cache helpers
  // -----------------------------
  function loadProfileCache() {
    if (profileCacheRef.current) return profileCacheRef.current;
    const raw = localStorage.getItem(PROFILE_CACHE_KEY);
    const obj = safeJsonParse(raw, {}) || {};
    profileCacheRef.current = obj;
    return obj;
  }

  function saveProfileCache(cacheObj) {
    profileCacheRef.current = cacheObj;
    localStorage.setItem(PROFILE_CACHE_KEY, JSON.stringify(cacheObj));
  }

  function getCachedProfile(uid) {
    const cache = loadProfileCache();
    return cache?.[uid] || null;
  }

  // function unlockAudio() {
  //   if (audioUnlockedRef.current) return;

  //   const audio = notifyAudioRef.current;
  //   if (!audio) return;

  //   audio.muted = true;
  //   audio
  //     .play()
  //     .then(() => {
  //       audio.pause();
  //       audio.currentTime = 0;
  //       audio.muted = false;
  //       audioUnlockedRef.current = true;
  //       setNotifyEnabled(true);
  //     })
  //     .catch(() => {});
  // }

  // function toggleNotification() {
  //   if (!audioUnlockedRef.current) {
  //     unlockAudio(); // first click unlocks
  //     return;
  //   }
  //   setNotifyEnabled((v) => !v);
  // }

  // async function getCustomerProfile(uid) {
  //   const cached = getCachedProfile(uid);
  //   if (cached?.avatar || cached?.name) return cached;

  //   try {
  //     const res = await fetch(
  //       `/api/customer/profile?uid=${encodeURIComponent(uid)}`
  //     );
  //     const json = await res.json();
  //     const profile = {
  //       avatar: json?.avatar || null,
  //       name: json?.name || json?.username || uid,
  //     };
  //     const cache = loadProfileCache();
  //     cache[uid] = { ...profile, updatedAt: Date.now() };
  //     saveProfileCache(cache);
  //     return profile;
  //   } catch {
  //     return cached || null;
  //   }
  // }

  async function refreshOnline(uids) {
    if (!uids.length) return;
    try {
      const res = await fetch(`${WUKONG_API_BASE}/user/onlinestatus`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(uids),
      });
      const arr = await res.json();
      const next = {};
      (arr || []).forEach((x) => {
        if (x?.uid) next[x.uid] = x.online === 1;
      });
      setOnlineMap((p) => ({ ...p, ...next }));
    } catch {}
  }

  async function clearUnread(customerUid) {
    try {
      await fetch(`${WUKONG_API_BASE}/conversations/clearUnread`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          uid: PLATFORM_UID,
          channel_id: customerUid,
          channel_type: 1,
        }),
      });
    } catch {}
  }

  async function loadConversationList() {
    try {
      const res = await fetch(`${WUKONG_API_BASE}/conversation/sync`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ uid: PLATFORM_UID, msg_count: 1 }),
      });

      const conv = await res.json();

      const mapped = (
        await Promise.all(
          (conv || []).map(async (item) => {
            const uid = item.channel_id;

            if (!uid || uid === PLATFORM_UID) return null;

            const recent = item.recents?.[0];
            //    const profile = await getCustomerProfile(uid);

            let raw = "";
            if (recent?.payload) raw = decodePayload(recent.payload);
            else if (recent?.content?.text) raw = recent.content.text;
            const { text, agent, customer } = parseMessagePayload(raw);

            let lastText = summarizeMessageText(text);
            lastText = fixUtf8(lastText);

            // OPTIONAL: prefix agent name (recommended)
            if (agent?.name) {
              lastText = `${agent.name}: ${lastText}`;
            }
            const cached = getCachedProfile(uid);

            const avatar = customer?.avatar || cached?.avatar || "/user.png";

            const name = customer?.name || cached?.name || uid;

            // ✅ if we got profile from last msg, cache it
            if (customer?.avatar || customer?.name) {
              const cache = loadProfileCache();
              cache[uid] = { avatar, name, updatedAt: Date.now() };
              saveProfileCache(cache);
            }

            return {
              uid,
              avatar,
              name,
              lastText,
              timestamp: recent?.timestamp ? recent.timestamp * 1000 : 0,
              unread: Number(item.unread || 0),
            };
          }),
        )
      ).filter(Boolean);

      mapped.sort((a, b) => b.timestamp - a.timestamp);
      setList(mapped);
      refreshOnline(mapped.map((x) => x.uid));
    } catch {}
  }
  const handleClearAllChannels = async () => {
    try {
      for (const c of list) {
        await fetch(`${WUKONG_API_BASE}/conversations/delete`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            uid: PLATFORM_UID,
            channel_id: c.uid,
            channel_type: 1,
          }),
        });
      }

      // clear UI list
      setList([]);

      // stop notification sound immediately
      stopRingtone();
    } catch (err) {
      console.error("Clear all channels failed", err);
    }
  };

  useEffect(() => {
    if (!rawEvents) return;
    if (rawEvents.type !== "typing") return;

    const data = JSON.parse(rawEvents.dataText || "{}");
    const uid = data.uid;
    const typing = Boolean(data.typing);

    if (!uid) return;

    setTypingMap((prev) => ({
      ...prev,
      [uid]: typing,
    }));

    if (typingTimerRef.current[uid]) {
      clearTimeout(typingTimerRef.current[uid]);
    }

    if (typing) {
      typingTimerRef.current[uid] = setTimeout(() => {
        setTypingMap((prev) => {
          const next = { ...prev };
          delete next[uid];
          return next;
        });
      }, 3000);
    }
  }, [rawEvents]);

  useEffect(() => {
    if (!messages?.length || !PLATFORM_UID) return;

    const msg = messages[messages.length - 1]; // ✅ only handle latest
    if (!msg) return;

    // ❗ WuKong guards
    if (msg.end === false) return;
    if (!msg.messageSeq) return;

    const from = msg.fromUID;
    const to = msg.toUID;
    const uid = from === PLATFORM_UID ? to : from;

    // 🔁 dedupe
    const msgId =
      msg.clientMsgNo || msg.messageID || `${uid}_${msg.messageSeq}`;

    if (lastMsgIdMapRef.current[uid] === msgId) return;
    lastMsgIdMapRef.current[uid] = msgId;

    // 🔓 decode raw payload
    let raw = "";
    if (msg.content?.text) raw = msg.content.text;
    else if (msg.payload) raw = decodePayload(msg.payload);

    // ✅ IMPORTANT: parse customer from message
    const { text, agent, customer } = parseMessagePayload(raw);

    let lastText = summarizeMessageText(text);
    lastText = fixUtf8(lastText);

    if (agent?.name) lastText = `${agent.name}: ${lastText}`;

    const isFromCustomer = from !== PLATFORM_UID;
    const isActiveChat = uid === activeUid;

    setList((prev) => {
      const ex = prev.find((x) => x.uid === uid);

      const nextItem = {
        uid,

        // ✅ PRIORITY: message customer → existing → default
        avatar: customer?.avatar || ex?.avatar || "/user.png",

        name: customer?.name || ex?.name || "Visitor",

        lastText,
        timestamp: Date.now(),

        unread: isActiveChat
          ? 0
          : isFromCustomer
            ? (ex?.unread || 0) + 1
            : ex?.unread || 0,
      };

      // 💾 cache profile ONLY if message provides it
      if (customer?.avatar || customer?.name) {
        const cache = loadProfileCache();
        cache[uid] = {
          avatar: nextItem.avatar,
          name: nextItem.name,
          updatedAt: Date.now(),
        };
        saveProfileCache(cache);
      }

      return [nextItem, ...prev.filter((x) => x.uid !== uid)];
    });

    refreshOnline([uid]);
  }, [messages, activeUid]);

  useEffect(() => {
    if (!PLATFORM_UID) return;

    const timer = setInterval(() => {
      const uids = (listRef.current || []).map((x) => x.uid);

      if (uids.length > 0) {
        refreshOnline(uids);
      }
    }, 8000);

    return () => clearInterval(timer);
  }, [PLATFORM_UID]);

  // useEffect(() => {
  //   return () => {
  //     destroyWukong();
  //   };
  // }, []);

  // useEffect(() => {
  //   if (!PLATFORM_UID || !AGENT_TOKEN) return;
  //   let timer;

  //   async function start() {
  //     await fetch(`${WUKONG_API_BASE}/user/token`, {
  //       method: "POST",
  //       headers: { "Content-Type": "application/json" },
  //       body: JSON.stringify({
  //         uid: PLATFORM_UID,
  //         token: AGENT_TOKEN,
  //         device_flag: 1,
  //         device_level: 1,
  //       }),
  //     });
  //     // before creating new listeners, cleanup old
  //     // wkRef.current?.unsubscribe?.();

  //     wkRef.current = initWukong({
  //       uid: PLATFORM_UID,
  //       token: AGENT_TOKEN,

  //       onStatusChange: (s) => {
  //         if (s === ConnectStatus.Connected) {
  //           connectedRef.current = true;
  //           setConnected(true);
  //           setStatus("Connected");
  //         }

  //         if (s === ConnectStatus.Disconnected) {
  //           connectedRef.current = false;
  //           setTimeout(() => {
  //             if (!connectedRef.current) {
  //               setConnected(false);
  //               setStatus("Disconnected");
  //             }
  //           }, 1500);
  //         }
  //       },

  //       onMessage: async (msg) => {
  //         if (msg.end === false) return;
  //         if (!msg.messageSeq) return;

  //         const from = msg.fromUID;
  //         const to = msg.toUID;
  //         const uid = from === PLATFORM_UID ? to : from;

  //         let raw = "";
  //         if (msg.content?.text) raw = msg.content.text;
  //         else if (msg.payload) raw = decodePayload(msg.payload);

  //         const { text, agent } = parseMessagePayload(raw);

  //         let lastText = summarizeMessageText(text);
  //         lastText = fixUtf8(lastText);

  //         if (agent?.name) lastText = `${agent.name}: ${lastText}`;

  //         const profile = extractProfileFromMessage(msg);

  //         setList((prev) => {
  //           const ex = prev.find((x) => x.uid === uid);

  //           const msgId =
  //             msg.clientMsgNo || msg.messageID || `${uid}_${msg.messageSeq}`;

  //           if (lastMsgIdMapRef.current[uid] === msgId) return prev;
  //           lastMsgIdMapRef.current[uid] = msgId;

  //           const isFromCustomer = from !== PLATFORM_UID;

  //           const nextItem = {
  //             uid,
  //             avatar: profile?.avatar || ex?.avatar || "/user.png",
  //             name: profile?.name || ex?.name || uid,
  //             lastText,
  //             timestamp: Date.now(),
  //             unread: isFromCustomer ? (ex?.unread || 0) + 1 : ex?.unread || 0,
  //           };

  //           // cache profile
  //           if (profile?.avatar || profile?.name) {
  //             const cache = loadProfileCache();
  //             cache[uid] = {
  //               avatar: nextItem.avatar,
  //               name: nextItem.name,
  //               updatedAt: Date.now(),
  //             };
  //             saveProfileCache(cache);
  //           }

  //           return [nextItem, ...prev.filter((x) => x.uid !== uid)];
  //         });

  //         refreshOnline([uid]);
  //       },
  //     });

  //     await loadConversationList();

  //     timer = setInterval(() => {
  //       const uids = (listRef.current || []).map((x) => x.uid);
  //       refreshOnline(uids);
  //     }, 8000);

  //     // ✅ return cleanup from start()
  //     return () => {
  //       timer && clearInterval(timer);
  //       wkRef.current?.unsubscribe?.();
  //       wkRef.current = null;
  //     };
  //   }

  //   // ✅ handle async cleanup properly
  //   let cleanup = null;
  //   start().then((fn) => (cleanup = fn));

  //   return () => cleanup?.();
  // }, [PLATFORM_UID, AGENT_TOKEN]);
  // useEffect(() => {
  //   if (!wkRef?.current) return;

  //   // subscribe to incoming messages
  //   const unsubscribe = wkRef.current.subscribe?.({
  //     onMessage: async (msg) => {
  //       if (msg.end === false) return;
  //       if (!msg.messageSeq) return;

  //       const from = msg.fromUID;
  //       const to = msg.toUID;
  //       const uid = from === PLATFORM_UID ? to : from;

  //       let raw = "";
  //       if (msg.content?.text) raw = msg.content.text;
  //       else if (msg.payload) raw = decodePayload(msg.payload);

  //       const { text, agent } = parseMessagePayload(raw);

  //       let lastText = summarizeMessageText(text);
  //       lastText = fixUtf8(lastText);

  //       if (agent?.name) lastText = `${agent.name}: ${lastText}`;

  //       const profile = extractProfileFromMessage(msg);

  //       setList((prev) => {
  //         const ex = prev.find((x) => x.uid === uid);

  //         const msgId =
  //           msg.clientMsgNo || msg.messageID || `${uid}_${msg.messageSeq}`;

  //         if (lastMsgIdMapRef.current[uid] === msgId) return prev;
  //         lastMsgIdMapRef.current[uid] = msgId;

  //         const isFromCustomer = from !== PLATFORM_UID;

  //         const nextItem = {
  //           uid,
  //           avatar: profile?.avatar || ex?.avatar || "/user.png",
  //           name: profile?.name || ex?.name || uid,
  //           lastText,
  //           timestamp: Date.now(),
  //           unread: isFromCustomer ? (ex?.unread || 0) + 1 : ex?.unread || 0,
  //         };

  //         return [nextItem, ...prev.filter((x) => x.uid !== uid)];
  //       });

  //       refreshOnline([uid]);
  //     },
  //   });

  //   return () => {
  //     unsubscribe?.();
  //   };
  // }, [wkRef?.current]);

  // useEffect(() => {
  //   if (!PLATFORM_UID) return;

  //   const unsubscribe = subscribeWukong({
  //     onStatusChange: (s) => {
  //       if (s === 1) {
  //         // Connected
  //         setConnected(true);
  //         setStatus("Connected");
  //       }
  //       if (s === 2) {
  //         // Disconnected
  //         setConnected(false);
  //         setStatus("Disconnected");
  //       }
  //     },

  //     onMessage: async (msg) => {
  //       if (msg.end === false) return;
  //       if (!msg.messageSeq) return;

  //       const from = msg.fromUID;
  //       const to = msg.toUID;
  //       const uid = from === PLATFORM_UID ? to : from;

  //       let raw = "";
  //       if (msg.content?.text) raw = msg.content.text;
  //       else if (msg.payload) raw = decodePayload(msg.payload);

  //       const { text, agent } = parseMessagePayload(raw);

  //       let lastText = summarizeMessageText(text);
  //       lastText = fixUtf8(lastText);

  //       if (agent?.name) lastText = `${agent.name}: ${lastText}`;

  //       setList((prev) => {
  //         const ex = prev.find((x) => x.uid === uid);

  //         return [
  //           {
  //             uid,
  //             avatar: ex?.avatar || "/user.png",
  //             name: ex?.name || uid,
  //             lastText,
  //             timestamp: Date.now(), // 🔥 always update
  //             unread:
  //               from !== PLATFORM_UID ? (ex?.unread || 0) + 1 : ex?.unread || 0,
  //           },
  //           ...prev.filter((x) => x.uid !== uid),
  //         ];
  //       });

  //       // setList((prev) => {
  //       //   const ex = prev.find((x) => x.uid === uid);

  //       //   const msgId =
  //       //     msg.clientMsgNo ||
  //       //     msg.messageID ||
  //       //     `${uid}_${msg.messageSeq}_${msg.timestamp || Date.now()}`;

  //       //   if (lastMsgIdMapRef.current[uid] === msgId) return prev;
  //       //   lastMsgIdMapRef.current[uid] = msgId;

  //       //   return [
  //       //     {
  //       //       uid,
  //       //       avatar: ex?.avatar || "/user.png",
  //       //       name: ex?.name || uid,
  //       //       lastText,
  //       //       timestamp: Date.now(),
  //       //       unread:
  //       //         from !== PLATFORM_UID ? (ex?.unread || 0) + 1 : ex?.unread || 0,
  //       //     },
  //       //     ...prev.filter((x) => x.uid !== uid),
  //       //   ];
  //       // });
  //     },
  //   });

  //   return () => unsubscribe();
  // }, [PLATFORM_UID]);

  const headerDotClass = useMemo(
    () => (connected ? "bg-green-400" : "bg-gray-300"),
    [connected],
  );
  useEffect(() => {
    if (!notifyEnabled) {
      stopRingtone();
      return;
    }

    const hasUnread = list.some((c) => Number(c.unread || 0) > 0);
    if (hasUnread) startRingtone();
    else stopRingtone();
  }, [list, notifyEnabled]);

  return (
    <div className="min-h-dvh bg-[#F7F8FA] flex flex-col" onClick={unlockAudio}>
      {/* HEADER */}
      <header className="bg-[#1677FF] text-white px-4 py-3 shadow flex items-center justify-between">
        <div className="flex items-center gap-3">
          {/* agent avatar (optional) */}
          <div className="relative w-9 h-9 rounded-full overflow-hidden bg-white/20 flex items-center justify-center">
            {AGENT_AVATAR ? (
              <img
                src={AGENT_AVATAR}
                className="w-full h-full object-cover"
                alt="Agent"
              />
            ) : (
              <span className="font-semibold">
                {agentName.substring(0, 1).toUpperCase()}
              </span>
            )}
            {/* <span
              className={`absolute -right-0.5 -bottom-0.5 w-3 h-3 rounded-full ring-2 ring-[#1677FF] ${headerDotClass}`}
              title={connected ? "Online" : "Offline"}
            /> */}
          </div>

          <div className="leading-tight">
            <div className="text-sm font-semibold">{agentName}</div>
            <div className="flex justify-between text-xs text-white/80">
              <span>
                {connected
                  ? "Online now"
                  : status === "Disconnected"
                    ? "Offline"
                    : "Connecting..."}
              </span>

              <span className="ml-5 text-white/50 max-w-[90px] truncate">
                {PLATFORM_UID}
              </span>
            </div>
          </div>
        </div>

        <div className="flex items-center gap-2">
          {/* 🔔 Notification Bell */}
          <button
            onClick={toggleNotification}
            className="relative p-2 rounded-full hover:bg-white/20 active:scale-95"
            title={notifyEnabled ? "Mute notification" : "Enable notification"}
          >
            <span className="text-lg">{notifyEnabled ? "🔔" : "🔕"}</span>
          </button>

          {/* Clear All */}
          {/* <button
            onClick={handleClearAllChannels}
            className="text-xs bg-red-500 px-3 py-1.5 rounded-md active:scale-95"
          >
            Clear All
          </button> */}

          <button
            onClick={() => {
              stopRingtone();

              // destroyWukong(); // 🔥 THIS LINE FIXES EVERYTHING

              Object.keys(localStorage)
                .filter((k) => k.startsWith("im_agent_"))
                .forEach((k) => localStorage.removeItem(k));

              router.replace("/agent/login");
            }}
            className="text-xs bg-gray-700 text-white px-3 py-1.5 rounded-md hover:bg-gray-800 active:scale-95"
          >
            Logout
          </button>
        </div>
      </header>

      {/* CHAT LIST */}
      <div className="flex-1 overflow-y-auto px-3 space-y-3 pb-5 pt-3">
        {list.map((c, idx) => {
          const isUserOnline = !!onlineMap?.[c.uid];
          const dot = isUserOnline ? "bg-green-500" : "bg-gray-300";
          const isActive = c.uid === activeUid;
          return (
            <button
              key={c.uid + idx}
              onClick={async () => {
                // 1️⃣ clear unread on server
                await clearUnread(c.uid);

                // 2️⃣ clear unread in UI immediately
                setList((prev) =>
                  prev.map((x) => (x.uid === c.uid ? { ...x, unread: 0 } : x)),
                );

                // 3️⃣ go to chat page
                onSelectChat(c.uid); // ⭐⭐ 关键这一行
              }}
              className={`
              w-full flex items-center gap-3
              p-3 rounded-xl
              border border-gray-200
              transition-colors duration-150
              ${isActive ? "bg-gray-200" : "bg-white hover:bg-gray-50"}
            `}
            >
              {/* Avatar */}
              <div className="relative w-12 h-12 rounded-full overflow-visible bg-gray-200 flex items-center justify-center shrink-0">
                {c.avatar ? (
                  <img
                    src={c.avatar}
                    className="w-full h-full rounded-full object-cover"
                    alt={c.name}
                  />
                ) : (
                  <span className="text-gray-600 font-semibold text-lg">
                    {c.name?.substring(0, 1)?.toUpperCase?.() ||
                      c.uid.substring(0, 1).toUpperCase()}
                  </span>
                )}

                {/* online dot */}
                <span
                  className={`
                  absolute
                -right-0.5
                -bottom-0.5
                  z-20
                  w-3
                  h-3
                  rounded-full
                  ring-2
                  ring-white
                  ${dot}
                `}
                />
              </div>

              {/* Text */}
              <div className="flex-1 text-left min-w-0">
                <div className="flex items-center justify-between gap-2">
                  <div className="font-semibold text-gray-900 truncate">
                    {getCustomerDisplayId(c.name)}
                    {/* {c.name?.split(":")[1] || c.name?.split("_")[1] || c.name} */}
                  </div>

                  {/* unread badge */}
                  {Number(c.unread || 0) > 0 && (
                    <span className="shrink-0 text-[11px] px-2 py-0.5 rounded-full bg-red-500 text-white">
                      {c.unread > 99 ? "99+" : c.unread}
                    </span>
                  )}
                </div>

                <div className="text-sm truncate mt-1">
                  {typingMap[c.uid] ? (
                    <span className="italic text-gray-500">Typing…</span>
                  ) : c.lastText ? (
                    <span className="text-gray-500">{c.lastText}</span>
                  ) : (
                    <span className="italic text-gray-400">
                      No messages yet
                    </span>
                  )}
                </div>
              </div>

              {/* Time */}
              <div className="text-[11px] text-gray-400 whitespace-nowrap">
                {c.timestamp
                  ? new Date(c.timestamp).toLocaleTimeString([], {
                      hour: "2-digit",
                      minute: "2-digit",
                    })
                  : ""}
              </div>
            </button>
          );
        })}

        {!list.length && (
          <div className="text-center text-sm text-gray-500 py-10">
            No conversations yet
          </div>
        )}
      </div>
    </div>
  );
}
