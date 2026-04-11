"use client";

import { useContext, useEffect, useMemo, useRef, useState } from "react";
import Image from "next/image";
import Link from "next/link";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";
import { useRouter } from "next/navigation";
import { formatDate, getMemberInfo } from "@/utils/utility";
import { useTransactionListQuery } from "@/services/transactionApi";
import { useDispatch, useSelector } from "react-redux";
import {
  appendPage,
  resetList,
  nextPage,
} from "@/store/slice/transactionListSlice";

import { resetList as resetFilterList } from "@/store/slice/transactionFilterSlice";

import { setCurrentTransaction } from "@/store/slice/transactionDetailSlice";
import { UIContext } from "@/contexts/UIProvider";
import EmptyRecord from "@/components/shared/EmptyRecord";
import SharedLoading from "@/components/shared/LoadingBar/ShareLoading";
import { LIMIT } from "@/constants/globals";

const TABS = ["credit", "point", "history"];

export default function TransactionsScreen() {
  const t = useTranslations(); // no namespace
  const [active, setActive] = useState(() => {
    // read before first render
    const saved =
      typeof window !== "undefined"
        ? sessionStorage.getItem("txActiveTab")
        : null;

    return saved || "credit"; // default if nothing saved
  });

  const [restored, setRestored] = useState(false);
  const scrollRef = useRef(null);
  const loadMoreRef = useRef(null);
  const loadingPageRef = useRef(false);
  const currentPageRef = useRef(1);
  const totalPagesRef = useRef(1);

  const restoredRef = useRef(false);
  const router = useRouter();
  const [mounted, setMounted] = useState(false);
  useEffect(() => setMounted(true), []);
  const dispatch = useDispatch();
  // const { setLoading } = useContext(UIContext);

  // user id for API
  const info = getMemberInfo();
  const userId = info?.member_id;

  // map UI tab -> API type
  const activeType = active;

  const listState = useSelector((s) => s.transactionList[activeType]);

  const { rows: cachedRows, page, hasNextPage } = listState;

  // fetch list (POST query)
  const { data, isLoading, isFetching, refetch } = useTransactionListQuery(
    {
      member_id: userId,
      type: activeType,
      limit: LIMIT,
      page: page,
    },
    {
      skip: !userId,
      refetchOnMountOrArgChange: true,
    },
  );
  // save when active changes
  useEffect(() => {
    sessionStorage.setItem("txActiveTab", active);
  }, [active]);

  useEffect(() => {
    const force = sessionStorage.getItem("txForceRefresh");
    if (!force) return;

    sessionStorage.removeItem("txForceRefresh");

    // 1️⃣ Reset ALL lists (page -> 1, rows -> [])
    dispatch(resetList("credit"));
    dispatch(resetList("point"));
    dispatch(resetList("history"));

    refetch();
  }, [activeType, dispatch, refetch]);
  // useEffect(() => {
  //   setLoading(isLoading || isFetching);
  // }, [isLoading, isFetching, setLoading]);

  // normalize rows for UI
  const rows = useMemo(() => {
    let list = cachedRows;

    // pick correct array based on activeType
    // switch (activeType) {
    //   case "credit":
    //     list = cachedRows;
    //     break;
    //   case "history":
    //     list = cachedRows;
    //     break;
    //   case "point":
    //     list = cachedRows;
    //     break;
    //   default:
    //     list = [];
    // }

    return list.map((item) => {
      // ✅ common deposit logic
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
            // CREDIT WITHDRAW — detect QR withdraw
            txKey =
              item.payment_id === 1
                ? "transaction.tx.qrPayment" // 商店QR提现
                : "transaction.tx.withdraw"; // 普通提现
          } else {
            // POINT WITHDRAW
            txKey = "transaction.tx.pointWithdraw"; // 积分提现 / 积分兑换
          }
          break;

        case "reload":
          if (activeType === "credit") {
            txKey = "transaction.tx.reload"; // 上分
          } else {
            txKey = "transaction.tx.pointReload"; // 积分上分
          }
          break;

        case "gamedeposit":
          txKey = "transaction.tx.gameDeposit"; // 游戏充值
          break;

        case "gamewithdraw":
          txKey = "transaction.tx.gameWithdraw"; // 游戏提现
          break;

        case "deposit":
          txKey = "transaction.tx.deposit"; // 商店充值
          break;

        default:
          txKey = "transaction.tx.unknown"; // fallback
      }

      // ✅ credit
      if (activeType === "credit") {
        return {
          transType: "credit",
          id: item.invoiceno,

          amount: item.amount,
          type: item.type, // deposit / withdraw

          title: item.title + "" + amountText,

          time: item.submit_on || item.created_on || item.updated_on,

          status:
            item.status === 1
              ? "success"
              : item.status === 0
                ? "pending"
                : "failed",

          bank: (() => {
            if (item.type === "withdraw" && item.payment_id !== 1) {
              return item.bankaccount?.bank?.bank_name || "-";
            } else if (item.type === "withdraw" && item.payment_id == 1) {
              return "CASH";
            }

            if (item.type === "deposit" && item.payment_id === 3) return "FPAY";
            if (item.type === "deposit" && item.payment_id === 4)
              return "SUPERPAY";

            // 3️⃣ no matching payment_id
            return "CASH";
          })(),

          // Extra useful metadata
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

      // ✅ point
      if (activeType === "point") {
        return {
          transType: "point",
          id: item.prefix,

          amount: item.amount,
          type: item.type,

          title: t(txKey, { amount: amountText }),

          time: item.start_on || item.created_on,

          status:
            item.status === 1
              ? "success"
              : item.status === 0
                ? "pending"
                : "failed",

          // ⭐ POINT: show provider name, not bank
          bank:
            item.gamemember?.provider?.provider_name ||
            item.gamemember?.name ||
            "-",

          providerIcon: item.gamemember?.provider?.icon || null,
          providerPlatform: item.gamemember?.provider?.provider_name || "",
          gameMemberId: item.gamemember?.gamemember_id,

          beforeBalance: item.before_balance,
          afterBalance: item.after_balance,

          raw: item,
        };
      }

      // ✅ history
      if (activeType === "history") {
        const providerName = item?.gamemember?.provider?.provider_name || "";

        return {
          transType: "history",
          id: item.prefix,
          title: item.game_name,
          provider: providerName, // <── ADD THIS
          game: item.game_name,
          time: item.startdt || item.created_on,
          bet: Number(item.betamount || 0).toFixed(2),
          win: Number(item.winloss || 0).toFixed(2),
          begin: Number(item.before_balance || 0).toFixed(2),
          end: Number(item.after_balance || 0).toFixed(2),
          status: item.error === "0" ? "success" : "failed",
        };
      }

      // fallback
      return {
        id: item.id,
        amount: item.amount,
        type: item.type,
        title: t(txKey, { amount: amountText }),
        time: item.created_on,
        status:
          item.status === 1
            ? "success"
            : item.status === 0
              ? "pending"
              : "failed",
        bank: "",
      };
    });
  }, [cachedRows, t, activeType]);

  useEffect(() => {
    if (!hasNextPage) return;

    const target = loadMoreRef.current;
    const root = scrollRef.current;
    if (!target || !root) return;

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (
          entry.isIntersecting &&
          !isFetching &&
          !loadingPageRef.current &&
          hasNextPage
        ) {
          loadingPageRef.current = true;
          dispatch(nextPage(activeType));
        }
      },
      {
        root, // 🔴 MUST be the scroll container
        rootMargin: "200px",
      },
    );

    observer.observe(target);
    return () => observer.disconnect();
  }, [hasNextPage, isFetching, activeType, dispatch]);

  useEffect(() => {
    if (!data) return;

    loadingPageRef.current = false;

    let newItems = [];
    let pagination;

    if (activeType === "credit") {
      newItems = data.credit || [];
      pagination = data.creditpagination;
    } else if (activeType === "point") {
      newItems = data.point || [];
      pagination = data.pointpagination;
    } else {
      newItems = data.history || [];
      pagination = data.historypagination;
    }

    const apiPage = Number(pagination?.currentpage ?? 1);
    const totalPages = Number(pagination?.totalpages ?? 1);
    const hasNext = Boolean(pagination?.hasnextpage);

    currentPageRef.current = apiPage;
    totalPagesRef.current = totalPages;

    dispatch(
      appendPage({
        type: activeType,
        rows: newItems,
        page: apiPage,
        hasNextPage: hasNext,
      }),
    );
  }, [data, activeType, dispatch]);

  // useEffect(() => {
  //   if (!data) return;

  //   loadingPageRef.current = false;
  //   let newItems = [];
  //   let pagination;

  //   if (activeType === "credit") {
  //     newItems = data.credit || [];
  //     pagination = data.creditpagination;
  //   } else if (activeType === "point") {
  //     newItems = data.point || [];
  //     pagination = data.pointpagination;
  //   } else {
  //     newItems = data.history || [];
  //     pagination = data.historypagination;
  //   }

  //   const apiPage = Number(pagination?.currentpage ?? 1);
  //   const totalPages = Number(pagination?.totalpages ?? 1);

  //   currentPageRef.current = apiPage;
  //   totalPagesRef.current = totalPages;

  //   setCachedRows((prev) => {
  //     if (apiPage === 1) return newItems;

  //     const getKey = (item) => {
  //       if (activeType === "credit") return item.credit_id;
  //       if (activeType === "point") return item.gamepoint_id;
  //       if (activeType === "history") return item.gamelog_id;
  //       return item.id;
  //     };

  //     const seen = new Set(prev.map(getKey));

  //     return [...prev, ...newItems.filter((i) => !seen.has(getKey(i)))];
  //   });

  //   // 🔴 THIS is the end condition
  //   setHasNextPage(Boolean(pagination?.hasnextpage));
  // }, [data, activeType]);

  // useEffect(() => {
  //   dispatch(resetList(activeType));
  // }, [activeType, dispatch]);

  // useEffect(() => {
  //   const saved = sessionStorage.getItem("txScrollTop");
  //   if (!saved) return;
  //   if (!scrollRef.current) return;

  //   // wait until page 1 data is loaded
  //   if (page !== 1) return;
  //   if (isFetching) return;

  //   scrollRef.current.scrollTop = Number(saved);
  //   sessionStorage.removeItem("txScrollTop");
  // }, [page, isFetching]);

  // useEffect(() => {
  //   if (!hasNextPage || isFetching) return;

  //   const onScroll = () => {
  //     const nearBottom =
  //       window.innerHeight + window.scrollY >= document.body.offsetHeight - 200;

  //     if (!nearBottom) return;

  //     setPage((p) => p + 1);
  //   };

  //   window.addEventListener("scroll", onScroll);
  //   return () => window.removeEventListener("scroll", onScroll);
  // }, [hasNextPage, isFetching]);
  useEffect(() => {
    const restore = () => {
      if (restoredRef.current) return;

      const saved = sessionStorage.getItem("txScrollTop");
      if (!saved) return;

      // ✅ pick the real scroller
      const el = scrollRef.current;
      const val = Number(saved);

      // run after paint
      requestAnimationFrame(() => {
        if (el && el.scrollHeight > el.clientHeight) {
          el.scrollTop = val; // container scroll
        } else {
          //window.scrollTo(0, val); // window scroll
        }

        restoredRef.current = true;
        sessionStorage.removeItem("txScrollTop");
      });
    };

    // 1) normal mount restore
    restore();

    // 2) back/forward cache restore (no remount case)
    const onPageShow = () => restore();
    window.addEventListener("pageshow", onPageShow);

    return () => window.removeEventListener("pageshow", onPageShow);
  }, []);
  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white flex flex-col">
      {/* Header */}
      <div className="sticky top-0 z-20 bg-[#00143D]">
        <div className="relative flex items-center px-4 py-4">
          <button onClick={() => router.back()} className="z-10 cursor-pointer">
            <Image src={IMAGES.arrowLeft} alt="back" width={20} height={20} />
          </button>
          <h1 className="absolute inset-x-0 text-lg font-semibold text-center">
            {t("transaction.title")}
          </h1>
        </div>
        {/* Tabs */}
        <div className="mt-4 flex items-center justify-around text-sm">
          <button
            onClick={() => {
              loadingPageRef.current = false;
              dispatch(resetList("credit"));
              setActive("credit");
            }}
            className={`relative pb-2 text-sm whitespace-nowrap ${
              active === "credit" ? "text-[#F8AF07]" : "text-[#F8AF0780]"
            }`}
          >
            {t("transaction.tabs.credit")}
            {active === "credit" && (
              <span className="absolute left-0 -bottom-[2px] h-[3px] w-full rounded-sm bg-[#F8AF07]" />
            )}
          </button>

          <button
            onClick={() => {
              loadingPageRef.current = false;
              dispatch(resetList("point"));
              setActive("point");
            }}
            className={`relative pb-2 text-sm whitespace-nowrap ${
              active === "point" ? "text-[#F8AF07]" : "text-[#F8AF0780]"
            }`}
          >
            {t("transaction.tabs.points")}
            {active === "point" && (
              <span className="absolute left-0 -bottom-[2px] h-[3px] w-full rounded-sm bg-[#F8AF07]" />
            )}
          </button>

          <button
            onClick={() => {
              loadingPageRef.current = false;
              dispatch(resetList("history"));
              setActive("history");
            }}
            className={`relative pb-2 text-sm whitespace-nowrap ${
              active === "history" ? "text-[#F8AF07]" : "text-[#F8AF0780]"
            }`}
          >
            {t("transaction.tabs.bets")}
            {active === "history" && (
              <span className="absolute left-0 -bottom-[2px] h-[3px] w-full rounded-sm bg-[#F8AF07]" />
            )}
          </button>
        </div>
      </div>

      {/* List */}
      <div
        ref={scrollRef}
        className="flex-1 min-h-0 overflow-y-auto hide-scrollbar"
      >
        <div className="mt-4 divide-y divide-white/5 bg-[#162344]">
          {/* 1️⃣ Loading state */}
          {rows.length === 0 && (isLoading || isFetching) ? (
            <>
              <SharedLoading />
            </>
          ) : rows.length === 0 ? (
            /* 2️⃣ Empty */
            <EmptyRecord />
          ) : (
            /* 3️⃣ Real Data */
            rows.map((r, index) => {
              if (activeType === "history") {
                return (
                  <div
                    onClick={() => {
                      sessionStorage.setItem(
                        "txScrollTop",
                        String(scrollRef.current?.scrollTop || 0),
                      );

                      dispatch(setCurrentTransaction(r));
                      router.push(`/transactions/detail/${r.id}`);
                    }}
                    key={`${r.id}-${index}`}
                    className="cursor-pointer"
                  >
                    <div className="flex items-center justify-between px-4 py-3 text-white">
                      {/* Left content */}
                      <div className="flex-1">
                        {/* title */}
                        <div className="text-[14px] font-semibold text-[#F8AF07]">
                          {r.provider
                            ? `${r.provider}${r.title ? ` (${r.title})` : ""}`
                            : r.title}
                        </div>

                        {/* datetime */}
                        <div className="mt-1 text-xs text-[#6E6E6E]">
                          {mounted ? formatDate(r.time) : ""}
                        </div>

                        {/* bet / win */}
                        <div className="mt-2 text-sm">
                          <span className="mr-4">
                            {t("transaction.detail.bet")} RM{r.bet}
                          </span>
                          <span>
                            {t("transaction.detail.win")} RM{r.win}
                          </span>
                        </div>

                        {/* balances */}
                        <div className="mt-1 text-sm text-white/80">
                          {t("transaction.detail.begin")} RM{r.begin}
                          &nbsp;&nbsp;
                          {t("transaction.detail.end")} RM{r.end}
                        </div>
                      </div>

                      {/* Right arrow (centered vertically) */}
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
                    sessionStorage.setItem(
                      "txScrollTop",
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
                      {/* Left icon */}
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

                      {/* Main content */}
                      <div className="flex-1">
                        <div className="text-[14px] font-semibold text-[#F8AF07]">
                          {r.title}
                        </div>

                        <div className="text-[14px] font-semibold text-white">
                          {r.providerPlatform}
                        </div>

                        <div className="mt-1 text-xs text-[#6E6E6E]">
                          {r.time}
                        </div>
                      </div>

                      {/* Status */}
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

                      {/* Right arrow */}
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
      {/* Floating filter */}
      <button
        onClick={(e) => {
          e.preventDefault(); // stop default <Link> nav
          dispatch(resetFilterList(activeType)); // reset filter

          sessionStorage.removeItem("txFromFilter");
          sessionStorage.removeItem("txFilterQuick");
          sessionStorage.removeItem("txFilterDateRange");
          sessionStorage.removeItem("txFilterScrollTop");

          router.push(`/transactions/filter/${activeType}`); // SPA navigation
        }}
        aria-label={t("transaction.actions.filter")}
        className="fixed bottom-6 right-[max(24px,calc((100vw-480px)/2+24px))] z-50 grid h-12 w-12 place-items-center rounded-full"
      >
        <span className="relative block h-24 w-24">
          <Image
            src={IMAGES.transactions.iconFilter}
            alt="filter"
            width={50}
            height={50}
            className="object-contain"
          />
        </span>
      </button>
    </div>
  );
}
