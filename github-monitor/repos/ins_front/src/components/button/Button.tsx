import React, { FC } from "react";

import styles from "./Button.module.css";

interface IButton {
  title: any;
  fontSize?: "small" | "middle" | "large";
  type?: "primary-gradient" | "primary" | "light" | "secondary";
  className?: string;
  onClick?: (e: any) => void;
  style?: React.CSSProperties;
  disabled?: boolean;
  buttonType?: "button" | "submit" | "reset";
}

const Button: FC<IButton> = ({
  title,
  fontSize = "middle",
  type = "primary",
  className,
  onClick,
  style,
  disabled = false,
  buttonType,
}) => {
  const typeStyles = () => {
    switch (type) {
      case "light":
        return styles.backgroundLight;
      case "primary":
        return styles.backgroundPrimary;
      case "primary-gradient":
        return styles.backgroundPrimaryGradient;
      case "secondary":
        return styles.backgroundSecondary;
      default:
        return styles.backgroundPrimaryGradient;
    }
  };

  const fontSizeStyles = () => {
    switch (fontSize) {
      case "small":
        return styles.fontSizeSmall;
      case "large":
        return styles.fontSizeSmall;
    }
  };

  return (
    <div
      className={`${styles.buttonContainer} ${className}
    `}
    >
      <button
        className={`
        ${typeStyles()}
        ${fontSizeStyles()}`}
        onClick={onClick}
        style={style}
        disabled={disabled}
        type={buttonType}
      >
        {title}
      </button>
    </div>
  );
};

export default Button;
