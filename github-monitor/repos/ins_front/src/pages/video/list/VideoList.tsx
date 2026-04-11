import { useEffect, useState } from "react";
import VideoCard from "../../../components/video-card/VideoCard";

import styles from "./VideoList.module.css";
import Pagination from "../../../components/pagination/Pagination";
import Image from "../../../components/Image/Image";

import {
  ActorInfoType,
  AsType,
  VideoListType,
  VideoType,
} from "../../../utils/type";
import useAxios from "../../../hooks/useAxios";
import { Link, NavLink, useNavigate, useSearchParams } from "react-router-dom";
import u from "../../../utils/utils";
import { hotMenuList } from "../../../utils/data";
import { useConfig } from "../../../contexts/config.context";
import { useTranslation } from "react-i18next";
import { THEME_COLOR } from "../../../utils/constant";
// import Loading from "../../../components/loading/Loading";

export const VideoList = () => {
  const navigate = useNavigate();
  const { t } = useTranslation();
  const { req: videoListRequest } = useAxios("video/lists", "post");
  const { req: videoHotListRequest } = useAxios("video/hotLists", "post");
  const { req: actorInfoRequest } = useAxios("actor/info", "post");

  const { asList } = useConfig();
  const [searchParams, setSearchParams] = useSearchParams();
  const actorId = searchParams.get("actor_id");
  const publisherId = searchParams.get("publisher_id");
  const publisherName = searchParams.get("publisher_name");
  const tagId = searchParams.get("tag_id");
  const tagName = searchParams.get("tag_name");
  const type = searchParams.get("type");
  const listParams = searchParams.get("list");
  const order = searchParams.get("order");
  const keyword = searchParams.get("keyword");
  const siteType = u.siteType();
  const limit = u.isMobile()
    ? siteType.theme === THEME_COLOR.GREEN
      ? 15
      : 20
    : 20;

  const as3: Array<AsType> = asList[3] || [];
  const as4: Array<AsType> = asList[4] || [];

  const [actorInfo, setActorInfo] = useState<ActorInfoType>();
  const [videoList, setVideoList] = useState<VideoListType>();
  const [total, setTotal] = useState(0);
  // const [loading, setLoading] = useState(false);

  const handleGetVideoList = async (pageNum: string = "1") => {
    // setLoading(true);
    // type: 1推荐 2vip专项 3免费 4字幕
    const types = ["1", "2", "3", "4"];

    try {
      const params: any = {
        page: pageNum,
        limit: limit,
      };
      if (actorId) {
        params["actor_id"] = actorId;
      }

      if (publisherId) {
        params["publisher_id"] = publisherId;
      }

      if (tagId) {
        params["tag_id"] = tagId;
      }

      if (type && types.includes(type)) {
        params["type"] = type;
      }

      if (keyword !== null && keyword !== "") {
        params["keyword"] = keyword;
      }
      // console.log("params-1", params);
      const res = await videoListRequest(params);
      const list = res?.data?.data || {};

      setVideoList(list);
      setTotal(list.total);
      // setLoading(false);
    } catch (err) {
      console.log(err);
      // setLoading(false);
    }
  };

  const handleGetVideoHotList = async (pageNum: string = "1") => {
    // setLoading(true);
    try {
      const params: any = {
        page: pageNum,
        limit: limit,
        order: order,
      };
      // console.log("params-2", params);
      const res = await videoHotListRequest(params);
      const list = res?.data?.data || {};

      setVideoList(list);
      // setLoading(false);
    } catch (err) {
      console.log(err);
      // setLoading(false);
    }
  };

  const handleGetActorInfo = async () => {
    try {
      const params: any = {
        aid: actorId || "",
      };

      const res = await actorInfoRequest(params);
      const info = res?.data?.data || {};

      setActorInfo(info);
    } catch (err) {
      console.log(err);
    }
  };

  const activeHandler = (clickedActive: any) => {
    let num = parseInt(clickedActive);

    if (videoList && num > videoList?.last_page) {
      num = videoList?.last_page;
    }
    const paramsObj: any = {};
    for (const params of searchParams) {
      const key = params[0];
      const value = params[1];
      paramsObj[key] = value;
    }
    paramsObj["page"] = num;

    setSearchParams(paramsObj);
  };

  useEffect(() => {
    const page = searchParams.get("page") || "1";
    if (listParams === null && order === null) {
      handleGetVideoList(page);
    } else {
      handleGetVideoHotList(page);
    }
  }, [searchParams]);

  useEffect(() => {
    if (actorId) {
      handleGetActorInfo();
    }
  }, [actorId]);

  useEffect(() => {
    window.scrollTo({
      top: 0,
    });
  }, [searchParams]);

  return (
    <>
      <div className={styles.videoListSection}>
        {keyword && (
          <div className={styles.videoListKeyword}>
            <p>
              搜索关键字是 <span>"{keyword}"</span> 的主题结果如下：
            </p>
          </div>
        )}
        {actorId && Object.keys(actorInfo || {}).length > 0 && (
          <div className={styles.actorContainer}>
            {actorInfo?.tid && actorInfo?.tid > 1 && (
              <div className={styles.actorTrend}>
                <button
                  className={styles.actorTrendButton}
                  onClick={() =>
                    navigate(`/actor/trend?actor_id=${actorInfo?.id}`)
                  }
                >
                  <img src="/icon-actor-msg.png" width={12} height={12} />
                  {t("pornstarsNews")}
                </button>
              </div>
            )}
            <div className={styles.actorAvatar}>
              <Image srcValue={actorInfo?.image} />
            </div>
            <div className={styles.actorInfo}>
              <p>
                {siteType.theme === THEME_COLOR.YELLOW
                  ? t("author")
                  : t("pornstars")}
              </p>
              <p className={styles.actorName}>{actorInfo?.name}</p>
              <p>
                {total} {t("videosQuantity")}
              </p>
            </div>
          </div>
        )}
        {publisherId && (
          <div className={styles.publisherContainer}>
            <div className={styles.publisherInfo}>
              <p>{t("film")}</p>
              <p className={styles.publisherName}>{publisherName || "-"}</p>
              <p>
                {total} {t("videosQuantity")}
              </p>
            </div>
          </div>
        )}
        {tagId && (
          <div className={styles.tagContainer}>
            <div className={styles.tagInfo}>
              <p>{t("theme")}</p>
              <p className={styles.tagName}>{tagName || "-"}</p>
              <p>
                {total} {t("videosQuantity")}
              </p>
            </div>
          </div>
        )}
        {listParams && order && (
          <div className={styles.hotMenuList}>
            {hotMenuList?.map((menu: any, index: any) => (
              <div className={styles.hotMenuItem} key={index}>
                <NavLink
                  to={menu.path}
                  className={`${
                    menu.order === order ? `${styles.hotMenuItemActive}` : ""
                  }`}
                >
                  {/* <p>{menu.title}</p> */}
                  <p>{t(menu.locale)}</p>
                </NavLink>
              </div>
            ))}
          </div>
        )}
        <div
          className={
            siteType.theme === THEME_COLOR.GREEN
              ? styles.videoListContainer
              : styles.videoListOtherContainer
          }
        >
          {/* {loading && (
            <div className={styles.loadingContainer}>
              <div className={styles.loading}>
                <Loading color="white" width={80} />
              </div>
            </div>
          )} */}
          {videoList?.data?.map((video: VideoType) => (
            <VideoCard
              key={video.id}
              id={video.id}
              title={video.title || ""}
              actor={video.actor}
              play={video.play || "0"}
              collectCount={video.collect_count}
              thumb={video.thumb}
              preview={video.preview}
              subtitle={video.subtitle}
              vip={video.private}
              vertical={siteType.theme === THEME_COLOR.GREEN ? true : false}
              className={
                siteType.theme === THEME_COLOR.GREEN
                  ? styles.videoItem
                  : styles.videoOtherItem
              }
              size="small"
            />
          ))}
        </div>
        <div className="videoListPagination">
          <Pagination
            active={videoList?.current_page || 1}
            size={videoList?.last_page || 1} // last_page
            total={videoList?.total || 1}
            step={u.isMobile() ? 1 : 3}
            limit={limit}
            onClickHandler={activeHandler}
          />
        </div>
        <div className={styles.asVideoListFooter}>
          {u.isMobile()
            ? as4?.map((item: AsType, index: number) => {
                return (
                  <Link key={index} to={item.url || "#"} target="_blank">
                    <Image layout="ads" srcValue={item?.thumb} alt="thumb" />
                  </Link>
                );
              })
            : as3?.map((item: AsType, index: number) => {
                return (
                  <Link key={index} to={item.url || "#"} target="_blank">
                    <Image layout="ads" srcValue={item?.thumb} alt="thumb" />
                  </Link>
                );
              })}
        </div>
        {/* {as3 && !u.isMobile() && (
          <div className={styles.asVideoListFooter}>
            <Link to={as3.url || "#"} target="_blank">
              <Image srcValue={as3?.thumb} alt="thumb" />
            </Link>
          </div>
        )}
        {as4 && u.isMobile() && (
          <div className={styles.asVideoListFooter}>
            <Link to={as4.url || "#"} target="_blank">
              <Image srcValue={as4?.thumb} alt="thumb" />
            </Link>
          </div>
        )} */}
      </div>
    </>
  );
};

export default VideoList;
