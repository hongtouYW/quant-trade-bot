"use client";

import { useRouter } from "next/navigation";
import { useState, useEffect, useRef } from "react";

import { WUKONG_WS_ADDR, WUKONG_API_BASE } from "@/utils/constants";
import { ConnectStatus } from "wukongimjssdk";
import { initWukong } from "@/utils/wukong-customer";

import data from "@emoji-mart/data";
import Picker from "@emoji-mart/react";
import { decodeWuKongPayload } from "../../utils/wukong-customer";
import { APSKY_API, browserInfo, getBrowserInfo } from "../../utils/constants";
import { toast } from "react-hot-toast";
import { compressImage } from "../../utils/imageCompress";

// UTF-8 safe decode for WuKong payload
function b64_to_utf8(str) {
  try {
    return decodeURIComponent(escape(atob(str)));
  } catch {
    return str;
  }
}

export default function CustomerChat() {
  const router = useRouter();

  const [username, setUsername] = useState("");
  const [myUid, setMyUid] = useState("");
  const [avatar, setAvatar] = useState("");

  const [messages, setMessages] = useState([]);
  const [lastReadSeq, setLastReadSeq] = useState(0);
  const [input, setInput] = useState("");
  const [status, setStatus] = useState("Connecting...");
  const [agentStatus, setAgentStatus] = useState("Connecting...");
  const [client, setClient] = useState(null);

  const [shouldAutoScroll, setShouldAutoScroll] = useState(true);
  const [showNewMsgAlert, setShowNewMsgAlert] = useState(false);
  const messageEndRef = useRef(null);
  const [clearNewTimer, setClearNewTimer] = useState(null);
  // Popup + emoji states
  const [files, setFiles] = useState([]);
  const [showPopup, setShowPopup] = useState(false);
  const [caption, setCaption] = useState("");
  const [compress, setCompress] = useState(true);
  const [showMainEmoji, setShowMainEmoji] = useState(false);
  const [showEmoji, setShowEmoji] = useState(false);
  const [activeIndex, setActiveIndex] = useState(0);

  //Agent
  const [agentTyping, setAgentTyping] = useState(false);
  const isTypingRef = useRef(false);
  const typingTimerRef = useRef(null);
  const [platformUid, setPlatformUid] = useState(
    process.env.NEXT_PUBLIC_PLATFORM_UID,
  );
  const AGENT_NAME = process.env.NEXT_PUBLIC_AGENT_NAME;
  const AGENT_AVATAR = process.env.NEXT_PUBLIC_AGENT_AVATAR;

  // 🔵 Loading bar state
  const [loading, setLoading] = useState(false);

  const [showMenu, setShowMenu] = useState(false);
  const [previewMedia, setPreviewMedia] = useState(null);
  const sendingAttachmentRef = useRef(false);
  const [uploading, setUploading] = useState(false);

  // Read username from URL
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const combinedUid = params.get("uid"); // pid:uid
    if (!combinedUid) return;

    const key = "im_" + combinedUid.toLowerCase();
    const saved = localStorage.getItem(key);

    if (!saved) {
      router.push("/login");
      return;
    }

    const parsed = JSON.parse(saved);

    setUsername(parsed.username);
    setMyUid(parsed.uid);
    setAvatar(parsed.avatar);

    // ✅ IMPORTANT: platformUid is finalized HERE
    setPlatformUid(parsed.pid);
  }, []);

  useEffect(() => {
    if (shouldAutoScroll) {
      messageEndRef.current?.scrollIntoView({ behavior: "smooth" });
    } else {
      setShowNewMsgAlert(true); // user not at bottom → show button
    }
  }, [messages]);

  // -----------------------------------------------------------------------
  // LOAD HISTORY (with loading bar)
  // -----------------------------------------------------------------------
  async function loadHistory(uid) {
    setLoading(true);
    try {
      const res = await fetch(`${WUKONG_API_BASE}/channel/messagesync`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          login_uid: uid,
          channel_id: platformUid,
          channel_type: 1,
          start_message_seq: 0,
          end_message_seq: 0,
          pull_mode: 1,
          limit: 99999999,
        }),
      });

      const json = await res.json();

      const baseline =
        Number(localStorage.getItem(`im_baseline_${platformUid}_${uid}`)) || 0;

      return (json.messages || [])
        .filter((m) => m.message_seq > baseline) // 🔥 hide old msgs
        .map((m) => {
          const decoded = decodeWuKongPayload(m.payload);

          return {
            id: m.message_seq,
            fromUID: m.from_uid,
            text: decoded.text, // ✅ correct
            agent: decoded.agent, // ✅ optional
            customer: decoded.customer, // ✅ optional
            time: Number(m.timestamp) * 1000,
          };
        });
    } finally {
      setLoading(false);
    }
  }

  // -----------------------------------------------------------------------
  // AGENT ONLINE
  // -----------------------------------------------------------------------
  async function checkAgentOnline() {
    const res = await fetch(`${WUKONG_API_BASE}/user/onlinestatus`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify([platformUid]),
    });

    const list = await res.json();
    const agent = list?.[0];

    return agent?.online === 1;
  }

  async function sendTypingEvent(isTyping) {
    if (!myUid) return;

    await fetch(`${WUKONG_API_BASE}/event`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        client_msg_no: `typing_${myUid}_${Date.now()}`,
        channel_id: platformUid,
        channel_type: 1,
        from_uid: myUid,
        event: {
          type: "typing",
          data: JSON.stringify({ typing: isTyping, uid: myUid }),
        },
      }),
    });
  }

  useEffect(() => {
    if (!platformUid) return; // wait until it’s set

    const t = setInterval(async () => {
      try {
        const online = await checkAgentOnline(platformUid);
        setAgentStatus(online ? "Online" : "Offline");
      } catch (e) {
        console.error(e);
      }
    }, 5000);

    return () => clearInterval(t);
  }, [platformUid]); // ✅ re-run when platformUid updates
  // -----------------------------------------------------------------------
  // INIT WUKONG
  // -----------------------------------------------------------------------
  async function sendProfileEvent({ uid, username, avatar }) {
    const clientMsgNo = `profile_${uid}`;

    await fetch(`${WUKONG_API_BASE}/event`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        client_msg_no: clientMsgNo,
        channel_id: platformUid, // person channel → agent uid
        channel_type: 1,
        from_uid: uid, // customer
        event: {
          type: "user_profile",
          data: JSON.stringify({
            uid: uid,
            username,
            avatar,
          }),
        },
      }),
    });
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

  useEffect(() => {
    if (!myUid || !platformUid) return;

    let destroyed = false;

    // load history
    loadHistory(myUid).then((h) => {
      if (destroyed) return;
      setMessages(h);
    });

    const c = initWukong({
      uid: myUid,
      // addr: WUKONG_WS_ADDR,
      connectAddrCallback: () => process.env.NEXT_PUBLIC_WUKONG_WS_ADDR,

      onStatusChange: (s) => {
        const connected = s === ConnectStatus.Connected;
        setStatus(connected ? "Connected" : "Disconnected");

        if (connected) {
          sendProfileEvent({
            uid: myUid,
            username,
            avatar,
          });
        }
      },

      onEvent: (evt) => {
        if (evt.type !== "typing") return;
        const data = decodeWuKongEventData(evt.data);
        setAgentTyping(Boolean(data.typing));
      },

      onMessage: async (msg) => {
        // 1️⃣ Decode payload (your existing logic)
        const decoded = decodeWuKongPayload(msg.content?.text);

        // 2️⃣ Normalize timestamp (ms)
        const time = (msg.timestamp ? msg.timestamp : Date.now() / 1000) * 1000;

        // 3️⃣ Build local message
        const newMsg = {
          id: msg.messageSeq || msg.clientMsgNo || Date.now(),
          fromUID: msg.fromUID,
          text: decoded.text,
          agent: decoded.agent,
          customer: decoded.customer,
          time,
        };

        // 4️⃣ Push to UI
        setMessages((prev) => [...prev, newMsg]);

        // 5️⃣ Mark read (your existing logic)
        localStorage.setItem("im_read_customer_" + myUid, newMsg.id);
        setLastReadSeq(newMsg.id);

        // 6️⃣ Decide role (customer page)
        // const fromRole = msg.fromUID === myUid ? "visitor" : "agent";

        // // 7️⃣ 🔁 Sync to ABP (ONLY here)
        // try {
        //   await fetch(`${APSKY_API}/api/app/chat-session/sync-message`, {
        //     method: "POST",
        //     headers: { "Content-Type": "application/json" },
        //     body: JSON.stringify({
        //       platformId: platformUid, // ✅ business platform
        //       fromUid: decoded.agent?.agentId, // ✅ business uid
        //       fromRole, // agent | visitor
        //       content: decoded.text,
        //       messageType: msg.content?.type || 0,
        //       messageTime: new Date(time),
        //     }),
        //   });
        // } catch (err) {
        //   console.error("Chat sync failed", err);
        // }
      },
    });

    setClient(c);

    return () => {
      destroyed = true;
      c?.disconnect?.();
    };
  }, [myUid, platformUid]);

  // -----------------------------------------------------------------------
  // SEND TEXT
  // -----------------------------------------------------------------------

  async function handleSend() {
    if (!input.trim() || !client) return;
    const text = input.trim(); // ✅ FIX: define text once
    const payload = JSON.stringify({
      content: input.trim(),
      customer: {
        uid: myUid,
        name: username,
        avatar: avatar,
      },
    });

    await client.sendText(platformUid, payload);
    sendTypingEvent(false);

    setInput("");

    await fetch(`${APSKY_API}/api/app/chat-session/sync-message`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        platformId: platformUid, // customer platform
        fromUid: myUid, // customer uid
        fromRole: "visitor",
        content: text,
        messageType: 0,
        browserInfo: getBrowserInfo(),
        messageTime: new Date(),
      }),
    });
  }

  function formatTime(ts) {
    if (!ts) return "";
    const d = new Date(ts);
    return d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
  }

  // -----------------------------------------------------------------------
  // FILE HANDLING
  // -----------------------------------------------------------------------
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

    // default date format
    return d.toLocaleDateString();
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

  // -----------------------------------------------------------------------
  // SEND ATTACHMENT
  // -----------------------------------------------------------------------
  async function sendAttachment() {
    if (!client || files.length === 0) return;

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

        // ⭐⭐⭐⭐⭐ ONLY ADD THIS ⭐⭐⭐⭐⭐
        let uploadFile = item.file;

        if (uploadFile.size > 1024 * 1024) {
          uploadFile = await compressImage(uploadFile);
        }

        const form = new FormData();
        form.append("file", uploadFile);
        // ⭐⭐⭐⭐⭐ END ⭐⭐⭐⭐⭐

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

        const payload = JSON.stringify({
          content,
          customer: {
            uid: myUid,
            name: username,
            avatar: avatar,
          },
        });

        await client.sendText(platformUid, payload);

        await fetch(`${APSKY_API}/api/app/chat-session/sync-message`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            platformId: platformUid,
            fromUid: myUid,
            browserInfo: getBrowserInfo(),
            fromRole: "visitor",
            content,
            messageType:
              item.type === "image" ? 1 : item.type === "video" ? 2 : 3,
            messageTime: new Date(),
          }),
        });
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
  // -----------------------------------------------------------------------
  // Delete Messge
  // -----------------------------------------------------------------------

  async function deleteChat() {
    if (!myUid) return;

    if (!messages.length) return;

    const ok = confirm("Delete this chat?");
    if (!ok) return;

    const lastSeq = messages[messages.length - 1].id;

    // 🔥 baseline = last message_seq
    localStorage.setItem(
      `im_baseline_${platformUid}_${myUid}`,
      String(lastSeq),
    );

    setMessages([]);
    setLastReadSeq(lastSeq); // IMPORTANT
  }

  // -----------------------------------------------------------------------
  // RENDER PAGE
  // -----------------------------------------------------------------------
  return (
    <div className="h-dvh flex flex-col bg-[#ECECEC] overflow-hidden">
      {/* HEADER */}
      <header className="bg-[#66B2FF] text-white px-4 py-3 flex items-center justify-between">
        <div className="flex items-center gap-3 px-2">
          {/* <button onClick={() => router.back()}>
            <svg width="22" height="22">
              <path
                d="M15 19l-7-7 7-7"
                stroke="white"
                strokeWidth="2"
                fill="none"
              />
            </svg>
          </button> */}

          <img
            src={AGENT_AVATAR}
            className="w-9 h-9 rounded-full object-cover bg-white/20"
            alt="Agent"
          />

          <div>
            <div className="font-semibold">{AGENT_NAME}</div>
            <div className="text-xs text-white/80">
              {agentTyping ? "Typing…" : agentStatus}
            </div>
          </div>
        </div>

        <div className="relative">
          {/* 3-dot button */}
          {/* <button
            onClick={(e) => {
              e.stopPropagation();
              setShowMenu((v) => !v);
            }}
            className="w-8 h-8 flex items-center justify-center rounded-full hover:bg-white/20"
          >
            <span className="text-xl leading-none">︙</span>
          </button> */}

          {/* Dropdown */}
          {showMenu && (
            <div className="absolute right-0 mt-2 w-44 bg-white rounded-xl shadow-lg overflow-hidden z-50">
              {/* <button
                onClick={() => {
                  setShowMenu(false);

                  const pid = platformUid; // ⭐ from your chat state
                  const uid = myUid;

                  // delete cookies
                  document.cookie = `chat_phone_${pid}=; Max-Age=0; path=/`;
                  document.cookie = `chat_name_${pid}=; Max-Age=0; path=/`;
                  document.cookie = `chat_avatar_${pid}=; Max-Age=0; path=/`;

                  Object.keys(localStorage)
                    .filter((k) => k.startsWith(`im_read_customer_${pid}`))
                    .forEach((k) => localStorage.removeItem(k));

                  // delete session
                  localStorage.removeItem(`im_${uid}`);

                  // redirect
                  window.location.href = `/customer-login?pid=${pid}`;
                }}
                className="w-full text-left px-4 py-3 text-sm text-gray-800 hover:bg-gray-100"
              >
                Switch User
              </button> */}

              {/* <button
                onClick={() => {
                  setShowMenu(false);
                  deleteChat(); // 🔥 your existing delete function
                }}
                className="w-full text-left px-4 py-3 text-sm text-red-600 hover:bg-gray-100"
              >
                Clear Message
              </button> */}

              {/* <button
                onClick={() => {
                  setShowMenu(false);
                  window.open("/agent/chat/list", "_blank");
                }}
                className="w-full text-left px-4 py-3 text-sm text-gray-800 hover:bg-gray-100"
              >
                Agent demo login
              </button> */}
            </div>
          )}
        </div>
      </header>

      {/* 🔵 LOADING BAR */}
      {loading && <div className="h-[3px] w-full bg-blue-500 animate-pulse" />}

      {/* MESSAGE LIST */}
      <div
        className="flex-1 overflow-y-auto p-4 space-y-3"
        onDrop={handleDrop}
        onScroll={(e) => {
          const el = e.target;
          const nearBottom =
            el.scrollHeight - el.scrollTop - el.clientHeight < 40;

          setShouldAutoScroll(nearBottom);

          if (nearBottom) {
            setShowNewMsgAlert(false);

            if (clearNewTimer) clearTimeout(clearNewTimer);

            const t = setTimeout(() => {
              setLastReadSeq((prev) => {
                const newest = messages[messages.length - 1]?.id;
                if (newest) {
                  localStorage.setItem("im_read_customer_" + myUid, newest);
                  return newest;
                }
                return prev;
              });
            }, 5000);

            setClearNewTimer(t);
          }
        }}
        onDragOver={(e) => e.preventDefault()}
      >
        {messages.map((m, index) => {
          const isMe = m.fromUID === myUid;

          const prev = messages[index - 1];
          const showDate =
            index === 0 ||
            new Date(prev.time).toDateString() !==
              new Date(m.time).toDateString();

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
              <div
                id={"msg_" + m.id}
                className={`relative flex items-end gap-2 ${
                  isMe ? "justify-end" : "justify-start"
                }`}
              >
                {/* AGENT AVATAR (LEFT) */}
                {!isMe && (
                  <img
                    src={m.agent?.avatar || AGENT_AVATAR}
                    className="w-8 h-8 rounded-full object-cover bg-gray-300 shrink-0"
                    alt={m.agent?.name || "Agent"}
                  />
                )}

                {/* MESSAGE BUBBLE */}
                <div
                  className={`max-w-[75%] px-4 py-2 text-sm rounded-2xl shadow ${
                    isMe
                      ? "bg-[#66B2FF] text-black rounded-br-none"
                      : "bg-white text-gray-900 rounded-bl-none"
                  }`}
                >
                  {!isMe && m.agent?.name && (
                    <div className="text-xs font-semibold text-gray-500 mb-1">
                      {m.agent.name}
                    </div>
                  )}

                  {/* UNREAD BADGE */}
                  {/* {m.id > lastReadSeq && !isMe && (
                    <span className="unreadBadge">NEW</span>
                  )} */}

                  {/* MESSAGE CONTENT */}
                  {(() => {
                    const parts = (m.text || "")
                      .split("\n")
                      .map((p) => p.trim())
                      .filter(Boolean);
                    if (parts.length === 0) return null;

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
                      u.match(/\.(jpg|jpeg|png)$/i),
                    );
                    const videos = urls.filter((u) => u.match(/\.(mp4|mov)$/i));

                    // IMAGES ONLY
                    if (images.length > 0 && videos.length === 0) {
                      return (
                        <div className="space-y-2">
                          <div
                            className={`grid gap-2 ${
                              images.length > 1 ? "grid-cols-2" : ""
                            }`}
                          >
                            {images.map((url, i) => (
                              <img
                                key={i}
                                src={url}
                                className="w-40 h-40 object-cover rounded cursor-pointer"
                                onClick={() =>
                                  setPreviewMedia({ type: "image", url })
                                }
                              />
                            ))}
                          </div>
                          {caption && <div className="text-sm">{caption}</div>}
                        </div>
                      );
                    }

                    // VIDEOS ONLY
                    if (videos.length > 0 && images.length === 0) {
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
                              className="w-40 rounded cursor-pointer"
                            />
                          ))}
                          {caption && <div className="text-sm">{caption}</div>}
                        </div>
                      );
                    }

                    // MIXED MEDIA
                    if (images.length > 0 || videos.length > 0) {
                      return (
                        <div className="space-y-2">
                          {images.length > 0 && (
                            <div className="grid grid-cols-2 gap-2">
                              {images.map((url, i) => (
                                <img
                                  key={i}
                                  src={url}
                                  className="w-40 h-40 object-cover rounded cursor-pointer"
                                  onClick={() => window.open(url, "_blank")}
                                />
                              ))}
                            </div>
                          )}

                          {videos.length > 0 && (
                            <div className="space-y-2">
                              {videos.map((url, i) => (
                                <video
                                  key={i}
                                  src={url}
                                  controls
                                  className="w-40 rounded cursor-pointer"
                                />
                              ))}
                            </div>
                          )}

                          {caption && <div className="text-sm">{caption}</div>}
                        </div>
                      );
                    }

                    // TEXT ONLY
                    return <span>{m.text}</span>;
                  })()}

                  {/* TIME */}
                  <div className="text-[10px] text-right mt-1 opacity-70">
                    {formatTime(m.time)}
                  </div>
                </div>

                {/* CUSTOMER AVATAR (RIGHT) */}
                {isMe && avatar && (
                  <img
                    src={avatar}
                    className="w-8 h-8 rounded-full object-cover bg-gray-300 shrink-0"
                    alt="Me"
                  />
                )}
              </div>
            </div>
          );
        })}

        {/* NEW MESSAGE ALERT */}
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
        <div className="h-50" />
      </div>

      {/* POPUP (FULL, unchanged) */}
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
          >
            {/* Preview area */}
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

              {/* arrows */}
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

              {/* counter */}
              {files.length > 1 && (
                <div className="absolute bottom-3 right-3 bg-black/70 text-xs px-2 py-1 rounded-full">
                  {activeIndex + 1} / {files.length}
                </div>
              )}

              {/* delete */}
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

            {/* compress option */}
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

            {/* caption input */}
            <div className="px-4 py-3">
              <div className="flex items-center bg-[#2b2b2b] rounded-lg px-3 py-2">
                <input
                  value={caption}
                  onChange={(e) => setCaption(e.target.value)}
                  placeholder="Caption"
                  className="flex-1 bg-transparent outline-none text-sm"
                />

                <button
                  onClick={() => setShowEmoji((v) => !v)}
                  className="text-xl ml-2"
                >
                  😊
                </button>
              </div>

              {showEmoji && (
                <div className="mt-2">
                  <Picker
                    data={data}
                    onEmojiSelect={(e) => setCaption((p) => p + e.native)}
                  />
                </div>
              )}
            </div>

            {/* popup buttons */}
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

      {/* INPUT BAR */}
      <div className="bg-white px-3 py-2 flex items-center gap-3 border-t shadow relative">
        <button
          onClick={() => {
            setShowMainEmoji((v) => !v);
            setShowEmoji(false);
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
          className="flex-1 text-black border border-gray-300 rounded-full px-3 py-2 outline-none text-sm"
          placeholder="Type a message…"
          value={input}
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

            // ⏱️ debounce stop typing (WhatsApp style)
            if (typingTimerRef.current) clearTimeout(typingTimerRef.current);
            typingTimerRef.current = setTimeout(() => {
              if (isTypingRef.current) {
                isTypingRef.current = false;
                sendTypingEvent(false);
              }
            }, 1500);
          }}
          onKeyDown={(e) => {
            if (e.key === "Enter") handleSend();
          }}
        />

        <button
          className="bg-[#66B2FF] text-white px-4 py-2 rounded-full shadow"
          onClick={handleSend}
        >
          Send
        </button>

        {showMainEmoji && (
          <div className="absolute bottom-[64px] left-3 z-40">
            <Picker
              data={data}
              onEmojiSelect={(e) => setInput((prev) => prev + e.native)}
            />
          </div>
        )}

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
                className="
    absolute 
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
    shadow-lg
  "
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
    </div>
  );
}
