// import { FC, useEffect, useRef, useState } from "react";
// import videojs from "video.js";
// import "video.js/dist/video-js.css";

// import Player from "video.js/dist/types/player";

// import styles from "./VideoJs.module.css";
// import Image from "../Image/Image";
// import { useUser } from "../../contexts/user.context";
// import useAxios from "../../hooks/useAxios";

// import Cookies from "universal-cookie";
// import { TOKEN_NAME } from "../../utils/constant";
// import { useModal } from "../../contexts/modal.context";
// import { useNavigate } from "react-router";
// import { useTranslation } from "react-i18next";
// import u from "../../utils/utils";

// const cookies = new Cookies();

// export const VideoJS: FC<any> = (props) => {
//   const { t } = useTranslation();
//   const { options, onReady, preview, videoInfo } = props;
//   const modal = useModal();
//   const navigate = useNavigate();
//   const { userVip } = useUser();
//   const siteType = u.siteType();

//   const { req } = useAxios("video/getVideoUrl", "post");

//   const videoRef: any = useRef(null);
//   const playerRef: any = useRef<Player>(null);
//   // const [msg, setMsg] = useState<any>([]);
//   const [videoPlaying, setVideoPlaying] = useState(false);

//   const _handlePlayerEvents = () => {
//     const player = playerRef.current;

//     if (player) {
//       player.on("playing", () => {
//         setVideoPlaying(true);
//       });

//       player.on("pause", () => {
//         // console.log("pause");
//         // setVideoPlaying(false);
//       });
//     }
//   };

//   // const hanldeVideoPlaying = async () => {
//   //   setMsg((prev: any) => {
//   //     return [...prev, `====hanldeVideoPlaying-Start===`];
//   //   });
//   //   let params: any;
//   //   const player = playerRef.current;
//   //   const token = cookies.get(TOKEN_NAME);
//   //   if (token) {
//   //     params = {
//   //       token,
//   //       vid: videoInfo.id,
//   //     };
//   //   } else {
//   //     params = {
//   //       // token,
//   //       vid: videoInfo.id,
//   //     };
//   //   }
//   //   setMsg((prev: any) => {
//   //     return [...prev, `====hanldeVideoPlaying-Checking Video Info===`];
//   //   });

//   //   // vip video required user login and vip
//   //   if (videoInfo?.private === 1 || videoInfo?.private === 2) {
//   //     if (!token) {
//   //       return modal.info({
//   //         title: `${t("information")}`,
//   //         content: `${t("requireLoginBeforeWatchVideo")}`,
//   //         confirmFn: () => navigate(`/user/login`),
//   //         confirmText: `${t("signinNow")}`,
//   //       });
//   //     }

//   //     if (videoInfo?.private === 2 && userVip.vip === 0) {
//   //       return modal.info({
//   //         title: `${t("information")}`,
//   //         content: `${t("requireVipBeforeWatchVideo")}`,
//   //         confirmFn: () => navigate(`/user/vip`),
//   //         confirmText: `${t("buyNow")}`,
//   //       });
//   //     }
//   //   }
//   //   // console.log("params", params);
//   //   setMsg((prev: any) => {
//   //     return [...prev, `====hanldeVideoPlaying-Start Reqeust API===`];
//   //   });

//   //   try {
//   //     const res = await req(params);
//   //     setMsg((prev: any) => {
//   //       return [...prev, "====hanldeVideoPlaying-Done Request API==="];
//   //     });
//   //     // console.log("res", res);

//   //     if (res?.data?.code === 1) {
//   //       setMsg((prev: any) => {
//   //         return [
//   //           ...prev,
//   //           `====hanldeVideoPlaying-ReadResponse-Request API Success-${res?.data?.data}===`,
//   //         ];
//   //       });
//   //       const videoSrc = res?.data?.data;
//   //       // console.log('src', videoSrc)
//   //       setMsg((prev: any) => {
//   //         return [
//   //           ...prev,
//   //           `====hanldeVideoPlaying-Set Video Playing-${res?.data?.data}===`,
//   //         ];
//   //       });
//   //       setVideoPlaying(true);

