import { useRef } from "react";
import QRCode from "react-qr-code";

import { useConfig } from "../../contexts/config.context";
import { useUser } from "../../contexts/user.context";
import html2canvas from "html2canvas";
import u from "../../utils/utils";

import styles from "./IdentifyCard.module.css";
import Button from "../button/Button";
import { useTranslation } from "react-i18next";
import { THEME_COLOR } from "../../utils/constant";

const IdentifyCard = () => {
  const { t, i18n } = useTranslation();
  const { configList } = useConfig();
  const { currentUser } = useUser();
  const siteType = u.siteType();

  const ToCaptureRef: any = useRef();

  const handleCapture = () => {
    const canvasPromise = html2canvas(ToCaptureRef.current, {
      useCORS: true, // in case you have images stored in your application
      backgroundColor: null,
    });

    canvasPromise.then((canvas) => {
      u.saveAsImg(
        canvas.toDataURL("image/png"),
        `${t("identifyCard")}_${currentUser?.username}.png`
      );
    });
  };

  const themeTitle = () => {
    switch (siteType.theme) {
      case THEME_COLOR.GREEN:
        return t("censored");
      case THEME_COLOR.PURPLE:
        return t("uncensored");
      case THEME_COLOR.YELLOW:
        return t("anime");
      case THEME_COLOR.BLUE:
        return t("4k");
      default:
        return t("censored");
    }
  };

  return (
    <>
      <div className="identifyCardContainer" ref={ToCaptureRef}>
        {siteType.theme && (
          <img
            src={`/${siteType.theme}/identify-card.png`}
            className={`${styles.identifyBackgroundImg} identifyCardBackgroundImg`}
            style={{
              height:
                i18n.language === "zh"
                  ? "auto"
                  : i18n.language === "ru"
                  ? (u.isMobile()
                    ? "660px"
                    : "670px")
                  : (u.isMobile()
                  ? "620px"
                  : "640px"),
            }}
          />
        )}
        <div className={styles.identifyContent}>
          <div
            className={styles.identifyHeader}
            style={{
              marginBottom:
                i18n.language === "zh"
                  ? "38px"
                  : i18n.language === "ru"
                  ? "40px"
                  : "35px",
            }}
          >
            <div className={styles.identifyHeaderLogo}>
              <img src="/logo-white.png" className="headerLogo" />
            </div>
            <div className={styles.identifyHeaderText}>
              <span className={styles.headerLogoTag}>{themeTitle()}</span>
            </div>
          </div>
          <div className={styles.identifyBody}>
            <div className={styles.identifyBodyTitle}>
              <p className={styles.bodyTitleAddress}>
                {`${t("overseasAddress")}: ${configList?.life_domain}`}
              </p>
              <p className={styles.bodyTitleCertificate}>
                -{t("preventLossCredentials")}-
              </p>
              <p className={styles.bodyTitleIdentifyCard}>{`INSAV ${t(
                "identifyCard"
              )}`}</p>
            </div>
            <div
              className={styles.identifyBodyQrcode}
              style={{
                backgroundImage: `url("/${siteType.theme}/qrcode-bg.png")`,
              }}
            >
              <div className={styles.identifyBodyQrcodeImg}>
                <QRCode
                  size={256}
                  style={{ height: "auto", maxWidth: "100%", width: "100%" }}
                  value={`${window.location.origin}/auth_login?username=${currentUser?.username}&password=${currentUser?.ori_password}`}
                  viewBox={`0 0 256 256`}
                />
              </div>
            </div>
            <div className="identifyBodyPersonalInfo">
              <div className={styles.personalLoginInfo}>
                <div className="username">{`${t("username")}: ${
                  currentUser?.username
                }`}</div>
                <div className="password">{`${t("password")}: ${
                  currentUser?.ori_password
                }`}</div>
              </div>
              <div className={styles.personalAddressInfo}>
                <span className="address">{`${t("homeAddress")}: ${
                  window.location.origin
                }`}</span>
              </div>
            </div>
          </div>
          <div
            className={styles.identifyFooter}
            style={{
              backgroundImage: `url("/${siteType.theme}/background-light.png")`,
            }}
          >
            <div className={styles.identifyFooterHint}>
              <div className={styles.identifyFooterHintInstructions}>
                <p className={styles.instructionsTitle}>{`${t(
                  "instructions"
                )}:`}</p>
                <p className={styles.instructionsContent}>
                  {t("identifyCardInstructions")}
                </p>
              </div>
              <div className="identifyFooterHintTips">
                <p className={styles.tipsTitle}>{`${t("kindTips")}:`}</p>
                <p className={styles.tipsContent}>{t("kindTipsDesc")}</p>
              </div>
              <div className={styles.identifyFooterHintWarning}>
                <span>{`*${t("screenshotHints")}`}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div className={styles.identifyScreenshotButton}>
        <Button
          title={t("saveScreenshot")}
          fontSize="large"
          className={styles.identifyBtn}
          onClick={handleCapture}
        />
      </div>
    </>
  );
};

export default IdentifyCard;
