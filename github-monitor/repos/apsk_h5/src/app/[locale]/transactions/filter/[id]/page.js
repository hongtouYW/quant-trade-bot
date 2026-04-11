"use client";

import { useContext, useEffect, useMemo, useRef, useState } from "react";
import Image from "next/image";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";
import { UIContext } from "@/contexts/UIProvider";
import { useParams, useRouter } from "next/navigation";
import { formatDate, getMemberInfo } from "@/utils/utility";
import { useTransactionListQuery } from "@/services/transactionApi";
import { useDispatch, useSelector } from "react-redux";
import { setCurrentTransaction } from "@/store/slice/transactionDetailSlice";
import SharedLoading from "@/components/shared/LoadingBar/ShareLoading";
import { LIMIT } from "@/constants/globals";
import {
  appendPage,
  resetList,
  nextPage,
} from "@/store/slice/transactionFilterSlice";

const QUICK = [
  "all",
  "today",
  "yesterday",
  "thisWeek",
  "lastWeek",
  "thisMonth",
  "lastMonth",
  "prevDay",
  "nextDay",
];

export default function TransactionsFilter() {
  const t = useTranslations();
  const router = useRouter();
  const params = useParams();
  const [mounted, setMounted] = useState(false);
  const scrollRef = useRef(null);
  const loadMoreRef = useRef(null);
  const FILTER_QUICK_KEY = "txFilterQuick";
  const FILTER_SCROLL_KEY = "txFilterScrollTop";
  const FILTER_DATE_KEY = "txFilterDateRange";

  const filterReadyRef = useRef(false);
  const [filterReady, setFilterReady] = useState(false);

  useEffect(() => setMounted(true), []);

  // Derive playerId from params ONLY after mount
  const [activeTab, setActiveTab] = useState(null);

  useEffect(() => {
    if (mounted && params?.id) setActiveTab(params.id);
  }, [mounted, params]);

  const { showDatePicker } = useContext(UIContext);
  const dispatch = useDispatch();
  // map UI tab -> redux type
  const activeType = activeTab;

  // read redux cache
  const listState = useSelector((s) =>
    activeType ? s.transactionFilter[activeType] : null,
  );

  const cachedRows = listState?.rows ?? [];
  const page = listState?.page ?? 1;
  const hasNextPage = listState?.hasNextPage ?? true;

  const filterLoadingRef = useRef(false);
  // ---- helpers ----
  function truncateDay(d) {
    const x = new Date(d);
    x.setHours(0, 0, 0, 0);
    return x;
  }
  function addDays(d, n) {
    const x = new Date(d);
    x.setDate(x.getDate() + n);
    return x;
  }
  function startOfWeek(d) {
    const x = truncateDay(d);
    const dow = x.getDay();
    const delta = (dow + 6) % 7; // Monday
    return addDays(x, -delta);
  }
  function endOfWeek(d) {
    return addDays(startOfWeek(d), 6);
  }
  function startOfMonth(d) {
    return new Date(d.getFullYear(), d.getMonth(), 1);
  }
  function endOfMonth(d) {
    return new Date(d.getFullYear(), d.getMonth() + 1, 0);
  }
  function fmt(d) {
    if (!(d instanceof Date) || isNaN(d)) return "";
    const day = String(d.getDate()).padStart(2, "0");
    const mon = String(d.getMonth() + 1).padStart(2, "0");
    const yr = d.getFullYear();
    return `${day}/${mon}/${yr}`;
  }
  function toApiDate(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(
      2,
      "0",
    )}-${String(d.getDate()).padStart(2, "0")}`;
  }

  const now = new Date();
  const [activeQuick, setActiveQuick] = useState("all");
  const [startDate, setStartDate] = useState(addDays(truncateDay(now), -89));
  const [endDate, setEndDate] = useState(truncateDay(now));

  const fromFilter =
    typeof window !== "undefined" &&
    sessionStorage.getItem("txFromFilter") === "1";

  // user id for API
  const info = getMemberInfo();
  const userId = info?.member_id;
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  // map quick filter → range
  function setQuick(key) {
    setActiveQuick(key);
    sessionStorage.setItem(FILTER_QUICK_KEY, key);

    let s, e;

    switch (key) {
      case "all": {
        e = truncateDay(now);
        s = addDays(e, -89);
        break;
      }
      case "today": {
        s = e = truncateDay(now);
        break;
      }
      case "yesterday": {
        s = e = addDays(truncateDay(now), -1);
        break;
      }
      case "thisWeek": {
        s = startOfWeek(now);
        e = endOfWeek(now);
        break;
      }
      case "lastWeek": {
        s = addDays(startOfWeek(now), -7);
        e = addDays(s, 6);
        break;
      }
      case "thisMonth": {
        s = startOfMonth(now);
        e = endOfMonth(now);
        break;
      }
      case "lastMonth": {
        const d = new Date(now.getFullYear(), now.getMonth() - 1, 1);
        s = startOfMonth(d);
        e = endOfMonth(d);
        break;
      }
      case "prevDay": {
        s = addDays(today, -1);
        e = today;
        break;
      }
      case "nextDay": {
        s = today;
        e = addDays(today, 1);
        break;
      }
      default:
        return;
    }

    // 🔥 reset paging AFTER computing range
    dispatch(resetList(activeType));

    setStartDate(s);
    setEndDate(e);

    // ✅ persist date range
    sessionStorage.setItem(
      FILTER_DATE_KEY,
      JSON.stringify({
        start: s.toISOString(),
        end: e.toISOString(),
      }),
    );
  }

  useEffect(() => {
    const savedQuick = sessionStorage.getItem("txFilterQuick");
    const savedRange = sessionStorage.getItem("txFilterDateRange");

    if (savedQuick) setActiveQuick(savedQuick);

    if (savedRange) {
      try {
        const { start, end } = JSON.parse(savedRange);
        setStartDate(new Date(start));
        setEndDate(new Date(end));
      } catch {}
    }

    // 🔥 mark filter restored
    filterReadyRef.current = true;
    setFilterReady(true);
  }, []);

  // 🔹 call API
  const { data, isLoading, isFetching } = useTransactionListQuery(
    {
      member_id: userId,
      startdate: toApiDate(startDate),
      enddate: toApiDate(endDate),
      type: activeTab,
      page,
      limit: LIMIT,
    },
    {
      skip: !userId || !activeTab || !filterReady,
      refetchOnMountOrArgChange: !fromFilter,
    },
  );

  const rowsFromApi = useMemo(() => {
    let list = [];
    if (!data || !activeType) return [];
    // choose correct list from API response
    switch (activeType) {
      case "credit":
        list = Array.isArray(data?.credit) ? data.credit : [];
        break;
      case "history":
        list = Array.isArray(data?.history) ? data.history : [];
        break;
      case "point":
        list = Array.isArray(data?.point) ? data.point : [];
        break;
      default:
        list = [];
    }

    return list.map((item) => {
      const isDeposit =
        item.type === "deposit" ||
        item.type === "gamedeposit" ||
        item.type === "reload";

      const isReward =
        item.type === "newmemberregister" ||
        item.type === "newmemberreload" ||
        item.type === "newmemberrecruit" ||
        item.type === "newmembergamereload";

      const amountText = `${isDeposit ? "+" : isReward ? "+" : "-"}RM${Number(
        item.amount || item.betamount || 0,
      ).toFixed(2)}`;

      let txKey = "";
      switch (item.type) {
        case "withdraw":
          if (activeType === "credit") {
            txKey =
              item.payment_id === 1
                ? "transaction.tx.qrPayment"
                : "transaction.tx.withdraw";
          } else {
            txKey = "transaction.tx.pointWithdraw";
          }
          break;

        case "reload":
          if (activeType === "credit") {
            txKey = "transaction.tx.reload";
          } else {
            txKey = "transaction.tx.pointReload";
          }
          break;

        case "gamedeposit":
          txKey = "transaction.tx.gameDeposit";
          break;

        case "gamewithdraw":
          txKey = "transaction.tx.gameWithdraw";
          break;

        case "deposit":
          txKey = "transaction.tx.deposit";
          break;

        default:
          txKey = "transaction.tx.unknown";
      }

      // ================= CREDIT =================
      if (activeType === "credit") {
        return {
          transType: "credit",
          id: item.invoiceno,
          type: item.type,
          amount: item.amount,
          title: item.title + "" + amountText,
          time: item.submit_on || item.created_on || item.updated_on,
          status:
            item.status === 1
              ? "success"
              : item.status === 0
                ? "pending"
                : "failed",
          bank:
            item.type === "withdraw" && item.payment_id !== 1
              ? item.bankaccount?.bank?.bank_name || "-"
              : item.type === "withdraw" && item.payment_id === 1
                ? "CASH"
                : item.type === "deposit" && item.payment_id === 3
                  ? "FPAY"
                  : item.type === "deposit" && item.payment_id === 4
                    ? "SUPERPAY"
                    : "-",
          orderId: item.orderid,
          transactionId: item.transactionId,
          invoice: item.invoiceno,
          paymentId: item.payment_id,
          beforeBalance: item.before_balance,
          afterBalance: item.after_balance,
          charge: item.charge,
          raw: item,
        };
      }

      // ================= POINT =================
      if (activeType === "point") {
        return {
          transType: "point",
          id: item.gamepoint_id || item.id,
          type: item.type,
          amount: item.amount,
          title: t(txKey, { amount: amountText }),
          time: item.start_on || item.created_on,
          status:
            item.status === 1
              ? "success"
              : item.status === 0
                ? "pending"
                : "failed",
          bank:
            item.gamemember?.provider?.provider_name ||
            item.gamemember?.name ||
            "-",
          raw: item,
        };
      }

      // ================= HISTORY =================
      if (activeType === "history") {
        return {
          transType: "history",
          id: item.gamelog_id || item.id,
          title: item.game_name,
          provider: item?.gamemember?.provider?.provider_name || "",
          game: item.game_name,
          time: item.startdt || item.created_on,
          bet: Number(item.betamount || 0).toFixed(2),
          win: Number(item.winloss || 0).toFixed(2),
          begin: Number(item.before_balance || 0).toFixed(2),
          end: Number(item.after_balance || 0).toFixed(2),
          status: item.error === "0" ? "success" : "failed",
          raw: item,
        };
      }

      // ================= FALLBACK =================
      return {
        id: item.id,
        type: item.type,
        amount: item.amount,
        title: t(txKey, { amount: amountText }),
        time: item.created_on,
        status:
          item.status === 1
            ? "success"
            : item.status === 0
              ? "pending"
              : "failed",
        bank: "-",
        raw: item,
      };
    });
  }, [data, t, activeType]);
  const rows = cachedRows.length
    ? cachedRows
    : rowsFromApi.length
      ? rowsFromApi
      : [];

  // const rows = useMemo(() => {
  //   return cachedRows.map((item) => ({
  //     id: item.invoiceno || item.id,
  //     type: item.type,
  //     title: item.title || item.game_name || "-",
  //     time: item.submit_on || item.created_on || item.startdt,
  //     status:
  //       item.status === 1
  //         ? "success"
  //         : item.status === 0
  //         ? "pending"
  //         : "failed",
  //     provider: item?.gamemember?.provider?.provider_name,
  //     bet: item.betamount,
  //     win: item.winloss,
  //     begin: item.before_balance,
  //     end: item.after_balance,
  //     raw: item,
  //   }));
  // }, [cachedRows]);

  // 🔹 normalize rows
  // const rows = useMemo(() => {
  //   let list = [];

  //   // choose correct list
  //   switch (activeTab) {
  //     case "credit":
  //       list = Array.isArray(data?.credit) ? data.credit : [];
  //       break;
  //     case "history":
  //       list = Array.isArray(data?.history) ? data.history : [];
  //       break;
  //     case "point":
  //       list = Array.isArray(data?.point) ? data.point : [];
  //       break;
  //     default:
  //       list = [];
  //   }

  //   return list.map((item) => {
  //     const isDeposit =
  //       item.type === "deposit" ||
  //       item.type === "gamedeposit" ||
  //       item.type === "reload";

  //     const amountText = `${isDeposit ? "+" : "-"}RM${Number(
  //       item.amount || item.betamount || 0
  //     ).toFixed(2)}`;

  //     // -----------------------------
  //     // FIXED txKey (correct switch)
  //     // -----------------------------
  //     let txKey = "";
  //     switch (item.type) {
  //       case "withdraw":
  //         if (activeTab === "credit") {
  //           // CREDIT WITHDRAW — detect QR withdraw
  //           txKey =
  //             item.payment_id === 1
  //               ? "transaction.tx.qrPayment" // 商店QR提现
  //               : "transaction.tx.withdraw"; // 普通提现
  //         } else {
  //           // POINT WITHDRAW
  //           txKey = "transaction.tx.pointWithdraw"; // 积分提现 / 积分兑换
  //         }
  //         break;

  //       case "reload":
  //         if (activeTab === "credit") {
  //           txKey = "transaction.tx.reload"; // 上分
  //         } else {
  //           txKey = "transaction.tx.pointReload"; // 积分上分
  //         }
  //         break;

  //       case "gamedeposit":
  //         txKey = "transaction.tx.gameDeposit"; // 游戏充值
  //         break;

  //       case "gamewithdraw":
  //         txKey = "transaction.tx.gameWithdraw"; // 游戏提现
  //         break;

  //       case "deposit":
  //         txKey = "transaction.tx.deposit"; // 商店充值
  //         break;

  //       default:
  //         txKey = "transaction.tx.unknown"; // fallback
  //     }

  //     // ======================================
  //     // CREDIT
  //     // ======================================
  //     if (activeTab === "credit") {
  //       return {
  //         transType: "credit",
  //         id: item.invoiceno,

  //         amount: item.amount,
  //         type: item.type,

  //         title: t(txKey, { amount: amountText }),

  //         time: item.submit_on || item.created_on || item.updated_on,

  //         status:
  //           item.status === 1
  //             ? "success"
  //             : item.status === 0
  //             ? "pending"
  //             : "failed",

  //         bank: (() => {
  //           if (item.type === "withdraw" && item.payment_id !== 1) {
  //             return item.bankaccount?.bank?.bank_name || "-";
  //           }
  //           if (item.type === "withdraw" && item.payment_id == 1) {
  //             return "CASH";
  //           }
  //           if (item.type === "deposit" && item.payment_id === 3) return "FPAY";
  //           if (item.type === "deposit" && item.payment_id === 4)
  //             return "SUPERPAY";
  //           return "-";
  //         })(),

  //         orderId: item.orderid,
  //         transactionId: item.transactionId,
  //         invoice: item.invoiceno,
  //         paymentId: item.payment_id,
  //         beforeBalance: item.before_balance,
  //         afterBalance: item.after_balance,
  //         charge: item.charge,

  //         raw: item,
  //       };
  //     }

  //     // ======================================
  //     // POINT
  //     // ======================================
  //     if (activeTab === "point") {
  //       return {
  //         transType: "point",
  //         id: item.gamepoint_id || item.id,
  //         amount: item.amount,
  //         type: item.type,

  //         title: t(txKey, { amount: amountText }),

  //         time: item.start_on || item.created_on,

  //         status:
  //           item.status === 1
  //             ? "success"
  //             : item.status === 0
  //             ? "pending"
  //             : "failed",

  //         // SHOW provider name, or game name
  //         bank:
  //           item.gamemember?.provider?.provider_name ||
  //           item.gamemember?.name ||
  //           "-",
  //       };
  //     }

  //     // ======================================
  //     // HISTORY
  //     // ======================================
  //     if (activeTab === "history") {
  //       const providerName = item?.gamemember?.provider?.provider_name || "";

  //       return {
  //         transType: "history",
  //         id: item.gamelog_id || item.id,

  //         title: item.game_name,
  //         provider: providerName,

  //         game: item.game_name,
  //         time: item.startdt || item.created_on,

  //         bet: Number(item.betamount || 0).toFixed(2),
  //         win: Number(item.winloss || 0).toFixed(2),

  //         begin: Number(item.before_balance || 0).toFixed(2),
  //         end: Number(item.after_balance || 0).toFixed(2),

  //         status: item.error === "0" ? "success" : "failed",
  //       };
  //     }

  //     // fallback
  //     return {
  //       id: item.id,
  //       amount: item.amount,
  //       type: item.type,
  //       title: t(txKey, { amount: amountText }),
  //       time: item.created_on,
  //       status:
  //         item.status === 1
  //           ? "success"
  //           : item.status === 0
  //           ? "pending"
  //           : "failed",
  //       bank: "",
  //     };
  //   });
  // }, [data, t, activeTab]);

  // useEffect(() => {
  //   if (sessionStorage.getItem("txFromFilter")) {
  //     sessionStorage.removeItem("txFromFilter");
  //   }
  // }, []);

  useEffect(() => {
    if (!data || !activeType) return;
    filterLoadingRef.current = false;
    let pagination;

    if (activeType === "credit") {
      pagination = data.creditpagination;
    } else if (activeType === "point") {
      pagination = data.pointpagination;
    } else {
      pagination = data.historypagination;
    }

    const apiPage = Number(pagination?.currentpage ?? 1);
    const hasNext = Boolean(pagination?.hasnextpage);

    dispatch(
      appendPage({
        type: activeType,
        rows: rowsFromApi, // ✅ FORMATTED rows
        page: apiPage,
        hasNextPage: hasNext,
      }),
    );
  }, [data, activeType, rowsFromApi, dispatch]);

  useEffect(() => {
    if (!hasNextPage || isFetching) return;
    if (!scrollRef.current || !loadMoreRef.current) return;

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (!entry.isIntersecting) return;
        if (filterLoadingRef.current) return;
        filterLoadingRef.current = true;
        dispatch(nextPage(activeType));
      },
      { root: scrollRef.current, rootMargin: "200px" },
    );

    observer.observe(loadMoreRef.current);
    return () => observer.disconnect();
  }, [hasNextPage, isFetching, activeType, dispatch]);

  // Replace your existing scroll restoration useEffect with this:
  useEffect(() => {
    const isReturning = sessionStorage.getItem("txFromFilter") === "1";
    const saved = sessionStorage.getItem(FILTER_SCROLL_KEY);

    // Only execute if we have data and we are coming BACK from a detail
    if (isReturning && saved && rows.length > 0) {
      const el = scrollRef.current;
      if (el) {
        // Use a small delay to ensure React has finished rendering the list
        const timeoutId = setTimeout(() => {
          el.scrollTo({
            top: Number(saved),
            behavior: "instant",
          });

          // Important: clear it so it doesn't jump again on pagination
          sessionStorage.removeItem(FILTER_SCROLL_KEY);
        }, 50); // 50ms is usually enough for the paint

        return () => clearTimeout(timeoutId);
      }
    }
  }, [rows.length]); // Triggers when rows are pulled from Redux

  // useEffect(() => {
  //   if (!activeType) return;
  //   dispatch(resetList(activeType));
  // }, [activeType, startDate, endDate, dispatch]);

  return (
    <div className="mx-auto max-w-[480px] h-dvh overflow-hidden bg-[#00143D] text-white flex flex-col">
      {/* Sticky Header Wrapper */}
      <div className="sticky top-0 z-20 bg-[#0B1D48]">
        {/* ───────── Row 1: Nav header ───────── */}
        <div className="flex items-center justify-between px-4 py-3">
          <button onClick={() => router.back()} className="z-10 cursor-pointer">
            <Image src={IMAGES.arrowLeft} alt="back" width={20} height={20} />
          </button>

          <h1 className="text-lg font-semibold">
            {t("transaction.filter.title")}
          </h1>

          <button aria-label={t("transaction.filter.clear")} className="p-1">
            <span className="relative block h-6 w-6" />
          </button>
        </div>

        {/* ───────── Row 2: Filter bar ───────── */}
        <div className="pb-4 border-t border-white/10">
          {/* Range title */}
          <h2 className="px-4 mt-3 mb-3 text-sm font-semibold">
            {t("transaction.filter.rangeTitle")}
          </h2>

          {/* Quick chips */}
          <div className="px-4 flex flex-wrap gap-3">
            {QUICK.map((k) => (
              <button
                key={k}
                onClick={() => setQuick(k)}
                className={`rounded-full px-4 py-2 text-sm ${
                  activeQuick === k
                    ? "bg-[#F8AF07] text-[#00143D]"
                    : "text-[#F8AF07] border border-[#F8AF07]"
                }`}
              >
                {t(`transaction.filter.quick.${k}`)}
              </button>
            ))}

            <button
              onClick={() =>
                showDatePicker({
                  mode: "range",
                  type: "date",
                  start: startDate,
                  end: endDate,
                  format: "dd/MM/yyyy",
                  onApply: ({ start, end }) => {
                    dispatch(resetList(activeType));

                    // 2️⃣ update date range
                    setStartDate(start);
                    setEndDate(end);
                  },
                })
              }
              className="rounded-full px-4 py-2 text-sm bg-[#F8AF07] text-[#00143D]"
            >
              {t("transaction.filter.searchByDate")}
            </button>
          </div>

          {/* Date display */}
          <div className="px-4 mt-4 flex items-center justify-between">
            <span className="text-sm text-white/90">
              {fmt(startDate)} – {fmt(endDate)}
            </span>

            <button
              onClick={() => setQuick("all")}
              className="rounded-md px-5 py-2 bg-[linear-gradient(262.63deg,#FFC000_0%,#FE9121_100%)] text-[#00143D] font-medium"
            >
              {t("transaction.filter.reset")}
            </button>
          </div>
        </div>
      </div>

      {/* Body */}
      <div className="flex-1 min-h-0 flex flex-col">
        <div
          className="flex-1 min-h-0 overflow-y-auto hide-scrollbar"
          ref={scrollRef}
        >
          <div className="divide-y divide-white/10 bg-[#162344]">
            {rows.length === 0 && (isLoading || isFetching) ? (
              <SharedLoading />
            ) : rows.length === 0 ? (
              <div className="flex flex-col items-center justify-center py-12 text-white/70">
                <Image
                  src={IMAGES.empty}
                  alt="empty"
                  width={120}
                  height={120}
                  className="mb-3 object-contain"
                />
                <p className="text-sm">{t("common.noRecords")}</p>
              </div>
            ) : (
              rows.map((r, index) => {
                if (activeTab === "history") {
                  return (
                    <div
                      onClick={() => {
                        sessionStorage.setItem("txFromFilter", "1"); // ✅ mark intent

                        sessionStorage.setItem(
                          FILTER_SCROLL_KEY,
                          String(scrollRef.current?.scrollTop || 0),
                        );

                        dispatch(setCurrentTransaction(r));
                        router.push(`/transactions/detail/${r.id}`);
                      }}
                      key={`${r.id}-${index}`}
                      className="cursor-pointer"
                    >
                      <div className="flex items-center justify-between px-4 py-3 text-white">
                        <div className="flex-1">
                          <div className="text-[14px] font-semibold text-[#F8AF07]">
                            {r.provider
                              ? `${r.provider} (${r.title})`
                              : r.title}
                          </div>

                          <div className="mt-1 text-xs text-[#6E6E6E]">
                            {mounted ? formatDate(r.time) : ""}
                          </div>

                          <div className="mt-2 text-sm">
                            <span className="mr-4">
                              {t("transaction.detail.bet")} RM{r.bet}
                            </span>
                            <span>
                              {t("transaction.detail.win")} RM{r.win}
                            </span>
                          </div>

                          <div className="mt-1 text-sm text-white/80">
                            {t("transaction.detail.begin")} RM{r.begin}{" "}
                            &nbsp;&nbsp;
                            {t("transaction.detail.end")} RM{r.end}
                          </div>
                        </div>

                        <div className="relative h-2 w-2 ml-2">
                          <Image
                            src={IMAGES.iconRight}
                            alt="arrow"
                            fill
                            className="object-contain opacity-80"
                          />
                        </div>
                      </div>
                    </div>
                  );
                }

                // ✅ Credit / Point layout
                return (
                  <div
                    onClick={() => {
                      sessionStorage.setItem("txFromFilter", "1"); // ✅ mark intent

                      sessionStorage.setItem(
                        FILTER_SCROLL_KEY,
                        String(scrollRef.current?.scrollTop || 0),
                      );

                      dispatch(setCurrentTransaction(r));
                      router.push(`/transactions/detail/${r.id}`);
                    }}
                    key={`${r.id}-${index}`}
                    className="cursor-pointer"
                  >
                    <div className="w-full px-4 py-4 text-left">
                      <div className="flex items-center">
                        <div className="mr-3 flex h-6 w-6 items-center justify-center">
                          <div className="relative flex h-5 w-5 items-center justify-center text-lg font-bold">
                            {mounted &&
                              ([
                                "deposit",
                                "reload",
                                "gamedeposit",
                                "newmemberregister",
                                "newmemberreload",
                                "newmemberrecruit",
                                "newmembergamereload",
                              ].includes(r.type)
                                ? "+"
                                : "-")}
                          </div>
                        </div>

                        <div className="flex-1">
                          <div className="text-[14px] font-semibold text-[#F8AF07]">
                            {r.title}
                          </div>
                          <div className="mt-1 text-xs text-[#6E6E6E]">
                            {r.time}
                          </div>
                        </div>

                        <div className="mr-2 text-xs">
                          <span
                            className={
                              r.status === "success"
                                ? "text-[#3DD36B]"
                                : r.status === "failed"
                                  ? "text-[#FF6B6B]"
                                  : "text-white/70"
                            }
                          >
                            {r.status === "success"
                              ? t("transaction.status.success")
                              : r.status === "failed"
                                ? t("transaction.status.failed")
                                : t("transaction.status.pending")}
                          </span>
                        </div>

                        <div className="relative h-2 w-2">
                          <Image
                            src={IMAGES.iconRight}
                            alt="arrow"
                            fill
                            className="object-contain opacity-80"
                          />
                        </div>
                      </div>
                    </div>
                  </div>
                );
              })
            )}
            {/* Load more spinner */}
            {isFetching && page > 1 && hasNextPage && (
              <div className="flex justify-center py-4">
                <div className="h-5 w-5 animate-spin rounded-full border-2 border-white/30 border-t-[#F8AF07]" />
              </div>
            )}
            <div ref={loadMoreRef} className="h-1" />
          </div>
        </div>
      </div>
    </div>
  );
}
