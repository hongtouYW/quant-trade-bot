import { useEffect, useRef, useState } from "react";
import useAxios from "../../../../hooks/useAxios";
import Header from "../../components/header/Header";

import Cookies from "universal-cookie";

import styles from "./Collect.module.css";
import { THEME_COLOR, TOKEN_NAME } from "../../../../utils/constant";
import { CollectListType, VideoType } from "../../../../utils/type";
import VideoCard from "../../../../components/video-card/VideoCard";
import Button from "../../../../components/button/Button";
import Pagination from "../../../../components/pagination/Pagination";
import u from "../../../../utils/utils";
import { useTranslation } from "react-i18next";

const cookies = new Cookies();

const Collect = () => {
  const { t } = useTranslation();
  const { req } = useAxios("video/myCollect", "post");
  const { req: reqClearCollect } = useAxios("/video/clearCollect", "post");
  const checkboxListRef: any = useRef<HTMLUListElement>(null);
  const checkboxCheckAllRef: any = useRef<HTMLInputElement>(null);
  const siteType = u.siteType();

  const limit = 15;
  const [collectList, setCollectList] = useState<CollectListType>();
  const [isEditMode, setIsEditMode] = useState(false);
  const [deleteActorIds, setDeleteActorIds] = useState<Array<string>>([]);

  const handleDelete = async () => {
    // if (deleteActorIds?.length === collectList?.data.length) {
    //   console.log("all");
    // }

    if (deleteActorIds && deleteActorIds.length > 0) {
      const joinDeleteActorIds = deleteActorIds.join(",");

      try {
        const token = cookies.get(TOKEN_NAME);

        const params = {
          token,
          vid: joinDeleteActorIds,
        };

        const res = await reqClearCollect(params);
        // console.log("res-clear", res);

        if (res.data.code === 1) {
          setIsEditMode(false);
          handleGetMyCollectList();
          // const list = res?.data?.data || {};

          // setCollectList(list);
        }
      } catch (err) {
        console.log(err);
      }
    }
  };

  const handleCheckClick = (e: any, id: any) => {
    const checkedValue = e.target.checked;
    if (checkedValue) {
      setDeleteActorIds((prev) => [...prev, id]);
    } else {
      setDeleteActorIds((prev) => {
        const filter = prev.filter((val: any) => val !== id);

        return [...filter];
      });
    }
  };

  const handleCheckAllClick = (e: any) => {
    const checkedValue = e.target.checked;

    if (
      checkboxListRef.current &&
      checkboxListRef.current.children.length > 0
    ) {
      const actorCheckboxList = checkboxListRef.current.children;

      // If Check Select All, tick all
      if (checkedValue) {
        Object.keys(actorCheckboxList).forEach((key: any) => {
          actorCheckboxList[key].children["collectVideoLabel"].children[
            "actorId"
          ].checked = true;
        });
      } else {
        Object.keys(actorCheckboxList).forEach((key: any) => {
          actorCheckboxList[key].children["collectVideoLabel"].children[
            "actorId"
          ].checked = false;
        });
      }

      // Get all delete Ids
      const deleteIds: Array<string> = [];
      Object.keys(actorCheckboxList).forEach((key: any) => {
        const isChecked =
          actorCheckboxList[key].children["collectVideoLabel"].children[
            "actorId"
          ].checked;
        if (isChecked) {
          const valueId =
            actorCheckboxList[key].children["collectVideoLabel"].children[
              "actorId"
            ].getAttribute("data-id");
          deleteIds.push(valueId);
        }
      });
      setDeleteActorIds(deleteIds);
    }
  };

  const handleGetMyCollectList = async (pageNum: number = 1) => {
    try {
      const token = cookies.get(TOKEN_NAME);

      const params = {
        token,
        page: pageNum,
        limit: limit,
      };

      const res = await req(params);
      // console.log("res", res);

      if (res.data.code === 1) {
        const list = res?.data?.data || {};

        setCollectList(list);
      }
    } catch (err) {
      console.log(err);
    }
  };

  const activeHandler = (clickedActive: any) => {
    let num = parseInt(clickedActive);
    if (collectList && num > collectList?.last_page) {
      num = collectList?.last_page;
    }
    handleGetMyCollectList(num);
  };

  useEffect(() => {
    handleGetMyCollectList();
  }, []);

  return (
    <div className={styles.collectSection}>
      <Header title={t("favoritesList")} />
      <div
        className={styles.collectEdit}
        onClick={() => setIsEditMode((prev) => !prev)}
      >
        <p>{isEditMode ? `${t("cancel")}` : `${t("edit")}`}</p>
      </div>
      <div className={styles.collectContainer}>
        <ul className={styles.collectVideoList} ref={checkboxListRef}>
          {collectList?.data?.map((val: VideoType) => (
            <li
              key={val.id}
              className={`${
                siteType.theme === THEME_COLOR.GREEN
                  ? styles.collectVideoItemLi
                  : styles.collectOtherVideoItemLi
              }`}
            >
              {isEditMode && (
                <label
                  className={styles.collectVideoLabel}
                  id="collectVideoLabel"
                >
                  <input
                    type="checkbox"
                    name="actorId"
                    data-id={val.id}
                    onClick={(e) => handleCheckClick(e, val.id.toString())}
                  />
                  <span className={styles.checkboxContainer}></span>
                </label>
              )}
              <VideoCard
                // key={val.id}
                id={val.id}
                title={val.title || ""}
                actor={val.actor}
                play={val.play || "0"}
                collectCount={val.collect_count}
                thumb={val.thumb}
                preview={val.preview}
                subtitle={val.subtitle}
                vip={val.private}
                vertical={true}
                className={styles.collectVideoItem}
                size="extra-small"
              />
            </li>
          ))}
        </ul>
      </div>
      <Pagination
        active={collectList?.current_page || 1}
        size={collectList?.last_page || 1} // last_page
        total={collectList?.total || 1}
        step={u.isMobile() ? 1 : 3}
        limit={limit}
        onClickHandler={activeHandler}
        className={isEditMode ? styles.paginationCollectContainer : ""}
      />
      {collectList && collectList?.data?.length > 0 && isEditMode && (
        <div className={styles.collectFooter}>
          <div className={styles.collectFooterDelete}>
            <div className="selectAll">
              <label className={styles.collectVideoLabelFooter}>
                <input
                  type="checkbox"
                  name="selectAll"
                  ref={checkboxCheckAllRef}
                  onClick={(e) => handleCheckAllClick(e)}
                />
                <span className={styles.collectVideoLabelSpan}>
                  {t("selectAll")}
                </span>
                <span className={styles.checkboxContainer}></span>
              </label>
            </div>
            <div className={styles.deleteBtn}>
              <Button
                title={t("delete")}
                type="primary-gradient"
                fontSize="small"
                onClick={handleDelete}
                disabled={
                  deleteActorIds && deleteActorIds.length > 0 ? false : true
                }
              />
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default Collect;
