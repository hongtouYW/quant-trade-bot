import { FC } from "react";

import styles from "./Input.module.css";

interface IInput {
  label?: string;
  layout?: "horizontal" | "vertical";
  name?: string;
  type?: string;
  placeholder?: string;
  disabled?: boolean;
  value?: any;
  onChange?: (e: any) => void;
}

const Input: FC<IInput> = (props) => {
  return (
    <>
      <label
        htmlFor={props.label}
        style={{ display: props.layout === "vertical" ? "block" : "inline" }}
        className={styles.label}
      >
        {props.label}
      </label>
      <input
        {...props}
        className={styles.input}
        disabled={props.disabled}
        onChange={props.onChange}
      />
    </>
  );
};

export default Input;
