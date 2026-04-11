import { FC } from "react";

import styles from "./Header.module.css";
import { Link } from "react-router-dom";

interface IHeader {
  title: string;
  className?: string;
}

const Header: FC<IHeader> = ({ title, className }) => {
  return (
    <div className={`${styles.headerContainer} ${className}`}>
      <Link to="/user/center" className={styles.headerBackBtn}>
        <img src="/icon-arrow-left.png" alt="" width={30} height={30} />
      </Link>
      <p className={styles.headerText}>{title}</p>
    </div>
  );
};

export default Header;
