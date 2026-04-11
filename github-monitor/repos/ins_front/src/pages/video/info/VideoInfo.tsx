import { useEffect, useRef, useState } from "react";
import { Link, useNavigate, useSearchParams } from "react-router-dom";
import { useTranslation } from "react-i18next";

import DPlayer from "dplayer";
import Cookies from "universal-cookie";
import { ToastContainer, toast, Slide } from "react-toastify";

import useAxios from "../../../hooks/useAxios";

import { useUser } from "../../../contexts/user.context";
import { useConfig } from "../../../contexts/config.context";
import { useModal } from "../../../contexts/modal.context";

// import VideoJS from "../../../components/videoJs/VideoJS";
import TextArea from "../../../components/textarea/TextArea";
import Button from "../../../components/button/Button";
import Image from "../../../components/Image/Image";
import VideoCard from "../../../components/video-card/VideoCard";

import u from "../../../utils/utils";
import { THEME_COLOR, TOKEN_NAME } from "../../../utils/constant";
import { operateList, yellowOperateList } from "../../../utils/data";
import { AsType, TagType, VideoListType, VideoType } from "../../../utils/type";

import styles from "./VideoInfo.module.css";

const cookies = new Cookies();

enum DPlayerEvents {
  abort = "abort",
  canplay = "canplay",
  canplaythrough = "canplaythrough",
  durationchange = "durationchange",
  emptied = "emptied",
  ended = "ended",
  error = "error",
  loadeddata = "loadeddata",
  loadedmetadata = "loadedmetadata",
  loadstart = "loadstart",
  mozaudioavailable = "mozaudioavailable",
  pause = "pause",
  play = "play",
  playing = "playing",
  progress = "progress",
  ratechange = "ratechange",
  seeked = "seeked",
  seeking = "seeking",
  stalled = "stalled",
  suspend = "suspend",
  timeupdate = "timeupdate",
  volumechange = "volumechange",
  waiting = "waiting",
  screenshot = "screenshot",
  thumbnails_show = "thumbnails_show",
  thumbnails_hide = "thumbnails_hide",
  danmaku_show = "danmaku_show",
  danmaku_hide = "danmaku_hide",
  danmaku_clear = "danmaku_clear",
  danmaku_loaded = "danmaku_loaded",
  danmaku_send = "danmaku_send",
  danmaku_opacity = "danmaku_opacity",
  contextmenu_show = "contextmenu_show",
  contextmenu_hide = "contextmenu_hide",
  notice_show = "notice_show",
  notice_hide = "notice_hide",
  quality_start = "quality_start",
  quality_end = "quality_end",
  destroy = "destroy",
  resize = "resize",
  fullscreen = "fullscreen",
  fullscreen_cancel = "fullscreen_cancel",
  subtitle_show = "subtitle_show",
  subtitle_hide = "subtitle_hide",
  subtitle_change = "subtitle_change",
}

