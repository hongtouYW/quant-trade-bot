import { useEffect, useState } from "react";
import styles from "./FloatButton.module.css";
import u from "../../utils/utils";

const FloatButton = () => {
  const siteType = u.siteType();

  const [isScroll, setIsScroll] = useState(0);

  const onScroll = () => {
    setIsScroll(window.pageYOffset);
  };

  const scrollToTop = () => {
    window.scroll({ top: 0, left: 0, behavior: "smooth" });
  };

  useEffect(() => {
    window.addEventListener("scroll", onScroll);
    return () => {
      window.removeEventListener("scroll", onScroll);
    };
  }, []);

  return (
    isScroll > 500 && (
      <div className={styles.floatButtonContainer} onClick={scrollToTop}>
        <div className="floatButton">
          <img
            src={`/${siteType.theme}/btn_pagetop.png`}
            alt=""
            width={50}
            height={50}
          />
        </div>
      </div>
    )
  );
};

export default FloatButton;
