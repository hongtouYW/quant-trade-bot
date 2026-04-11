"use client";

import Image from "next/image";
import Carousel from "react-multi-carousel";
import "react-multi-carousel/lib/styles.css";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images"; // your mapping
import Marquee from "react-fast-marquee";
import QRCode from "react-qr-code";
import { useEffect, useState } from "react";
import { useGetVersionListQuery } from "@/services/commonApi";
import Link from "next/link";

// simple dot like your MemoDot (keep if u already have MemoDot)
const MemoDot = ({ onClick, active }) => (
  <button
    onClick={onClick}
    className={[
      "mx-1 h-2 w-2 rounded-full transition-all",
      active ? "bg-white" : "bg-white/40",
    ].join(" ")}
  />
);

export default function LandingPage() {
  const t = useTranslations("landing");
  const [activeTab, setActiveTab] = useState("tab1");
  const [cards, setCards] = useState([]);
  const banners = [
    { background: IMAGES.intro.banner1 },
    // if later you add more, push here
  ];

  // VERSION LIST API
  const { data, refetch } = useGetVersionListQuery(
    {},
    {
      skip: false, // allow initial auto-load
      refetchOnMountOrArgChange: false,
    }
  );

  // manual fetch once (your requirement)
  useEffect(() => {
    refetch();
  }, []);

  // extract Android / iOS
  const ios = data?.data?.find((v) => v.platform === "ios");
  const android = data?.data?.find((v) => v.platform === "android");

  // safe fallback URL (prevent broken QR / link)
  const androidUrl = android?.url || "";
  const iosUrl = ios?.url || "";

  const responsive = {
    desktop: {
      breakpoint: { max: 3000, min: 1024 },
      items: 1,
    },
    tablet: {
      breakpoint: { max: 1024, min: 640 },
      items: 1,
    },
    mobile: {
      breakpoint: { max: 640, min: 0 },
      items: 1,
    },
  };

  useEffect(() => {
    setCards(generateRandomCards());
  }, []);

  const generateRandomCards = () => {
    return Array.from({ length: 3 }, (_, i) => ({
      id: i + 1,
      league: `League ${Math.floor(Math.random() * 50) + 1}`,
      time: `${Math.floor(Math.random() * 24)}:${Math.floor(Math.random() * 60)
        .toString()
        .padStart(2, "0")}`,
      teamA: `Esport Team ${Math.floor(Math.random() * 100)}`,
      teamB: `Esport Team ${Math.floor(Math.random() * 100)}`,
      oddA: (Math.random() * (1.2 - 0.8) + 0.8).toFixed(2),
      oddB: (Math.random() * (1.2 - 0.8) + 0.8).toFixed(2),
    }));
  };
  const handleTabClick = (key) => {
    setActiveTab(key);
    setCards(generateRandomCards());
  };

  return (
    <div className="w-full bg-white text-black">
      {/* ========== Announcement Bar ========== */}
      <div className="w-full bg-[#FFB49B] py-2 overflow-hidden">
        <div className="mx-auto max-w-6xl flex items-center gap-3 px-4">
          {/* Icon */}
          <Image
            src={IMAGES.intro.campaign}
            alt="campaign"
            width={20}
            height={20}
            className="shrink-0"
          />

          {/* Scrolling text */}
          <Marquee
            gradient={false}
            speed={40}
            pauseOnHover={true}
            className="text-black text-sm"
          >
            {t("announcement.text")}
          </Marquee>
        </div>
      </div>

      {/* banner slider */}
      <section className="w-full bg-white">
        <Carousel
          responsive={responsive}
          infinite
          arrows={false}
          showDots
          autoPlay
          autoPlaySpeed={4000}
          containerClass="!h-[300px] md:!h-[520px]"
          itemClass="!h-[300px] md:!h-[520px]"
          dotListClass="!bottom-[10px]"
          customDot={<MemoDot />}
          draggable
          swipeable
        >
          {banners.map((banner, idx) => (
            <div key={idx} className="relative h-full w-full overflow-hidden">
              <Image
                src={banner.background}
                alt={`Banner ${idx + 1}`}
                fill
                priority={idx === 0}
                className="object-cover"
                sizes="100vw"
              />

              {/* subtle overlay like your example */}
              <div className="absolute inset-0 bg-gradient-to-br from-black/40 to-black/5" />
            </div>
          ))}
        </Carousel>
      </section>

      {/* ================= DOWNLOAD / APP PROMO ================= */}
      <section id="download" className="relative w-full bg-white mt-15">
        {/* PLAYER – outside container so it sticks to the right */}
        <div className="hidden md:block absolute bottom-0 right-0 w-[420px] z-0">
          <Image
            src={IMAGES.intro.player2}
            alt="player"
            width={2000}
            height={3500}
            className="w-full h-auto object-contain"
          />
        </div>

        {/* MAIN CONTENT */}
        <div className="mx-auto max-w-6xl px-4 py-12 md:py-16 grid md:grid-cols-2 gap-8 items-center relative z-10">
          {/* LEFT BLOCK */}
          <div className="space-y-5 relative">
            <div className="flex items-center gap-3">
              <h2 className="text-2xl md:text-3xl font-bold leading-tight">
                {t("download.title")}
              </h2>

              {/* <Image
                src={IMAGES.intro.logo}
                alt="logo"
                width={110}
                height={40}
                className="hidden md:block h-10 w-auto"
              /> */}
            </div>

            <p className="text-black/60 text-sm md:text-base">
              {t("download.desc")}
            </p>

            <div className="flex items-center gap-4 pt-2 md:justify-start justify-center">
              {/* QR CODE (Dynamic URL) */}
              <div className="w-24 h-24 bg-white rounded-xl ring-1 ring-black/10 flex items-center justify-center">
                <QRCode value={androidUrl || "https://google.com"} size={80} />
              </div>

              {/* App Buttons */}
              <div className="flex flex-col gap-2">
                {/* ANDROID */}
                <Link
                  href={androidUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="block"
                >
                  <Image
                    src={IMAGES.intro.playstore}
                    alt="playstore"
                    width={140}
                    height={60}
                    className="h-auto w-[140px]"
                  />
                </Link>

                {/* If iOS exists — future use */}
                {/* {iosUrl && (
                <a
                  href={iosUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="block"
                >
                  <Image
                    src={IMAGES.intro.appstore}
                    alt="appstore"
                    width={140}
                    height={60}
                    className="h-auto w-[140px]"
                  />
                </a>
              )} */}
              </div>
            </div>
          </div>

          {/* RIGHT BLOCK — DESKTOP ONLY */}
          <div className="relative min-h-[520px] hidden md:flex justify-center">
            <div className="absolute top-[130px] left-1/2 -translate-x-1/2 w-[210px] z-10">
              <Image
                src={IMAGES.intro.phone}
                alt="phone"
                width={920}
                height={1928}
                className="w-full h-auto drop-shadow-xl"
                priority
              />
            </div>
          </div>

          {/* MOBILE ONLY BLOCK */}
          <div className="md:hidden w-full relative z-10">
            <div className="relative h-[220px] flex items-end mt-45">
              {/* phone left */}
              <div className="z-10">
                <Image
                  src={IMAGES.intro.phone}
                  alt="phone"
                  width={150}
                  height={300}
                  className="w-[140px] h-auto"
                />
              </div>

              {/* player right */}
              <div className="absolute bottom-0 right-[-20px] z-0">
                <Image
                  src={IMAGES.intro.player2}
                  alt="player"
                  width={250}
                  height={430}
                  className="w-[220px] h-auto"
                />
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ================= GAME CENTER ================= */}
      <section id="games" className="w-full bg-[#DDE4ED99]">
        <div className="mx-auto max-w-6xl px-4 py-10 md:py-14">
          {/* HEADER */}
          <div className="flex items-center justify-between mb-5">
            <div>
              <h3 className="text-xl md:text-2xl font-bold">
                {t("games.title")}
              </h3>
              <p className="text-black/50 text-sm">{t("games.sub")}</p>
            </div>

            {/* Bigger arrows */}
            <div className="flex items-center gap-4 text-black/40">
              <span className="text-4xl font-bold cursor-pointer">‹</span>
              <span className="text-4xl font-bold cursor-pointer">›</span>
            </div>
          </div>

          {/* CARDS */}
          <div className="flex gap-4 overflow-x-auto no-scrollbar pb-2">
            {[1, 2, 3, 4].map((i) => (
              <div
                key={i}
                className="min-w-[220px] md:min-w-[260px] rounded-2xl overflow-hidden shadow-sm ring-1 ring-black/5 relative bg-white"
              >
                {/* FULL CARD BG */}
                <Image
                  src={IMAGES.intro.rectangle1}
                  alt="card background"
                  fill
                  className="object-cover"
                />

                <div className="relative z-10 flex">
                  {/* LEFT GIRL */}
                  <div className="relative w-[45%] h-full min-h-[140px]">
                    <Image
                      src={IMAGES.intro.girl}
                      alt="game girl"
                      fill
                      className="object-cover"
                    />
                  </div>

                  {/* RIGHT TEXT */}
                  <div className="w-[55%] p-4 flex flex-col justify-center">
                    {/* TITLE TEXT ONLY (no bg) */}
                    <div className="text-[#FAF7D1] text-sm md:text-base font-bold [text-shadow:0px_2px_4px_#00000066]">
                      {t("games.cardTitle")}
                    </div>

                    <div className="text-xs text-black/70 mt-1">
                      {t("games.cardLine1")}
                    </div>
                    <div className="text-xs text-black/70">
                      {t("games.cardLine2")}
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ================= LIVE SECTION ================= */}
      <section id="live" className="w-full bg-[#DDE4ED66]">
        <div className="mx-auto max-w-6xl px-4 py-10 md:py-14">
          {/* TITLE + TABS */}
          <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
            <h3 className="text-xl md:text-2xl font-bold">{t("live.title")}</h3>

            <div className="flex items-center gap-3 text-sm text-black/60">
              {/* TAB 1 */}
              <button
                onClick={() => handleTabClick("tab1")}
                className={`px-4 py-1.5 rounded-full font-medium
      ${
        activeTab === "tab1"
          ? "bg-blue-600 text-white"
          : "bg-black/5 text-black"
      }
    `}
              >
                {t("live.tab1")}
              </button>

              {/* TAB 2 */}
              <button
                onClick={() => handleTabClick("tab2")}
                className={`px-4 py-1.5 rounded-full font-medium
                  ${
                    activeTab === "tab2"
                      ? "bg-blue-600 text-white"
                      : "bg-black/5 text-black"
                  }
                `}
              >
                {t("live.tab2")}
              </button>

              {/* TAB 3 */}
              <button
                onClick={() => handleTabClick("tab3")}
                className={`px-4 py-1.5 rounded-full font-medium
      ${
        activeTab === "tab3"
          ? "bg-blue-600 text-white"
          : "bg-black/5 text-black"
      }
    `}
              >
                {t("live.tab3")}
              </button>
            </div>
          </div>

          {/* ⭐ PINK TOP LEAGUE CARDS */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            {cards.map((item) => (
              <div
                key={item.id}
                className="rounded-xl overflow-hidden bg-white shadow-sm ring-1 ring-black/5"
              >
                {/* Header */}
                <div className="bg-[#FFB3A6] px-4 py-2 flex items-center justify-between text-sm font-semibold text-black">
                  <span>{item.league}</span>
                  <span>{item.time}</span>
                </div>

                {/* Body */}
                <div className="px-4 py-3 text-xs text-black/80">
                  <div className="flex justify-between mb-1">
                    <span>{t("live.playingTeam")}</span>
                    <span>{t("live.odds")}</span>
                  </div>

                  <div className="flex justify-between">
                    <span>{item.teamA}</span>
                    <span>{item.oddA}</span>
                  </div>

                  <div className="flex justify-between">
                    <span>{item.teamB}</span>
                    <span>{item.oddB}</span>
                  </div>
                </div>
              </div>
            ))}
          </div>

          {/* VIDEO */}
          <div className="rounded-2xl overflow-hidden bg-black/5 ring-1 ring-black/5">
            <div className="relative w-full aspect-video">
              <iframe
                src="https://www.youtube.com/embed/Oza63bLiJRg?si=g64E5PEhWElPIW0A"
                title="YouTube video player"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                referrerPolicy="strict-origin-when-cross-origin"
                allowFullScreen
                className="absolute inset-0 w-full h-full"
              ></iframe>
            </div>
          </div>

          {/* CTA */}
          <div className="flex justify-center mt-5">
            <Link
              href="/login"
              className="px-8 py-2 rounded-full bg-yellow-400 text-black font-semibold inline-block text-center"
            >
              {t("live.cta")}
            </Link>
          </div>
        </div>
      </section>

      {/* ================= TECH / SERVICE ================= */}
      <section id="service" className="w-full bg-[#FFF]">
        <div className="grid md:grid-cols-4 gap-5 mx-auto max-w-6xl px-4 py-10 md:py-14">
          {[1, 2, 3, 4].map((i) => (
            <div
              key={i}
              className="rounded-2xl overflow-hidden shadow-sm ring-1 ring-black/5"
            >
              {/* TOP SECTION with TALL IMAGE as background */}
              <div
                className="relative h-72 w-full bg-cover bg-center"
                style={{
                  backgroundImage: `url(${IMAGES.intro.tall})`,
                }}
              >
                {/* TEXT OVERLAY */}
                <div className="absolute top-4 left-4 right-4">
                  <div className="text-[#FAF7D1] text-sm md:text-base font-bold [text-shadow:0px_2px_4px_#00000066]">
                    {t("games.cardTitle1")}
                  </div>

                  <div className="text-xs text-black/70 mt-1">
                    {t("games.cardLine1_1")}
                  </div>

                  <div className="text-xs text-black/70">
                    {t("games.cardLine2_1")}
                  </div>
                </div>

                {/* GIRL IMAGE — bottom aligned */}
                <div className="absolute bottom-0 left-0 right-0 h-40">
                  <Image
                    src={IMAGES.intro.girl2}
                    alt="agent"
                    fill
                    className="object-contain object-bottom"
                  />
                </div>
              </div>
            </div>
          ))}
        </div>
      </section>
    </div>
  );
}
