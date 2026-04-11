import { FC } from "react";

import styles from "./TextArea.module.css";

interface ITextArea {
  label?: string;
  layout?: "horizontal" | "vertical";
  name?: string;
  type?: string;
  placeholder?: string;
  rows?: number;
  value?: any;
  onChange?: (e: any) => void;
}

const TextArea: FC<ITextArea> = (props) => {
  return (
    <>
      <label
        htmlFor={props.label}
        style={{ display: props.layout === "vertical" ? "block" : "inline" }}
        className={styles.label}
      >
        {props.label}
      </label>
      <textarea
        {...props}
        rows={props.rows}
        className={styles.textarea}
        onChange={props.onChange}
      >
        {props.value}
      </textarea>
    </>
  );
};

export default TextArea;
