import { useNavigate, useLocation } from "react-router";
import Button from "../../components/button/Button";
import Input from "../../components/input/Input";
import useAxios from "../../hooks/useAxios";
import styles from "./Login.module.css";

import { ToastContainer, toast, Slide } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import u from "../../utils/utils";
import { useUser } from "../../contexts/user.context";
import { NAVIGATE_BOTTOM, NAVIGATE_CENTER, TOKEN_NAME } from "../../utils/constant";
import { useTranslation } from "react-i18next";
import { Link } from "react-router-dom";
import { useEffect, useState } from "react";
import i18n from "../../utils/i18n";
import { useModal } from "../../contexts/modal.context";
import Cookies from "universal-cookie";
import { useConfig } from "../../contexts/config.context";
import Loading from "../../components/loading/Loading";

const cookies = new Cookies();

const Login = () => {
  const { t } = useTranslation();
  const { configList } = useConfig();
  const navigate = useNavigate();
  const location = useLocation();
  const modal = useModal();
  const { setCurrentUser, fetchUserVipStatus } = useUser();
  const { req } = useAxios("user/login", "post");

  const [loading, setLoading] = useState(false);
  // const [msg, setMsg] = useState<any>([]);
  const [formData, setFormData] = useState({
    username: "",
    password: "",
  });

  const handleNavigateCustomerService = () => {
    if (configList && configList?.server_link) {
      window.open(configList?.server_link || "", "_blank");
    }
  };

  const handleForgetPassword = () => {
    return modal.info({
      title: `${t("information")}`,
      content: `${t("forgotPasswordDescContact")}`,
      confirmFn: () => handleNavigateCustomerService(),
      confirmText: `${t("confirm")}`,
    });
  };

  // const handleSubmit = async (e: React.SyntheticEvent) => {
  //   e.preventDefault();
  //   try {
  //     const target = e.target as typeof e.target & {
  //       username: { value: string };
  //       password: { value: string };
  //     };
  //     const username = target.username.value;
  //     const password = target.password.value;

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

  //     if (username && password) {
  //       setLoading(true);
  //       const params: any = {
  //         username,
  //         password,
  //       };

  //       const res = await req(params);
  //       // console.log("res", res);

  //       if (res?.data?.code === 1) {
  //         const token = res?.data?.data?.token || "";
  //         const tokenValue = res?.data?.data?.token_val || "";
  //         const currentUser = res?.data?.data || {};

  //         // 设置cookies 和 用户信息 全局
  //         u.setTokens(TOKEN_NAME, token, tokenValue);
  //         setCurrentUser(currentUser);

  //         toast.success(<p style={{ fontWeight: 900 }}>{t("loginSuccess")}</p>);

  //         // 首页弹窗cookies 和 身份卡
  //         // cookies.remove(NAVIGATE_CENTER);
  //         // cookies.remove(NAVIGATE_BOTTOM);
  //         // localStorage.setItem("isShow_identity", "1");

  //         // toast.success(<p style={{ fontWeight: 900 }}>{t("loginSuccess")}</p>);
  //         setLoading(false);
  //         window.location.href = "/";
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

  // const handleSubmitFrom = async () => {
  //   setMsg([]);
  //   setMsg((prev: any) => {
  //     return [...prev, "====handleSubmitFrom-Start==="];
  //   });
  //   const username = formData?.username;
  //   const password = formData?.password;
  //   setMsg((prev: any) => {
  //     return [...prev, "====handleSubmitFrom-Start Validate Form==="];
  //   });
  //   if (!username?.trim()) {
  //     setMsg((prev: any) => {
  //       return [...prev, "====handleSubmitFrom-Not Username==="];
  //     });
  //     return toast.error(
  //       <p style={{ fontWeight: 900 }}>{t("usernamePlaceholder")}</p>
  //     );
  //   }

  //   if (!password?.trim()) {
  //     setMsg((prev: any) => {
  //       return [...prev, "====handleSubmitFrom-Not Password==="];
  //     });

  //     return toast.error(
  //       <p style={{ fontWeight: 900 }}>{t("passwordPlaceholder")}</p>
  //     );
  //   }
  //   setMsg((prev: any) => {
  //     return [...prev, "====handleSubmitFrom-Done Validate Form==="];
  //   });

  //   try {
  //     if (username && password) {
  //       setMsg((prev: any) => {
  //         return [...prev, "====handleSubmitFrom-Start Loading==="];
  //       });
  //       setLoading(true);
  //       const params: any = {
  //         username,
  //         password,
  //       };
  //       setMsg((prev: any) => {
  //         return [...prev, `====handleSubmitFrom-Start Request API-${params}===`];
  //       });

  //       const res = await req(params);
  //       // console.log("res", res);
  //       setMsg((prev: any) => {
  //         return [...prev, "====handleSubmitFrom-Done Request API==="];
  //       });

  //       if (res?.data?.code === 1) {
  //         setMsg((prev: any) => {
  //           return [
  //             ...prev,
  //             `====handleSubmitFrom-ReadResponse-Request API Success-${res?.data}===`,
  //           ];
  //         });

  //         const token = res?.data?.data?.token || "";
  //         const tokenValue = res?.data?.data?.token_val || "";
  //         const currentUser = res?.data?.data || {};
  //         setMsg((prev: any) => {
  //           return [...prev, `====handleSubmitFrom-Get Response Value===`];
  //         });

  //         // 设置cookies 和 用户信息 全局
  //         u.setTokens(TOKEN_NAME, token, tokenValue);

  //         setMsg((prev: any) => {
  //           return [...prev, `====handleSubmitFrom-Success Set Token===`];
  //         });
  //         setCurrentUser(currentUser);
  //         setMsg((prev: any) => {
  //           return [...prev, `====handleSubmitFrom-Success Set CurrentUser===`];
  //         });

  //         toast.success(<p style={{ fontWeight: 900 }}>{t("loginSuccess")}</p>);

  //         // 首页弹窗cookies 和 身份卡
  //         // cookies.remove(NAVIGATE_CENTER);
  //         // cookies.remove(NAVIGATE_BOTTOM);
  //         // localStorage.setItem("isShow_identity", "1");

  //         // toast.success(<p style={{ fontWeight: 900 }}>{t("loginSuccess")}</p>);

  //         setMsg((prev: any) => {
  //           return [...prev, `====handleSubmitFrom-Start Close Loading===`];
  //         });
  //         setLoading(false);
  //         setMsg((prev: any) => {
  //           return [...prev, `====handleSubmitFrom-Navigate to Home Page===`];
  //         });
  //         window.location.href = "/";
  //       } else {
  //         console.log("res", res);
  //         setMsg((prev: any) => {
  //           return [
  //             ...prev,
  //             `====handleSubmitFrom-Done Request API-Failed Response-${res?.data?.msg}===`,
  //           ];
  //         });

  //         toast.error(res?.data?.msg);
  //         setLoading(false);
  //       }
  //     }
  //   } catch (err) {
  //     setMsg((prev: any) => {
  //       return [...prev, `====handleSubmitFrom-Catch Error-${err}===`];
  //     });
  //     console.log(err);
  //     setLoading(false);
  //   }
  // };

  const handleSubmitFrom = async () => {
    const username = formData?.username;
    const password = formData?.password;

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

    try {
      if (username && password) {
        setLoading(true);
        const params: any = {
          username,
          password,
        };
        const res = await req(params);
        if (res?.data?.code === 1) {
          const token = res?.data?.data?.token || "";
          // const tokenValue = res?.data?.data?.token_val || "";
          const currentUser = res?.data?.data || {};

          // 设置cookies 和 用户信息 全局
          u.setTokens(TOKEN_NAME, token, 1);
          setCurrentUser(currentUser);

          // Fetch VIP status after login
          await fetchUserVipStatus();

          toast.success(<p style={{ fontWeight: 900 }}>{t("loginSuccess")}</p>);

          // 首页弹窗cookies 和 身份卡
          localStorage.removeItem(NAVIGATE_CENTER);
          localStorage.removeItem(NAVIGATE_BOTTOM);
          localStorage.setItem("isShow_identity", "1");

          setLoading(false);

          // Redirect to the intended destination (e.g., video page) or home
          const redirectTo = location.state?.redirectTo || "/";
          navigate(redirectTo, { replace: true });
        } else {
          // console.log("res", res);
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
      <div className={styles.loginContainer}>
        <div className={styles.loginHeader}>
          <h2 className={styles.loginHeaderTitle}>{t("signIn")}</h2>
          <p className={styles.loginHeaderDesc}>{t("signInDesc")}</p>
        </div>
        <div className={styles.loginFormContainer}>
          {/* <div style={{ color: "#fff" }}>
            <ul>
              {msg &&
                msg.map((i: any) => {
                  return <li>{i}</li>;
                })}
            </ul>
          </div> */}
          {/* <form onSubmit={handleSubmit} className={styles.loginForm}> */}
          <div className={styles.loginForm}>
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
            <Button
              title={loading === true ? <Loading /> : t("confirmSimplify")}
              type="primary-gradient"
              fontSize="small"
              style={{ marginTop: "15px" }}
              buttonType="submit"
              onClick={handleSubmitFrom}
            />
          </div>
          {/* </form> */}
        </div>
        <div className={styles.loginFooter}>
          <div className={styles.forgetPasswordLink}>
            <p onClick={handleForgetPassword}>{t("forgotPassword")}</p>
          </div>
          <div className={styles.registerLink}>
            <Link to="/user/register">
              <p>{t("registerNow")}</p>
              <img src="/icon-arrow-right.png" alt="" width={22} height={22} />
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

export default Login;
