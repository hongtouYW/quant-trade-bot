// "use client";

// import { useRouter } from "next/navigation";
// import { useEffect, useRef, useState } from "react";
// import GameMenuModal from "./components/GameMenuModal";
// import GameWithdrawModal from "./components/GameWithdrawModal";
// import { decryptObject } from "@/utils/encryption";
// import { useTranslations } from "next-intl";

// const CLICK_TOLERANCE = 10;
// const DEFAULT_POS = { x: 20, y: 80 };

// // ⭐ NEW: drag threshold before treating as real drag
// const DRAG_THRESHOLD = 8;

// function clamp(v, min, max) {
//   return Math.max(min, Math.min(max, v));
// }

// export default function IframeTestPage() {
//   const router = useRouter();
//   const t = useTranslations();

//   // -------------------------------
//   // READ QUERY PARAMS VIA WINDOW
//   // -------------------------------
//   const [url, setUrl] = useState("");
//   const [gameMemberId, setGameMemberId] = useState("");
//   const [providerName, setProviderName] = useState("");
//   const [platformType, setPlatformType] = useState("");

//   useEffect(() => {
//     const params = new URLSearchParams(window.location.search);
//     const cipher = params.get("cipher");

//     if (cipher) {
//       const decoded = decryptObject(decodeURIComponent(cipher));
//       const clean = decoded.replace(/^&&/, "");
//       const search = new URLSearchParams(clean);

//       setUrl(search.get("url") || "");
//       setGameMemberId(search.get("gamemember_id") || "");
//       setProviderName(search.get("providerName") || "");
//       setPlatformType(search.get("platformType") || "");
//     }
//   }, []);

//   const [showMenu, setShowMenu] = useState(false);
//   const [showWithdrawModal, setShowWithdrawModal] = useState(false);

//   const btnRef = useRef(null);
//   const frameRef = useRef(null);

//   const dragInfoRef = useRef({
//     moved: false,
//     totalDx: 0,
//     totalDy: 0,
//     startX: 0, // ⭐ ADDED
//     startY: 0, // ⭐ ADDED
//   });

//   const [dragging, setDragging] = useState(false);
//   const [pos, setPos] = useState(DEFAULT_POS);

//   // -----------------------------------------------------
//   // FIX: Disable iframe pointer events when modal is open
//   // -----------------------------------------------------
//   useEffect(() => {
//     if (!frameRef.current) return;

//     const block = showMenu || showWithdrawModal;

//     frameRef.current.style.pointerEvents = block ? "none" : "auto";
//   }, [showMenu, showWithdrawModal]);

//   // -----------------------------------------------------
//   // Resize safety for floating button
//   // -----------------------------------------------------
//   useEffect(() => {
//     const handleResize = () => {
//       if (!btnRef.current) return;

//       const rect = btnRef.current.getBoundingClientRect();
//       const width = window.innerWidth;
//       const height = window.innerHeight;

//       const outX = pos.x + rect.width > width || pos.x < 0;
//       const outY = pos.y + rect.height > height || pos.y < 0;

//       if (outX || outY) {
//         setPos(DEFAULT_POS);
//       } else {
//         setPos((prev) => ({
//           x: clamp(prev.x, 10, width - rect.width - 10),
//           y: clamp(prev.y, 10, height - rect.height - 10),
//         }));
//       }
//     };

//     window.addEventListener("resize", handleResize);
//     return () => window.removeEventListener("resize", handleResize);
//   }, [pos]);

//   // -----------------------------------------------------
//   // Menu Actions
//   // -----------------------------------------------------
//   const handleRefresh = () => window.location.reload();

//   const handleWithdraw = () => {
//     setShowMenu(true);
//     setShowWithdrawModal(true);
//   };

//   const handleExit = () => {
//     router.replace("/");
//   };

//   // -----------------------------------------------------
//   // Drag + Tap Logic
//   // -----------------------------------------------------
//   const onPointerDown = (e) => {
//     // ⭐ CHANGE: not dragging yet (prevent iframe block too early)
//     setDragging(false);

//     dragInfoRef.current = {
//       moved: false,
//       totalDx: 0,
//       totalDy: 0,
//       startX: e.clientX,
//       startY: e.clientY,
//     };

//     btnRef.current.setPointerCapture(e.pointerId);
//   };

//   const onPointerMove = (e) => {
//     const info = dragInfoRef.current;
//     if (!info) return;

//     const dx = e.clientX - info.startX;
//     const dy = e.clientY - info.startY;

//     info.totalDx += Math.abs(dx);
//     info.totalDy += Math.abs(dy);

//     // ⭐ NEW: Only become drag after threshold
//     if (
//       !dragging &&
//       (info.totalDx > DRAG_THRESHOLD || info.totalDy > DRAG_THRESHOLD)
//     ) {
//       setDragging(true);

//       // ⭐ Only block iframe after real drag starts
//       if (frameRef.current) frameRef.current.style.pointerEvents = "none";
//     }

//     if (dragging) {
//       setPos((prev) => ({
//         x: prev.x + dx,
//         y: prev.y + dy,
//       }));
//     }

//     // update start positions
//     info.startX = e.clientX;
//     info.startY = e.clientY;
//   };

//   const onPointerUp = (e) => {
//     if (frameRef.current) frameRef.current.style.pointerEvents = "auto";
//     btnRef.current.releasePointerCapture(e.pointerId);

//     // TAP = open modal
//     if (!dragging) {
//       setShowMenu(true);
//     }

//     setDragging(false);
//   };

