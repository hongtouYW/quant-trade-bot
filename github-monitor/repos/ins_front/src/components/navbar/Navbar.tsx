import { Link, NavLink, useNavigate } from "react-router-dom";

import styles from "./Navbar.module.css";
import LogoMaker from "../logo-maker/LogoMaker";
import { useEffect, useState } from "react";
import {
  othersInsLink,
  productInfoLink,
  productInfoMobileLink,
  yellowProductInfoLink,
  yellowProductInfoMobileLink,
} from "../../utils/data";
import SearchBar from "../search-bar/SearchBar";
import u from "../../utils/utils";
// import { useUser } from "../../contexts/user.context";
import i18n from "../../utils/i18n";
import { useTranslation } from "react-i18next";
import { THEME_COLOR, TOKEN_NAME } from "../../utils/constant";
import Cookies from "universal-cookie";

const cookies = new Cookies();

const searchBarDropdown = [
  {
    label: "视频",
    locale: "video",
    value: "video",
  },
  {
    label: "主题",
    locale: "theme",
    value: "tag",
  },
  {
    label: "女优",
    locale: "pornstars",
    value: "actor",
  },
  {
    label: "片商",
    locale: "film",
    value: "publisher",
  },
];

const yellowSearchBarDropdown = [
  {
    label: "视频",
    locale: "video",
    value: "video",
  },
  {
    label: "类型",
    locale: "type",
    value: "tag",
  },
  {
    label: "作者",
    locale: "author",
    value: "author",
  },
];

