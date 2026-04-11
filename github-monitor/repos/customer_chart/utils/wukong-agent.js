import WKSDK, { MessageText, Channel, ChannelTypePerson } from "wukongimjssdk";

/* ------------------------------------------------------------------
 * GLOBAL STATE
 * ------------------------------------------------------------------ */

let sdkInstance = null;
let wired = false;
let lastAuth = null; // 🔥 remember last uid/token for lazy init

/**
 * Global subscriber sets
 */
const statusSubs = new Set();
const messageSubs = new Set();
const eventSubs = new Set();

/* ------------------------------------------------------------------
 * INTERNAL: ensure SDK exists + connected
 * ⚠️ MUST ONLY BE CALLED BY initWukong / lazy send
 * ------------------------------------------------------------------ */

function ensureSdk(uid, token) {
  const sdk = WKSDK.shared();

  // first time init
  if (!sdkInstance) {
    sdkInstance = sdk;

    // force wss
    sdk.config.provider.connectAddrCallback = async (callback) => {
      callback(process.env.NEXT_PUBLIC_WUKONG_WS_ADDR);
    };

    // required by sdk
    sdk.config.provider.conversationListCallback = async () => [];

    wired = false;
  }

  // detect auth change
  const needReconnect =
    sdkInstance.config.uid !== uid || sdkInstance.config.token !== token;

  sdkInstance.config.uid = uid;
  sdkInstance.config.token = token;

  // wire global dispatchers ONCE
  if (!wired) {
    wired = true;

    sdkInstance.connectManager.addConnectStatusListener((status, reason) => {
      statusSubs.forEach((fn) => fn?.(status, reason));
    });

    sdkInstance.chatManager.addMessageListener((msg) => {
      messageSubs.forEach((fn) => fn?.(msg));
    });

    sdkInstance.eventManager.addEventListener((evt) => {
      eventSubs.forEach((fn) => fn?.(evt));
    });
  }

  // connect logic
  try {
    if (needReconnect) {
      sdkInstance.connectManager.disconnect();
    }
    sdkInstance.connectManager.connect();
  } catch (e) {
    console.warn("WuKong connect error:", e);
  }

  return sdkInstance;
}

/* ------------------------------------------------------------------
 * ✅ INIT (CONNECT ONLY)
 * Call this ONCE in SplitPage
 * ------------------------------------------------------------------ */

export function initWukong({ uid, token }) {
  // 🔥 cache auth for lazy send
  lastAuth = { uid, token };

  const sdk = ensureSdk(uid, token);

  return {
    sdk,

    sendText: (toUID, text) => {
      const msg = new MessageText(text);
      const channel = new Channel(toUID, ChannelTypePerson);
      return sdk.chatManager.send(msg, channel);
    },
  };
}

/* ------------------------------------------------------------------
 * ✅ SUBSCRIBE (NO CONNECT, NO RECONNECT)
 * Call this in AgentChatList / AgentChat / CustomerChat
 * ------------------------------------------------------------------ */

export function subscribeWukong({ onStatusChange, onMessage, onEvent }) {
  if (onStatusChange) statusSubs.add(onStatusChange);
  if (onMessage) messageSubs.add(onMessage);
  if (onEvent) eventSubs.add(onEvent);

  return () => {
    if (onStatusChange) statusSubs.delete(onStatusChange);
    if (onMessage) messageSubs.delete(onMessage);
    if (onEvent) eventSubs.delete(onEvent);
  };
}

/* ------------------------------------------------------------------
 * ❌ DESTROY (LOGOUT ONLY)
 * ------------------------------------------------------------------ */

export function destroyWukong() {
  if (!sdkInstance) return;

  try {
    sdkInstance.connectManager.disconnect();
  } catch {}

  statusSubs.clear();
  messageSubs.clear();
  eventSubs.clear();

  sdkInstance = null;
  wired = false;
  lastAuth = null;
}

/* ------------------------------------------------------------------
 * HELPERS
 * ------------------------------------------------------------------ */

export function b64_to_utf8(base64) {
  try {
    const bytes = Uint8Array.from(atob(base64), (c) => c.charCodeAt(0));
    return new TextDecoder("utf-8").decode(bytes);
  } catch {
    return base64;
  }
}

/**
 * ✅ SAFE WuKong payload decoder
 * Handles:
 * - base64
 * - plain string
 * - Uint8Array
 * - wrapped JSON
 */
export function decodeWuKongPayload(payload, textFallback = "") {
  if (!payload) {
    return { text: textFallback, agent: null, customer: null };
  }

  let raw = "";

  try {
    if (typeof payload === "string") {
      raw = payload.includes("{") ? payload : b64_to_utf8(payload);
    } else if (payload instanceof Uint8Array || Array.isArray(payload)) {
      raw = new TextDecoder("utf-8").decode(new Uint8Array(payload));
    } else if (typeof payload === "object") {
      raw = JSON.stringify(payload);
    }
  } catch {
    raw = String(payload);
  }

  try {
    let decoded = JSON.parse(raw);

    // WuKong wrapped content
    if (
      typeof decoded?.content === "string" &&
      decoded.content.trim().startsWith("{")
    ) {
      try {
        decoded = JSON.parse(decoded.content);
      } catch {
        return {
          text: decoded.content,
          agent: decoded.agent || null,
          customer: decoded.customer || null,
        };
      }
    }

    return {
      text: decoded?.content ?? textFallback ?? "",
      agent: decoded?.agent || null,
      customer: decoded?.customer || null,
    };
  } catch {
    return {
      text: raw || textFallback || "",
      agent: null,
      customer: null,
    };
  }
}

/* ------------------------------------------------------------------
 * ✅ SAFE SEND (USED BY AGENT LIST / ANYWHERE)
 * ------------------------------------------------------------------ */

export function sendWukongText(toUID, text) {
  // 🔥 auto-init if needed
  if (!sdkInstance) {
    if (!lastAuth) {
      console.error("WuKong SDK not initialized (no auth)");
      return;
    }

    ensureSdk(lastAuth.uid, lastAuth.token);
  }

  const msg = new MessageText(text);
  const channel = new Channel(toUID, ChannelTypePerson);
  return sdkInstance.chatManager.send(msg, channel);
}
