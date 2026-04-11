import { FC } from "react";
import loadingWhite from "../../../public/loading-white.svg";
import loadingBlack from "../../../public/loading-black.svg";

import styles from "./Loading.module.css";
interface ILoading {
  color?: "black" | "white";
  width?: number;
}

const Loading: FC<ILoading> = (props) => {
  return (
    <div
      className={styles.loadingContainer}
      style={{ width: `${props.width}px` }}
    >
      <img
        src={props.color === "white" ? loadingWhite : loadingBlack}
        alt="loading"
      />
    </div>
  );
};

export default Loading;
