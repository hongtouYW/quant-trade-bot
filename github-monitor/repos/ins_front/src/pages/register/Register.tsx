import Button from "../../components/button/Button";
import Input from "../../components/input/Input";
import useAxios from "../../hooks/useAxios";

import { ToastContainer, toast, Slide } from "react-toastify";

import styles from "./Register.module.css";
import u from "../../utils/utils";
import { useNavigate } from "react-router";
import { useUser } from "../../contexts/user.context";
import { TOKEN_NAME } from "../../utils/constant";
import { useTranslation } from "react-i18next";
import { Link } from "react-router-dom";
import { useEffect, useState } from "react";
import i18n from "../../utils/i18n";
import Cookies from "universal-cookie";
import Loading from "../../components/loading/Loading";

const cookies = new Cookies();

const Register = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { setCurrentUser } = useUser();
  const { req } = useAxios("user/register", "post");

  const [loading, setLoading] = useState(false);
  const [formData, setFormData] = useState({
    username: "",
    password: "",
    repassword: "",
    invitation_code: "",
  });

  // const handleSubmit = async (e: React.SyntheticEvent) => {
  //   e.preventDefault();
  //   try {
  //     const target = e.target as typeof e.target & {
  //       username: { value: string };
  //       password: { value: string };
  //       repassword: { value: string };
  //       invitation_code: { value: string };
  //     };
  //     const username = target.username.value;
  //     const password = target.password.value;
  //     const repassword = target.repassword.value;
  //     const invitation_code = target.invitation_code.value;

  //     if (!username?.trim()) {
  //       return toast.error(
  //         <p style={{ fontWeight: 900 }}>{t("usernamePlaceholder")}</p>
  //       );
  //     }
  //     if (!password?.trim()) {
  //       return toast.error(
  //         <p style={{ fontWeight: 900 }}>{t("passwordPlaceholder")}</p>
  //       );
  //     }
  //     if (!repassword?.trim()) {
  //       return toast.error(
  //         <p style={{ fontWeight: 900 }}>{t("confirmPasswordPlaceholder")}</p>
  //       );
  //     }

  //     if (username && password && repassword) {
  //       setLoading(true);
  //       const params: any = {
  //         username,
  //         password,
  //         repassword,
  //       };

  //       if (invitation_code) {
  //         params["invitation_code"] = invitation_code;
  //       }

  //       const res = await req(params);

  //       if (res?.data?.code === 1) {
  //         const token = res?.data?.data?.token || "";
  //         const tokenValue = res?.data?.data?.token_val || "";
  //         const currentUser = res?.data?.data || {};

  //         // 设置cookies 和 用户信息 全局
  //         u.setTokens(TOKEN_NAME, token, tokenValue);
  //         setCurrentUser(currentUser);

  //         toast.success(
  //           <p style={{ fontWeight: 900 }}>{t("registerSuccessfully")}</p>
  //         );
  //         setLoading(false);
  //         navigate("/");
  //       } else {
  //         toast.error(res?.data?.msg);
  //         setLoading(false);
  //       }
  //     }
  //   } catch (err) {
  //     console.log(err);
  //     setLoading(false);
  //   }
  // };

  const handleSubmitFrom = async () => {
    try {
      const username = formData.username;
      const password = formData.password;
      const repassword = formData.repassword;
      const invitation_code = formData.invitation_code;

      if (!username?.trim()) {
        return toast.error(
          <p style={{ fontWeight: 900 }}>{t("usernamePlaceholder")}</p>
        );
      }
      if (!password?.trim()) {
        return toast.error(
          <p style={{ fontWeight: 900 }}>{t("passwordPlaceholder")}</p>
        );
      }
      if (!repassword?.trim()) {
        return toast.error(
          <p style={{ fontWeight: 900 }}>{t("confirmPasswordPlaceholder")}</p>
        );
      }

      if (username && password && repassword) {
        setLoading(true);
        const params: any = {
          username,
          password,
          repassword,
        };

        if (invitation_code) {
          params["invitation_code"] = invitation_code;
        }

        const res = await req(params);

        if (res?.data?.code === 1) {
          const token = res?.data?.data?.token || "";
          // const tokenValue = res?.data?.data?.token_val || "";
          const currentUser = res?.data?.data || {};

          // 设置cookies 和 用户信息 全局
          u.setTokens(TOKEN_NAME, token, 1);
          setCurrentUser(currentUser);

          toast.success(
            <p style={{ fontWeight: 900 }}>{t("registerSuccessfully")}</p>
          );
          setLoading(false);
          navigate("/");
        } else {
          toast.error(res?.data?.msg);
          setLoading(false);
        }
      }
    } catch (err) {
      console.log(err);
      setLoading(false);
    }
  };

  const handleChange = (e: any) => {
    const { name, value } = e.target;
    setFormData((prevState) => ({ ...prevState, [name]: value }));
  };

  useEffect(() => {
    const token = cookies.get(TOKEN_NAME);
    if (token) {
      navigate("/");
    }
  }, []);

  useEffect(() => {
    // const lang = localStorage.getItem("curr_lang");
    const lang = cookies.get("curr_lang");

    if (lang) {
      i18n.changeLanguage(lang);
    }
  }, []);

  return (
    <>
      <div className={styles.registerContainer}>
        <div className={styles.registerHeader}>
          <h2 className={styles.registerHeaderTitle}>{t("signup")}</h2>
          <p className={styles.registerHeaderDesc}>{t("signupDesc")}</p>
        </div>
        <div className={styles.registerFormContainer}>
          {/* <form onSubmit={handleSubmit} className={styles.registerForm}> */}
          <div className={styles.registerForm}>
            <Input
              label=""
              layout="vertical"
              name="username"
              type="text"
              placeholder={t("usernamePlaceholder")}
              onChange={handleChange}
              disabled={loading}
            />
            <Input
              label=""
              layout="vertical"
              name="password"
              type="password"
              placeholder={t("passwordPlaceholder")}
              onChange={handleChange}
              disabled={loading}
            />
            <Input
              label=""
              layout="vertical"
              name="repassword"
              type="password"
              placeholder={t("confirmPasswordPlaceholder")}
              onChange={handleChange}
              disabled={loading}
            />
            <Input
              label=""
              layout="vertical"
              name="invitation_code"
              type="text"
              placeholder={t("invitationCodePlaceholder")}
              onChange={handleChange}
              disabled={loading}
            />
            <Button
              title={loading === true ? <Loading /> : t("confirmRegister")}
              type="primary-gradient"
              fontSize="small"
              style={{ marginTop: "15px" }}
              buttonType="submit"
              onClick={handleSubmitFrom}
            />
          </div>
          {/* </form> */}
        </div>
        <div className="registerFooter">
          <div className={styles.loginLink}>
            <Link to={"/user/login"}>
              <img src="/icon-arrow-left.png" alt="" width={22} height={22} />
              <p>{t("alreadyHaveAccount")}</p>
            </Link>
          </div>
        </div>
      </div>
      <ToastContainer
        position="top-center"
        theme="colored"
        autoClose={1500}
        hideProgressBar={true}
        pauseOnHover
        className="toast_notification"
        transition={Slide}
        closeButton={false}
      />
    </>
  );
};

export default Register;