//   return (
//     <div className="fixed inset-0 w-screen h-screen overflow-hidden bg-black">
//       {/* GAME MENU */}
//       {showMenu && (
//         <GameMenuModal
//           gameMemberId={gameMemberId}
//           onClose={() => setShowMenu(false)}
//           onRefresh={handleRefresh}
//           onWithdraw={handleWithdraw}
//           onExit={handleExit}
//         />
//       )}

//       {/* WITHDRAW MODAL */}
//       {showWithdrawModal && (
//         <GameWithdrawModal
//           gameName={providerName}
//           gameMemberId={gameMemberId}
//           onClose={() => setShowWithdrawModal(false)}
//         />
//       )}

//       {/* FLOATING BUTTON */}
//       <div
//         ref={btnRef}
//         onPointerDown={onPointerDown}
//         onPointerMove={onPointerMove}
//         onPointerUp={onPointerUp}
//         style={{ left: pos.x, top: pos.y }}
//         className={`
//           absolute z-[9999]
//           w-14 h-14 rounded-full
//           flex flex-col items-center justify-center
//           backdrop-blur-sm cursor-pointer select-none touch-none
//           transition-opacity duration-200
//           ${dragging ? "opacity-100 bg-black/70" : "opacity-70 bg-black/50"}
//         `}
//       >
//         <svg
//           viewBox="0 0 24 24"
//           className="w-6 h-6 text-white"
//           fill="currentColor"
//         >
//           <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z" />
//         </svg>
//         <span className="text-white text-xs mt-[-2px] leading-none">
//           {t("common.home")}
//         </span>
//       </div>

//       {/* IFRAME */}
//       <div id="iframe-wrapper" className="absolute inset-0 w-full h-full">
//         <iframe
//           ref={frameRef}
//           src={url || ""}
//           className="w-full h-full border-none"
//           allow="fullscreen"
//         />
//       </div>
//     </div>
//   );
// }
"use client";

import { useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import GameMenuModal from "./components/GameMenuModal";
import GameWithdrawModal from "./components/GameWithdrawModal";
import { decryptObject } from "@/utils/encryption";
import { useTranslations } from "next-intl";

export default function IframeTestPage() {
  const router = useRouter();
  const t = useTranslations();

  // -------------------------------
  // READ QUERY PARAMS VIA WINDOW
  // -------------------------------
  const [url, setUrl] = useState("");
  const [gameMemberId, setGameMemberId] = useState("");
  const [providerName, setProviderName] = useState("");
  const [platformType, setPlatformType] = useState("");

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const cipher = params.get("cipher");

    if (cipher) {
      const decoded = decryptObject(decodeURIComponent(cipher));
      const clean = decoded.replace(/^&&/, "");
      const search = new URLSearchParams(clean);

      setUrl(search.get("url") || "");
      setGameMemberId(search.get("gamemember_id") || "");
      setProviderName(search.get("providerName") || "");
      setPlatformType(search.get("platformType") || "");
    }
  }, []);

  // -------------------------------
  // MODALS
  // -------------------------------
  const [showMenu, setShowMenu] = useState(false);
  const [showWithdrawModal, setShowWithdrawModal] = useState(false);

  // -------------------------------
  // HOME BUTTON OPACITY CONTROL
  // -------------------------------
  const [homeOpacity, setHomeOpacity] = useState("opacity-80");

  useEffect(() => {
    // ⭐ After 3 seconds, fade out the button
    const timer = setTimeout(() => {
      setHomeOpacity("opacity-40");
    }, 3000);

    return () => clearTimeout(timer);
  }, []);

  // -------------------------------
  // MENU ACTIONS
  // -------------------------------
  const handleRefresh = () => window.location.reload();

  const handleWithdraw = () => {
    setShowMenu(true);
    setShowWithdrawModal(true);
  };

  const handleExit = () => {
    router.replace("/");
  };

  return (
    <div className="fixed inset-0 w-screen h-screen overflow-hidden bg-black">
      {/* ⭐ MENU POPUP */}
      {showMenu && (
        <div className="absolute inset-0 z-[9999]">
          <GameMenuModal
            gameMemberId={gameMemberId}
            onClose={() => setShowMenu(false)}
            onRefresh={handleRefresh}
            onWithdraw={handleWithdraw}
            onExit={handleExit}
          />
        </div>
      )}

      {/* ⭐ WITHDRAW POPUP */}
      {showWithdrawModal && (
        <div className="absolute inset-0 z-[9999]">
          <GameWithdrawModal
            gameName={providerName}
            gameMemberId={gameMemberId}
            onClose={() => setShowWithdrawModal(false)}
          />
        </div>
      )}

      {/* ⭐ FIXED HOME BUTTON WITH AUTO FADE ⭐ */}
      <div
        onClick={() => setShowMenu(true)}
        className={`
          absolute z-[9000]
          left-10 top-0
          w-14 h-14 rounded-full
          flex flex-col items-center justify-center
          backdrop-blur-sm cursor-pointer select-none
          bg-black/50
          transition-opacity duration-500
          ${homeOpacity}   // 👈 Auto-fade after 3 sec
        `}
      >
        <svg
          viewBox="0 0 24 24"
          className="w-6 h-6 text-white"
          fill="currentColor"
        >
          <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z" />
        </svg>
        <span className="text-white text-xs mt-[-2px] leading-none">
          {t("common.home")}
        </span>
      </div>

      {/* ⭐ GAME IFRAME */}
      <div className="absolute inset-0 w-full h-full">
        <iframe
          src={url || ""}
          className="w-full h-full border-none"
          allow="fullscreen none"
          style={{
            overflow: "auto",
          }}
        />
      </div>
    </div>
  );
}
