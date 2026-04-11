import { Link, useSearchParams } from "react-router-dom";
import useAxios from "../../../hooks/useAxios";
import { useEffect, useState } from "react";
import { ReviewListType, ReviewType } from "../../../utils/type";

import styles from "./ReviewList.module.css";
import u from "../../../utils/utils";
import Pagination from "../../../components/pagination/Pagination";
import { useTranslation } from "react-i18next";
import Image from "../../../components/Image/Image";
const Review = () => {
  const { t } = useTranslation();
  const { req } = useAxios("review/lists", "post");
  const [searchParams, setSearchParams] = useSearchParams();

  const [reviewList, setReviewList] = useState<ReviewListType>();

  const limit = 10;

  const handleGetRreviewList = async (pageNum: string = "1") => {
    try {
      const params: any = {
        page: pageNum,
        limit: limit,
      };

      const res = await req(params);
      const list = res?.data?.data || {};
      // console.log("res", res);
      setReviewList(list);
    } catch (err) {
      console.log(err);
    }
  };

  const activeHandler = (clickedActive: any) => {
    let num = clickedActive
    ;
    if (reviewList && num > reviewList?.last_page) {
      num = reviewList?.last_page;
    }
    setSearchParams({ page: num });
  };
  useEffect(() => {
    const page = searchParams.get("page") || "1";

    handleGetRreviewList(page);
  }, [searchParams]);

  useEffect(() => {
    window.scrollTo({
      top: 0,
    });
  }, [searchParams]);

  return (
    <div className={styles.reviewContainer}>
      <div className={styles.reviewContainerHeader}>
        <p>{t("movieReview")}</p>
      </div>
      <div className="reviewContainerBody">
        <div className="reviewList">
          {reviewList?.data?.map((review: ReviewType) => {
            return (
              <div className={styles.reviewItem} key={review.id}>
                <div className={styles.reviewActorImg}>
                  <Link to={`/review/info?id=${review.id}`}>
                    {/* <img src={review?.thumb} alt="" /> */}
                    <Image srcValue={review?.thumb} alt="thumb" />
                  </Link>
                </div>
                <div className={styles.reviewActorContent}>
                  <div className={styles.reviewActorContentTitle}>
                    <Link to={`/review/info?id=${review.id}`}>
                      {review?.title}
                    </Link>
                  </div>
                  <div className={styles.reviewActorContentTag}>
                    <Link to={`/video/list?actor_id=${review.aid}`}>
                      #{review?.actor}
                    </Link>
                    <Link to={`/video/info?id=${review.vid}`}>
                      #{review?.mash}
                    </Link>
                  </div>
                  <Link to={`/review/info?id=${review.id}`}>
                    <div
                      className={styles.reviewActorContentDesc}
                      dangerouslySetInnerHTML={{
                        __html: `${review?.content?.substring(
                          0,
                          u.isMobile() ? 150 : 350
                        )}...`,
                      }}
                    >
                      {/* {`${review?.content
                      ?.replace(/(<p[^>]+?>|<p>|<\/p>)/gim, "")
                      .substring(0, 400)}...`} */}
                    </div>
                  </Link>
                </div>
              </div>
            );
          })}
        </div>
      </div>
      <Pagination
        active={reviewList?.current_page || 1}
        size={reviewList?.last_page || 1} // last_page
        total={reviewList?.total || 1}
        step={u.isMobile() ? 1 : 3}
        limit={limit}
        onClickHandler={activeHandler}
      />
    </div>
  );
};

export default Review;
