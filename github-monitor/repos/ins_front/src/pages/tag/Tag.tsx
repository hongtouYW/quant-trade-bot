import { useEffect, useState } from "react";
import styles from "./Tag.module.css";
import { TagListType, TagType } from "../../utils/type";
import useAxios from "../../hooks/useAxios";
import Image from "../../components/Image/Image";
import { Link, useSearchParams } from "react-router-dom";
import { INITIALTAGLIST } from "../../utils/data";

const Tag = () => {
  const { req } = useAxios("tag/lists", "post");

  const [tagList, setTagList] = useState<TagListType>(INITIALTAGLIST);
  const [searchParams] = useSearchParams();
  const keyword = searchParams.get("keyword");

  const handleGetTagList = async (pageNum: number = 1) => {
    try {
      const params: any = {
        page: pageNum,
        limit: 120,
      };

      if (keyword !== null && keyword !== "") {
        params["keyword"] = keyword;
      }

      const res = await req(params);
      const list = res?.data?.data || {};
      setTagList(list);
    } catch (err) {}
  };

  useEffect(() => {
    handleGetTagList();
  }, [keyword]);

  return (
    <div className={styles.tagContainer}>
      {keyword && (
        <div className={styles.tagKeyword}>
          <p>
            搜索关键字是 <span>"{keyword}"</span> 的主题结果如下：
          </p>
        </div>
      )}
      <ul className={styles.tagList}>
        {tagList?.data?.map((tag: TagType) => {
          const tagName = encodeURIComponent(tag?.name || '');

          return (
            <li className={styles.tagItem} key={tag.id}>
              <Link to={`/video/list?tag_id=${tag.id}&tag_name=${tagName}`}>
                <Image className={styles.tagItemImg} srcValue={tag.image} layout="horizontal" />
                <div className={styles.tagItemName}>
                  <p>{tag.name}</p>
                </div>
              </Link>
            </li>
          );
        })}
      </ul>
    </div>
  );
};

export default Tag;
