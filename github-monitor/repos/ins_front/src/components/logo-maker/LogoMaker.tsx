import { FC } from "react";
import styles from "./LogoMaker.module.css";
import u from "../../utils/utils";

interface ILogoMaker {
  color: "green" | "purple" | "orange" | "blue";
  tagText: string;
}

enum logoImageEnum {
  "green" = "/green/logo-mobile.png",
  "purple" = "/purple/logo-mobile.png",
  "yellow" = "/yellow/logo-mobile.png",
  "blue" = "/blue/logo-mobile.png",
}

enum colorEnum {
  "green" = "#a0f621",
  "purple" = "#6949FF",
  "yellow" = "#f3d07e",
  "blue" = "#1ec6f6",
}

enum tagColornum {
  "green" = "#000",
  "purple" = "#fff",
  "yellow" = "#000",
  "blue" = "#fff",
}

enum tagBackgroundEnum {
  "green" = "linear-gradient(106.42deg, #a0f621 37.32%, #49f3c0 85.5%)",
  "purple" = "linear-gradient(309deg, #6949FF 37.32%, #D821F6 85.5%)",
  "yellow" = "linear-gradient(106.42deg, #f3d07e 37.32%, #f0e6be 85.5%)",
  "blue" = "linear-gradient(106.42deg, #1ec6f6 37.32%, #1c91e2 85.5%)",
}

const LogoMaker: FC<ILogoMaker> = ({ tagText, color }) => {
  return (
    <>
      <div className={styles.navLogoMaker}>
        {u.isMobile() ? (
          <img src={color && (logoImageEnum as any)[color]} alt="" width={60} />
        ) : (
          <span
            className={styles.navLogoText}
            style={{
              color: color && (colorEnum as any)[color],
            }}
          >
            INS AV
          </span>
        )}
        <span
          className={styles.navLogoTag}
          style={{
            color: color && (tagColornum as any)[color],
            background: color && (tagBackgroundEnum as any)[color],
          }}
        >
          {tagText}
        </span>
      </div>
    </>
  );
};

export default LogoMaker;
