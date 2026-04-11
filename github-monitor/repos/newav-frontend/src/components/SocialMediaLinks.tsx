import { cn } from "@/lib/utils";

export interface SocialLink {
  platform: "twitter" | "instagram" | "tiktok" | "fantia" | "onlyfans" | "facebook" | "youtube";
  url: string;
  label: string;
}

interface SocialMediaLinksProps {
  links: SocialLink[];
  className?: string;
  iconSize?: number;
}

const SOCIAL_CONFIG = {
  fantia: {
    lightImage: "/brand_logo/fantia.jpeg",
    darkImage: "/brand_logo/fantia.jpeg",
    label: "Fantia",
    baseUrl: "https://fantia.jp/fanclubs/",
  },
  twitter: {
    lightImage: "/brand_logo/twitter_black.svg",
    darkImage: "/brand_logo/twitter_white.svg",
    label: "X (Twitter)",
    baseUrl: "https://twitter.com/",
  },
  tiktok: {
    lightImage: "/brand_logo/tiktok_black.svg",
    darkImage: "/brand_logo/tiktok_white.svg",
    label: "TikTok",
    baseUrl: "https://www.tiktok.com/@",
  },
  instagram: {
    lightImage: "/brand_logo/instagram.webp",
    darkImage: "/brand_logo/instagram.webp",
    label: "Instagram",
    baseUrl: "https://instagram.com/",
  },
  onlyfans: {
    lightImage: "/brand_logo/onlyfans.svg",
    darkImage: "/brand_logo/onlyfans.svg",
    label: "OnlyFans",
    baseUrl: "https://onlyfans.com/",
  },
  facebook: {
    lightImage: "/brand_logo/facebook.png",
    darkImage: "/brand_logo/facebook.png",
    label: "Facebook",
    baseUrl: "https://www.facebook.com/",
  },
  youtube: {
    lightImage: "/brand_logo/youtube.png",
    darkImage: "/brand_logo/youtube.png",
    label: "YouTube",
    baseUrl: "https://www.youtube.com/@",
  },
};

/**
 * Build the complete URL for a social media link
 * @param platform - The social media platform
 * @param value - The username or URL from the API
 * @returns The complete URL
 */
const buildSocialUrl = (platform: keyof typeof SOCIAL_CONFIG, value: string): string => {
  if (!value) return "";

  // Check if value is already a complete URL
  if (value.startsWith("http://") || value.startsWith("https://")) {
    return value;
  }

  // For username-only values, append to the base URL
  const config = SOCIAL_CONFIG[platform];
  return `${config.baseUrl}${value}`;
};

export const SocialMediaLinks = ({
  links,
  className = "",
  iconSize = 24,
}: SocialMediaLinksProps) => {
  if (!links || links.length === 0) {
    return null;
  }

  return (
    <div className={cn("flex items-center gap-4", className)}>
      {links.map((link) => {
        const config = SOCIAL_CONFIG[link.platform];
        const imageSize = iconSize || 24;
        const completeUrl = buildSocialUrl(link.platform, link.url);

        // Skip if URL couldn't be built
        if (!completeUrl) return null;

        return (
          <a
            key={link.platform}
            href={completeUrl}
            target="_blank"
            rel="noopener noreferrer"
            title={config.label}
            className="inline-flex items-center justify-center transition-all duration-200 hover:scale-110 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary/50 rounded-full p-2 hover:opacity-80"
            aria-label={`Visit ${config.label}`}
          >
            {/* Light mode image */}
            <img
              src={config.lightImage}
              alt={config.label}
              className="block dark:hidden"
              style={{ width: `${imageSize}px`, height: `${imageSize}px` }}
            />
            {/* Dark mode image */}
            <img
              src={config.darkImage}
              alt={config.label}
              className="hidden dark:block"
              style={{ width: `${imageSize}px`, height: `${imageSize}px` }}
            />
          </a>
        );
      })}
    </div>
  );
};
