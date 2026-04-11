import { userSideMenu } from "../../../../utils/data";
import { NavLink, useNavigate } from "react-router-dom";

import styles from "./Sidemenu.module.css";
import { useConfig } from "../../../../contexts/config.context";
import u from "../../../../utils/utils";
import { useUser } from "../../../../contexts/user.context";
import { useTranslation } from "react-i18next";
import { NAVIGATE_BOTTOM, NAVIGATE_CENTER } from "../../../../utils/constant";
// import Cookies from "universal-cookie";
// import { NAVIGATE_BOTTOM, NAVIGATE_CENTER } from "../../../../utils/constant";

// const cookies = new Cookies();

const UserSidemenu = () => {
  const { t } = useTranslation();
  const { configList } = useConfig();
  const { clearCurrentUser } = useUser();
  const navigate = useNavigate();

  const handleLogout = () => {
    u.removeTokens();
    clearCurrentUser();
    localStorage.removeItem(NAVIGATE_CENTER);
    localStorage.removeItem(NAVIGATE_BOTTOM);
    navigate("/");
  };

  return (
    <div className={styles.userSideMenuContainer}>
      {userSideMenu?.map((menu: any) => {
        if (menu.id === 11) {
          return (
            <a
              onClick={handleLogout}
              key={menu.id}
              className={styles.userSideMenuLink}
            >
              <div className={styles.userSideMenuLinkContent}>
                <img src={menu?.img} alt="" width={30} height={30} />
                {/* <p>{menu.text}</p> */}
                <p>{t(menu.locale)}</p>
              </div>
              <div className={styles.userSideMenuLinkArrow}>
                <img src="/icon_more.png" alt="" width={18} height={18} />
              </div>
            </a>
          );
        }
        return (
          <NavLink
            to={menu.path ? menu.path : configList?.server_link || "#"}
            key={menu.id}
            target={menu?.targetBlankPath ? "_blank" : "_self"}
            className={({ isActive }) =>
              isActive
                ? `${styles.userSideMenuLink} ${styles.userSideMenuLinkActive}`
                : styles.userSideMenuLink
            }
          >
            <div className={styles.userSideMenuLinkContent}>
              <div className={styles.userSideMenuLinkContentImg}>
                <img src={menu?.img} alt="img" />
              </div>
              {/* <p>{menu.text}</p> */}
              <p>{t(menu.locale)}</p>
            </div>
            <div className={styles.userSideMenuLinkArrow}>
              <img src="/icon_more.png" alt="" width={18} height={18} />
            </div>
          </NavLink>
        );
      })}
    </div>
  );
};

export default UserSidemenu;
