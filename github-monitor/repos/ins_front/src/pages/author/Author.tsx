import { Link, useSearchParams } from "react-router-dom";
import styles from "./Author.module.css";
import { useEffect, useState } from "react";
import useAxios from "../../hooks/useAxios";
import { AuthorListType, AuthorType } from "../../utils/type";
import Pagination from "../../components/pagination/Pagination";
import u from "../../utils/utils";
import Image from "../../components/Image/Image";

const Author = () => {
  const { req } = useAxios("actor/lists", "post");

  const [authorList, setAuthorList] = useState<AuthorListType>();
  const [searchParams] = useSearchParams();
  const keyword = searchParams.get("keyword");
  const limit = 30;

  const handleGetAuthorList = async (pageNum: number = 1) => {
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
      setAuthorList(list);
    } catch (err) {}
  };

  const activeHandler = (clickedActive: any) => {
    let num = parseInt(clickedActive);
    if (authorList && num > authorList?.last_page) {
      num = authorList?.last_page;
    }
    handleGetAuthorList(num);
  };

  useEffect(() => {
    handleGetAuthorList();
  }, [keyword]);

  return (
    <div className={styles.authorListContainer}>
      {keyword && (
        <div className={styles.authorListKeyword}>
          <p>
            搜索关键字是 <span>"{keyword}"</span> 的主题结果如下：
          </p>
        </div>
      )}
      <ul className={styles.authorList}>
        {authorList?.data?.map((author: AuthorType) => (
          <li className={styles.authorItem} key={author.id}>
            <Link
              to={`/video/list?actor_id=${author.id}`}
              className={styles.authorItemLink}
            >
              <div className={styles.authorItemImg}>
                {/* <img src={author.image} /> */}
                <Image srcValue={author.image} alt="author" />
              </div>
              <p>{author.name}</p>
            </Link>
          </li>
        ))}
      </ul>

      <Pagination
        active={authorList?.current_page || 1}
        size={authorList?.last_page || 1} // last_page
        total={authorList?.total || 1}
        step={u.isMobile() ? 1 : 3}
        limit={limit}
        onClickHandler={activeHandler}
      />
    </div>
  );
};

export default Author;
