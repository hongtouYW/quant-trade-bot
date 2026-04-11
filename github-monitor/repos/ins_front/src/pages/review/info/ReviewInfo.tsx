import { Link, useSearchParams } from "react-router-dom";
import useAxios from "../../../hooks/useAxios";
import { ReviewInfoType } from "../../../utils/type";
import { useEffect, useState } from "react";
import { ToastContainer, toast, Slide } from "react-toastify";

import styles from "./ReviewInfo.module.css";
import Button from "../../../components/button/Button";
import TextArea from "../../../components/textarea/TextArea";
import { useUser } from "../../../contexts/user.context";
import { useTranslation } from "react-i18next";

import Cookies from "universal-cookie";
import { TOKEN_NAME } from "../../../utils/constant";

const cookies = new Cookies();

const ReviewInfo = () => {
  const { t } = useTranslation();
  const { currentUser } = useUser();
  const { req } = useAxios("review/info", "post");

  const [showShare, setShowShare] = useState(false);
  const [reviewInfo, setReviewInfo] = useState<ReviewInfoType>();

  const [searchParams] = useSearchParams();
  const id = searchParams.get("id");

  const handleGetReviewInfo = async () => {
    try {
      const params: any = {
        id: id || "",
      };

      const res = await req(params);
      const info = res?.data?.data || {};

      setReviewInfo(info);
    } catch (err) {
      console.log(err);
    }
  };

  const handleShareLink = () => {
    const token = cookies.get(TOKEN_NAME);

    if (!token) {
      toast.error(<p style={{ fontWeight: 900 }}>{t("shareAfterSignIn")}</p>);
      // navigate("/user/login");
      return;
    }

    setShowShare((prev) => !prev);
  };

  const handleCopyShareCode = (value: string) => {
    navigator.clipboard.writeText(value);
    toast.success(<p style={{ fontWeight: 900 }}>{t("copied")}</p>);
  };

  useEffect(() => {
    handleGetReviewInfo();
  }, [id]);

  return (
    <>
      <div className={styles.reviewInfoContaier}>
        <div className="reviewInfoHeader">
          <div className={styles.reviewInfoTitle}>
            <p>{reviewInfo?.title || "-"}</p>
          </div>
          <div className={styles.reviewInfoTag}>
            <Link to={`/video/list?actor_id=${reviewInfo?.aid}`}>
              #{reviewInfo?.actor}
            </Link>
            <Link to={`/video/info?id=${reviewInfo?.vid}`}>
              #{reviewInfo?.mash}
            </Link>
          </div>
        </div>
        <div className={styles.reviewInfoShare}>
          <Link to={`/video/info?id=${reviewInfo?.vid}`}>{t("watchThis")}</Link>
          <button onClick={handleShareLink}>{t("webpageShare")}</button>
        </div>
        {showShare && (
          <div className={styles.copyShare}>
            <div className={styles.copyShareField}>
              <TextArea
                label=""
                layout="vertical"
                name="share"
                type="textarea"
                placeholder=""
                rows={3}
                value={`${window.location.origin}/video/info/id/${
                  reviewInfo?.id
                }/code/${currentUser.code}   ${t("shareDesc")}${t("shareDesc1")} ${
                  currentUser.code
                }`}
              />
              <div className={styles.copyShareHint}>
                <span>{t("shareAndValidRegistration")}</span>

                <Button
                  title={t("copyShare")}
                  type="primary-gradient"
                  fontSize="small"
                  className={styles.copyShareBtn}
                  onClick={() =>
                    handleCopyShareCode(
                      `${window.location.origin}/video/info/id/${reviewInfo?.id}/code/${currentUser.code}   ${t("shareDesc")}${t("shareDesc1")} ${currentUser.code}`
                    )
                  }
                />
              </div>
            </div>
          </div>
        )}
        <div className="reviewInfoContent">
          <div
            className={styles.reviewInfoDesc}
            dangerouslySetInnerHTML={{
              __html: `${reviewInfo?.content}`,
            }}
          ></div>
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

export default ReviewInfo;
