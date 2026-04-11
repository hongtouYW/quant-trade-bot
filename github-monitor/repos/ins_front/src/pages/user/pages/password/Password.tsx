import Button from "../../../../components/button/Button";
import Input from "../../../../components/input/Input";
import useAxios from "../../../../hooks/useAxios";
import Header from "../../components/header/Header";
import Cookies from "universal-cookie";

import { ToastContainer, toast, Slide } from "react-toastify";

import styles from "./Password.module.css";
import { useNavigate } from "react-router";
import { useUser } from "../../../../contexts/user.context";
import { TOKEN_NAME } from "../../../../utils/constant";
import u from "../../../../utils/utils";
import { useTranslation } from "react-i18next";
import { useState } from "react";

const cookies = new Cookies();

const Password = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { fetchCurrentUser } = useUser();
  const { req } = useAxios("user/changepassword", "post");

  const [formData, setFormData] = useState({
    currentPassword: "",
    newPassword: "",
    confirmNewPassword: "",
  });

  // const handleSubmit = async (e: React.SyntheticEvent) => {
  //   e.preventDefault();

  //   try {
  //     const target = e.target as typeof e.target & {
  //       currentPassword: { value: string };
  //       newPassword: { value: string };
  //       confirmNewPassword: { value: string };
  //     };

  //     const currentPassword = target.currentPassword.value;
  //     const newPassword = target.newPassword.value;
  //     const confirmNewPassword = target.confirmNewPassword.value;

  //     if (!currentPassword) {
  //       return toast.error(
  //         <p style={{ fontWeight: 900 }}>{t("enterCurrentPassword")}</p>
  //       );
  //     }

  //     if (!newPassword) {
  //       return toast.error(
  //         <p style={{ fontWeight: 900 }}>{t("enterNewPassword")}</p>
  //       );
  //     }

  //     if (!confirmNewPassword) {
  //       return toast.error(
  //         <p style={{ fontWeight: 900 }}>{t("enterNewConfirmPassword")}</p>
  //       );
  //     }

  //     if (currentPassword && newPassword && confirmNewPassword) {
  //       const token = cookies.get(TOKEN_NAME);

  //       const params: any = {
  //         token,
  //         old_password: currentPassword,
  //         password: newPassword,
  //         repassword: confirmNewPassword,
  //       };

  //       const res = await req(params);

  //       if (res?.data?.code === 1) {
  //         const token = res?.data?.data?.token || "";
  //         const tokenValue = res?.data?.data?.token_val || "";

  //         u.setTokens(TOKEN_NAME, token, tokenValue);

  //         toast.success(<p style={{ fontWeight: 900 }}>{res?.data?.msg}</p>);

  //         setTimeout(() => {
  //           navigate("/user/center");
  //         }, 1000);
  //         fetchCurrentUser();
  //       } else {
  //         toast.error(res?.data?.msg);
  //       }
  //     }
  //   } catch (err) {
  //     console.log(err);
  //   }
  // };

  const handleSubmitFrom = async () => {
    try {
      const currentPassword = formData.currentPassword;
      const newPassword = formData.newPassword;
      const confirmNewPassword = formData.confirmNewPassword;

      if (!currentPassword) {
        return toast.error(
          <p style={{ fontWeight: 900 }}>{t("enterCurrentPassword")}</p>
        );
      }

      if (!newPassword) {
        return toast.error(
          <p style={{ fontWeight: 900 }}>{t("enterNewPassword")}</p>
        );
      }

      if (!confirmNewPassword) {
        return toast.error(
          <p style={{ fontWeight: 900 }}>{t("enterNewConfirmPassword")}</p>
        );
      }

      if (currentPassword && newPassword && confirmNewPassword) {
        const token = cookies.get(TOKEN_NAME);

        const params: any = {
          token,
          old_password: currentPassword,
          password: newPassword,
          repassword: confirmNewPassword,
        };

        const res = await req(params);

        if (res?.data?.code === 1) {
          const token = res?.data?.data?.token || "";
          // const tokenValue = res?.data?.data?.token_val || "";

          u.setTokens(TOKEN_NAME, token, 1);

          toast.success(<p style={{ fontWeight: 900 }}>{res?.data?.msg}</p>);
          setFormData({
            currentPassword: "",
            newPassword: "",
            confirmNewPassword: "",
          });

          setTimeout(() => {
            navigate("/user/center");
          }, 1000);
          fetchCurrentUser();
        } else {
          toast.error(res?.data?.msg);
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
      <Header title={t("changePassword")} />
      <div className={styles.passwordForm}>
        {/* <form onSubmit={handleSubmit}> */}
        <Input
          label={t("currentPassword")}
          layout="vertical"
          name="currentPassword"
          type="password"
          placeholder={t("enterCurrentPassword")}
          onChange={handleChange}
        />
        <Input
          label={t("newPassword")}
          layout="vertical"
          name="newPassword"
          type="password"
          placeholder={t("newPasswordPlaceholder")}
          onChange={handleChange}
        />
        <Input
          label={t("confirmNewPassword")}
          layout="vertical"
          name="confirmNewPassword"
          type="password"
          placeholder={t("confirmNewPasswordPlaceholder")}
          onChange={handleChange}
        />
        <Button
          title={t("save")}
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

export default Password;
