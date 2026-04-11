import Header from "../../components/header/Header";
import IdentifyCard from "../../../../components/identifyCard/IdentifyCard";

import styles from "./Identify.module.css";
import { useTranslation } from "react-i18next";
const Identify = () => {
  const { t } = useTranslation();
  return (
    <>
      <Header title={t("identifyCard")} />
      <div className={styles.identifyContainer}>
        <IdentifyCard />
      </div>
    </>
  );
};

export default Identify;
