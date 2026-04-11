import { useQuery } from "@tanstack/react-query";
import { getBannerImageSrc } from "@/lib/imageUtils";

interface UseBase64ImageOptions {
  originalUrl: string;
  customDomain?: string;
  enabled?: boolean;
}

export const useBase64Image = ({
  originalUrl,
  enabled = true,
}: UseBase64ImageOptions) => {
  return useQuery({
    queryKey: ["base64Image", originalUrl],
    queryFn: ({ signal }) => getBannerImageSrc(originalUrl, signal),
    enabled: enabled,
    staleTime: 1000 * 60 * 60, // 1 hour - images don't change often
    gcTime: 1000 * 60 * 60 * 24, // 24 hours - keep in cache for a day
    refetchOnWindowFocus: false,
    refetchOnReconnect: false,
    retry: 2, // Retry failed requests twice
  });
};
