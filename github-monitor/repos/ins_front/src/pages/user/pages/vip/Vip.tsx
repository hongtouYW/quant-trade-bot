import { useEffect, useState } from "react";
import Button from "../../../../components/button/Button";
import useAxios from "../../../../hooks/useAxios";
import Header from "../../components/header/Header";

import styles from "./Vip.module.css";
import { PlatformType, VipPackageType } from "../../../../utils/type";
import Modal from "../../../../components/modal/Modal";
import Cookies from "universal-cookie";
import { TOKEN_NAME } from "../../../../utils/constant";
import { useModal } from "../../../../contexts/modal.context";
import { useTranslation } from "react-i18next";
import { ToastContainer, toast, Slide } from "react-toastify";
import u from "../../../../utils/utils";
import { NavLink, useNavigate } from "react-router-dom";

const cookies = new Cookies();

const Vip = () => {
  const { t } = useTranslation();
  const modal = useModal();
  const siteType = u.siteType();
  const navigate = useNavigate();

  const { req } = useAxios("vip/lists", "post");
  const { req: platformsListReq } = useAxios("vip/platforms", "post");
  const { req: buyVipReq } = useAxios("vip/buy", "post");
  // const platformDialogRef = useRef<HTMLDialogElement | null>(null);
  // const notifyPaymentDialogRef = useRef<HTMLDialogElement | null>(null);
  const [vipPackageList, setVipPackageList] = useState([]);
  const [platformList, setPlatformList] = useState([]);
  const [loadingPurchase, setLoadingPurchase] = useState(false);
  const [selectedVipPackage, setSelectedVipPackage] =
    useState<VipPackageType>();

  const [paymentUrl, setPaymentUrl] = useState("");
  const [isOpenNotifyPaymentDialog, setIsOpenNotifyPaymentDialog] =
    useState(false);
  const [isOpenPlatformDialog, setIsOpenPlatformDialog] = useState(false);

  const handleGetVipPackageList = async () => {
    try {
      const res = await req();
      const list = res?.data?.data || [];

      setVipPackageList(list);
    } catch (err) {
      console.log(err);
    }
  };

  const handleGetPlatforms = async (vipPackage: VipPackageType) => {
    setSelectedVipPackage(vipPackage);

    try {
      const res = await platformsListReq({ vid: vipPackage.id });
      const list = res?.data?.data || [];
      // console.log("list", list);
      setPlatformList(list);
      // platformDialogRef.current?.showModal();
      setIsOpenPlatformDialog(true);
    } catch (err) {
      console.log(err);
    }
  };

  const handleBuyVip = async (platform: PlatformType) => {
    setLoadingPurchase(true);
    try {
      const token = cookies.get(TOKEN_NAME);

      const params = {
        token,
        vid: selectedVipPackage?.id,
        pid: platform.id,
      };

      const res = await buyVipReq(params);
      const url = res?.data?.data?.pay_url;

      if (res.data.code === 1 && url) {
        setPaymentUrl(url);
        setLoadingPurchase(false);
        setTimeout(function () {
          window.open(url, "_blank");
        }, 100);
        closeDialog();
        setIsOpenNotifyPaymentDialog(true);
        // notifyPaymentDialogRef.current?.showModal();

        // return modal.info({
        //   title: `${t("information")}`,
        //   content: `${t("paymentCompletedDesc")}`,
        //   confirmFn: () => navigate(`/user/center`),
        //   confirmText: `${t("confirm")}`,
        // });
      } else {
        setLoadingPurchase(false);
        toast.error(res?.data?.msg);
        closeDialog();
      }
    } catch (err) {
      setLoadingPurchase(false);
      console.log(err);
    }
  };

  const handleClosePaymentNotification = () => {
    setIsOpenNotifyPaymentDialog(false);
    return modal.info({
      title: `${t("information")}`,
      content: `${t("paymentCompletedDesc")}`,
      confirmFn: () => navigate(`/user/center`),
      confirmText: `${t("confirm")}`,
    });
    // if (notifyPaymentDialogRef.current) {
    //   notifyPaymentDialogRef.current.close();
    //   return modal.info({
    //     title: `${t("information")}`,
    //     content: `${t("paymentCompletedDesc")}`,
    //     confirmFn: () => navigate(`/user/center`),
    //     confirmText: `${t("confirm")}`,
    //   });
    // }
  };

  const closeDialog = () => {
    setIsOpenPlatformDialog(false);
    // if (platformDialogRef.current) {
    //   platformDialogRef.current.close();
    //   // return modal.info({
    //   //   title: `${t("information")}`,
    //   //   content: `${t("paymentCompletedDesc")}`,
    //   //   confirmFn: () => navigate(`/user/center`),
    //   //   confirmText: `${t("confirm")}`,
    //   // });
    // }
  };

  useEffect(() => {
    handleGetVipPackageList();
  }, []);

  return (
    <>
      <Header title={t("purchaseVIPList")} />
      <div className={styles.vipListContainer}>
        <div className={styles.vipList}>
          {vipPackageList.map((vipPackage: VipPackageType) => {
            return (
              <div
                className={styles.vipItem}
                style={{
                  backgroundImage:
                    vipPackage.is_sale === 1
                      ? `url("/${siteType.theme}/price-sales_bg.png")`
                      : `url("/${siteType.theme}/price_bg.png")`,
                }}
                key={vipPackage.id}
              >
                <div className={styles.vipItemHeader}>
                  <div className={styles.vipItemHeaderPrice}>
                    {vipPackage.is_sale === 1 && (
                      <p className={styles.vipItemHeaderPromo}>
                        {t("specialOffer")}
                      </p>
                    )}
                    <p className={styles.vipItemHeaderDiscountPrice}>
                      ￥{vipPackage.money}
                    </p>
                    {vipPackage.is_sale === 1 && (
                      <p className={styles.vipItemHeaderOriPrice}>{`(${t(
                        "originalPrice"
                      )}: ￥${vipPackage.cost})`}</p>
                    )}
                  </div>
                </div>
                <div className="vipItemBody">
                  <div className={styles.vipItemContent}>
                    <p className={styles.vipItemTitle}>{vipPackage.title}</p>
                    <p className={styles.vipItemDesc}>{vipPackage.des}</p>
                  </div>
                </div>
                <div className={styles.vipItemFooter}>
                  <Button
                    title={t("buyNow")}
                    type="light"
                    onClick={() => handleGetPlatforms(vipPackage)}
                  />
                </div>
              </div>
            );
          })}
        </div>
      </div>
      {isOpenPlatformDialog && (
        <Modal type="custom">
          <div
            // ref={platformDialogRef}
            className={`${styles.platformDialog} ${
              isOpenPlatformDialog === true ? "show" : "hide"
            }`}
            // open={isOpenPlatformDialog}
          >
            <div className={styles.platformContainer}>
              {loadingPurchase && (
                <div className={styles.platformLoading}>
                  <img src="/loading.gif" alt="loading" />
                </div>
              )}
              <span
                className={styles.closePlatformDialog}
                onClick={closeDialog}
              >
                &times;
              </span>
              <div className={styles.platformList}>
                {platformList.map((platform: PlatformType) => {
                  return (
                    <div
                      className={styles.platformItem}
                      onClick={() => handleBuyVip(platform)}
                      key={platform.id}
                    >
                      <div className={styles.platformImg}>
                        <img
                          src={platform?.type === 1 ? " /zfb.png" : "/wx.png"}
                          width={100}
                        />
                      </div>
                      <div className={styles.platformInfo}>
                        <p>
                          <span className={styles.platformInfoSmallText}>
                            {t("use")}
                            {platform?.name}
                          </span>
                          - {t("pay")} {selectedVipPackage?.money}
                          {t("yuan")}(RMB)
                        </p>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          </div>
        </Modal>
      )}
      {isOpenNotifyPaymentDialog && (
        <Modal type="custom">
          <div
            // ref={notifyPaymentDialogRef}
            className={`${styles.notifyPaymentDialog} ${
              isOpenNotifyPaymentDialog === true ? "show" : "hide"
            }`}
            // open={isOpenNotifyPaymentDialog}
          >
            <div className={styles.notifyPaymentContainer}>
              <div className={styles.notifyPaymentTitle}>
                <p>{t("reminder")}</p>
              </div>
              <div className={styles.notifyPaymentContent}>
                <ul>
                  <li>{t("reminderPoint1")}</li>
                  <li>{t("reminderPoint2")}</li>
                </ul>
              </div>
              <div className={styles.notifyPaymentBtn}>
                <NavLink to={paymentUrl} target="_blank">
                  {t("proceedPayment")}
                </NavLink>
              </div>
              <div
                className={styles.cancelPaymentBtn}
                onClick={handleClosePaymentNotification}
              >
                {t("gotIt")}
              </div>
            </div>
          </div>
        </Modal>
      )}
      <ToastContainer
        position="top-center"
        theme="colored"
        autoClose={3000}
        hideProgressBar={true}
        pauseOnHover
        className="toast_notification"
        transition={Slide}
        closeButton={false}
      />
    </>
  );
};

export default Vip;
