import { useQuery } from "@tanstack/react-query";
import { fetchPublisherInfo } from "@/services/publisher.service.ts";

export const usePublisherInfo = (publisherId: string) =>
  useQuery({
    queryKey: ["publisherInfo", publisherId], // Include the param in query key
    queryFn: ({ signal }) => fetchPublisherInfo(publisherId, signal),
    // Return full API response to enable proper error handling
    // Component can access data via response.data and error info via response.code/msg
    enabled: !!publisherId,
  });
