import { Link, useNavigate } from "react-router-dom";
import Button from "../../../../components/button/Button";
import Header from "../../components/header/Header";

import styles from "./Center.module.css";
import { useUser } from "../../../../contexts/user.context";
import u from "../../../../utils/utils";
import { useEffect } from "react";
import Cookies from "universal-cookie";
import { TOKEN_NAME } from "../../../../utils/constant";
import UserSidemenu from "../../components/sidemenu/Sidemenu";
import { useTranslation } from "react-i18next";

const cookies = new Cookies();

const Center = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { currentUser, userVip } = useUser();
  const siteType = u.siteType();

  const handleNavigate = () => {
    navigate("/user/vip");
  };
  useEffect(() => {
    const token = cookies.get(TOKEN_NAME);

    if (!token) {
      navigate("/user/login");
    }
  }, []);

  return (
    <div>
      <Header title={t("account")} className={styles.centerContainerHeader} />
      <div className={styles.centerContainer}>
        <div className={styles.personalInfoContainer}>
          <div className="avatar">
            <img src="/icon-user.png" alt="" width={50} height={50} />
          </div>
          <div className="personalInfo">
            <div className={styles.username}>
              {currentUser?.username || "-"}
            </div>
            <div className={styles.editInfo}>
              <span>
                <Link to="/user/edit">
                  {currentUser?.signature || `${t("editProfile")}`}
                </Link>
              </span>
              <img
                src="/icon_file-text.png"
                alt="edit"
                width={15}
                height={15}
              />
            </div>
          </div>
        </div>
        <div
          className={styles.becomeVipContainer}
          style={{ backgroundImage: `url("/${siteType.theme}/bg-vip.png")` }}
        >
          <div className={styles.becomeVipBody}>
            <p>
              {userVip.vip === 1
                ? `${t("membershipExpiredTime")}:${u.timestampToDate(
                    userVip.vipTime || ""
                  )}`
                : `${t("becomeVIP")}`}
            </p>
            <span>
              {userVip.vip === 1
                ? `${t("unlimitedViewDesc")}`
                : `${t("becomeVIPDesc")}`}
            </span>
          </div>
          <div className={styles.becomeVipButton}>
            <Button
              title={
                userVip.vip === 1
                  ? `${t("continuePurchase")}`
                  : `${t("joinVIP")}`
              }
              fontSize="small"
              type="light"
              onClick={handleNavigate}
            />
          </div>
        </div>
      </div>
      <div className={styles.centerUserMenu}>
        <UserSidemenu />
      </div>
    </div>
  );
};

export default Center;
