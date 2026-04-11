// src/utils/transferAllPoints.js
import { getClientIp } from "@/utils/utility";
import { useBalanceStore } from "@/store/zustand/balanceStore";
import { useProviderStore } from "@/store/zustand/providerStore";

/**
 * Global helper to transfer all game points → main wallet credit.
 *
 * @param {Object} params
 * @param {Object} params.info - member info (must include member_id)
 * @param {string|number} params.gameMemberId - target gameMemberId
 * @param {Function} params.triggerGetPlayerView - RTK lazy query trigger for player
 * @param {Function} params.fromPointToCredit - RTK mutation for transfer
 * @param {Function} params.setIsGlobalLoading - UIContext global loading setter
 */

export const handleTransferAllPointsToCredit = async ({
  info,
  gameMemberId,
  triggerGetPlayerView,
  transferAllPointToCredit,
  setIsGlobalLoading,
}) => {
  const { setCredits, setPoints, setIsTransferring, markTransferDone } =
    useBalanceStore.getState();

  if (!info?.member_id || !gameMemberId) return false;

  try {
    setIsTransferring(true);
    setIsGlobalLoading?.(true);

    const resp = await transferAllPointToCredit({
      gamemember_id: gameMemberId,
      member_id: info.member_id,
      ip: await getClientIp(),
    }).unwrap();

    if (resp?.status === true || resp?.status == 200) {
      setCredits(Number(resp.member.balance));
      setPoints(Number(resp.player.balance));
      return true; // ⭐ success
    }

    return false; // failed
  } catch (err) {
    console.error("Global transfer failed:", err);
    return false; // failed
  } finally {
    setIsTransferring(false);
    markTransferDone();
    setIsGlobalLoading?.(false);
  }
};

export async function refreshBalancesCore({
  info,
  gameMemberId = null, // ✅ THIS is correct
  triggerGetMemberView,
  triggerGetPlayerView,
  setCredits,
  setPoints,
}) {
  // 1️⃣ Refresh credit
  const creditRes = await triggerGetMemberView({
    member_id: info.member_id,
  }).unwrap();

  if (creditRes?.data?.balance != null)
    setCredits(Number(creditRes.data.balance));

  // 2️⃣ Refresh points (if game)
  if (gameMemberId) {
    const pointRes = await triggerGetPlayerView({
      member_id: info.member_id,
      gamemember_id: gameMemberId,
    }).unwrap();

    if (pointRes?.data?.balance != null)
      setPoints(Number(pointRes.data.balance));
  }

  return true;
}
