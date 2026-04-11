import type { ReactNode } from "react";
import { useMemo } from "react";

export interface InfoCardLink {
  label: string;
  url: string;
}

export interface InfoCardSection {
  title: string;
  links?: InfoCardLink[];
  content?: string;
  subtitle?: string;
}

export interface InfoCardProps {
  sections: InfoCardSection[];
  imageUrl?: string;
  imageBgUrl?: string;
  className?: string;
  children?: ReactNode;
  useRandomImage?: boolean;
}

const AVAILABLE_IMAGES = [
  "/address_card_image/aijav_1.png",
  "/address_card_image/aijav_2.png",
  "/address_card_image/aijav_3.png",
  "/address_card_image/aijav_4.png",
  "/address_card_image/aijav_5.png",
  "/address_card_image/aijav_6.png",
  "/address_card_image/aijav_7.png",
  "/address_card_image/aijav_8.png",
  "/address_card_image/aijav_9.png",
  "/address_card_image/aijav_10.png",
];

const getRandomImage = (): string => {
  return AVAILABLE_IMAGES[Math.floor(Math.random() * AVAILABLE_IMAGES.length)];
};

export const InfoCard = ({
  sections,
  imageUrl,
  imageBgUrl,
  className = "",
  children,
  useRandomImage = true,
}: InfoCardProps) => {
  // Use random image if useRandomImage is true and no imageUrl is provided
  const finalImageUrl = useMemo(() => {
    if (imageUrl) return imageUrl;
    if (useRandomImage) return getRandomImage();
    return undefined;
  }, [imageUrl, useRandomImage]);

  const backgroundStyle = imageBgUrl
    ? {
        backgroundImage: `url(${imageBgUrl}), linear-gradient(103.22deg, rgba(250, 113, 84, 0.2) 2.89%, rgba(249, 23, 109, 0.2) 50.13%, rgba(243, 8, 207, 0.2) 99.3%)`,
        backgroundSize: "cover, cover",
        backgroundPosition: "center, center",
      }
    : {
        background:
          "linear-gradient(103.22deg, rgba(250, 113, 84, 0.2) 2.89%, rgba(249, 23, 109, 0.2) 50.13%, rgba(243, 8, 207, 0.2) 99.3%)",
      };

  const borderStyle = {
    ...backgroundStyle,
    border: "1.5px solid",
    borderImage: "linear-gradient(90deg, #FA7154, #F9176D, #F308CF) 1",
  };

  return (
    <div
      className={`relative inline-flex flex-row items-stretch gap-0 overflow-hidden max-h-[300px] ${className}`}
      style={borderStyle}
    >
      {/* Content sections */}
      <div className="flex flex-col p-5 gap-2">
        {sections.map((section, index) => (
          <div key={index} className="flex-1 md:flex-auto">
            {/* Title */}
            <div className="mb-1 xs:mb-1 md:mb-2 font-pingfang text-xs xs:text-xs md:text-sm font-medium leading-[13px] xs:leading-[13px] md:leading-[14px] text-foreground break-words">
              {section.title}
            </div>

            {/* Links or content */}
            {section.links && section.links.length > 0 ? (
              <div className="space-y-0.5 xs:space-y-0.5 md:space-y-1">
                {section.links.map((link, linkIndex) => (
                  <a
                    key={linkIndex}
                    href={link.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="block font-pingfang text-xs xs:text-xs md:text-sm font-normal leading-[15px] xs:leading-[15px] md:leading-[17px] text-blue-500 hover:underline truncate"
                    title={link.label}
                  >
                    {link.label}
                  </a>
                ))}
              </div>
            ) : section.content ? (
              <div className="font-pingfang text-xs xs:text-xs md:text-sm font-normal leading-[15px] xs:leading-[15px] md:leading-[17px] text-black break-words">
                {section.content}
              </div>
            ) : null}
          </div>
        ))}
      </div>

      {/* Image overlay on the right - stretches to full height of content */}
      {finalImageUrl && (
        <div className="max-w-[230px]">
          <img
            src={finalImageUrl}
            alt="card-image"
            className="h-full w-full object-cover"
          />
        </div>
      )}

      {/* Custom children support */}
      {children}
    </div>
  );
};

export default InfoCard;
