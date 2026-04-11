import { useQuery } from "@tanstack/react-query";
import { fetchPublisherInfo } from "@/services/publisher.service.ts";
import type { ApiResponse } from "@/types/api-response.ts";
import type { PublisherInfo } from "@/types/publisher.types.ts";

export const usePublisherInfo = (
  publisherId: string,
  initialData?: ApiResponse<PublisherInfo>,
) =>
  useQuery({
    queryKey: ["publisherInfo", publisherId],
    queryFn: ({ signal }) => fetchPublisherInfo(publisherId, signal),
    enabled: !!publisherId,
    initialData,
  });
