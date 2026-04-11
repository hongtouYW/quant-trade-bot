import { RouterProvider } from "react-router";
import { router } from "./router";
import { useEffect } from "react";
import { API_ENDPOINTS } from "./api/api-endpoint";
import { comicStatistics } from "./utils/plugin/comicStatistics";

import "react-loading-skeleton/dist/skeleton.css";
// Import Swiper styles
import "swiper/css";
// import swiper css for navigation and pagination
// @ts-expect-error - swiper css is not typed
import "swiper/css/navigation";
// @ts-expect-error - swiper css is not typed
import "swiper/css/pagination";

function App() {
  useEffect(() => {
    let globalScript: HTMLScriptElement | null = null;
    // let googleScript: HTMLScriptElement | null = null;
    // let googleScript2: HTMLScriptElement | null = null;

    // 百度统计
    if (import.meta.env.VITE_BAIDU_STATISTICS) {
      globalScript = document.createElement("script");
      globalScript.src = `https://hm.baidu.com/hm.js?${import.meta.env.VITE_BAIDU_STATISTICS
        }`;
      globalScript.async = true;
      document.body.appendChild(globalScript);
    }
    // Comic Statistics
    comicStatistics(API_ENDPOINTS.statisticsVisit, {}).then(() => { });

    // Google Analytics
    // if (import.meta.env.VITE_GOOGLE_ANALYTICS_ID) {
    //   googleScript = document.createElement("script");
    //   googleScript.src = `https://www.googletagmanager.com/gtag/js?id=${
    //     import.meta.env.VITE_GOOGLE_ANALYTICS_ID
    //   }`;
    //   googleScript.async = true;
    //   document.head.appendChild(googleScript);

    //   googleScript2 = document.createElement("script");
    //   googleScript2.innerHTML = `
    //     window.dataLayer = window.dataLayer || [];
    //     function gtag(){dataLayer.push(arguments);}
    //     gtag('js', new Date());
    //     gtag('config', '${import.meta.env.VITE_GOOGLE_ANALYTICS_ID}');
    //   `;
    //   document.head.appendChild(googleScript2);
    // }
    // Comic Statistics
    comicStatistics(API_ENDPOINTS.statisticsVisit, {}).then(() => { });

    return () => {
      if (globalScript) {
        document.body.removeChild(globalScript);
      }
      // if (googleScript) {
      //   document.head.removeChild(googleScript);
      // }
      // if (googleScript2) {
      //   document.head.removeChild(googleScript2);
      // }
    };
  }, []);
  return (
    <>
      {import.meta.env.VITE_APP_REGION === "global" ? (
        <>
          <title>18Toon - Free + Premium Hentai | Adult Manga Online</title>
          <meta
            name="description"
            content="18Toon is an adult manga platform offering free hentai and curated adult comics. Enjoy daily updates of Japanese and Western hentai with high-quality reading experience."
          />

          <link rel="canonical" href="https://18toon.vip/" />

          <meta
            name="keywords"
            content="adult comics, mature graphic novels, NSFW animations, 18+ comic art, mature manga, animated fantasy stories, erotic art storytelling, adult visual novels, mature cartoon stories, 18+ art collection"
          />
          <meta
            name="juicyads-site-verification"
            content="02be8de1e3899b0b5e2ca2aab7554a6e"
          />
          {/* Open Graph */}
          <meta
            property="og:title"
            content="18Toon - Free & Premium Adult Manga Platform"
          />
          <meta
            property="og:description"
            content="Free HD hentai + premium 4K uncensored manga. Daily updates of Japanese adult comics & Western hentai"
          />
          <meta property="og:type" content="website" />
          <meta property="og:url" content="https://18toon.vip/" />
          <meta
            property="og:image"
            content={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/${import.meta.env.VITE_LOGO_URL || "logo-2.png"}`}
          />

          {/* Twitter Card */}
          <meta name="twitter:card" content="summary_large_image" />
          <meta name="twitter:title" content="18Toon - Adult Manga & Hentai" />
          <meta
            name="twitter:description"
            content="Free + premium hentai manga, HD uncensored reading"
          />

          {/* Adult Content Tags */}
          <meta name="rating" content="Adult" />
          <meta name="rta" content="RTA-5042-1996-1400-1577-RTA" />
          <meta name="robots" content="18+, adult" />
        </>
      ) : import.meta.env.VITE_APP_REGION === "china" ? (
        <>
          <title>18漫</title>

          {/* <meta
            name="description"
            content="18Toon 成人漫画平台，提供免费H漫与精选成人漫画。每日更新日本与西方题材，让你轻松畅享高品质成人内容。"
          />

          <link rel="canonical" href="https://18toon.com/zh/" />

          <meta
            name="keywords"
            content="免费H漫, 成人漫画, H漫画, 日本成人漫画, 西方H漫, 18禁漫画, VIP H漫, 18Toon"
          />

          <meta property="og:title" content="18Toon - 免费与高级成人漫画平台" />
          <meta
            property="og:description"
            content="免费高清H漫 + 高级4K无修正版内容。每日更新日本成人漫画与西方H漫画。"
          />
          <meta property="og:type" content="website" />
          <meta property="og:url" content="https://18toon.com/zh/" />
          <meta
            property="og:image"
            content="https://18toon.com/og-home-zh.jpg"
          />

          <meta name="twitter:card" content="summary_large_image" />
          <meta name="twitter:title" content="18Toon - 成人漫画与H漫在线平台" />
          <meta
            name="twitter:description"
            content="免费H漫 + 高级无修正版成人漫画，支持高清阅读与每日更新。"
          />

          <meta name="rating" content="Adult" />
          <meta name="rta" content="RTA-5042-1996-1400-1577-RTA" />
          <meta name="robots" content="18+, adult" /> */}
        </>
      ) : (
        <>
          <title>18Toon - Free + Premium Hentai | Adult Manga Online</title>
        </>
      )}
      <RouterProvider router={router} />
    </>
  );
}

export default App;
