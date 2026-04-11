"use client";

import { use, useEffect, useRef, useState } from "react";

import { AGENT_UID, WUKONG_WS_ADDR, WUKONG_API_BASE } from "@/utils/constants";
import { subscribeWukong, sendWukongText } from "@/utils/wukong-agent";

// import { initWukong } from "@/utils/wukong";
// import { ConnectStatus } from "wukongimjssdk";

import data from "@emoji-mart/data";
import Picker from "@emoji-mart/react";
import { useRouter } from "next/navigation";
import { stopRingtone, unlockAudio } from "@/utils/notificationAudio";
import { decodeWuKongPayload } from "../../../../utils/wukong-agent";
import {
  APSKY_API,
  browserInfo,
  getBrowserInfo,
  getCustomerDisplayId,
} from "../../../../utils/constants";
import { toast } from "react-hot-toast";
import { compressImage } from "../../../../utils/imageCompress";
// ----------------------------------------
// BASE64 DECODING (CustomerChat version is better, using that)
// ----------------------------------------
function b64_to_utf8(str) {
  try {
    return decodeURIComponent(escape(atob(str)));
  } catch {
    return str;
  }
}

// HH:mm
function formatTime(ts) {
  if (!ts) return "";
  const d = new Date(ts);
  return d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
}

// Today / Yesterday / date
function formatDateHeader(ts) {
  const d = new Date(ts);
  const today = new Date();

  const isSameDay =
    d.getFullYear() === today.getFullYear() &&
    d.getMonth() === today.getMonth() &&
    d.getDate() === today.getDate();

  if (isSameDay) return "Today";

  const yesterday = new Date();
  yesterday.setDate(today.getDate() - 1);

  const isYesterday =
    d.getFullYear() === yesterday.getFullYear() &&
    d.getMonth() === yesterday.getMonth() &&
    d.getDate() === yesterday.getDate();

  if (isYesterday) return "Yesterday";

  return d.toLocaleDateString();
}

// ----------------------------------------
// DECODE MESSAGE CONTENT
// ----------------------------------------
// function decodeWuKongContent(msg) {
//   // 1️⃣ payload (primary)
//   if (msg?.content?.payload) {
//     try {
//       // Use the safe base64-to-UTF8 decoding, then parse JSON
//       const decoded = JSON.parse(b64_to_utf8(msg.content.payload));
//       return decoded?.content || "";
//     } catch {
//       return "";
//     }
//   }

//   // 2️⃣ text fallback
//   if (msg?.content?.text) {
//     // Note: The original code had a 'repairEmoji' function which is complex
//     // We'll rely on b64_to_utf8/decodeURIComponent for safety, or just return text
//     return msg.content.text;
//   }

//   return "";
// }

