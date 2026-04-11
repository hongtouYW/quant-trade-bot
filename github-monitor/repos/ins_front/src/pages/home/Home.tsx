import { useEffect, useState } from "react";
import Marquee from "react-fast-marquee";

import useAxios from "../../hooks/useAxios";
import VideoIndexList from "./components/videoIndexList/VideoIndexList";
import Banner from "../../components/banner/Banner";
import { INITIALBANNERLIST, INITIALVIDEOLIST } from "../../utils/data";

import {
  HOME_NOTICE,
  NAVIGATE_BOTTOM,
  NAVIGATE_CENTER,
  TOKEN_NAME,
} from "../../utils/constant";

import styles from "./Home.module.css";
// import ConfigLinks from "./components/configLinks/ConfigLinks";
import { useConfig } from "../../contexts/config.context";
import { Link, NavLink } from "react-router-dom";
import { AsType, LinkType, NoticeType } from "../../utils/type";
// import { useModal } from "../../contexts/modal.context";
import { useUser } from "../../contexts/user.context";
import Cookies from "universal-cookie";
import u from "../../utils/utils";
import { useTranslation } from "react-i18next";
import Modal from "../../components/modal/Modal";
import Image from "../../components/Image/Image";
import ModalImage from "../../components/modalImage/ModalImage";

const cookies = new Cookies();
const THREE_HOURS = 3 * 60 * 60 * 1000; // 3 hours in ms

