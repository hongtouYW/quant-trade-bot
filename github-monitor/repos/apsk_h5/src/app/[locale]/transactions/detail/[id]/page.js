"use client";

import { useParams, useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";
import Image from "next/image";
import { useSelector } from "react-redux";

export default function TransactionDetail() {
  const { id } = useParams();
  const router = useRouter();
  const t = useTranslations();

  // read from slice
  const tx = useSelector((state) => state.transactionDetail.currentTransaction);

  if (!tx) {
    return (
      <div className="mx-auto max-w-[480px] min-h-dvh bg-[#162344] text-white flex items-center justify-center">
        <div className="text-center">
          <p className="text-sm">{t("transaction.detail.notFound")}</p>
          <button
            onClick={() => router.back()}
            className="mt-4 rounded bg-[#F8AF07] px-4 py-2 text-black font-medium"
          >
            {t("common.back")}
          </button>
        </div>
      </div>
    );
  }

  // Detect history vs credit/point
  const isHistory = !!tx.bet && !!tx.win;

  // ✅ credit/point
  const isPositive = [
    "deposit",
    "reload",
    "gamedeposit",
    "newmemberregister",
    "newmemberreload",
    "newmemberrecruit",
    "newmembergamereload",
  ].includes(tx.type?.toLowerCase?.());
  const amountText = `${isPositive ? "+" : "-"}RM${Number(
    tx.amount || 0,
  ).toFixed(2)}`;

  const txId =
    tx.id || tx.credit_id || tx.orderid || tx.transactionId || id || "-";

  const statusClass =
    tx.status === "success"
      ? "text-[#3DD36B]"
      : tx.status === "failed"
        ? "text-[#FF6B6B]"
        : "text-[#6E6E6E]";

  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#162344] text-white">
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
      </div>

      {/* Card */}
      <div className="px-4 py-4 bg-[#162344]">
        <div className="rounded-md border border-white/30 bg-[#0B2456]/30 p-4">
          {isHistory ? (
            <>
              {/* 供应商 */}
              <Row
                label={t("transaction.detail.provider")}
                value={tx.provider}
              />

              {/* 游戏 */}
              <Row
                label={t("transaction.detail.game")}
                value={tx.title || "-"}
              />

              {/* 投注 */}
              <Row label={t("transaction.detail.bet")} value={`RM${tx.bet}`} />

              {/* 赢取 */}
              <Row label={t("transaction.detail.win")} value={`RM${tx.win}`} />

              {/* 开始余额 */}
              <Row
                label={t("transaction.detail.begin")}
                value={`RM${tx.begin}`}
              />

              {/* 结束余额 */}
              <Row label={t("transaction.detail.end")} value={`RM${tx.end}`} />

              {/* 交易时间 */}
              <Row
                label={t("transaction.detail.time")}
                value={tx.time || "-"}
              />
            </>
          ) : (
            <>
              {/* <Row
                label={t("transaction.title")}
                value={
                  tx.title.includes("+") || tx.title.includes("-")
                    ? tx.title.split(/[-+]/)[0].trim()
                    : tx.title
                }
              /> */}

              {tx.transType === "credit" && (
                <Row
                  label={tx.title.replace(amountText, "").trim()}
                  value={<span className="text-[#F8AF07]">{amountText}</span>}
                />
              )}

              {tx.transType === "point" && (
                <Row
                  label={t("transaction.detail.pTopup")}
                  value={<span className="text-[#F8AF07]">{amountText}</span>}
                />
              )}

              {tx.transType === "credit" && (
                <Row
                  label={(() => {
                    if (tx.type === "deposit") {
                      return t("transaction.detail.method");
                    }

                    if (tx.paymentId === 1) {
                      return t("transaction.detail.method");
                    }

                    return t("transaction.detail.bank");
                  })()}
                  value={tx.bank || "-"}
                />
              )}

              {tx.transType === "point" && (
                <Row
                  label={
                    t("transaction.detail.provider") // Withdraw → Bank
                  }
                  value={tx.bank || "-"}
                />
              )}

              <Row
                label={t("transaction.detail.time")}
                value={tx.time || "-"}
              />
              <Row label={t("transaction.detail.id")} value={tx.id} />
              <Row
                label={t("transaction.detail.status")}
                value={
                  <span className={statusClass}>
                    {tx.status === "success"
                      ? t("transaction.status.success")
                      : tx.status === "failed"
                        ? t("transaction.status.failed")
                        : t("transaction.status.pending")}
                  </span>
                }
              />
            </>
          )}
        </div>
      </div>
    </div>
  );
}

function Row({ label, value }) {
  return (
    <div className="flex items-center justify-between py-3 border-b border-white/10 last:border-b-0">
      <div className="text-sm text-white/80">{label}</div>
      <div className="text-sm text-right text-white/90">{value}</div>
    </div>
  );
}
