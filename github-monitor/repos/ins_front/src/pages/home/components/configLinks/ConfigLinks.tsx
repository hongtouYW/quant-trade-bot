import { useEffect, useState } from "react";
import useAxios from "../../../../hooks/useAxios";

import styles from "./ConfigLinks.module.css";
import { LinkType } from "../../../../utils/type";
import { Link } from "react-router-dom";
import { useTranslation } from "react-i18next";
import Image from "../../../../components/Image/Image";
// import u from "../../../../utils/utils";

const ConfigLinks = () => {
  const { i18n } = useTranslation();
  // const siteType = u.siteType();
  const { req: linksRequest } = useAxios("config/links", "post");

  const [links, setLinks] = useState([]);

  const handleGetLinks = async () => {
    try {
      const res = await linksRequest();
      const list = res?.data?.data || [];

      setLinks(list);
    } catch (err) {
      console.log(err);
    }
  };

  useEffect(() => {
    handleGetLinks();
  }, []);

  return (
    <div>
      <p className={styles.friendlyRecommendationLabel}>
        {i18n.t("friendlyRecommendation")}
      </p>
      <div className={styles.friendlyRecommendation}>
        {/* <div className={styles.friendlyRecommendationImg}>
        {siteType.theme && (
          <img
            src={`/${siteType.theme}/friendly-${i18n.language || "en"}.png`}
            alt=""
          />
        )}
      </div> */}
        {/* <div className={styles.friendlyArrowLeft}>&#x2b05;</div> */}

        <div className={styles.friendlyRecommendationList}>
          {links.map((link: LinkType) => (
            <Link
              to={link.url || "#"}
              className={styles.friendlyRecommendationItem}
              key={link.id}
              target="_blank"
            >
              <div className={styles.friendlyRecommendationItemImg}>
                <Image srcValue={link.image} alt={link.title} />
                {/* <img src={link.image} alt={link.title} /> */}
              </div>
              <p>{link.title}</p>
            </Link>
          ))}
        </div>
        {/* <div className={styles.friendlyArrowRight}>&#x2b05;</div> */}
      </div>
    </div>
  );
};

export default ConfigLinks;