//   //       player.src({
//   //         src: videoSrc,
//   //         type: "application/x-mpegURL",
//   //       });
//   //       setMsg((prev: any) => {
//   //         return [
//   //           ...prev,
//   //           `====hanldeVideoPlaying-Done Set Video Playing-${res?.data?.data}===`,
//   //         ];
//   //       });
//   //       playerRef.current.play();
//   //       setMsg((prev: any) => {
//   //         return [
//   //           ...prev,
//   //           `====hanldeVideoPlaying-Playing Video-${res?.data?.data}===`,
//   //         ];
//   //       });
//   //     } else {
//   //       if (res?.data?.code === 3004) {
//   //         setMsg((prev: any) => {
//   //           return [
//   //             ...prev,
//   //             `====hanldeVideoPlaying-ReadResponse-Request API-${res?.data?.code}===`,
//   //           ];
//   //         });
//   //         return modal.info({
//   //           title: `${t("information")}`,
//   //           content: res?.data?.msg,
//   //           confirmFn: () => navigate(`/user/vip`),
//   //           confirmText: `${t("buyNow")}`,
//   //         });
//   //       } else {
//   //         setMsg((prev: any) => {
//   //           return [
//   //             ...prev,
//   //             `====hanldeVideoPlaying-ReadResponse-Request API-Error${res?.data?.msg}===`,
//   //           ];
//   //         });
//   //         return modal.info({
//   //           title: `${t("information")}`,
//   //           content: `${res?.data?.msg}.`,
//   //           // confirmFn: () => navigate(`/user/login`),
//   //           // confirmText: `${t("signinNow")}`,
//   //         });
//   //       }
//   //     }
//   //   } catch(err) {
//   //     setMsg((prev: any) => {
//   //       return [...prev, `====hanldeVideoPlaying-Catch Error-${err}===`];
//   //     });
//   //     console.log(err);
//   //   }
//   // };

//   const hanldeVideoPlaying = async () => {
//     let params: any;
//     const player = playerRef.current;
//     const token = cookies.get(TOKEN_NAME);
//     if (token) {
//       params = {
//         token,
//         vid: videoInfo.id,
//       };
//     } else {
//       params = {
//         // token,
//         vid: videoInfo.id,
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
//     // console.log("params", params);

//     try {
//       const res = await req(params);
//       // console.log("res", res);

//       if (res?.data?.code === 1) {
//         const videoSrc = res?.data?.data;
//         // console.log('src', videoSrc)
//         setVideoPlaying(true);

//         player.src({
//           src: videoSrc,
//           type: "application/x-mpegURL",
//         });
//         playerRef.current.play();
//       } else {
//         if (res?.data?.code === 3004) {
//           return modal.info({
//             title: `${t("information")}`,
//             content: res?.data?.msg,
//             confirmFn: () => navigate(`/user/vip`),
//             confirmText: `${t("buyNow")}`,
//           });
//         } else {
//           return modal.info({
//             title: `${t("information")}`,
//             content: `${res?.data?.msg}.`,
//             // confirmFn: () => navigate(`/user/login`),
//             // confirmText: `${t("signinNow")}`,
//           });
//         }
//       }
//     } catch (err) {
//       console.log(err);
//     }
//   };

//   useEffect(() => {
//     // Make sure Video.js player is only initialized once
//     if (!playerRef.current) {
//       // The Video.js player needs to be _inside_ the component el for React 18 Strict Mode.
//       const videoElement = document.createElement("video-js");

//       videoElement.classList.add("vjs-big-play-centered");
//       videoRef.current.appendChild(videoElement);

//       // console.log("options", options);
//       const player = (playerRef.current = videojs(videoElement, options, () => {
//         // videojs.log("player is ready");
//         onReady && onReady(player);
//         _handlePlayerEvents();
//       }));
//     } else {
//       const player = playerRef.current;

//       player.autoplay(options.autoplay);
//       player.src(options.sources);
//     }
//   }, [onReady, options, videoRef]);

//   // Dispose the Video.js player when the functional component unmounts
//   useEffect(() => {
//     const player = playerRef.current;

//     return () => {
//       if (player && !player.isDisposed()) {
//         player.dispose();
//         playerRef.current = null;
//       }
//     };
//   }, [playerRef]);

//   useEffect(() => {
//     const lastResource = playerRef?.current?.lastSource_;
//     if (lastResource) {
//       setVideoPlaying(false);
//       playerRef.current.pause();
//       // playerRef.current.dispose();
//       // playerRef.current.autoplay(options.autoplay);
//       // playerRef.current.src(options.sources);
//     }
//   }, [videoInfo]);

//   return (
//     <div
//       className={`${
//         !videoPlaying ? `${styles.player}` : `${styles.playerMaxContent}`
//       }`}
//       data-vjs-player
//     >
//       {/* <div style={{ color: "#fff" }}>
//         <ul>
//           {msg &&
//             msg.map((i: any) => {
//               return <li>{i}</li>;
//             })}
//         </ul>
//       </div> */}
//       <div
//         ref={videoRef}
//         className={`${
//           !videoPlaying
//             ? `${styles.videoPlayerRef}`
//             : `${styles.videoPlayerRefMaxContent}`
//         }`}
//       />
//       {!videoPlaying && (
//         <Image
//           srcValue={preview}
//           alt={preview}
//           className={styles.playerPreviewImg}
//           layout="horizontal"
//         />
//       )}
//       {!videoPlaying && (
//         <div className={styles.playerIconBg}>
//           <img
//             className={styles.playerIcon}
//             src={`/${siteType.theme}/icon_play.png`}
//             onClick={hanldeVideoPlaying}
//           />
//         </div>
//       )}
//     </div>
//   );
// };

// export default VideoJS;
