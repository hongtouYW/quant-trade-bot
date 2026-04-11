import { format, isAfter } from "date-fns";

export const formattedVipTime = (
  timestamp: number | undefined,
  dateFormat = "yyyy/MM/dd",
) => {
  if (!timestamp) {
    return;
  }

  const vipEndTime = new Date(Number(timestamp) * 1000);
  const isVipValid = isAfter(vipEndTime, new Date());

  if (!isVipValid) {
    return;
  }

  const formatted = format(vipEndTime, dateFormat);
  return formatted;
};
