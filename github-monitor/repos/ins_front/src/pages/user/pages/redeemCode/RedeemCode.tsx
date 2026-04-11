import Button from "../../../../components/button/Button";
import Input from "../../../../components/input/Input";
import useAxios from "../../../../hooks/useAxios";
import Header from "../../components/header/Header";

import Cookies from "universal-cookie";

import { ToastContainer, toast, Slide } from "react-toastify";

import styles from "./RedeemCode.module.css";
import { TOKEN_NAME } from "../../../../utils/constant";
import { useTranslation } from "react-i18next";
import { useState } from "react";

const cookies = new Cookies();

const RedeemCode = () => {
  const { t } = useTranslation();
  const { req } = useAxios("user/redeemcode", "post");

  const [formData, setFormData] = useState({
    code: "",
  });

  // const handleSubmit = async (e: React.SyntheticEvent) => {
  //   e.preventDefault();
  //   const target = e.target as typeof e.target & {
  //     code: { value: string };
  //   };
  //   const code = target.code.value;

  //   if (!code) {
  //     return toast.error(<p style={{ fontWeight: 900 }}>{t("titlePlaceholder")}</p>);
  //   }

  //   if (code) {
  //     const token = cookies.get(TOKEN_NAME);

  //     const params: any = {
  //       token,
  //       code,
  //     };

  //     const res = await req(params);

  //     if (res?.data?.code === 1) {
  //       toast.success(<p style={{ fontWeight: 900 }}>{res?.data?.msg}</p>);
  //       target.code.value = "";
  //     } else {
  //       toast.error(<p style={{ fontWeight: 900 }}>{res?.data?.msg}</p>);
  //     }
  //   }
  // };

  const handleSubmitFrom = async () => {
    const code = formData.code;

    try {
      if (!code) {
        return toast.error(
          <p style={{ fontWeight: 900 }}>{t("titlePlaceholder")}</p>
        );
      }
      if (code) {
        const token = cookies.get(TOKEN_NAME);
        const params: any = {
          token,
          code,
        };
        const res = await req(params);
        if (res?.data?.code === 1) {
          toast.success(<p style={{ fontWeight: 900 }}>{res?.data?.msg}</p>);
          setFormData({
            code: "",
          });
        } else {
          toast.error(<p style={{ fontWeight: 900 }}>{res?.data?.msg}</p>);
        }
      }
    } catch (err) {
      console.log(err);
    }
  };

  const handleChange = (e: any) => {
    const { name, value } = e.target;
    setFormData((prevState) => ({ ...prevState, [name]: value }));
  };

  return (
    <>
      <Header title={t("redeemCenter")} />
      <div className={styles.redeemCodeForm}>
        {/* <form onSubmit={handleSubmit}> */}
        <Input
          label={t("redeemCode")}
          layout="vertical"
          name="code"
          type="text"
          placeholder={t("redeemCodePlaceholder")}
          onChange={handleChange}
          value={formData.code}
        />
        <Button
          title={t("confirmRedeem")}
          type="primary-gradient"
          fontSize="small"
          style={{ marginTop: "15px" }}
          buttonType="submit"
          onClick={handleSubmitFrom}
        />
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

export default RedeemCode;
