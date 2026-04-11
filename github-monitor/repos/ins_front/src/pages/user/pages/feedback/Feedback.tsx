import Button from "../../../../components/button/Button";
import Input from "../../../../components/input/Input";
import TextArea from "../../../../components/textarea/TextArea";
import Header from "../../components/header/Header";

import Cookies from "universal-cookie";

import { ToastContainer, toast, Slide } from "react-toastify";

import styles from "./Feedback.module.css";
import { TOKEN_NAME } from "../../../../utils/constant";
import useAxios from "../../../../hooks/useAxios";
import { useTranslation } from "react-i18next";
import { useState } from "react";

const cookies = new Cookies();

const Feedback = () => {
  const { t } = useTranslation();
  const { req } = useAxios("user/feedback", "post");

  const [formData, setFormData] = useState({
    title: "",
    content: "",
  });

  // const handleSubmit = async (e: React.SyntheticEvent) => {
  //   e.preventDefault();

  //   const target = e.target as typeof e.target & {
  //     title: { value: string };
  //     content: { value: string };
  //   };

  //   const title = target.title.value;
  //   const content = target.content.value;

  //   if (!title) {
  //     return toast.error(
  //       <p style={{ fontWeight: 900 }}>{t("feedbackTitleNoty")}</p>
  //     );
  //   }

  //   if (!content) {
  //     return toast.error(
  //       <p style={{ fontWeight: 900 }}>{t("feedbackContentNoty")}</p>
  //     );
  //   }

  //   if (title && content) {
  //     const token = cookies.get(TOKEN_NAME);

  //     const params: any = {
  //       token,
  //       title,
  //       content,
  //     };

  //     const res = await req(params);

  //     if (res?.data?.code === 1) {
  //       toast.success(<p style={{ fontWeight: 900 }}>{res?.data?.msg}</p>);
  //       target.title.value = "";
  //       target.content.value = "";
  //     } else {
  //       toast.error(<p style={{ fontWeight: 900 }}>{res?.data?.msg}</p>);
  //     }
  //   }
  // };

  const handleSubmitFrom = async () => {
    const title = formData?.title;
    const content = formData?.content;

    if (!title) {
      return toast.error(
        <p style={{ fontWeight: 900 }}>{t("feedbackTitleNoty")}</p>
      );
    }

    if (!content) {
      return toast.error(
        <p style={{ fontWeight: 900 }}>{t("feedbackContentNoty")}</p>
      );
    }

    if (title && content) {
      const token = cookies.get(TOKEN_NAME);

      const params: any = {
        token,
        title,
        content,
      };

      const res = await req(params);

      if (res?.data?.code === 1) {
        toast.success(<p style={{ fontWeight: 900 }}>{res?.data?.msg}</p>);
        setFormData({
          title: "",
          content: "",
        });
        // target.title.value = "";
        // target.content.value = "";
      } else {
        toast.error(<p style={{ fontWeight: 900 }}>{res?.data?.msg}</p>);
      }
    }
  };

  const handleChange = (e: any) => {
    const { name, value } = e.target;
    setFormData((prevState) => ({ ...prevState, [name]: value }));
  };

  return (
    <>
      <Header title={t("feedback")} />
      <div className={styles.feedbackForm}>
        {/* <form onSubmit={handleSubmit}> */}
        <div>
          <Input
            label={t("title")}
            layout="vertical"
            name="title"
            type="text"
            placeholder={t("titlePlaceholder")}
            onChange={handleChange}
            value={formData.title}
          />
          <TextArea
            label={t("content")}
            layout="vertical"
            name="content"
            type="textarea"
            placeholder={t("contentPlaceholder")}
            rows={5}
            onChange={handleChange}
            value={formData.content}
          />
          <Button
            title={t("submit")}
            type="primary-gradient"
            fontSize="small"
            style={{ marginTop: "15px" }}
            buttonType="submit"
            onClick={handleSubmitFrom}
          />
        </div>
        {/* </form> */}
      </div>
      <ToastContainer
        position="top-center"
        theme="colored"
        autoClose={3000}
        hideProgressBar={true}
        pauseOnHover
        className="toast_notification"
        transition={Slide}
        closeButton={false}
      />
    </>
  );
};

export default Feedback;