export default function AgentChat({
  customerUid,
  messages: rawMessages,
  onAgentSend,
  rawEvents,
}) {
  // const { uid } = use(params);
  // const customerUid = decodeURIComponent(uid);
  const wkRef = useRef(null);
  const router = useRouter();
  const [status, setStatus] = useState("Connecting...");
  // const [messages, setMessages] = useState([]);
  const [input, setInput] = useState("");
  // const clientRef = useRef(null);

  // useEffect(() => {
  //   clientRef.current = getWukongClient();
  // }, []);

  const [mounted, setMounted] = useState(false);
  useEffect(() => {
    setMounted(true);
  }, []);

  // unread / scroll
  const [lastReadSeq, setLastReadSeq] = useState(0);
  const [shouldAutoScroll, setShouldAutoScroll] = useState(true);
  const [showNewMsgAlert, setShowNewMsgAlert] = useState(false);
  const [clearNewTimer, setClearNewTimer] = useState(null);
  const messageEndRef = useRef(null);

  // popup UI
  const [showMainEmoji, setShowMainEmoji] = useState(false);
  const [showPopup, setShowPopup] = useState(false);
  const [files, setFiles] = useState([]);
  const [activeIndex, setActiveIndex] = useState(0);
  const [caption, setCaption] = useState("");
  const [compress, setCompress] = useState(true);
  const [showPopupEmoji, setShowPopupEmoji] = useState(false);

  const readKey = `im_read_agent_${customerUid}`;
  const [baselineSeq, setBaselineSeq] = useState(0);

  const [customerProfile, setCustomerProfile] = useState(null);
  const [customerOnline, setCustomerOnline] = useState(false);
  // typing indicator
  const [customerTyping, setCustomerTyping] = useState(false);
  const typingTimerRef = useRef(null);
  const isTypingRef = useRef(false);
  const tempSeqRef = useRef(0); // ✅ ADD THIS

  // const AGENT_AVATAR = process.env.NEXT_PUBLIC_AGENT_AVATAR;
  const [agentSession, setAgentSession] = useState(null);
  const [agentLoaded, setAgentLoaded] = useState(false);
  const [messagesState, setMessagesState] = useState([]);
  const [previewMedia, setPreviewMedia] = useState(null);
  const sendingAttachmentRef = useRef(false);
  const [uploading, setUploading] = useState(false);

  useEffect(() => {
    try {
      const key = Object.keys(localStorage).find((k) =>
        k.startsWith("im_agent_"),
      );
      if (key) {
        const raw = localStorage.getItem(key);
        if (raw) setAgentSession(JSON.parse(raw));
      }
    } catch (e) {
      console.error("Load agent session failed", e);
    } finally {
      setAgentLoaded(true);
    }
  }, []);

  useEffect(() => {
    setMessagesState([]);
    setBaselineSeq(0);
  }, [customerUid]);

  const AGENT_NAME =
    agentSession?.agentName ||
    agentSession?.agentId ||
    process.env.NEXT_PUBLIC_AGENT_NAME ||
    "Agent";

  const AGENT_AVATAR =
    agentSession?.avatar || process.env.NEXT_PUBLIC_AGENT_AVATAR || "";

  const AGENT_PLATFORM_ID = agentSession?.platformId;
  const AGENT_TOKEN = agentSession?.token;
  const AGENT_ID = agentSession?.agentId;
  const agentReady = Boolean(AGENT_PLATFORM_ID && AGENT_TOKEN);

  useEffect(() => {
    if (!rawEvents) return;
    if (rawEvents.type !== "typing") return;
    if (!customerUid) return;

    let data = {};
    try {
      data = JSON.parse(rawEvents.dataText || "{}");
    } catch {
      return;
    }

    const uid = data.uid;
    const typing = Boolean(data.typing);

    // ✅ event must belong to this customer
    if (!uid || uid !== customerUid) return;

    // ⏱ clear old timer
    if (typingTimerRef.current) {
      clearTimeout(typingTimerRef.current);
      typingTimerRef.current = null;
    }

    setCustomerTyping(typing);

    // auto clear
    if (typing) {
      typingTimerRef.current = setTimeout(() => {
        setCustomerTyping(false);
      }, 3000);
    }
  }, [rawEvents, customerUid]);

  // ----------------------------------------
  // FILE MAP
  // ----------------------------------------
  function mapFiles(arr) {
    return arr.map((f) => ({
      file: f,
      url: URL.createObjectURL(f),
      name: f.name,
      type: f.type.startsWith("image")
        ? "image"
        : f.type.startsWith("video")
          ? "video"
          : "file",
    }));
  }

  function handleUpload(e) {
    const list = e.target.files;
    if (!list || list.length === 0) return;

    const selected = mapFiles(Array.from(list));
    setFiles((prev) => {
      const merged = [...prev, ...selected];
      if (prev.length === 0) setActiveIndex(0);
      return merged;
    });
    setShowPopup(true);
    e.target.value = "";
  }

  function handleDrop(e) {
    e.preventDefault();
    const list = e.dataTransfer.files;
    if (!list || list.length === 0) return;

    const dropped = mapFiles(Array.from(list));
    setFiles((prev) => {
      const merged = [...prev, ...dropped];
      if (prev.length === 0) setActiveIndex(0);
      return merged;
    });
    setShowPopup(true);
  }

  function decodeWuKongEventData(data) {
    if (!data) return {};

    // ✅ Uint8Array or array-like object
    if (typeof data === "object" && data.length !== undefined) {
      try {
        const uint8 = new Uint8Array(data);
        const text = new TextDecoder("utf-8").decode(uint8);
        return JSON.parse(text);
      } catch (e) {
        console.error("decode bytes failed", e);
        return {};
      }
    }

    // ✅ string JSON
    if (typeof data === "string") {
      try {
        return JSON.parse(data);
      } catch {
        return {};
      }
    }

    // ✅ already object
    if (typeof data === "object") {
      return data;
    }

    return {};
  }

  // ----------------------------------------
  // 🔥 FIXED: SEND ATTACHMENT (upload → optimistic update → send)
  // ----------------------------------------
  async function sendAttachment() {
    if (files.length === 0) return;

    if (sendingAttachmentRef.current) return;
    sendingAttachmentRef.current = true;
    setUploading(true);

    const toastId = toast.loading("Uploading...");

    try {
      for (const item of files) {
        if (!item.file.type.startsWith("image/")) {
          toast.error("Only images allowed");
          continue;
        }

        const MAX_SIZE = 10 * 1024 * 1024;
        if (item.file.size > MAX_SIZE) {
          toast.error("File too large (max 10MB)");
          continue;
        }

        let uploadFile = item.file;

        if (uploadFile.size > 1024 * 1024) {
          uploadFile = await compressImage(uploadFile);
        }

        const form = new FormData();
        form.append("file", uploadFile);

        const res = await fetch("/api/upload", {
          method: "POST",
          body: form,
        });

        if (!res.ok) {
          toast.error("Upload failed");
          continue;
        }

        const data = await res.json();
        if (!data?.url) {
          toast.error("Upload failed");
          continue;
        }

        let content = data.url;
        if (caption.trim()) {
          content = `[${caption.trim()}]\n${data.url}`;
        }

        const currentTempSeq = Number.MAX_SAFE_INTEGER - tempSeqRef.current++;

        setMessagesState((prev) => {
          const optimisticMsg = {
            id: `temp_${currentTempSeq}`,
            seq: currentTempSeq,
            fromUID: AGENT_PLATFORM_ID,
            msg: content,
            time: Date.now(),
            isTemporary: true,
          };

          return [...prev, optimisticMsg].sort((a, b) => a.time - b.time);
        });

        onAgentSend({
          customerUid,
          content,
          agent: {
            agentId: AGENT_ID,
            name: AGENT_NAME,
            avatar: AGENT_AVATAR,
          },
        });

        try {
          await sendWukongText(
            customerUid,
            JSON.stringify({
              content,
              agent: {
                agentId: AGENT_ID,
                name: AGENT_NAME,
                avatar: AGENT_AVATAR,
              },
            }),
          );

          await fetch(`${APSKY_API}/api/app/chat-session/sync-message`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              platformId: AGENT_PLATFORM_ID,
              fromUid: AGENT_ID,
              visitorUid: customerUid,
              fromRole: "agent",
              content,
              browserInfo: getBrowserInfo(),
              messageType:
                item.type === "image" ? 1 : item.type === "video" ? 2 : 3,
              messageTime: new Date(),
            }),
          });
        } catch (err) {
          console.error("Send attachment error:", err);
        }
      }

      setFiles([]);
      setCaption("");
      setShowPopup(false);
      setActiveIndex(0);
    } finally {
      toast.dismiss(toastId);
      setUploading(false);
      sendingAttachmentRef.current = false;
    }
  }

  useEffect(() => {
    if (typeof window === "undefined") return;

    const v = Number(
      localStorage.getItem(`im_baseline_agent_${customerUid}`) || 0,
    );

    setBaselineSeq(v);
  }, [customerUid]);
  useEffect(() => {
    stopRingtone();
  }, []);

  // ----------------------------------------
  // AUTO-SCROLL ON NEW MESSAGES
  // ----------------------------------------
  useEffect(() => {
    if (shouldAutoScroll) {
      messageEndRef.current?.scrollIntoView({ behavior: "smooth" });
    } else {
      setShowNewMsgAlert(true);
    }
  }, [messagesState, shouldAutoScroll]);

  useEffect(() => {
    if (!rawMessages || !customerUid || !AGENT_PLATFORM_ID) return;

    setMessagesState((prev) => {
      let next = [...prev];

      for (const msg of rawMessages) {
        const seq = msg.messageSeq;
        if (!seq || seq <= baselineSeq) continue;

        const from = msg.fromUID;
        const to = msg.toUID;
        const target = from === AGENT_PLATFORM_ID ? to : from;
        if (target !== customerUid) continue;

        // prevent duplicates
        if (next.some((m) => m.seq === seq)) continue;

        const decoded = decodeWuKongPayload(msg.payload || msg.content?.text);

        const time = msg.timestamp ? msg.timestamp * 1000 : Date.now();

        const realMsg = {
          id: seq,
          seq,
          fromUID: msg.fromUID,
          msg: decoded.text,
          agent: decoded.agent,
          customer: decoded.customer,
          time,
        };

        // remove optimistic temp msg if exists
        next = next.filter(
          (m) => !(m.isTemporary && m.fromUID === AGENT_PLATFORM_ID),
        );

        next.push(realMsg);
      }

      return next.sort((a, b) => a.time - b.time);
    });
  }, [rawMessages, customerUid, baselineSeq, AGENT_PLATFORM_ID]);

  // useEffect(() => {
  //   if (!customerUid || !AGENT_PLATFORM_ID) return;

  //   const unsubscribe = subscribeWukong({
  //     onMessage: (msg) => {
  //       const from = msg.fromUID;
  //       const to = msg.toUID;
  //       const target = from === AGENT_PLATFORM_ID ? to : from;
  //       if (target !== customerUid) return;

  //       const seq = msg.messageSeq;
  //       if (!seq || seq <= baselineSeq) return;

  //       const decoded = decodeWuKongPayload(msg.payload || msg.content?.text);
  //       const time = msg.timestamp ? msg.timestamp * 1000 : Date.now();

  //       setMessages((prev) => {
  //         const filtered = prev.filter(
  //           (m) =>
  //             !(m.fromUID === AGENT_PLATFORM_ID && m.isTemporary) &&
  //             m.seq !== seq
  //         );

  //         return [
  //           ...filtered,
  //           {
  //             id: seq,
  //             seq,
  //             fromUID: msg.fromUID,
  //             msg: decoded.text,
  //             agent: decoded.agent,
  //             customer: decoded.customer,
  //             time,
  //           },
  //         ].sort((a, b) => a.seq - b.seq);
  //       });
  //     },

  //     onEvent: (evt) => {
  //       if (evt.type !== "typing") return;

  //       const data = decodeWuKongEventData(evt.data);
  //       const typing = Boolean(data.typing);

  //       if (typingTimerRef.current) {
  //         clearTimeout(typingTimerRef.current);
  //       }

  //       setCustomerTyping(typing);

  //       if (typing) {
  //         typingTimerRef.current = setTimeout(() => {
  //           setCustomerTyping(false);
  //         }, 3000);
  //       }
  //     },
  //   });

  //   return () => {
  //     unsubscribe();
  //     if (typingTimerRef.current) {
  //       clearTimeout(typingTimerRef.current);
  //       typingTimerRef.current = null;
  //     }
  //   };
  // }, [customerUid, baselineSeq]);

  useEffect(() => {
    if (!agentReady || !customerUid) return;

    (async () => {
      const res = await fetch(`${WUKONG_API_BASE}/channel/messagesync`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          login_uid: AGENT_PLATFORM_ID,
          channel_id: customerUid,
          channel_type: 1,
          pull_mode: 1,
          limit: 99999999,
        }),
      });

      const json = await res.json();
      const list = json?.messages || [];

      const history = list
        .map((m) => {
          const decoded = decodeWuKongPayload(m.payload);
          return {
            id: m.message_seq,
            seq: m.message_seq,
            fromUID: m.from_uid,
            msg: decoded.text,
            agent: decoded.agent,
            customer: decoded.customer,
            time: Number(m.timestamp) * 1000,
          };
        })
        .filter((m) => m.seq > baselineSeq)
        .sort((a, b) => a.seq - b.seq);

      // ✅ IMPORTANT PART
      setMessagesState(history);
    })();
  }, [customerUid, agentReady, baselineSeq]);
  // // ----------------------------------------
  // // INIT WUKONG
  // // ----------------------------------------
  // useEffect(() => {
  //   // ⛔ wait until agent session is ready
  //   if (!agentReady) return;
  //   if (!customerUid) return;

  //   let destroyed = false;

  //   async function start() {
  //     // -----------------------------
  //     // 1️⃣ restore unread seq
  //     // -----------------------------
  //     const savedSeq = Number(localStorage.getItem(readKey) || 0);
  //     setLastReadSeq(savedSeq);

  //     // -----------------------------
  //     // 2️⃣ register token (required)
  //     // -----------------------------
  //     try {
  //       await fetch(`${WUKONG_API_BASE}/user/token`, {
  //         method: "POST",
  //         headers: { "Content-Type": "application/json" },
  //         body: JSON.stringify({
  //           uid: AGENT_PLATFORM_ID,
  //           token: AGENT_TOKEN,
  //           device_flag: 1,
  //           device_level: 1,
  //         }),
  //       });
  //     } catch (err) {
  //       console.error("❌ Token register failed:", err);
  //       return;
  //     }

  //     if (destroyed) return;

  //     // -----------------------------
  //     // 3️⃣ connect Wukong websocket
  //     // -----------------------------
  //     wkRef.current?.unsubscribe?.();
  //     const wk = initWukong({
  //       uid: AGENT_PLATFORM_ID,
  //       token: AGENT_TOKEN,
  //       // addr: WUKONG_WS_ADDR,
  //       connectAddrCallback: () => "wss://chatclient.apsk.cc/imws",
  //       onStatusChange: (s) => {
  //         if (destroyed) return;
  //         setStatus(
  //           s === ConnectStatus.Connected ? "Connected" : "Disconnected"
  //         );
  //       },

  //       onEvent: (evt) => {
  //         if (destroyed) return;
  //         if (evt.type !== "typing") return;

  //         const data = decodeWuKongEventData(evt.data);
  //         const typing = Boolean(data.typing);

  //         if (typingTimerRef.current) {
  //           clearTimeout(typingTimerRef.current);
  //           typingTimerRef.current = null;
  //         }

  //         setCustomerTyping(typing);

  //         if (typing) {
  //           typingTimerRef.current = setTimeout(() => {
  //             setCustomerTyping(false);
  //           }, 3000);
  //         }
  //       },

  //       onMessage: async (msg) => {
  //         if (destroyed) return;

  //         const from = msg.fromUID;
  //         const to = msg.toUID;
  //         const target = from === AGENT_PLATFORM_ID ? to : from;
  //         if (target !== customerUid) return;

  //         const seq = msg.messageSeq;
  //         if (!seq || seq <= baselineSeq) return;

  //         const decoded = decodeWuKongPayload(msg.content?.text);
  //         const time = msg.timestamp ? msg.timestamp * 1000 : Date.now();

  //         const realMsg = {
  //           id: seq,
  //           seq,
  //           fromUID: msg.fromUID,
  //           msg: decoded.text,
  //           agent: decoded.agent,
  //           customer: decoded.customer,
  //           time,
  //         };

  //         setMessages((prev) => {
  //           const filtered = prev.filter(
  //             (m) =>
  //               !(m.fromUID === AGENT_PLATFORM_ID && m.isTemporary) &&
  //               m.seq !== seq
  //           );

  //           return [...filtered, realMsg].sort((a, b) => a.seq - b.seq);
  //         });

  //         // 🔁 sync to ABP (agent side only)
  //         const fromRole =
  //           msg.fromUID === AGENT_PLATFORM_ID ? "agent" : "visitor";

  //         // try {
  //         //   await fetch(`${APSKY_API}/api/app/chat-session/sync-message`, {
  //         //     method: "POST",
  //         //     headers: { "Content-Type": "application/json" },
  //         //     body: JSON.stringify({
  //         //       platformId: AGENT_PLATFORM_ID,
  //         //       fromUid: msg.fromUID,
  //         //       fromRole,
  //         //       content: decoded.text,
  //         //       messageType: msg.content?.type || 0,
  //         //       messageTime: new Date(time),
  //         //     }),
  //         //   });
  //         // } catch (e) {
  //         //   console.error("❌ Agent sync failed", e);
  //         // }
  //       },
  //     });
  //     wkRef.current = wk;
  //     setClient({ sendText: wk.sendText });

  //     if (destroyed) return;

  //     // -----------------------------
  //     // 4️⃣ load history
  //     // -----------------------------
  //     try {
  //       const res = await fetch(`${WUKONG_API_BASE}/channel/messagesync`, {
  //         method: "POST",
  //         headers: { "Content-Type": "application/json" },
  //         body: JSON.stringify({
  //           login_uid: AGENT_PLATFORM_ID,
  //           channel_id: customerUid,
  //           channel_type: 1,
  //           start_message_seq: 0,
  //           end_message_seq: 0,
  //           pull_mode: 1,
  //           limit: 200,
  //         }),
  //       });

  //       const json = await res.json();
  //       const list = json?.messages || [];

  //       const history = list
  //         .map((m) => {
  //           const decoded = decodeWuKongPayload(m.payload);
  //           return {
  //             id: m.message_seq,
  //             seq: m.message_seq,
  //             fromUID: m.from_uid,
  //             msg:
  //               typeof decoded.text === "string"
  //                 ? decoded.text
  //                 : String(decoded.text ?? ""),
  //             agent: decoded.agent,
  //             customer: decoded.customer,
  //             time: Number(m.timestamp) * 1000,
  //           };
  //         })
  //         .filter((m) => m.seq > baselineSeq)
  //         .sort((a, b) => a.seq - b.seq);

  //       if (!destroyed) {
  //         setMessages(history);
  //       }
  //     } catch (err) {
  //       console.error("❌ History load failed:", err);
  //     }
  //   }

  //   start();

  //   // -----------------------------
  //   // cleanup on unmount / uid change
  //   // -----------------------------
  //   return () => {
  //     destroyed = true;

  //     wkRef.current?.unsubscribe?.();
  //     wkRef.current = null;

  //     if (typingTimerRef.current) {
  //       clearTimeout(typingTimerRef.current);
  //       typingTimerRef.current = null;
  //     }
  //   };

  //   // eslint-disable-next-line react-hooks/exhaustive-deps
  // }, [customerUid, agentReady]);

  useEffect(() => {
    // 🔍 find the latest CUSTOMER message (not agent)
    for (let i = messagesState.length - 1; i >= 0; i--) {
      const m = messagesState[i];

      if (
        m.fromUID !== AGENT_PLATFORM_ID && // not agent
        m.customer // must have customer info
      ) {
        setCustomerProfile((prev) => ({
          ...prev,
          avatar: m.customer.avatar || prev?.avatar,
          name: m.customer.name || prev?.name,
        }));
        break;
      }
    }
  }, [messagesState, AGENT_PLATFORM_ID]);

  useEffect(() => {
    // 1) profile from localStorage (same as customer list)
    // try {
    //   const raw = localStorage.getItem("im_profile_cache_v1");
    //   if (raw) {
    //     const map = JSON.parse(raw);
    //     if (map?.[customerUid]) {
    //       setCustomerProfile(map[customerUid]);
    //     }
    //   }
    // } catch {}

    // 2) online status polling
    const checkOnline = async () => {
      try {
        const res = await fetch(`${WUKONG_API_BASE}/user/onlinestatus`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify([customerUid]),
        });
        const arr = await res.json();
        setCustomerOnline(arr?.[0]?.online === 1);
      } catch {}
    };

    checkOnline();
    const t = setInterval(checkOnline, 5000);
    return () => clearInterval(t);
  }, [customerUid]);

  async function sendTypingEvent(isTyping) {
    if (!agentReady) return; // 🛑 GUARD

    await fetch(`${WUKONG_API_BASE}/event`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        client_msg_no: `typing_${AGENT_PLATFORM_ID}_${Date.now()}`,
        channel_id: customerUid,
        channel_type: 1,
        from_uid: AGENT_PLATFORM_ID,
        event: {
          type: "typing",
          data: JSON.stringify({ typing: isTyping }),
        },
      }),
    });
  }

  function clearUnreadNow(seq) {
    if (!agentReady || !seq) return; // 🛑 GUARD

    localStorage.setItem(readKey, seq);
    setLastReadSeq(seq);

    // optional but recommended (sync server)
    fetch(`${WUKONG_API_BASE}/conversations/clearUnread`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        uid: AGENT_PLATFORM_ID,
        channel_id: customerUid,
        channel_type: 1,
      }),
    }).catch(() => {});
  }

  // ----------------------------------------
  // SEND TEXT
  // ----------------------------------------
  const handleSend = async () => {
    if (!agentReady) return; // 🛑 GUARD
    if (!input.trim()) return;

    const text = input.trim();
    setInput("");

    const payload = JSON.stringify({
      content: text,
      agent: {
        agentId: AGENT_ID,
        name: AGENT_NAME,
        avatar: AGENT_AVATAR,
      },
    });

    // 🔥 ADD THIS LINE
    onAgentSend({
      customerUid,
      content: text, // 🔥 FIX HERE,
      agent: {
        agentId: AGENT_ID,
        name: AGENT_NAME,
        avatar: AGENT_AVATAR,
      },
    });

    const now = Date.now();
    const tempSeq = Number.MAX_SAFE_INTEGER - tempSeqRef.current++;

    setMessagesState((prev) =>
      [
        ...prev,
        {
          id: `temp_${tempSeq}`,
          seq: tempSeq, // ✅ CHANGED
          fromUID: AGENT_PLATFORM_ID,
          msg: text,
          time: now,
          isTemporary: true,
        },
      ].sort((a, b) => a.seq - b.seq),
    );

    await sendWukongText(customerUid, payload);
    sendTypingEvent(false);

    await fetch(`${APSKY_API}/api/app/chat-session/sync-message`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        platformId: AGENT_PLATFORM_ID,
        fromUid: AGENT_ID,
        visitorUid: customerUid,
        fromRole: "agent",
        browserInfo: getBrowserInfo(),
        content: text,
        messageType: 0,
        messageTime: new Date(now),
      }),
    });
  };
  // ----------------------------------------
  // UI
  // ----------------------------------------
  return (
    <div className="h-dvh flex flex-col bg-[#ECECEC] overflow-hidden">
      {/* HEADER (sticky top) */}
      <header className="bg-[#0084FF] text-white px-4 py-4 flex items-center justify-between shadow">
        <div className="flex items-center gap-3">
          {/* Back button */}

          {/* <button
            onClick={() => {
              unlockAudio();
              router.back();
            }}
          >
            <svg width="22" height="22">
              <path
                d="M15 19l-7-7 7-7"
                stroke="white"
                strokeWidth="2"
                fill="none"
              />
            </svg>
          </button> */}

          {/* Avatar */}
          <div className="relative w-9 h-9 rounded-full overflow-visible bg-white/20 flex items-center justify-center">
            <img
              src={customerProfile?.avatar || "/user.png"}
              className="w-full h-full rounded-full object-cover"
              alt={customerProfile?.name || "Customer"}
              onError={(e) => {
                e.currentTarget.src = "/user.png";
              }}
            />

            {/* Online dot */}
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
          ring-[#0084FF]
          ${customerOnline ? "bg-green-400" : "bg-gray-300"}
        `}
              title={customerOnline ? "Online" : "Offline"}
            />
          </div>

          {/* Name + status */}
          <div className="leading-tight">
            <div className="font-semibold text-sm">
              {getCustomerDisplayId(customerProfile?.name)}
            </div>
            <div className="text-xs text-white/80">
              {customerTyping
                ? "Typing…"
                : customerOnline
                  ? "Online"
                  : "Offline"}
            </div>
          </div>
        </div>

        {/* Delete */}
        {/* <button
          onClick={() => {
            if (!messages.length) return;
            if (!confirm("Delete this chat?")) return;

            const lastSeq = messages[messages.length - 1].seq;
            localStorage.setItem(
              `im_baseline_agent_${customerUid}`,
              String(lastSeq)
            );
            setBaselineSeq(lastSeq);
            setMessages([]);
            setLastReadSeq(lastSeq);
          }}
          className="text-xs bg-red-500 px-3 py-1.5 rounded-md hover:bg-red-600"
        >
          Delete
        </button> */}
      </header>

      {/* MESSAGE LIST (only this scrolls) */}
      <div
        className="flex-1 overflow-y-auto px-3 py-4 space-y-3 relative"
        onDrop={handleDrop}
        onDragOver={(e) => e.preventDefault()}
        onScroll={(e) => {
          const el = e.target;
          const nearBottom =
            el.scrollHeight - el.scrollTop - el.clientHeight < 40;

          setShouldAutoScroll(nearBottom);

          if (nearBottom) {
            setShowNewMsgAlert(false);

            if (clearNewTimer) clearTimeout(clearNewTimer);

            const t = setTimeout(() => {
              const newest = messagesState[messagesState.length - 1]?.seq;
              if (newest) {
                clearUnreadNow(newest); // Use the clearUnreadNow helper
              }
            }, 5000);

            setClearNewTimer(t);
          }
        }}
      >
        {messagesState
          .filter((m) => m.seq > baselineSeq) // DELETE BASELINE FILTER
          .map((m, index, arr) => {
            console.log(m);
            const isMe = m.fromUID === AGENT_PLATFORM_ID;

            const prev = arr[index - 1];
            const showSpeaker =
              !prev ||
              prev.fromUID !== m.fromUID ||
              prev.agent?.agentId !== m.agent?.agentId ||
              prev.agent?.name !== m.agent?.name;

            const showDate =
              index === 0 ||
              (prev?.time &&
                new Date(prev.time).toDateString() !==
                  new Date(m.time).toDateString());

            // const isUnreadCustomerMsg = !isMe && m.seq > lastReadSeq; // logic not used, keeping for context

            return (
              <div key={m.id}>
                {/* DATE HEADER */}
                {showDate && (
                  <div className="w-full flex justify-center my-3">
                    <span className="bg-gray-300 text-gray-700 px-3 py-1 rounded-full text-xs">
                      {formatDateHeader(m.time)}
                    </span>
                  </div>
                )}

                {/* MESSAGE ROW */}

                {showSpeaker && (
                  <div
                    className={`text-[11px] mb-1 ${
                      isMe
                        ? "text-right text-blue-600"
                        : "text-left text-gray-500"
                    }`}
                  >
                    {isMe ? m.agent?.name : null}
                  </div>
                )}

                <div
                  className={`flex items-end gap-2 ${
                    isMe ? "justify-end" : "justify-start"
                  }`}
                >
                  {/* CUSTOMER AVATAR */}
                  {!isMe && (
                    <div className="w-8 h-8 rounded-full overflow-hidden bg-gray-300 shrink-0">
                      <img
                        src={
                          m.customer?.avatar ||
                          customerProfile?.avatar ||
                          "/user.png"
                        }
                        className="w-full h-full rounded-full object-cover"
                        alt={
                          m.customer?.name ||
                          customerProfile?.name ||
                          "Customer"
                        }
                        onError={(e) => {
                          e.currentTarget.src = "/user.png";
                        }}
                      />
                    </div>
                  )}

                  {/* MESSAGE BUBBLE */}
                  <div
                    className={`relative max-w-[75%] px-4 py-2 text-sm rounded-2xl shadow ${
                      isMe
                        ? "bg-[#0084FF] text-white rounded-br-none"
                        : "bg-white text-gray-900 rounded-bl-none"
                    }`}
                  >
                    {/* MESSAGE CONTENT */}
                    {(() => {
                      const parts = (m.msg || "")
                        .split("\n")
                        .map((p) => p.trim())
                        .filter(Boolean);

                      if (!parts.length) return null;

                      const first = parts[0];
                      const hasCaption =
                        first.startsWith("[") && first.endsWith("]");
                      const caption = hasCaption ? first.slice(1, -1) : "";
                      const urls = hasCaption ? parts.slice(1) : parts;

                      // const urls = (hasCaption ? parts.slice(1) : parts).map(
                      //   (u) => {
                      //     if (u.startsWith("/uploads/")) {
                      //       return (
                      //         "http://72.61.148.252:9999" +
                      //         u.replace("/uploads", "")
                      //       );
                      //     }
                      //     return u;
                      //   }
                      // );

                      const images = urls.filter((u) =>
                        /\.(jpg|jpeg|png)$/i.test(u),
                      );
                      const videos = urls.filter((u) =>
                        /\.(mp4|mov)$/i.test(u),
                      );

                      // images
                      if (images.length && !videos.length) {
                        return (
                          <div className="space-y-2">
                            <div
                              className={
                                images.length === 1
                                  ? "w-40"
                                  : "grid grid-cols-3 gap-1 max-w-[240px]"
                              }
                            >
                              {images.map((url, i) => (
                                <img
                                  key={i}
                                  src={url}
                                  className="rounded cursor-pointer object-cover"
                                  onClick={() =>
                                    setPreviewMedia({ type: "image", url })
                                  }
                                />
                              ))}
                            </div>
                            {caption && (
                              <div className="text-xs">{caption}</div>
                            )}
                          </div>
                        );
                      }

                      // videos
                      if (videos.length && !images.length) {
                        return (
                          <div className="space-y-2">
                            {videos.map((url, i) => (
                              <video
                                key={i}
                                src={url}
                                controls
                                onClick={() =>
                                  setPreviewMedia({ type: "video", url })
                                }
                                className="w-40 rounded"
                              />
                            ))}
                            {caption && (
                              <div className="text-xs">{caption}</div>
                            )}
                          </div>
                        );
                      }

                      // text
                      return <span>{m.msg}</span>;
                    })()}

                    {/* TIME */}
                    <div className="text-[10px] text-right mt-1 opacity-60">
                      {formatTime(m.time)}
                    </div>
                  </div>

                  {/* AGENT AVATAR */}
                  {isMe && (
                    <div className="w-8 h-8 rounded-full overflow-hidden shrink-0">
                      <img
                        src={AGENT_AVATAR}
                        className="w-full h-full object-cover"
                        alt="Agent"
                      />
                    </div>
                  )}
                </div>
              </div>
            );
          })}

        {/* NEW MESSAGE BUTTON */}
        {showNewMsgAlert && (
          <button
            onClick={() => {
              messageEndRef.current?.scrollIntoView({ behavior: "smooth" });
              setShowNewMsgAlert(false);
              setShouldAutoScroll(true);
            }}
            className="absolute bottom-20 left-1/2 -translate-x-1/2 px-4 py-1 bg-blue-500 text-white rounded-full text-xs shadow"
          >
            New messages ↓
          </button>
        )}

        <div ref={messageEndRef} />
        <div className="h-10" />
      </div>

      {/* POPUP for image/video upload (No change needed here, logic updated in sendAttachment) */}
      {showPopup && files.length > 0 && (
        <div
          className="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4"
          onDrop={handleDrop}
          onDragOver={(e) => e.preventDefault()}
        >
          <div
            className="bg-[#1e1e1e] text-white w-full max-w-md rounded-xl shadow-xl overflow-hidden"
            onDrop={(e) => {
              e.preventDefault();
              e.stopPropagation();
              handleDrop(e);
            }}
            onDragOver={(e) => e.preventDefault()}
          >
            {/* Preview Header */}
            <div className="relative bg-black flex items-center justify-center">
              {(() => {
                const current = files[activeIndex];
                if (!current) return null;

                if (current.type === "image") {
                  return (
                    <img
                      src={current.url}
                      className="max-h-[320px] object-contain"
                    />
                  );
                }

                if (current.type === "video") {
                  return (
                    <video
                      src={current.url}
                      controls
                      className="max-h-[330px]"
                    />
                  );
                }

                return (
                  <div className="text-center p-10">
                    <div className="text-6xl">📄</div>
                    <div className="mt-2">{current.name}</div>
                  </div>
                );
              })()}

              {/* Left / Right arrows if multiple */}
              {files.length > 1 && (
                <>
                  <button
                    onClick={() =>
                      setActiveIndex((prev) =>
                        prev === 0 ? files.length - 1 : prev - 1,
                      )
                    }
                    className="absolute left-3 top-1/2 -translate-y-1/2 bg-black/60 w-8 h-8 rounded-full flex items-center justify-center text-lg"
                  >
                    ‹
                  </button>

                  <button
                    onClick={() =>
                      setActiveIndex((prev) =>
                        prev === files.length - 1 ? 0 : prev + 1,
                      )
                    }
                    className="absolute right-3 top-1/2 -translate-y-1/2 bg-black/60 w-8 h-8 rounded-full flex items-center justify-center text-lg"
                  >
                    ›
                  </button>
                </>
              )}

              {/* Counter */}
              {files.length > 1 && (
                <div className="absolute bottom-3 right-3 bg-black/70 text-xs px-2 py-1 rounded-full">
                  {activeIndex + 1} / {files.length}
                </div>
              )}

              {/* Delete all files */}
              <button
                onClick={() => {
                  setFiles([]);
                  setShowPopup(false);
                  setActiveIndex(0);
                }}
                className="absolute top-3 right-3 bg-black/70 w-9 h-9 rounded-full flex items-center justify-center"
              >
                🗑️
              </button>
            </div>

            {/* Compress Option */}
            {/* <div className="px-4 py-3 border-b border-white/10">
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={compress}
                  onChange={() => setCompress(!compress)}
                />
                Compress the image
              </label>
            </div> */}

            {/* Caption Input + Emoji */}
            <div className="px-4 py-3">
              <div className="flex items-center bg-[#2b2b2b] rounded-lg px-3 py-2">
                <input
                  value={caption}
                  onChange={(e) => setCaption(e.target.value)}
                  placeholder="Caption"
                  className="flex-1 bg-transparent outline-none text-sm"
                />

                <button
                  onClick={() => setShowPopupEmoji((v) => !v)}
                  className="text-xl ml-2"
                >
                  😊
                </button>
              </div>

              {showPopupEmoji && (
                <div className="mt-2">
                  <Picker
                    data={data}
                    onEmojiSelect={(e) => setCaption((p) => p + e.native)}
                  />
                </div>
              )}
            </div>

            {/* Buttons */}
            <div className="flex justify-end gap-3 px-4 py-3 border-t border-white/10">
              <button
                onClick={() => {
                  setFiles([]);
                  setCaption("");
                  setShowPopup(false);
                  setActiveIndex(0);
                }}
                className="px-4 py-2 rounded-lg bg-gray-700 text-sm"
              >
                Cancel
              </button>

              <button
                onClick={sendAttachment}
                disabled={uploading}
                className={`
          px-4 py-2 rounded-lg text-sm text-white
          ${uploading ? "bg-blue-300 cursor-not-allowed" : "bg-blue-500"}
        `}
              >
                {uploading ? "Sending..." : "Send"}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* INPUT BAR (sticky bottom) */}
      <div className="bg-white px-3 py-2 flex items-center gap-3 border-t shadow relative">
        <button
          onClick={() => {
            setShowMainEmoji((v) => !v);
            setShowPopupEmoji(false);
          }}
          className="text-2xl"
        >
          😊
        </button>

        <label className="text-2xl cursor-pointer">
          📎
          <input
            type="file"
            className="hidden"
            multiple
            onChange={handleUpload}
          />
        </label>

        <input
          className="flex-1 text-black border border-gray-300 rounded-full px-3 py-2 text-sm outline-none"
          value={input}
          placeholder="Type a message…"
          onChange={(e) => {
            const val = e.target.value;
            setInput(val);

            // 🔵 START typing (only once)
            if (val.length > 0 && !isTypingRef.current) {
              isTypingRef.current = true;
              sendTypingEvent(true);
            }

            // 🔴 STOP typing if input cleared
            if (val.length === 0 && isTypingRef.current) {
              isTypingRef.current = false;
              sendTypingEvent(false);
            }

            // ⏱️ debounce stop typing
            if (typingTimerRef.current) clearTimeout(typingTimerRef.current);
            typingTimerRef.current = setTimeout(() => {
              if (isTypingRef.current) {
                isTypingRef.current = false;
                sendTypingEvent(false);
              }
            }, 1500);
          }}
          onKeyDown={(e) => {
            if (e.key === "Enter") {
              handleSend();
            }
          }}
        />

        <button
          onClick={handleSend}
          className="bg-[#0084FF] text-white px-4 py-2 text-sm rounded-full shadow"
        >
          Send
        </button>

        {showMainEmoji && (
          <div className="absolute bottom-[60px] left-3 z-40">
            <Picker
              data={data}
              onEmojiSelect={(e) => setInput((prev) => prev + e.native)}
            />
          </div>
        )}
      </div>

      {previewMedia && (
        <div
          className="fixed inset-0 bg-black/90 z-[999] flex items-center justify-center"
          onClick={() => setPreviewMedia(null)}
        >
          <div
            className="relative max-w-full max-h-full p-4"
            onClick={(e) => e.stopPropagation()}
          >
            {/* Close Button */}
            <button
              onClick={() => setPreviewMedia(null)}
              className="    absolute 
    top-4 
    right-4 
    z-50
    w-10 
    h-10 
    rounded-full 
    bg-black/70 
    backdrop-blur 
    flex 
    items-center 
    justify-center 
    text-white 
    text-xl 
    shadow-lg"
            >
              ✕
            </button>

            {previewMedia.type === "image" && (
              <img
                src={previewMedia.url}
                className="max-h-[90vh] max-w-[90vw] object-contain rounded"
              />
            )}

            {previewMedia.type === "video" && (
              <video
                src={previewMedia.url}
                controls
                autoPlay
                className="max-h-[90vh] max-w-[90vw] rounded"
              />
            )}
          </div>
        </div>
      )}
    </div>
  );
}
