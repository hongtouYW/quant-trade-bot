import { useTranslation } from "react-i18next";
import styles from "./Footer.module.css";

const Footer = () => {
  const { t } = useTranslation();
  return (
    <div className={styles.footerContainer}>
      <p>{t('copyright')} © 2018-{new Date().getFullYear()} {t('allRightsReserved')}.</p>
    </div>
  );
};

export default Footer;