const Navbar = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  // const { currentUser } = useUser();
  const [currentLang, setCurrentLang] = useState(
    cookies.get("curr_lang") || "zh"
  );
  const siteType = u.siteType();
  const token = cookies.get(TOKEN_NAME);

  const [openLogoDropdown, setOpenLogoDropdown] = useState(false);
  const [openProductDropdown, setOpenProductDropdown] = useState(false);

  const handleSearch = (formData: any) => {
    // e.preventDefault();

    // const formData = new FormData(e.currentTarget);
    const selectValue = formData?.selectValue;
    const inputValue = formData?.inputValue;

    if (selectValue === "video") {
      navigate(`${selectValue}/list?keyword=${inputValue}`);
    } else {
      navigate(`${selectValue}/index?keyword=${inputValue}`);
    }
  };

  const handleRefresh = () => {
    navigate("/");
    location.reload();
  };

  const handleChangeLanguage = (e: any) => {
    const language = e.target.value;
    i18n.changeLanguage(language);
    // localStorage.setItem("curr_lang", language);
    u.setCookies("curr_lang", language, 7, true);

    // 刷新页面
    location.reload();
  };

  useEffect(() => {
    // const lang = localStorage.getItem("curr_lang") || "zh";
    const lang = cookies.get("curr_lang") || "zh";

    if (lang) {
      setCurrentLang(lang);
      i18n.changeLanguage(lang);
    }
  }, []);

  return (
    <nav className={styles.navbar}>
      <div className={styles.navbarCard}>
        <div className={styles.navbarContainer}>
          <div
            className={styles.navbarLeft}
            onMouseLeave={() => {
              setOpenLogoDropdown(false);
              setOpenProductDropdown(false);
            }}
          >
            <div
              className={styles.navLogoContainer}
              onMouseLeave={() => setOpenLogoDropdown(false)}
            >
              <div
                className={styles.navLogo}
                onMouseEnter={() => setOpenLogoDropdown(true)}
              >
                {othersInsLink
                  .filter((val: any) => val.theme === siteType.theme)
                  .map((link: any, index: any) => {
                    return (
                      <NavLink to="/" key={index} onClick={handleRefresh}>
                        <LogoMaker
                          tagText={t(link.locale)}
                          color={link.theme}
                        />
                      </NavLink>
                    );
                  })}
                <div className={styles.navLogoIcon}>
                  {openLogoDropdown ? (
                    <img src="/chevron-up.svg" alt="" width={16} height={16} />
                  ) : (
                    <img
                      src="/chevron-down.svg"
                      alt=""
                      width={16}
                      height={16}
                    />
                  )}
                </div>
              </div>
              <div
                className={`${styles.navLogoDropdown} ${
                  openLogoDropdown
                    ? u.isMobile()
                      ? styles.hide
                      : styles.show
                    : styles.hide
                }`}
              >
                {othersInsLink
                  .filter((val: any) => val.theme !== siteType.theme)
                  .map((link: any, index: any) => {
                    const hostname = window.location.hostname.split(".");
                    const domain =
                      hostname.length > 1
                        ? hostname.slice(-2).join(".")
                        : hostname[0];
                    const linkHref = link.name
                      ? `${window.location.protocol}//${link.name}.${domain}`
                      : `${window.location.protocol}//${domain}`;

                    return (
                      <Link to={linkHref} key={index} target="_blank">
                        <LogoMaker
                          tagText={t(link.locale)}
                          color={link.theme}
                        />
                      </Link>
                    );
                  })}
              </div>
              <div className={styles.navLogoOthers}>
                {othersInsLink
                  .filter((val: any) => val.theme !== siteType.theme)
                  .map((link: any, index: any) => {
                    const hostname = window.location.hostname.split(".");
                    const domain =
                      hostname.length > 1
                        ? hostname.slice(-2).join(".")
                        : hostname[0];
                    const linkHref = link.name
                      ? `${window.location.protocol}//${link.name}.${domain}`
                      : `${window.location.protocol}//${domain}`;
                    return (
                      <Link to={linkHref} key={index} target="_blank">
                        {/* {link.text} */}
                        <p>{t(link.locale)}</p>
                      </Link>
                    );
                  })}
              </div>
            </div>
            <div
              className={styles.navProductContainer}
              onMouseEnter={() => setOpenProductDropdown(true)}
            >
              <div className={styles.navProduct}>
                <div className="navProductMaker">
                  <span className={styles.navProductText}>
                    {t("productInformation")}
                  </span>
                </div>
                <div className="navProductIcon">
                  {openProductDropdown ? (
                    <img src="/chevron-up.svg" alt="" width={16} height={16} />
                  ) : (
                    <img
                      src="/chevron-down.svg"
                      alt=""
                      width={16}
                      height={16}
                    />
                  )}
                </div>
              </div>
              <div
                className={`${styles.navProductDropdown} ${
                  openProductDropdown ? styles.show : styles.hide
                }`}
                onMouseLeave={() => setOpenProductDropdown(false)}
              >
                {(siteType.theme === THEME_COLOR.YELLOW
                  ? yellowProductInfoLink
                  : productInfoLink
                ).map((link: any, index: any) => (
                  <NavLink
                    to={link.path}
                    key={index}
                    className={() => {
                      return window.location.pathname +
                        window.location.search ===
                        link.path
                        ? `${styles.navProductDropdownActive}`
                        : "";
                    }}
                  >
                    {/* <p> {link.text}</p> */}
                    <p>{t(link.locale)}</p>
                  </NavLink>
                ))}
              </div>
            </div>
            <div className={styles.navReviewContainer}>
              {siteType.theme === THEME_COLOR.GREEN && (
                <Link to="/review/index">
                  <p>{t("movieReview")}</p>
                </Link>
              )}
            </div>
          </div>
          <div className={styles.navbarCenter}>
            <div className={styles.navbarSearchBar}>
              <SearchBar
                handleSearch={handleSearch}
                options={
                  siteType.theme === THEME_COLOR.YELLOW
                    ? yellowSearchBarDropdown
                    : searchBarDropdown
                }
              />
            </div>
          </div>
          <div className={styles.navbarRight}>
            <div className={styles.profile}>
              <NavLink to={`${token ? "/user/center" : "/user/login"}`}>
                <img src="/icon-user.png" alt="" width={25} height={25} />
              </NavLink>
              <div className={styles.bxLanguage}>
                <select
                  name="translate"
                  className="translate"
                  onChange={handleChangeLanguage}
                  defaultValue={currentLang}
                  style={{ width: i18n.language !== "ru" ? "45px" : "auto" }}
                >
                  <option value="en" id="en">
                    EN
                  </option>
                  <option value="zh" id="zh">
                    中文
                  </option>
                  <option value="ru" id="ru">
                    язык
                  </option>
                </select>
              </div>
            </div>
          </div>
        </div>
        <div className={styles.navContainerMobile}>
          <div className={styles.navbarSearchBarMobile}>
            <SearchBar
              handleSearch={handleSearch}
              options={
                siteType.theme === THEME_COLOR.YELLOW
                  ? yellowSearchBarDropdown
                  : searchBarDropdown
              }
            />
          </div>
          <div className={styles.menuListMobile}>
            {(siteType.theme === THEME_COLOR.YELLOW
              ? yellowProductInfoMobileLink
              : productInfoMobileLink
            ).map((link: any, index: any) => {
              if (link.show && link.show !== siteType.theme) {
                return;
              }
              return (
                <NavLink
                  key={index}
                  to={link.path}
                  className={() => {
                    const pathname =
                      window.location.pathname + window.location.search;
                    return link?.active?.includes(pathname)
                      ? `${styles.menuMobile} ${styles.menuActive}`
                      : styles.menuMobile;
                  }}
                >
                  {/* {link.text} */}
                  {t(link.locale)}
                </NavLink>
              );
            })}
          </div>
        </div>
      </div>
    </nav>
  );
};

export default Navbar;
