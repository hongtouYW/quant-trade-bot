import { useEffect, useState } from "react";
import useAxios from "../../../../hooks/useAxios";
import Header from "../../components/header/Header";

import styles from "./Notice.module.css";
import { NoticeType } from "../../../../utils/type";
import { useTranslation } from "react-i18next";

const Notice = () => {
  const { t } = useTranslation();
  const { req } = useAxios("notice/lists", "post");

  const [noticeList, setNoticeList] = useState<Array<NoticeType>>([]);

  const handleGetNoticeList = async () => {
    try {
      const res = await req();

      setNoticeList(res?.data?.data || []);
    } catch (err) {}
  };

  useEffect(() => {
    handleGetNoticeList();
  }, []);

  return (
    <>
      <Header title={t("announcement")} />
      <div className={styles.noticeContainer}>
        <div className={styles.noticeList}>
          {noticeList?.map((notice: NoticeType, index: any) => {
            return (
              <div className={styles.noticeItem} key={index}>
                <div className={styles.noticeTitle}>{notice?.title}</div>
                <div className={styles.noticeContent}>
                  <div className={styles.noticeContentDesc}>
                    <div
                      dangerouslySetInnerHTML={{ __html: `${notice?.content}` }}
                    ></div>
                  </div>
                </div>
                {/* <div className={styles.noticeContentDate}>2024-07-15 00:00:00</div> */}
              </div>
            );
          })}
        </div>
      </div>
    </>
  );
};

export default Notice;
