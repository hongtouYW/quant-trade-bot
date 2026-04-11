import { useEffect, useState } from "react";
import Image from "../../../../components/Image/Image";
import Button from "../../../../components/button/Button";
import useAxios from "../../../../hooks/useAxios";
import Header from "../../components/header/Header";
import Cookies from "universal-cookie";

import styles from "./Subscribe.module.css";
import { TOKEN_NAME } from "../../../../utils/constant";
import { SubscribeListType, SubscribeType } from "../../../../utils/type";
import u from "../../../../utils/utils";
import { Link } from "react-router-dom";
import Pagination from "../../../../components/pagination/Pagination";
import { useTranslation } from "react-i18next";

const cookies = new Cookies();

const Subscribe = () => {
  const { t } = useTranslation();
  const { req } = useAxios("actor/mySubscribe", "post");
  const { req: subscribeActorReq } = useAxios("actor/subscribe", "post");

  const [subscribeList, setSubscribeList] = useState<SubscribeListType>();
  const limit = 12;

  const handleGetMySubscribeList = async (pageNum: number = 1) => {
    try {
      const token = cookies.get(TOKEN_NAME);
      const params = {
        token,
        page: pageNum,
        limit: limit,
      };
      const res = await req(params);

      if (res.data.code === 1) {
        const list = res?.data?.data || {};
        setSubscribeList(list);
      }
    } catch (err) {
      console.log(err);
    }
  };

  const activeHandler = (clickedActive: any) => {
    let num = parseInt(clickedActive);
    if (subscribeList && num > subscribeList?.last_page) {
      num = subscribeList?.last_page;
    }
    handleGetMySubscribeList(num);
  };

  const handleUnsubscribe = async (aid: number) => {
    try {
      const token = cookies.get(TOKEN_NAME);
      const params = {
        token,
        aid: aid,
      };

      const res = await subscribeActorReq(params);
   
      if (res.data.code === 1) {
        handleGetMySubscribeList();
      }
    } catch (err) {
      console.log(err);
    }
  };

  useEffect(() => {
    handleGetMySubscribeList();
  }, []);

  return (
    <>
      <Header title={t("subscriptionList")} />
      <div className={styles.subscribeContainer}>
        <div className={styles.subscribeList}>
          {subscribeList?.data?.map((subscribe: SubscribeType) => {
            // console.log("subscribe", subscribe);
            return (
              <div className={styles.subscribeItem} key={subscribe.id}>
                <Link to={`/video/list?actor_id=${subscribe.id}`}>
                  <div className={styles.subscribeActorInfo}>
                    <div className={styles.subscribeActorImage}>
                      <Image srcValue={subscribe.image} alt={subscribe.name} />
                    </div>
                    <div className="subscribeActorContent">
                      <p className={styles.subscribeActorName}>
                        {subscribe.name}
                      </p>
                      <p className={styles.subscribeActorDate}>
                        {u.timestampToDate(subscribe.add_time || "")}
                      </p>
                    </div>
                  </div>
                </Link>
                <div className="subscribeActorCancelBtn">
                  <Button
                    title={t("unsubscribe")}
                    type="secondary"
                    onClick={() => handleUnsubscribe(subscribe?.id)}
                  />
                </div>
              </div>
            );
          })}
        </div>
        <Pagination
          active={subscribeList?.current_page || 1}
          size={subscribeList?.last_page || 1} // last_page
          total={subscribeList?.total || 1}
          step={u.isMobile() ? 1 : 3}
          limit={limit}
          onClickHandler={activeHandler}
        />
      </div>
    </>
  );
};

export default Subscribe;