const Home = () => {
  const { t } = useTranslation();
  // const modal = useModal();
  const { currentUser, userVip } = useUser();
  const { asList, configList } = useConfig();
  // const noticeDialogRef = useRef<HTMLDialogElement | null>(null);

  const [videoList, setVideoList] = useState({
    recommendedList: [...INITIALVIDEOLIST],
    vipList: [...INITIALVIDEOLIST],
    freeList: [...INITIALVIDEOLIST],
    hotList: [...INITIALVIDEOLIST],
  });
  const [bannerList, setBannerList] = useState([...INITIALBANNERLIST]);
  const [noticeList, setNoticeList] = useState<Array<NoticeType>>([]);
  const [showNotice, setShowNotice] = useState(false);
  const [imgCenterObj, setImgCenterObj] = useState({
    open: false,
    image: "",
  });
  const [imgBottomObj, setImgBottomObj] = useState({
    open: false,
    image: "",
  });
  const [links, setLinks] = useState({
    list: [],
    open: false,
  });

  const { req: videoIndexRequest } = useAxios("video/indexLists", "post");
  const { req: videoIndexHotRequest } = useAxios("video/hotLists", "post");
  const { req: bannerRequest } = useAxios("banner/lists", "post");
  const { req: noticeRequest } = useAxios("notice/lists", "post");
  const { req: linksRequest } = useAxios("config/links", "post");

  const as1: Array<AsType> = asList[1] || [];
  const as2: AsType = u.isMobile() ? asList[3]?.[0] : asList[2]?.[0];
  const as5: AsType = u.isMobile() ? asList[6]?.[0] : asList[5]?.[0];

  const handleGetBannerList = async () => {
    try {
      const res = await bannerRequest();
      const list = res?.data?.data || [];
      // console.log('list', list)
      setBannerList(list);
    } catch (err) {
      console.log(err);
    }
  };

  const handleGetVideoIndexListRecommended = async () => {
    // type: 1推荐 2vip专项 3免费
    try {
      const params = {
        page: 1,
        limit: 12,
        type: 1,
      };
      const res = await videoIndexRequest(params);
      const list = res?.data?.data?.data || [];
      // console.log('list', list)
      setVideoList((prev) => {
        return {
          ...prev,
          recommendedList: list,
        };
      });
    } catch (err) {
      console.log(err);
    }
  };

  const handleGetVideoIndexListVip = async () => {
    // type: 1推荐 2vip专项 3免费
    try {
      const params = {
        page: 1,
        limit: 15,
        type: 2,
      };
      const res = await videoIndexRequest(params);
      const list = res?.data?.data?.data || [];

      setVideoList((prev) => {
        return {
          ...prev,
          vipList: list,
        };
      });
    } catch (err) {
      console.log(err);
    }
  };

  const handleGetVideoIndexListFree = async () => {
    // type: 1推荐 2vip专项 3免费
    try {
      const params = {
        page: 1,
        limit: 12,
        type: 3,
      };
      const res = await videoIndexRequest(params);
      const list = res?.data?.data?.data || [];

      setVideoList((prev) => {
        return {
          ...prev,
          freeList: list,
        };
      });
    } catch (err) {
      console.log(err);
    }
  };

  const handleGetVideoIndexListHot = async () => {
    try {
      const params = {
        page: 1,
        limit: 12,
        order: 3,
      };
      const res = await videoIndexHotRequest(params);
      const list = res?.data?.data?.data || [];

      setVideoList((prev) => {
        return {
          ...prev,
          hotList: list,
        };
      });
    } catch (err) {
      console.log(err);
    }
  };

  const handleGetNoticeList = async () => {
    try {
      const res = await noticeRequest();

      setNoticeList(res?.data?.data || []);
    } catch (err) {
      console.log(err);
    }
  };

  const handleCloseNotice = () => {
    setShowNotice(false);
    // 1 = "close"
    u.setCookies(HOME_NOTICE, "1", 0.5);
  };

  const handleCloseCenterImg = () => {
    const expiresHours = new Date();
    expiresHours.setHours(expiresHours.getHours() + 6);
    const formattedExpiresHours = u.dateToTimestamp(expiresHours);

    u.setLocalItemExpires(NAVIGATE_CENTER, "1", formattedExpiresHours);

    setImgCenterObj((prev) => {
      return {
        ...prev,
        open: false,
      };
    });
  };

  const handleCloseBottomImg = () => {
    // 1 = "pending", 2 = "close"
    const imgBottom = localStorage.getItem(NAVIGATE_BOTTOM);

    // 设置 6小时
    const expiresHours = new Date();
    expiresHours.setHours(expiresHours.getHours() + 6);
    const formattedExpiresHours = u.dateToTimestamp(expiresHours);

    if (imgBottom) {
      const formattedImgCenter = JSON.parse(imgBottom);
      const values = formattedImgCenter["values"];

      if (values === "1") {
        u.setLocalItemExpires(NAVIGATE_BOTTOM, "2", formattedExpiresHours);

        setImgBottomObj((prev) => {
          return {
            ...prev,
            open: false,
          };
        });
      }
      if (values === "2") {
        setImgBottomObj((prev) => {
          return {
            ...prev,
            open: false,
          };
        });
      }
    } else {
      if (as2?.url) {
        window.open(as2?.url, "_blank");
      }
      u.setLocalItemExpires(NAVIGATE_BOTTOM, "1", formattedExpiresHours);
    }
  };

  const handleGetLinks = async () => {
    try {
      const res = await linksRequest();
      const list = res?.data?.data || [];
      // console.log("list", list);

      setLinks((prev) => {
        return {
          ...prev,
          list: list,
        };
      });
    } catch (err) {
      console.log(err);
    }
  };

  useEffect(() => {
    handleGetVideoIndexListRecommended();
    handleGetVideoIndexListVip();
    handleGetVideoIndexListFree();
    handleGetVideoIndexListHot();
    handleGetBannerList();
    handleGetNoticeList();
    handleGetLinks();
  }, []);

  useEffect(() => {
    const lastShown = localStorage.getItem("modalShownAtLinks");
    const now = Date.now();

    if (!lastShown || now - Number(lastShown) > THREE_HOURS) {
      setLinks((prev) => {
        return {
          ...prev,
          open: true,
        };
      });
      // localStorage.setItem("modalShownAtLinks", String(now));
    }
  }, []);

  useEffect(() => {
    const homeNotice = cookies.get(HOME_NOTICE);
    if (!homeNotice && noticeList.length > 0) {
      // if (noticeDialogRef.current) noticeDialogRef.current.showModal();
      setShowNotice(true);
    }
  }, [noticeList]);

  useEffect(() => {
    const token = cookies.get(TOKEN_NAME);

    if (as5) {
      if (!token || (token && userVip.vip === 0)) {
        const imgCenter = localStorage.getItem(NAVIGATE_CENTER);
        if (imgCenter) {
          const formattedImgCenter = JSON.parse(imgCenter);
          const expires = formattedImgCenter["expires"];
          const now = Date.now();

          if (now > expires) {
            setImgCenterObj({
              image: as5.thumb || "",
              open: true,
            });
          } else {
            setImgCenterObj({
              image: as5.thumb || "",
              open: false,
            });
          }
        } else {
          setImgCenterObj({
            image: as5.thumb || "",
            open: true,
          });
        }
      } else {
        setImgCenterObj((prev) => {
          return {
            ...prev,
            open: false,
          };
        });
      }
    }
  }, [as5, currentUser, userVip]);

  useEffect(() => {
    const token = cookies.get(TOKEN_NAME);
    if (as2) {
      if (!token || (token && userVip.vip === 0)) {
        const imgBottom = localStorage.getItem(NAVIGATE_BOTTOM);
        // 1 = "pending", 2 = "close"
        if (imgBottom) {
          const formattedImgBottom = JSON.parse(imgBottom);
          const expires = formattedImgBottom["expires"];
          const values = formattedImgBottom["values"];
          const now = Date.now();

          if (values === "1") {
            // 1 = pending 还需要显示
            setImgBottomObj({
              image: as2.thumb || "",
              open: true,
            });
          } else {
            // 当2 是close了 要检查超过expires时间了没
            if (now < expires) {
              setImgBottomObj({
                image: as2.thumb || "",
                open: true,
              });
            } else {
              setImgBottomObj({
                image: as2.thumb || "",
                open: false,
              });
            }
          }
        } else {
          setImgBottomObj({
            image: as2.thumb || "",
            open: true,
          });
        }
      } else {
        setImgBottomObj({
          image: as2.thumb || "",
          open: false,
        });
      }
    }
  }, [as2, currentUser, userVip]);

  return (
    <div key="home">
      <div className={styles.homeContainer}>
        <div className={styles.bannerContainer}>
          <div className="bannerBodySwiper">
            <Banner slides={bannerList} />
          </div>
        </div>
        <div className={styles.noticeMarquee}>
          <div className={styles.noticeBulletin}>BULLETIN</div>
          <div className={styles.noticeIcons}>
            <img src="/speaker.svg" alt="" width={25} height={25} />
          </div>
          <div key="marquee">
            <Marquee speed={70}>
              {configList?.notice
                ?.split("\n")
                .map((notice: string, index: number) => {
                  return (
                    <p
                      className={styles.marqueeText}
                      key={index}
                      style={{
                        margin: u.isMobile() ? "0px 150px" : "0px 200px",
                      }}
                    >
                      {notice}
                    </p>
                  );
                })}
            </Marquee>
          </div>
        </div>
        {/* <ConfigLinks /> */}
        <VideoIndexList
          key="recommendedVideos"
          title={t("recommendedVideos")}
          list={videoList.recommendedList}
          type={2}
          videoType={1}
        />
        <VideoIndexList
          key="mostPopularWeek"
          title={t("mostPopularWeek")}
          list={videoList.hotList}
        />
        <VideoIndexList
          key="membersOnly"
          title={t("membersOnly")}
          list={videoList.vipList}
          type={3}
          videoType={2}
        />
        <VideoIndexList
          key="freeVideos"
          title={t("freeVideos")}
          list={videoList.freeList}
          type={2}
          videoType={3}
        />
        <div>
          {as1?.map((item: AsType, index: number) => {
            return (
              <div key={index} className={styles.asHomeFooter}>
                <Link to={item.url || "#"} target="_blank">
                  <Image layout="ads" srcValue={item?.thumb} alt="thumb" />
                </Link>
              </div>
            );
          })}
        </div>
        {/* {as1 && (
          <div key="as1" className={styles.asHomeFooter}>
            <Link to={as1.url || "#"} target="_blank">
              <Image srcValue={as1?.thumb} alt="thumb" />
            </Link>
          </div>
        )} */}
      </div>
      {imgCenterObj.open && (
        <ModalImage
          key="imgCenterObj"
          open={imgCenterObj.open}
          dialogName="img-dialog-center"
          className={styles.imageCenterModal}
        >
          <div className={styles.imgCenterContainer}>
            <button
              className={styles.closeDialogImg}
              onClick={handleCloseCenterImg}
            >
              &times;
            </button>
            <div className={styles.imageContent}>
              <NavLink to={as5?.url || ""} target="_blank">
                <Image srcValue={as5?.thumb} />
              </NavLink>
            </div>
          </div>
        </ModalImage>
      )}
      {imgBottomObj.open && (
        <ModalImage
          key="imgBottomObj"
          open={imgBottomObj.open}
          dialogName="img-dialog-bottom"
          className={styles.imageBottomModal}
        >
          <div className={styles.imgBottomContainer}>
            <button
              className={styles.closeDialogImg}
              onClick={handleCloseBottomImg}
            >
              &times;
            </button>
            <div className={styles.imageContent}>
              <NavLink to={as2?.url || ""} target="_blank">
                <Image srcValue={as2?.thumb} layout="horizontal" />
              </NavLink>
            </div>
          </div>
        </ModalImage>
      )}
      {showNotice && (
        <Modal key="custom" type="custom">
          <div
            // ref={noticeDialogRef}
            className={`${styles.noticeDialogContainer} ${
              showNotice === true ? "show" : "hide"
            }`}
            // open={showNotice}
          >
            <div className={styles.noticeContainer}>
              <p>系统公告</p>
              <span
                className={styles.noticeContainerClose}
                onClick={handleCloseNotice}
              >
                &times;
              </span>
            </div>
            {noticeList?.map((notice: NoticeType, index: any) => {
              return (
                <div
                  key={index}
                  dangerouslySetInnerHTML={{ __html: `${notice?.content}` }}
                  className={styles.noticeContainerContent}
                ></div>
              );
            })}
            <div className={styles.noticeContainerFooter}>
              <button onClick={handleCloseNotice}>我知道了</button>
            </div>
          </div>
        </Modal>
      )}
      {links.open && (
        <Modal type="custom">
          <div className={styles.configLinksContainer}>
            <div className={styles.configLinksHeader}>
              <p>{t("friendlyRecommendation")}</p>
              <span
                className={styles.configLinksHeaderClose}
                onClick={() => {
                  setLinks((prev) => {
                    return {
                      ...prev,
                      open: false,
                    };
                  });
                  localStorage.setItem("modalShownAtLinks", String(Date.now()));
                }}
              >
                &times;
              </span>
            </div>
            <div className={styles.configLinksBody}>
              <div className={styles.configLinksItemList}>
                {links?.list?.map((link: LinkType) => (
                    <a href={link.url} target="_blank" rel="noopener noreferrer" key={link.id} className={styles.configLinksItem}>
                        <div className={styles.configLinksItemImg}>
                          <Image srcValue={link.image} alt={link.title} />
                        </div>
                        <p>{link.title}</p>
                    </a>
                ))}
              </div>
            </div>
          </div>
        </Modal>
      )}
    </div>
  );
};

export default Home;
