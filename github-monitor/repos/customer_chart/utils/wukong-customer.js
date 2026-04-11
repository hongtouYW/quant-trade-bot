import WKSDK, { MessageText, Channel, ChannelTypePerson } from "wukongimjssdk";
import { WUKONG_API_BASE } from "./constants";

let sdkInstance = null; // 🔥 GLOBAL SINGLETON

export function initWukong({
  uid,
  token,
  addr,
  onStatusChange,
  onMessage,
  onEvent,
}) {
  // 🔴 VERY IMPORTANT: destroy old connection first
  if (sdkInstance) {
    try {
      sdkInstance.connectManager.disconnect();
    } catch {}
    sdkInstance = null;
  }

  const sdk = WKSDK.shared();
  sdkInstance = sdk;

  // ------------------------
  // CLUSTER MODE
  // ------------------------
  sdk.config.provider.connectAddrCallback = async (callback) => {
    // 🔥 FORCE WSS, ignore WuKong ws_addr
    callback(process.env.NEXT_PUBLIC_WUKONG_WS_ADDR);
  };

  // sdk.config.provider.connectAddrCallback = async (callback) => {
  //   const res = await fetch(`${WUKONG_API_BASE}/route?uid=${uid}`);
  //   const data = await res.json();
  //   callback(data.ws_addr);
  // };

  sdk.config.uid = uid;
  sdk.config.token = token;

  // ⭐ REQUIRED FOR WEB SDK
  sdk.config.provider.conversationListCallback = async () => {
    return [];
  };

  // ------------------------
  // STATUS LISTENER
  // ------------------------
  sdk.connectManager.addConnectStatusListener((status, reason) => {
    onStatusChange?.(status, reason);
  });

  // ------------------------
  // MESSAGE LISTENER
  // ------------------------
  sdk.chatManager.addMessageListener((msg) => {
    onMessage?.(msg);
  });

  // ------------------------
  // EVENT LISTENER
  // ------------------------
  sdk.eventManager.addEventListener((evt) => {
    onEvent?.(evt);
  });

  // 🔌 CONNECT
  sdk.connectManager.connect();

  return {
    sdk,
    sendText: (toUID, text) => {
      const msg = new MessageText(text);
      const channel = new Channel(toUID, ChannelTypePerson);
      return sdk.chatManager.send(msg, channel);
    },
  };
}

// --------------------------------------------------
// 🔥 HARD DESTROY (LOGOUT / PAGE UNMOUNT)
// --------------------------------------------------
export function destroyWukong() {
  if (!sdkInstance) return;

  try {
    sdkInstance.connectManager.disconnect();
  } catch {}

  sdkInstance = null;
}

export function b64_to_utf8(base64) {
  try {
    const bytes = Uint8Array.from(atob(base64), (c) => c.charCodeAt(0));
    return new TextDecoder("utf-8").decode(bytes);
  } catch {
    return base64; // already plain text
  }
}

export function decodeWuKongPayload(payload, textFallback = "") {
  if (!payload) {
    return { text: textFallback, agent: null, customer: null };
  }

  const raw = b64_to_utf8(payload);

  try {
    let decoded = JSON.parse(raw);

    // 🔥 CASE 1: WuKong wrapper { content: "JSON_STRING", type }
    if (
      typeof decoded?.content === "string" &&
      decoded.content.startsWith("{")
    ) {
      try {
        decoded = JSON.parse(decoded.content);
      } catch {
        // content was just normal text
        return {
          text: decoded.content,
          agent: null,
          customer: null,
        };
      }
    }

    // 🔥 FINAL normalized output
    return {
      text: decoded?.content ?? "",
      agent: decoded?.agent || null,
      customer: decoded?.customer || null,
    };
  } catch {
    // fallback plain text
    return {
      text: raw || textFallback,
      agent: null,
      customer: null,
    };
  }
}
