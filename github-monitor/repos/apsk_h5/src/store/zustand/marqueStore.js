import { create } from "zustand";

const MAX_ITEMS = 99;
const STORAGE_KEY = "marquee_items";
const LOCAL_ITEM_TTL = 60 * 60 * 1000;
const normalizeText = (text) => String(text || "").replace(/\s+/g, " ").trim();

const normalizeLocalItem = (item) => {
  if (!item?.id || !item?.text) return null;

  const time = Number(item.time) || Date.now();
  const text = normalizeText(item.text);
  if (!text) return null;

  return {
    id: String(item.id),
    text,
    time,
    expiresAt: Number(item.expiresAt) || time + LOCAL_ITEM_TTL,
    source: "local",
  };
};

const normalizeApiItem = (item, index) => {
  if (!item) return null;

  if (typeof item === "string") {
    const text = normalizeText(item);
    if (!text) return null;

    return {
      id: `api-${text}`,
      text,
      source: "api",
    };
  }

  if (!item?.text) return null;

  const text = normalizeText(item.text);
  if (!text) return null;

  return {
    id: String(item.id || `api-${text}`),
    text,
    source: "api",
  };
};

const mergeItems = (localItems = [], apiItems = []) => {
  const normalizedLocal = localItems
    .map(normalizeLocalItem)
    .filter((item) => item.expiresAt > Date.now())
    .filter(Boolean)
    .sort((a, b) => b.time - a.time);

  const usedTexts = new Set(normalizedLocal.map((item) => item.text));

  const normalizedApi = apiItems
    .map(normalizeApiItem)
    .filter(Boolean)
    .filter((item) => {
      if (usedTexts.has(item.text)) return false;
      usedTexts.add(item.text);
      return true;
    });

  return [...normalizedLocal, ...normalizedApi].slice(0, MAX_ITEMS);
};

export const useMarqueeStore = create((set, get) => ({
  items: [],
  localItems: [],
  apiItems: [],

  init: () => {
    try {
      const stored = JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");
      const localItems = Array.isArray(stored)
        ? stored.map(normalizeLocalItem).filter(Boolean).slice(0, MAX_ITEMS)
        : [];
      const validLocalItems = localItems.filter(
        (item) => item.expiresAt > Date.now(),
      );

      set((state) => ({
        localItems: validLocalItems,
        items: mergeItems(validLocalItems, state.apiItems),
      }));

      localStorage.setItem(STORAGE_KEY, JSON.stringify(validLocalItems));
    } catch {}
  },

  setApiItems: (apiItems = []) => {
    const normalizedApiItems = Array.isArray(apiItems) ? apiItems : [];

    set((state) => ({
      apiItems: normalizedApiItems,
      items: mergeItems(state.localItems, normalizedApiItems),
    }));
  },

  addItem: (item) => {
    const newItem = normalizeLocalItem(item);
    if (!newItem) return;

    const updatedLocalItems = [
      newItem,
      ...get().localItems.filter((it) => it.id !== newItem.id),
    ]
      .filter((item) => item.expiresAt > Date.now())
      .sort((a, b) => b.time - a.time)
      .slice(0, MAX_ITEMS);

    set((state) => ({
      localItems: updatedLocalItems,
      items: mergeItems(updatedLocalItems, state.apiItems),
    }));

    localStorage.setItem(STORAGE_KEY, JSON.stringify(updatedLocalItems));
  },
}));
