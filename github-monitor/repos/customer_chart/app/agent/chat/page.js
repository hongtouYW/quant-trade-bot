"use client";

import { useEffect, useRef, useState } from "react";
import AgentChat from "./[uid]/page";
import AgentChatList from "./list/page";
import {
  initWukong,
  subscribeWukong,
  decodeWuKongPayload,
} from "@/utils/wukong-agent";
// import { PLATFORM_UID } from "../../../utils/constants";

export default function AgentChatSplitPage() {
  const [selectedUid, setSelectedUid] = useState(null);
  const wkInitedRef = useRef(false);
  const [chatListMessages, setChatListMessages] = useState([]);
  const [chatMessagesMap, setChatMessagesMap] = useState({});
  const [rawMessages, setRawMessages] = useState([]);
  const [connected, setConnected] = useState(false);
  const [status, setStatus] = useState("Connecting...");
  const [rawEvents, setRawEvents] = useState({});
  const [agentSession, setAgentSession] = useState(null);
  const [agentLoaded, setAgentLoaded] = useState(false);

  useEffect(() => {
    try {
      const key = Object.keys(localStorage).find((k) =>
        k.startsWith("im_agent_")
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

  // const key = Object.keys(localStorage).find((k) => k.startsWith("im_agent_"));
  // if (!key) return;

  // const session = JSON.parse(localStorage.getItem(key));
  // if (!session?.platformId || !session?.token) return;

  const appendLocalAgentRawMessage = ({ customerUid, content, agent }) => {
    const now = Date.now();

    setRawMessages((prev) => [
      ...prev,
      {
        fromUID: agentSession.platformId,
        toUID: customerUid,
        content: {
          text: JSON.stringify({ content, agent }),
        },
        messageSeq: now,
        clientMsgNo: `local_${now}`,
        timestamp: now / 1000,
      },
    ]);
  };

  useEffect(() => {
    if (!agentSession) return; // wait until loaded
    const key = Object.keys(localStorage).find((k) =>
      k.startsWith("im_agent_")
    );
    if (!key) return;

    if (wkInitedRef.current) return;
    wkInitedRef.current = true;

    initWukong({
      uid: agentSession.platformId,
      token: agentSession.token,
    });

    const unsubscribe = subscribeWukong({
      onStatusChange: (s) => {
        if (s === 1) {
          setConnected(true);
          setStatus("Connected");
        }
        if (s === 2) {
          setConnected(false);
          setStatus("Disconnected");
        }
      },

      onEvent: (evt) => {
        setRawEvents(evt);
      },

      onMessage: (msg) => {
        if (!msg?.messageSeq) return;

        setRawMessages((prev) => [...prev, msg]);

        // if (!msg?.messageSeq) return;

        // const from = msg.fromUID;
        // const to = msg.toUID;
        // const uid = from === session.platformId ? to : from;

        // const decoded = decodeWuKongPayload(msg.payload || msg.content?.text);

        // const time = msg.timestamp ? msg.timestamp * 1000 : Date.now();

        // setChatListMessages((prev) => {
        //   const ex = prev.find((c) => c.uid === uid);
        //   return [
        //     {
        //       uid,
        //       lastText: decoded.text || "",
        //       timestamp: time,
        //       unread: uid === selectedUid ? 0 : (ex?.unread || 0) + 1,
        //     },
        //     ...prev.filter((c) => c.uid !== uid),
        //   ];
        // });

        // setChatMessagesMap((prev) => {
        //   const list = prev[uid] || [];
        //   if (list.some((m) => m.seq === msg.messageSeq)) return prev;

        //   return {
        //     ...prev,
        //     [uid]: [
        //       ...list,
        //       {
        //         id: msg.messageSeq,
        //         seq: msg.messageSeq,
        //         fromUID: msg.fromUID,
        //         msg: decoded.text || "",
        //         agent: decoded.agent,
        //         customer: decoded.customer,
        //         time,
        //       },
        //     ],
        //   };
        // });
      },
    });

    return () => unsubscribe();
  }, [agentSession]); // 👈 important dependency

  return (
    <div className="h-dvh flex bg-[#ECECEC] overflow-hidden">
      {/* LEFT */}
      <div className="w-[360px] border-r bg-[#F7F8FA] overflow-y-auto">
        <AgentChatList
          rawEvents={rawEvents}
          connected={connected}
          status={status}
          activeUid={selectedUid}
          onSelectChat={setSelectedUid}
          messages={rawMessages}
        />
      </div>

      {/* RIGHT */}
      <div className="flex-1">
        {selectedUid ? (
          <AgentChat
            rawEvents={rawEvents}
            onAgentSend={appendLocalAgentRawMessage}
            customerUid={selectedUid}
            connected={connected}
            status={status}
            messages={rawMessages}
          />
        ) : (
          <div className="h-full flex items-center justify-center text-gray-400">
            Select a chat to start
          </div>
        )}
      </div>
    </div>
  );
}
