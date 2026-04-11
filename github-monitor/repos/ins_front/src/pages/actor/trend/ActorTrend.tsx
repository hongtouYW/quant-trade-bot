import { useEffect, useState } from "react";
import useAxios from "../../../hooks/useAxios";
import { useSearchParams } from "react-router-dom";
import {
  ActorInfoType,
  ActorTrendListType,
  ActorTrendType,
} from "../../../utils/type";
import Image from "../../../components/Image/Image";
import Cookies from "universal-cookie";
import { ToastContainer, toast, Slide } from "react-toastify";

import styles from "./ActorTrend.module.css";
import { TOKEN_NAME } from "../../../utils/constant";
import { useTranslation } from "react-i18next";

const cookies = new Cookies();

const ActorTrend = () => {
  const { t } = useTranslation();
  const { req } = useAxios("actor/trend", "post");
  const { req: actorInfoRequest } = useAxios("actor/info", "post");
  const { req: subscribeActorReq } = useAxios("actor/subscribe", "post");

  const [searchParams] = useSearchParams();
  const actorId = searchParams.get("actor_id");

  const [actorTrendList, setActorTrendList] = useState<ActorTrendListType>();
  const [actorInfo, setActorInfo] = useState<ActorInfoType>();

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

  const handleGetActorTrendList = async () => {
    try {
      const params: any = {
        aid: actorId || "",
      };

      const res = await req(params);
      const data = res?.data?.data || {};

      setActorTrendList(data);
    } catch (err) {
      console.log(err);
    }
  };

  const handleSubscribeActor = async () => {
    const token = cookies.get(TOKEN_NAME);

    if (actorInfo?.is_subscribe === 1) {
      return;
    }
    if (!token) {
      toast.error(
        <p style={{ fontWeight: 900 }}>{t("subscribeDescAfterSignin")}</p>
      );
      // navigate("/user/login");
      return;
    }

    const params = {
      token,
      aid: actorInfo?.id,
    };
    const res = await subscribeActorReq(params);

    if (res?.data.code === 1) {
      toast.success(
        <p style={{ fontWeight: 900 }}>{t("subscriptionSuccess")}</p>
      );
      handleGetActorInfo();
    }
  };

  useEffect(() => {
    handleGetActorTrendList();
  }, []);

  useEffect(() => {
    handleGetActorInfo();
  }, []);

  return (
    <>
      <div className={styles.actorTrendContainer}>
        <div className={styles.actorTrendHeader}>
          <div className={styles.actorTrendHeaderTitle}>
            <p>{actorInfo?.name}的最新消息</p>
          </div>
          <div className={styles.actorTrendHeaderActor}>
            <div className={styles.actorTrendAvatar}>
              <Image srcValue={actorInfo?.image} />
            </div>
            <div className="actorTrendActorInfo">
              <div className={styles.actorInfo}>
                <p>{t("pornstars")}</p>
                <p className={styles.actorName}>{actorInfo?.name}</p>
              </div>
            </div>
          </div>
          <div className={styles.actorTrendHeaderSubscribe}>
            <button className={styles.actorTrendButton}>
              {t("pornstarsNews")}
            </button>
            <button
              className={styles.actorSubscribeButton}
              onClick={handleSubscribeActor}
            >
              {actorInfo?.is_subscribe === 1 ? (
                <span className={styles.subscribeActorIcon}>
                  <img
                    src="/icon_fav_on.png"
                    alt="subscribe"
                    width={16}
                    height={16}
                  />
                  已订阅
                </span>
              ) : (
                t("subscribe")
              )}
            </button>
          </div>
        </div>
        <div className={styles.actorTrendContent}>
          <div className="actorTrendContentList">
            {actorTrendList?.data?.map((trend: ActorTrendType) => {
              return (
                <div className={styles.actorTrendContentItem}>
                  <div className={styles.contentTitle}>
                    <p className={styles.contentActorName}>{actorInfo?.name}</p>
                    <p className={styles.contentDate}>{trend?.create_at}</p>
                  </div>
                  <div className={styles.contentDesc}>
                    <p>{trend?.text}</p>
                  </div>
                  <div className={styles.contentMedia}>
                    {trend?.media?.map((media: string) => {
                      return (
                        <>
                          <div className={styles.contentMediaImg}>
                            {/* <img src={media} /> */}
                            <Image srcValue={media} alt="media" />
                          </div>
                        </>
                      );
                    })}
                  </div>
                </div>
              );
            })}
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

export default ActorTrend;
