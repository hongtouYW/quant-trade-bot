import { FC } from "react";
import { Link, useNavigate } from "react-router-dom";

import Image from "../Image/Image";

import { ActorType } from "../../utils/type";

import styles from "./VideoCard.module.css";
import { useModal } from "../../contexts/modal.context";
import { useUser } from "../../contexts/user.context";
import { useTranslation } from "react-i18next";
import { THEME_COLOR, TOKEN_NAME } from "../../utils/constant";
import u from "../../utils/utils";

interface IVideoCard {
  id: number;
  title: string;
  actor?: ActorType;
  className?: string;
  play?: string;
  collectCount?: string;
  thumb?: string;
  preview?: string;
  subtitle?: number;
  vip?: number;
  vertical?: boolean;
  first?: boolean;
  size?: "middle" | "small" | "extra-small";
}
import Cookies from "universal-cookie";

const cookies = new Cookies();

const VideoCard: FC<IVideoCard> = ({
  id,
  title,
  actor,
  className,
  play,
  collectCount,
  thumb,
  preview,
  subtitle,
  vip,
  vertical = false,
  first = false,
  size = "middle",
}) => {
  const { t, i18n } = useTranslation();
  const navigate = useNavigate();
  const modal = useModal();
  const { currentUser, userVip } = useUser();
  const siteType = u.siteType();

  const handleVideoNavigate = () => {
    console.log("currentUser", currentUser);
    const token = cookies.get(TOKEN_NAME);

    if (id && title) {
      if (vip === 1 || vip === 2) {
        if (!token) {
          return modal.info({
            title: `${t("information")}`,
            content: `${t("requireLoginBeforeWatchVideo")}`,
            confirmFn: () => navigate(`/user/login`, { state: { redirectTo: `/video/info?id=${id}` } }),
            confirmText: `${t("signinNow")}`,
          });
        }

        if (vip === 2 && userVip.vip === 0) {
          return modal.info({
            title: `${t("information")}`,
            content: `${t("requireVipBeforeWatchVideo")}`,
            confirmFn: () => navigate(`/user/vip`),
            confirmText: `${t("buyNow")}`,
          });
        }
      }
      navigate(`/video/info?id=${id}`);
    }
  };

  const videoCardClassNanme = () => {
    switch (size) {
      case "small":
        return siteType.theme === THEME_COLOR.GREEN
          ? "videoCardImageSmall"
          : "videoOtherCardImageSmall";
      case "extra-small":
        return siteType.theme === THEME_COLOR.GREEN
          ? "videoCardImageExSmall"
          : "videoOtherCardImageExSmall";
      default:
        return siteType.theme === THEME_COLOR.GREEN
          ? "videoCardImage"
          : "videoOtherCardImage";
    }
  };

  return (
    <>
      <div
        className={`${
          siteType.theme === THEME_COLOR.GREEN
            ? styles.videoCardContainer
            : styles.videoOtherCardContainer
        } ${
          first === true ? `${styles.videoCardContainerFirst}` : ""
        } ${className}`}
      >
        <div className={styles.videoCardImageContainer}>
          <div
            className={`${styles[videoCardClassNanme()]}`}
            // className={`${
            //   size === "small"
            //     ? `${
            //         siteType.theme === THEME_COLOR.GREEN
            //           ? styles.videoCardImageSmall
            //           : styles.videoOtherCardImageSmall
            //       }`
            //     : size === "extra-small"
            //     ? `${
            //         siteType.theme === THEME_COLOR.GREEN
            //           ? styles.videoCardImageExSmall
            //           : styles.videoOtherCardImageExSmall
            //       }`
            //     : `${
            //         siteType.theme === THEME_COLOR.GREEN
            //           ? styles.videoCardImage
            //           : styles.videoOtherCardImage
            //       }`
            // } ${
            //   vertical === false
            //     ? `${styles.videoCardImageHorizontal}`
            //     : styles.videoCardImageVertical
            // }`}
            onClick={handleVideoNavigate}
          >
            <Image
              className={`${
                vertical === false
                  ? `${styles.videoCardImg}`
                  : `${styles.videoCardImgVertical}`
              }`}
              srcValue={vertical === false ? preview : thumb}
              layout={vertical === false ? "horizontal" : "vertical"}
              lazyload={false}
            />
          </div>
          {subtitle === 1 && (
            <div className={styles.cnTag}>
              <img
                src={`/${siteType.theme}/zimu-${i18n.language || "en"}.svg`}
                alt="zimu"
              />
            </div>
          )}
          {vip === 2 && (
            <div className={styles.vipTag}>
              <img src={`/vip-${i18n.language || "en"}.svg`} alt="vip" />
            </div>
          )}
          <div className={styles.nameTag}>
            <Link to={`/video/list?actor_id=${actor?.id}`}>
              {actor?.name || ""}
            </Link>
          </div>
        </div>
        <div className={styles.videoCardContent}>
          <div className={styles.videoCardTitle}>
            <p>{title?.slice(0, 12)}</p>
          </div>
          <div className={styles.videoCardViewFavorite}>
            <div className={styles.videoCardView}>
              <img src="/icon-see.png" alt="zimu" width={15} height={15} />
              <span>{play || ""}</span>
            </div>
            <div className={styles.videoCardFavorite}>
              <img src="/icon-love.png" alt="zimu" width={15} height={15} />
              <span>{collectCount || ""}</span>
            </div>
          </div>
        </div>
      </div>
    </>
  );
};

export default VideoCard;