const VideoInfo = () => {
  const { t } = useTranslation();
  const dPlayerRef = useRef<any>(null);

  const navigate = useNavigate();
  const { asList, configList } = useConfig();
  const { currentUser, userVip } = useUser();
  const modal = useModal();
  const siteType = u.siteType();
  const { req } = useAxios("video/info", "post");
  const { req: subscribeActorReq } = useAxios("actor/subscribe", "post");
  const { req: collectVideoReq } = useAxios("video/collect", "post");
  const { req: videoHotListRequest } = useAxios("video/hotLists", "post");
  const { req: requestGetVideoUrl } = useAxios("video/getVideoUrl", "post");

  const [searchParams] = useSearchParams();
  const videoId = searchParams.get("id");

  const as4: Array<AsType> = asList[4] || [];
  const as5: Array<AsType> = asList[5] || [];
  const as6: Array<AsType> = asList[6] || [];
  const as7: Array<AsType> = asList[7] || [];
  // console.log("as4", as4);
  // console.log("as5", as5);
  // console.log("as6", as6);
  // console.log("as7", as7);

  const [dVideo, setDVideo] = useState<any>();
  // const [videoPlaying, setVideoPlaying] = useState(false);
  const [imagePreview, setImagePreview] = useState(false);
  const [videoAdv, setVideoAdv] = useState<any>([]);
  const [videoInfo, setVideoInfo] = useState<VideoType>();
  const [showShare, setShowShare] = useState(false);
  const [videoHotList, setVideoHotList] = useState<VideoListType>();
  // const [videoJsOptions, setVideoJsOptions] = useState({
  //   controls: true,
  //   responsive: true,
  //   fluid: true,
  //   textTrackSettings: false,
  //   playbackRates: [0.5, 0.75, 1, 1.25, 1.5, 2],
  //   enableSmoothSeeking: true,
  //   playsinline: true,
  //   nativeControlsForTouch: true,
  //   controlBar: {
  //     skipButtons: {
  //       forward: 10,
  //       backward: 10,
  //       muteToggle: true,
  //     },
  //     volumePanel: {
  //       inline: false,
  //     },
  //   },
  //   // tracks: [
  //   //   {
  //   //     kind: "captions",
  //   //     src: "https://mmjs.1vkx.cn/default.vtt",
  //   //     srlang: "en",
  //   //     label: "English",
  //   //     default: true,
  //   //   },
  //   // ],
  // });

  const subscribeActor = async () => {
    const actoId = videoInfo?.actor?.id;
    const token = cookies.get(TOKEN_NAME);

    const params = {
      token,
      aid: actoId,
    };
    const res = await subscribeActorReq(params);

    if (res?.data?.code === 1) {
      if (res?.data?.data === 1) {
        toast.success(
          <p style={{ fontWeight: 900 }}>{t("subscriptionSuccess")}</p>
        );
      } else {
        toast.success(
          <p style={{ fontWeight: 900 }}>{t("subscriptionCancel")}</p>
        );
      }
      handleGetVideoInfo();
    }
  };

  const collectVideo = async () => {
    const videoId = videoInfo?.id;
    const token = cookies.get(TOKEN_NAME);

    const params = {
      token,
      vid: videoId,
    };
    const res = await collectVideoReq(params);

    if (res?.data?.code === 1) {
      if (res?.data?.data === 1) {
        toast.success(<p style={{ fontWeight: 900 }}>{t("collectSuccess")}</p>);
      } else {
        toast.success(<p style={{ fontWeight: 900 }}>{t("collectCancel")}</p>);
      }
      handleGetVideoInfo();
    }
  };

  const handleOperatorOnClick = async (id: number) => {
    // 1 - 演员列表, 5 - 片商 6 - 訂閱該演員, 7 - 收藏該影片, 8 - 分享该影片
    // const token = cookies.get(TOKEN_NAME);
    const token = cookies.get(TOKEN_NAME);

    if (id === 1) {
      const actoId = videoInfo?.actor?.id;
      navigate(`/video/list?actor_id=${actoId}`);
    }

    if (id === 5) {
      const publisherId = videoInfo?.publisher?.id;
      navigate(`/video/list?publisher_id=${publisherId}`);
    }

    if (id === 6) {
      if (!token) {
        return modal.info({
          title: `${t("information")}`,
          content: `${t("requireLoginBeforeSubscribe")}`,
          confirmFn: () => navigate(`/user/login`),
          confirmText: `${t("signinNow")}`,
        });
      }

      if (videoInfo?.is_subscribe === 0) {
        modal.info({
          title: `${t("information")}`,
          content: `${t("confirmSubscription")}`,
          confirmFn: () => subscribeActor(),
          confirmText: `${t("subscribeNow")}`,
        });
      } else {
        modal.info({
          title: `${t("information")}`,
          content: `${t("cancelSubscription")}`,
          confirmFn: () => subscribeActor(),
          confirmText: `${t("cancelSubscribed")}`,
        });
      }
      // TODO: 确定订阅 popup 和 toast
    }

    if (id === 7) {
      if (!token) {
        return modal.info({
          title: `${t("information")}`,
          content: `${t("requireLoginBeforeCollect")}`,
          confirmFn: () => navigate(`/user/login`),
          confirmText: `${t("signinNow")}`,
        });
      }

      if (videoInfo?.is_collect === 0) {
        modal.info({
          title: `${t("information")}`,
          content: `${t("collectedSuccessful")}`,
          confirmFn: () => collectVideo(),
          confirmText: `${t("collectNow")}`,
        });
      } else {
        modal.info({
          title: `${t("information")}`,
          content: `${t("collectedCancel")}`,
          confirmFn: () => collectVideo(),
          confirmText: `${t("collectCancelNow")}`,
        });
      }
      // TODO: 确定订阅 popup 和 toast
    }

    if (id === 8) {
      if (!token) {
        toast.error(<p style={{ fontWeight: 900 }}>{t("shareAfterSignIn")}</p>);
        // navigate("/user/login");
        return;
      }

      setShowShare((prev) => !prev);
    }
  };

  const controlArrowKey = (e: any, dp: any) => {
    // e.preventDefault();
    // left arrow
    if (e.keyCode == 37) {
      e.preventDefault();
      dp.video.currentTime = dp.video.currentTime - 10;
    }

    // right arrow
    if (e.keyCode == 39) {
      e.preventDefault();
      dp.video.currentTime = dp.video.currentTime + 10;
    }
    // up arrow
    if (e.keyCode == 38) {
      e.preventDefault();
      if (dp.video.volume > 10) {
        dp.video.volume = 10;
      }
      dp.volume(dp.video.volume, true, false);
    }

    // down arrow
    if (e.keyCode == 40) {
      e.preventDefault();
      if (dp.video.volume < 0) {
        dp.video.volume = 0;
      }
      dp.volume(dp.video.volume, true, false);
    }
  };

  const handleGetVideoHotList = async () => {
    try {
      const params: any = {
        page: 1,
        limit:
          siteType.theme && siteType.theme === THEME_COLOR.GREEN
            ? u.isMobile()
              ? 9
              : 10
            : 8,
      };
      const res = await videoHotListRequest(params);
      const list = res?.data?.data || {};
      setVideoHotList(list);
    } catch (err) {
      console.log(err);
    }
  };

  const handleGetVideoInfo = async () => {
    try {
      let params: any;
      const token = cookies.get(TOKEN_NAME);
      if (token) {
        params = {
          vid: videoId || 1,
          token,
        };
      } else {
        params = {
          vid: videoId || 1,
        };
      }
      const res = await req(params);

      if (res?.data?.code === 1) {
        const info = res?.data?.data || {};
        // console.log('info', info)
        setVideoInfo(info);
        handleGetVideoUrl(info);
      }
    } catch (err) {
      console.log(err);
    }
  };

  const handleCopyShareCode = (value: string) => {
    navigator.clipboard.writeText(value);
    toast.success(<p style={{ fontWeight: 900 }}>{t("copied")}</p>);
  };

  const handleGetVideoUrl = async (info: any) => {
    let params: any;
    const token = cookies.get(TOKEN_NAME);
    const lang = cookies.get("curr_lang") || "zh";

    try {
      if (token) {
        params = {
          token,
          vid: info?.id,
        };
      } else {
        params = {
          // token,
          vid: info?.id,
        };
      }

      if (info?.private === 1 || info?.private === 2) {
        if (!token) {
          setImagePreview(true);
          return;
        }

        if (info?.private === 2 && userVip.vip === 0) {
          setImagePreview(true);
          return;
        }
      }
      setImagePreview(false);
      const res = await requestGetVideoUrl(params);
      const url = res?.data?.data;

      const preview = await u.formatImageUrl(info?.preview, true, "1200x807");
      const panorama = await u.formatImageUrl(info?.panorama, false);
      const zimu = info?.zimu;
      const dp = new DPlayer({
        container: dPlayerRef.current,
        autoplay: false,
        lang: lang === "zh" ? "zh-cn" : lang,
        screenshot: true,
        hotkey: true,
        preload: "auto",
        // volume: 0.7,
        mutex: true,
        video: {
          url: url,
          pic: preview,
          thumbnails: panorama,
          type: "auto",
          // defaultQuality: 0,
        },
        subtitle: {
          url: zimu || "",
          type: "webvtt",
          fontSize: "25px",
          bottom: "10%",
          color: "#b7daff",
        },
      });

      setDVideo(dp);

      if (dp) {
        // setVideoPlaying(true);
        // setTimeout(function () {
        //   dp.play();
        // }, 100);
        // setTimeout(function () {
        //   dp.pause();
        // }, 1000);

        // dp?.on(DPlayerEvents.playing, function () {
        //   console.log("playing");
        // });
        dPlayerRef.current.children[1].children[0].setAttribute(
          "controlsList",
          "nodownload noremote footbar"
        );
        dPlayerRef.current.children[1].children[0].textTracks[0].mode =
          "showing";

        const dplayerPlay: any = document.querySelector(".dplayer-mobile-play");
        dplayerPlay.style.display = "block";

        // Disable right-click context menu
        document.addEventListener("contextmenu", function (e) {
          e.preventDefault();
        });

        dp?.on(DPlayerEvents.contextmenu_show, function () {
          return false;
        });

        dp?.on(DPlayerEvents.play, function () {
          document.addEventListener("keydown", (e) => controlArrowKey(e, dp));
          dplayerPlay.style.display = "none";
        });

        dp?.on(DPlayerEvents.pause, function () {
          dplayerPlay.style.display = "block";
        });

        dp?.on(DPlayerEvents.abort, function () {
          document.addEventListener("keydown", (e) => controlArrowKey(e, dp));
        });
      }
    } catch (err) {
      console.log(err);
    }
  };

  // const handlePlayVideo = () => {
  //   const lang = cookies.get("curr_lang") || "zh";

  //   const dp = new DPlayer({
  //     container: dPlayerRef.current,
  //     autoplay: true,
  //     lang: lang === "zh" ? "zh-cn" : lang,
  //     screenshot: true,
  //     hotkey: true,
  //     preload: "auto",
  //     // volume: 0.7,
  //     mutex: true,
  //     video: {
  //       url: videoInfo?.url || "",
  //       pic: videoInfo?.preview,
  //       thumbnails: `/${siteType.theme}/icon_play.png`,
  //       type: "auto",
  //       defaultQuality: 0,
  //     },
  //     subtitle: {
  //       url: videoInfo?.zimu || "",
  //       type: "webvtt",
  //       fontSize: "25px",
  //       bottom: "10%",
  //       color: "#b7daff",
  //     },
  //   });

  //   setDVideo(dp);

  //   if (dp) {
  //     setVideoPlaying(true);
  //     setTimeout(function () {
  //       dp.play();
  //     }, 100);

  //     dp?.on(DPlayerEvents.playing, function () {
  //       console.log("playing");
  //     });

  //     dp?.on(DPlayerEvents.play, function () {
  //       console.log("play");
  //       document.addEventListener("keydown", (e) => controlArrowKey(e, dp));
  //     });

  //     dp?.on(DPlayerEvents.abort, function () {
  //       document.addEventListener("keydown", (e) => controlArrowKey(e, dp));
  //     });
  //   }
  // };
  // const handleVideoPlaying = async () => {
  //   let params: any;
  //   const token = cookies.get(TOKEN_NAME);
  //   const lang = cookies.get("curr_lang") || "zh";

  //   try {
  //     if (token) {
  //       params = {
  //         token,
  //         vid: videoInfo?.id,
  //       };
  //     } else {
  //       params = {
  //         // token,
  //         vid: videoInfo?.id,
  //       };
  //     }

  //     // vip video required user login and vip
  //     if (videoInfo?.private === 1 || videoInfo?.private === 2) {
  //       if (!token) {
  //         return modal.info({
  //           title: `${t("information")}`,
  //           content: `${t("requireLoginBeforeWatchVideo")}`,
  //           confirmFn: () => navigate(`/user/login`),
  //           confirmText: `${t("signinNow")}`,
  //         });
  //       }

  //       if (videoInfo?.private === 2 && userVip.vip === 0) {
  //         return modal.info({
  //           title: `${t("information")}`,
  //           content: `${t("requireVipBeforeWatchVideo")}`,
  //           confirmFn: () => navigate(`/user/vip`),
  //           confirmText: `${t("buyNow")}`,
  //         });
  //       }
  //     }

  //     const res = await requestGetVideoUrl(params);

  //     if (res?.data?.code === 1) {
  //       const url = res?.data.data;
  //       if (videoInfo) {
  //         const dp = new DPlayer({
  //           container: dPlayerRef.current,
  //           autoplay: false,
  //           lang: lang === "zh" ? "zh-cn" : lang,
  //           screenshot: true,
  //           hotkey: true,
  //           preload: "auto",
  //           // volume: 0.7,
  //           mutex: true,
  //           video: {
  //             url: url,
  //             pic: videoInfo?.preview,
  //             thumbnails: `/${siteType.theme}/icon_play.png`,
  //             type: "auto",
  //           },
  //           subtitle: {
  //             url: videoInfo?.zimu || "",
  //             type: "webvtt",
  //             fontSize: "25px",
  //             bottom: "10%",
  //             color: "#b7daff",
  //           },
  //         });
  //         setDVideo(dp);
  //         if (dp) {
  //           setVideoPlaying(true);
  //           setTimeout(function () {
  //             dp.play();
  //           }, 100);

  //           dp?.on(DPlayerEvents.playing, function () {
  //             console.log("playing");
  //           });

  //           dp?.on(DPlayerEvents.play, function () {
  //             console.log("play");
  //             document.addEventListener("keydown", (e) =>
  //               controlArrowKey(e, dp)
  //             );
  //           });

  //           dp?.on(DPlayerEvents.abort, function () {
  //             document.addEventListener("keydown", (e) =>
  //               controlArrowKey(e, dp)
  //             );
  //           });

  //           dp?.on(DPlayerEvents.contextmenu_show, function () {
  //             console.log("context");
  //           });
  //         }
  //       }
  //     }
  //   } catch (err) {
  //     console.log(err);
  //   }
  // };

  const handleNoPermissionError = () => {
    const token = cookies.get(TOKEN_NAME);

    // vip video required user login and vip
    if (videoInfo?.private === 1 || videoInfo?.private === 2) {
      if (!token) {
        return modal.info({
          title: `${t("information")}`,
          content: `${t("requireLoginBeforeWatchVideo")}`,
            confirmFn: () => navigate(`/user/login`, { state: { redirectTo: `/video/info?id=${videoInfo?.id}` } }),
          confirmText: `${t("signinNow")}`,
        });
      }

      if (videoInfo?.private === 2 && userVip.vip === 0) {
        return modal.info({
          title: `${t("information")}`,
          content: `${t("requireVipBeforeWatchVideo")}`,
          confirmFn: () => navigate(`/user/vip`),
          confirmText: `${t("buyNow")}`,
        });
      }
    }
  };

  useEffect(() => {
    if (videoId) {
      handleGetVideoInfo();
    }
  }, [videoId, userVip]);

  useEffect(() => {
    handleGetVideoHotList();
  }, [siteType.theme]);

  useEffect(() => {
    const configAdv = configList?.video_adv;
    if (configAdv) {
      const formattedConfigAdv = JSON.parse(configAdv || "");
      setVideoAdv(formattedConfigAdv || []);
    }
  }, [configList?.video_adv, videoInfo]);

  useEffect(() => {
    window.scrollTo(0, 0);

    if (dVideo) {
      // setVideoPlaying(false);
      dVideo.destroy();
    }
  }, [videoInfo]);

  return (
    <>
      <div className={styles.videoInfoContainer}>
        {/* <div
          className={`${
            !videoPlaying
              ? `${styles.videoJsContainer}`
              : `${styles.videoJsContainerMaxContent}`
          }`}
        >
          {Object.keys(videoInfo || {}).length > 0 && (
            <VideoJS
              options={videoJsOptions}
              onReady={handlePlayerReady}
              preview={videoInfo?.preview}
              videoInfo={videoInfo}
            />
          )}
        </div> */}
        <div className={styles.dPlayerContainer}>
          {imagePreview && (
            <div>
              <Image
                className={styles.previewCvr}
                srcValue={videoInfo?.preview}
                layout="horizontal"
              />
              <Image
                className={styles.dPlayerContainerPreviewImg}
                srcValue={videoInfo?.preview}
                layout="horizontal"
              />
              <div className={styles.playerIconBg}>
                <img
                  className={styles.playerIcon}
                  src={`/play-solid.svg`}
                  onClick={handleNoPermissionError}
                />
              </div>
            </div>
          )}
          <div className={styles.dplayer} ref={dPlayerRef}></div>
        </div>
        {Object.keys(videoInfo || {}).length > 0 && (
          <div className={styles.videoInfo}>
            <div className={styles.videoInfoContent}>
              <p>{videoInfo?.title}</p>
              <span className={styles.videoInfoDesc}>
                {videoInfo?.description}
              </span>
            </div>
            <div className={styles.videoInfoOperate}>
              <div
                className={
                  siteType.theme === THEME_COLOR.YELLOW
                    ? `${styles.videoInfoOperateList} ${styles.videoInfoYellowOperateList}`
                    : styles.videoInfoOperateList
                }
              >
                {siteType.theme === THEME_COLOR.YELLOW
                  ? yellowOperateList?.map((operator: any) => {
                      const key: string = operator.key || "";

                      let imgSrc = operator.icon;
                      let label = operator.label;
                      if (
                        (operator.id === 6 && videoInfo?.is_subscribe === 1) ||
                        (operator.id === 7 && videoInfo?.is_collect === 1)
                      ) {
                        imgSrc =
                          operator.id === 7
                            ? `/${siteType.theme}${operator.icon_true}.png`
                            : operator.icon_true;
                        label = operator.label_on;
                      }

                      return (
                        <div
                          className={styles.videoInfoYellowOperateItem}
                          key={operator.id}
                          onClick={() => handleOperatorOnClick(operator.id)}
                        >
                          <div className={styles.videoInfoOperateImg}>
                            <img src={imgSrc} alt={operator.label} />
                          </div>
                          {operator.key && (
                            <p className={styles.videoInfoOperateInfo}>
                              {operator.secondKey
                                ? (videoInfo as any)?.[key][operator.secondKey]
                                : (videoInfo as any)?.[key]}
                            </p>
                          )}
                          {operator.label && (
                            <p className={styles.videoInfoOperateLabel}>
                              {t(label)}
                            </p>
                          )}
                          {operator.freeVIP === true && (
                            <div className={styles.getFreeVIp}>
                              {t("getFreeVIP")}
                            </div>
                          )}
                        </div>
                      );
                    })
                  : operateList?.map((operator: any) => {
                      const key: string = operator.key || "";

                      let imgSrc = operator.icon;
                      let label = operator.label;
                      if (
                        (operator.id === 6 && videoInfo?.is_subscribe === 1) ||
                        (operator.id === 7 && videoInfo?.is_collect === 1)
                      ) {
                        imgSrc =
                          operator.id === 7
                            ? `/${siteType.theme}${operator.icon_true}.png`
                            : operator.icon_true;
                        label = operator.label_on;
                      }

                      return (
                        <div
                          className={styles.videoInfoOperateItem}
                          key={operator.id}
                          onClick={() => handleOperatorOnClick(operator.id)}
                        >
                          <div className={styles.videoInfoOperateImg}>
                            <img src={imgSrc} alt={operator.label} />
                          </div>
                          {operator.key && (
                            <p className={styles.videoInfoOperateInfo}>
                              {operator.secondKey
                                ? (videoInfo as any)?.[key][operator.secondKey]
                                : (videoInfo as any)?.[key]}
                            </p>
                          )}
                          {operator.label && (
                            <p className={styles.videoInfoOperateLabel}>
                              {t(label)}
                            </p>
                          )}
                          {operator.freeVIP === true && (
                            <div className={styles.getFreeVIp}>
                              {t("getFreeVIP")}
                            </div>
                          )}
                        </div>
                      );
                    })}
              </div>
            </div>
          </div>
        )}
        <div className={styles.asVideoInfo}>
          {u.isMobile()
            ? as5?.map((item: AsType, index: number) => {
                return (
                  <Link key={index} to={item.url || "#"} target="_blank">
                    <Image layout="ads" srcValue={item?.thumb} alt="thumb" />
                  </Link>
                );
              })
            : as4?.map((item: AsType, index: number) => {
                return (
                  <Link key={index} to={item.url || "#"} target="_blank">
                    <Image layout="ads" srcValue={item?.thumb} alt="thumb" />
                  </Link>
                );
              })}
        </div>
        <div className={styles.asVideoInfo1}>
          {u.isMobile()
            ? as7?.map((item: AsType, index: number) => {
                return (
                  <Link key={index} to={item.url || "#"} target="_blank">
                    <Image layout="ads" srcValue={item?.thumb} alt="thumb" />
                  </Link>
                );
              })
            : as6?.map((item: AsType, index: number) => {
                return (
                  <Link key={index} to={item.url || "#"} target="_blank">
                    <Image layout="ads" srcValue={item?.thumb} alt="thumb" />
                  </Link>
                );
              })}
        </div>
        {/* {as4 && !u.isMobile() && (
          <div className={styles.asVideoInfo}>
            <Link to={as4.url || "#"} target="_blank">
              <Image srcValue={as4?.thumb} alt="thumb" />
            </Link>
          </div>
        )} */}
        {/* {as6 && !u.isMobile() && (
          <div className={styles.asVideoInfo1}>
            <Link to={as6.url || "#"} target="_blank">
              <Image srcValue={as6?.thumb} alt="thumb" />
            </Link>
          </div>
        )} */}
        {/* {as5 && u.isMobile() && (
          <div className={styles.asVideoInfo}>
            <Link to={as5.url || "#"} target="_blank">
              <Image srcValue={as5?.thumb} alt="thumb" />
            </Link>
          </div>
        )} */}
        {/* {as7 && u.isMobile() && (
          <div className={styles.asVideoInfo1}>
            <Link to={as7.url || "#"} target="_blank">
              <Image srcValue={as7?.thumb} alt="thumb" />
            </Link>
          </div>
        )} */}
        {showShare && (
          <div className={styles.copyShare}>
            <div className={styles.copyShareField}>
              <TextArea
                label=""
                layout="vertical"
                name="share"
                type="textarea"
                placeholder=""
                rows={3}
                value={`${window.location.origin}/video/info/id/${
                  videoInfo?.id
                }/code/${currentUser.code}   ${t("shareDesc")}${t(
                  "shareDesc1"
                )}  ${currentUser.code}`}
              />
              <div className={styles.copyShareHint}>
                <span>{t("shareAndValidRegistration")}</span>
                <Button
                  title={t("copyShare")}
                  type="primary-gradient"
                  fontSize="small"
                  className={styles.copyShareBtn}
                  onClick={() =>
                    handleCopyShareCode(
                      `${window.location.origin}/video/info/id/${
                        videoInfo?.id
                      }/code/${currentUser.code}  ${t("shareDesc")}${t(
                        "shareDesc1"
                      )}  ${currentUser.code}`
                    )
                  }
                />
              </div>
            </div>
          </div>
        )}
        <div className={styles.videoType}>
          <div className={styles.videoTypeTitle}>{t("type")}</div>
          <div className={styles.videoTypeTagList}>
            {videoInfo?.tags?.map((tag: TagType) => (
              <Link
                key={tag.id}
                to={`/video/list?tag_id=${tag.id}&tag_name=${tag.name}`}
              >
                <div className={styles.videoTypeTag}>
                  <p>{tag.name}</p>
                </div>
              </Link>
            ))}
          </div>
        </div>
        <div className={styles.videoPromote}>
          {videoAdv.map((val: any, index: any) => {
            return (
              <Link
                to={val?.url}
                style={{
                  color:
                    (index + 1) % 3 === 1
                      ? "#F6E121"
                      : (index + 1) % 3 === 2
                      ? "#44D2FF"
                      : "#DE5AFF",
                }}
                key={index}
              >
                {val?.title}
              </Link>
            );
          })}
        </div>
        <div className={styles.videoHotListContainer}>
          <div className={styles.videoHotListTitle}>
            <p className={styles.videoHotListLabel}>{t("popularVideos")}</p>
            {/* <p className={styles.videoHotListMore}>更多</p> */}
          </div>
          <div className={styles.videoHotListItem}>
            {videoHotList?.data?.map((video: VideoType) => (
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
                    ? styles.videoHotItem
                    : styles.videoOtherHotItem
                }
                size="small"
              />
            ))}
          </div>
        </div>
      </div>
      <ToastContainer
        position="top-center"
        theme="colored"
        autoClose={1000}
        hideProgressBar={true}
        pauseOnHover
        className="toast_notification"
        transition={Slide}
        closeButton={false}
      />
    </>
  );
};

export default VideoInfo;
