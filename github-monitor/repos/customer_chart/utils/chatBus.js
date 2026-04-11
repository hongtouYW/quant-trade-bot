// utils/chatBus.js
const localMsgSubs = new Set();

export function emitLocalMessage(msg) {
  localMsgSubs.forEach((fn) => fn(msg));
}

export function subscribeLocalMessage(fn) {
  localMsgSubs.add(fn);
  return () => localMsgSubs.delete(fn);
}
