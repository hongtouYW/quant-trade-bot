import { Link, NavLink, useSearchParams } from "react-router-dom";
import Image from "../../components/Image/Image";
import { actorMenuList } from "../../utils/data";

import styles from "./Actor.module.css";
import useAxios from "../../hooks/useAxios";
import { useEffect, useState } from "react";
import { ActorListType, ActorType } from "../../utils/type";
import Pagination from "../../components/pagination/Pagination";
import u from "../../utils/utils";
import { useTranslation } from "react-i18next";
// import Loading from "../../components/loading/Loading";

const Actor = () => {
  const { t } = useTranslation();
  const { req } = useAxios("actor/lists", "post");
  const [searchParams, setSearchParams] = useSearchParams();
  const order = searchParams.get("order") || 1;
  const keyword = searchParams.get("keyword");
  const limit = u.isMobile() ? 30 : 31;

  // const [loading, setLoading] = useState(false);
  const [actorList, setActorList] = useState<ActorListType>();

  const handleGetActorList = async (pageNum: string = "1") => {
    // setLoading(true);
    try {
      const params: any = {
        page: pageNum,
        limit: limit,
        order: order,
      };

      if (keyword !== null && keyword !== "") {
        params["keyword"] = keyword;
      }

      const res = await req(params);
      const list = res?.data?.data || {};

      setActorList(list);
      // setLoading(false);
    } catch (err) {
      console.log(err);
      // setLoading(false);
    }
  };

  const activeHandler = (clickedActive: any) => {
    let num = parseInt(clickedActive);

    if (actorList && num > actorList?.last_page) {
      num = actorList?.last_page;
    }
    // handleGetActorList(num);

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

    handleGetActorList(page);
  }, [searchParams]);

  useEffect(() => {
    window.scrollTo({
      top: 0,
    });
  }, [searchParams]);

  return (
    <div className={styles.actorListSection}>
      {keyword && (
        <div className={styles.actorKeyword}>
          <p>
            搜索关键字是 <span>"{keyword}"</span> 的主题结果如下：
          </p>
        </div>
      )}
      <div className={styles.actorListMenu}>
        {actorMenuList?.map((menu: any, index: any) => (
          <div className={styles.actorMenuItem} key={index}>
            <NavLink
              to={menu.path}
              className={`${
                menu.order === order ? `${styles.actorMenuItemActive}` : ""
              }`}
            >
              {/* <p>{menu.title}</p> */}
              <p>{t(menu.locale)}</p>
            </NavLink>
          </div>
        ))}
      </div>
      {/* {loading && (
        <div className={styles.loadingContainer}>
          <div className={styles.loading}>
            <Loading color="white" width={80} />
          </div>
        </div>
      )} */}
      <div className={styles.actorListContainer}>
        <div className={styles.actorFirstThreeList}>
          {(actorList?.data || [])
            .slice(0, 3)
            .map((actor: ActorType, index: number) => (
              <Link
                to={`/video/list?actor_id=${actor.id}`}
                key={actor.id}
                className={styles.actorFirstThreeItem}
              >
                <div className={styles.actorFirstThreeAvatar}>
                  <Image srcValue={actor.image} />
                </div>
                <div className={styles.actorFirstThreeInfo}>
                  <p className={styles.actorFirstThreeName}>{actor.name}</p>
                  <p
                    className={`${
                      actorList?.current_page === 1
                        ? `${styles.actorFirstThreeTag} ${styles.actorFirstThreeTagBgColor}`
                        : `${styles.actorFirstThreeTag}`
                    }`}
                  >{`第${
                    (actorList?.current_page || 1) *
                      (actorList?.per_page || 1) -
                    (actorList?.per_page || 1) +
                    index +
                    1
                  }名`}</p>
                </div>
              </Link>
            ))}
        </div>
        <div className={styles.actorOthersList}>
          {(actorList?.data || [])
            .slice(3, actorList?.data.length)
            .map((actor: ActorType, index: number) => (
              <Link
                to={`/video/list?actor_id=${actor.id}`}
                key={actor.id}
                className={styles.actorOthersItem}
              >
                <div className={styles.actorOthersAvatar}>
                  <Image srcValue={actor.image} />
                </div>
                <div className={styles.actorOthersInfo}>
                  <p className={styles.actorOthersName}>{actor.name}</p>
                  <p>{`第${
                    (actorList?.current_page || 1) *
                      (actorList?.per_page || 1) -
                    (actorList?.per_page || 1) +
                    index +
                    4
                  }名`}</p>
                </div>
              </Link>
            ))}
        </div>
        <Pagination
          active={actorList?.current_page || 1}
          size={actorList?.last_page || 1} // last_page
          total={actorList?.total || 1}
          step={u.isMobile() ? 1 : 3}
          limit={limit}
          onClickHandler={activeHandler}
        />
      </div>
    </div>
  );
};

export default Actor;
