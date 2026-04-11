import { useEffect, useState } from "react";
import useAxios from "../../hooks/useAxios";
import { PublisherListType, PublisherType } from "../../utils/type";

import styles from "./Publisher.module.css";
import { Link, useSearchParams } from "react-router-dom";
import Pagination from "../../components/pagination/Pagination";
import u from "../../utils/utils";
// import Loading from "../../components/loading/Loading";

const Publisher = () => {
  const { req } = useAxios("publisher/lists", "post");

  const [publisherList, setPublisherList] = useState<PublisherListType>();
  const [searchParams, setSearchParams] = useSearchParams();
  const keyword = searchParams.get("keyword");
  const limit = 60;

  // const [loading, setLoading] = useState(false);

  const handleGetPublisherList = async (pageNum: string = "1") => {
    // setLoading(true);
    try {
      const params: any = {
        page: pageNum,
        limit: limit,
      };

      if (keyword !== null && keyword !== "") {
        params["keyword"] = keyword;
      }

      const res = await req(params);
      const list = res?.data?.data || {};
      setPublisherList(list);
      // setLoading(false);
    } catch (err) {
      console.log(err);
      // setLoading(false);
    }
  };

  const activeHandler = (clickedActive: any) => {
    let num = parseInt(clickedActive);
    if (publisherList && num > publisherList?.last_page) {
      num = publisherList?.last_page;
    }
    // handleGetPublisherList(num);
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

    handleGetPublisherList(page);
  }, [searchParams]);

  useEffect(() => {
    window.scrollTo({
      top: 0,
    });
  }, [searchParams]);

  return (
    <div className={styles.publisherContainer}>
      {keyword && (
        <div className={styles.publisherKeyword}>
          <p>
            搜索关键字是 <span>"{keyword}"</span> 的主题结果如下：
          </p>
        </div>
      )}
      {/* {loading && (
        <div className={styles.loadingContainer}>
          <div className={styles.loading}>
            <Loading color="white" width={80} />
          </div>
        </div>
      )} */}
      <ul className={styles.publisherList}>
        {publisherList?.data?.map((publisher: PublisherType) => {
          const publisherName = encodeURIComponent(publisher?.name || "");
          return (
            <li className={styles.publisherItem} key={publisher.id}>
              <Link
                to={`/video/list?publisher_id=${publisher.id}&publisher_name=${publisherName}`}
              >
                <p>{publisher.name}</p>
              </Link>
            </li>
          );
        })}
      </ul>
      <Pagination
        active={publisherList?.current_page || 1}
        size={publisherList?.last_page || 1} // last_page
        total={publisherList?.total || 1}
        step={u.isMobile() ? 1 : 3}
        limit={limit}
        onClickHandler={activeHandler}
      />
    </div>
  );
};

export default Publisher;
