"use client";

import { useState } from "react";
import Image from "next/image";
import { useTranslations } from "next-intl";
import { QRCodeCanvas } from "qrcode.react";
import { IMAGES } from "@/constants/images";

export default function ShareDialog({ open, onClose, text, url }) {
  const t = useTranslations();
  const [showQR, setShowQR] = useState(false);

  if (!open) return null;

  const ICONS = [
    { src: IMAGES.earn.iconFb, alt: "facebook" },
    { src: IMAGES.earn.iconTelegram, alt: "telegram" },
    { src: IMAGES.earn.iconWatapps, alt: "whatsapp" },
    { src: IMAGES.earn.iconWechat, alt: "wechat" },
  ];

  const handleShare = (platform) => {
    if (!url) return;

    const message = encodeURIComponent(text);
    const encodedURL = encodeURIComponent(url);

    let shareUrl = "";

    switch (platform) {
      case "facebook":
        shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodedURL}`;
        break;
      case "telegram":
        shareUrl = `https://t.me/share/url?url=${encodedURL}&text=${message}`;
        break;
      case "whatsapp":
        shareUrl = `https://api.whatsapp.com/send?text=${message}%20${encodedURL}`;
        break;
      case "wechat":
        setShowQR(true);
        return;
    }

    window.open(shareUrl, "_blank");
  };

  return (
    <>
      {/* BACKDROP */}
      <div
        className="fixed inset-0 z-[200] bg-black/60 backdrop-blur-sm flex justify-center items-center"
        onClick={onClose}
      />

      {/* MAIN DIALOG */}
      {!showQR && (
        <div className="fixed mx-2 inset-0 z-[201] flex justify-center items-center">
          <div className="w-full max-w-[420px] rounded-2xl bg-[#0B1D48] p-6 text-white shadow-2xl text-center">
            {/* Title */}
            <h3 className="text-lg font-bold mb-3">{text}</h3>

            {/* Icons */}
            <div className="flex justify-center items-center gap-5 mb-8">
              {ICONS.map((it) => (
                <button
                  key={it.alt}
                  onClick={() => handleShare(it.alt)}
                  className="active:scale-95 transition"
                >
                  <Image
                    src={it.src}
                    alt={it.alt}
                    width={130}
                    height={38}
                    className="object-contain"
                  />
                </button>
              ))}
            </div>

            {/* Close */}
            <button
              onClick={onClose}
              className="
                w-full h-12 rounded-full bg-gradient-to-b
                from-[#F8AF07] to-[#FFFC86]
                text-black font-semibold active:scale-95 transition
              "
            >
              {t("common.close")}
            </button>
          </div>
        </div>
      )}

      {/* WECHAT QR DIALOG */}
      {showQR && (
        <div className="fixed inset-0 z-[201] flex justify-center items-center">
          <div className="w-full max-w-[420px] rounded-2xl bg-[#0B1D48] p-6 text-white shadow-2xl text-center">
            {/* Title */}
            <h3 className="text-lg font-bold mb-3">
              {t("earn.share.wechatTip")}
            </h3>

            {/* QR CODE */}
            <QRCodeCanvas
              value={url}
              size={220}
              bgColor="#FFFFFF"
              fgColor="#000000"
              level="H"
              className="mx-auto rounded-md mb-6"
            />

            {/* CLOSE */}
            <button
              onClick={() => setShowQR(false)}
              className="
                w-full h-12 rounded-full bg-gradient-to-b
                from-[#F8AF07] to-[#FFFC86]
                text-black font-semibold active:scale-95 transition
              "
            >
              {t("common.close")}
            </button>
          </div>
        </div>
      )}
    </>
  );
}
