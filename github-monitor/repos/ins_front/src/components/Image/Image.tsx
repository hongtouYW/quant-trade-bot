import { FC, useEffect, useState } from "react";
import defaultVerticalImg from "../../../public/video-card-loading.png";
import defaultHorizontalImg from "../../../public/ins-loading.png";
import defaultAdsHorizontalImg from "../../../public/ins-ads-loading.png";
import { LazyLoadImage } from "react-lazy-load-image-component";
// import { useConfig } from "../../contexts/config.context";
import u from "../../utils/utils";
// import { useConfig } from "../../contexts/config.context";
// import { useConfig } from "../../contexts/config.context";

interface IImage {
  alt?: string;
  srcValue?: string;
  className?: string;
  layout?: "horizontal" | "vertical" | "ads";
  lazyload?: boolean;
  size?: boolean;
  imageSize?: string;
}

const Image: FC<IImage> = ({
  alt = "",
  srcValue = "",
  className = "",
  layout = "vertical",
  lazyload = true,
  size = true,
  imageSize = "600x600",
}) => {
  // const { configList } = useConfig();
  // console.log("configList", configList);
  const defaultImg =
    layout === "vertical"
      ? defaultVerticalImg
      : layout === "ads"
      ? defaultAdsHorizontalImg
      : defaultHorizontalImg;
  const [imgSrc, setImgSrc] = useState(defaultImg);

  // const fmtimg = async () => {
  //   if (srcValue) {
  //     const authenticationImg = u.addImageAuthentication(srcValue);
  //     setImgSrc(authenticationImg);
  //   }

  //   // let imageUrlKey = "https://mig.zzbabylon.com";
  //   // let newSrcValue: any = srcValue;

  //   // if (
  //   //   srcValue &&
  //   //   (srcValue.includes("https://") || srcValue.includes("http://"))
  //   // ) {
  //   //   const convertSrcValue = new URL(srcValue);
  //   //   newSrcValue = convertSrcValue?.pathname;
  //   //   imageUrlKey = convertSrcValue?.origin;
  //   // }

  //   // if (newSrcValue) {
  //   //   if (newSrcValue !== "") {
  //   //     const vidKeyParam = u.addImgKeyParam(newSrcValue);

  //   //     const dynamicEncryptUrl = `${imageUrlKey}${newSrcValue}${vidKeyParam}`;

  //   //     return setImgSrc(dynamicEncryptUrl);
  //   //   }
  //   // }
  // };

  const fmtimg = async () => {
    const imageUrl = await u.formatImageUrl(srcValue, size, imageSize);
    setImgSrc(imageUrl || "");
  };

  useEffect(() => {
    fmtimg();
  }, [srcValue]);

  return lazyload ? (
    <LazyLoadImage
      className={className}
      src={imgSrc}
      alt={alt || "Image Alt"}
    />
  ) : (
    <img className={className} src={imgSrc} alt={alt || "Image Alt"} />
  );
};

export default Image;
