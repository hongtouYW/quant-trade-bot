import { FC } from "react";

import { Swiper, SwiperSlide } from "swiper/react";
import { Autoplay, Pagination } from "swiper/modules";

import VideoCard from "../../../../components/video-card/VideoCard";
import { VideoType } from "../../../../utils/type";
import u from "../../../../utils/utils";

// Import Swiper styles
import "swiper/css";
import "swiper/css/pagination";
import "swiper/css/navigation";

import styles from "./VideoIndexList.module.css";
import { Link } from "react-router-dom";
import { useTranslation } from "react-i18next";
import { THEME_COLOR } from "../../../../utils/constant";

interface IVideoIndexList {
  title: string;
  list: Array<any>;
  type?: number; // 1: (1 row), 2: (4 in 1 row), 3: (5 in 1 row)
  videoType?: number;
}

const VideoIndexList: FC<IVideoIndexList> = ({
  title,
  type = 1,
  videoType,
  list = [],
}) => {
  const { t } = useTranslation();
  const siteType = u.siteType();
  list = u.isMobile()
    ? list
    : list.slice(0, siteType.theme === THEME_COLOR.GREEN ? 10 : 8);

  return (
    <div
      className={`${
        siteType.theme === THEME_COLOR.GREEN
          ? styles.videoIndexListContainer
          : styles.videoOtherIndexListContainer
      }`}
    >
      <div className={styles.videoIndexListTitle}>
        <p className={styles.videoIndexListLabel}>{title}</p>
        <Link
          to={
            videoType
              ? `/video/list?type=${videoType}`
              : "/video/list?list=hot&order=3"
          }
          className={styles.videoIndexListMoreLink}
        >
          <p className={styles.videoIndexListMore}>{t("more")}</p>
        </Link>
      </div>

      {type === 3 ? (
        <div>
          <div
            className={`${styles.videoIndexListType3Body} videoIndexListType3Body`}
          >
            {list.map((val: VideoType) => (
              <VideoCard
                key={val.id}
                id={val.id}
                title={val.title || ""}
                actor={val.actor}
                play={val.play || "0"}
                collectCount={val.collect_count}
                thumb={val.thumb}
                preview={val.preview}
                subtitle={val.subtitle}
                vip={val.private}
                vertical={siteType.theme === THEME_COLOR.GREEN ? true : false}
              />
            ))}
          </div>
          <div className="videoIndexListType3BodySwiper">
            <Swiper
              spaceBetween={10}
              centeredSlides={true}
              // autoplay={true}
              loop={true}
              slidesPerView="auto"
              initialSlide={1}
              grabCursor={true}
              pagination={{
                enabled: true,
                clickable: true,
              }}
              modules={[Autoplay, Pagination]}
            >
              {u.arrayToChunk(list, 5).map((list: Array<VideoType>, index) => {
                return (
                  <SwiperSlide
                    key={index}
                    className={styles.videoIndexListType3SwiperSlideBody}
                  >
                    {list.map((val: VideoType) => (
                      <VideoCard
                        key={val.id}
                        id={val.id}
                        title={val.title || ""}
                        actor={val.actor}
                        play={val.play || "0"}
                        collectCount={val.collect_count}
                        thumb={val.thumb}
                        preview={val.preview}
                        subtitle={val.subtitle}
                        vip={val.private}
                        first={true}
                      />
                    ))}
                  </SwiperSlide>
                );
              })}
            </Swiper>
          </div>
        </div>
      ) : type === 2 ? (
        <div>
          <div
            className={`${styles.videoIndexListType2Body} videoIndexListType2Body`}
          >
            {list.map((val: VideoType) => (
              <VideoCard
                key={val.id}
                id={val.id}
                title={val.title || ""}
                actor={val.actor}
                play={val.play || "0"}
                collectCount={val.collect_count}
                thumb={val.thumb}
                preview={val.preview}
                subtitle={val.subtitle}
                vip={val.private}
                vertical={siteType.theme === THEME_COLOR.GREEN ? true : false}
              />
            ))}
          </div>
          <div className="videoIndexListType2BodySwiper">
            <Swiper
              spaceBetween={10}
              centeredSlides={true}
              // autoplay={true}
              loop={true}
              slidesPerView="auto"
              initialSlide={1}
              grabCursor={true}
              pagination={{
                enabled: true,
                clickable: true,
              }}
              modules={[Autoplay, Pagination]}
            >
              {u.arrayToChunk(list, 4).map((item: Array<VideoType>, index) => {
                return (
                  <SwiperSlide
                    key={index}
                    className={styles.videoIndexListType2SwiperSlideBody}
                  >
                    {item.map((val: VideoType) => (
                      <VideoCard
                        key={val.id}
                        id={val.id}
                        title={val.title || ""}
                        actor={val.actor}
                        play={val.play || "0"}
                        collectCount={val.collect_count}
                        thumb={val.thumb}
                        preview={val.preview}
                        subtitle={val.subtitle}
                        vip={val.private}
                      />
                    ))}
                  </SwiperSlide>
                );
              })}
            </Swiper>
          </div>
        </div>
      ) : (
        <div className={`${styles.videoIndexListType1Body}`}>
          {list.map((val: VideoType) => (
            <VideoCard
              key={val.id}
              id={val.id}
              title={val.title || ""}
              actor={val.actor}
              play={val.play || "0"}
              collectCount={val.collect_count}
              thumb={val.thumb}
              preview={val.preview}
              subtitle={val.subtitle}
              vip={val.private}
              vertical={siteType.theme === THEME_COLOR.GREEN ? true : false}
            />
          ))}
        </div>
      )}
    </div>
  );
};

export default VideoIndexList;
