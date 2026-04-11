import type {
  GlobalVipResponse,
  PaymentPlatform,
  PaymentPlatformsRequest,
  PurchasePackageRequest,
  PurchasePackageResponse,
} from "@/types/plan.types.ts";
import type { ApiResponse, PaginatedData } from "@/types/api-response.ts";
import type { VipOrder } from "@/types/transaction.types.ts";
import axios from "@/lib/axios";

export const fetchGlobalVip = async (
  signal?: AbortSignal,
): Promise<GlobalVipResponse> => {
  const response = await axios.post("/index/globalVip", {}, { signal });
  return response.data;
};

export const fetchPaymentPlatforms = async (
  payload: PaymentPlatformsRequest,
): Promise<ApiResponse<PaymentPlatform[]>> => {
  const response = await axios.post("/vip/platforms", payload);
  return response.data;
};

export const purchasePackage = async (
  payload: PurchasePackageRequest,
): Promise<ApiResponse<PurchasePackageResponse>> => {
  const response = await axios.post("/vip/buy", payload);
  return response.data;
};

export const fetchVipOrders = async (
  signal?: AbortSignal,
): Promise<ApiResponse<PaginatedData<VipOrder>>> => {
  const response = await axios.post("/vip/myOrder", {}, { signal });
  return response.data;
};
