import Button from "../../../../components/button/Button";
import TextArea from "../../../../components/textarea/TextArea";
import useAxios from "../../../../hooks/useAxios";
import Header from "../../components/header/Header";
import Cookies from "universal-cookie";

import styles from "./EditProfile.module.css";
import { TOKEN_NAME } from "../../../../utils/constant";
import { toast, ToastContainer, Slide } from "react-toastify";
import { useNavigate } from "react-router";
import { useUser } from "../../../../contexts/user.context";
import { useTranslation } from "react-i18next";
import { useState } from "react";

const cookies = new Cookies();

const EditProfile = () => {
  const { t } = useTranslation();
  const { req } = useAxios("user/editInfo", "post");
  const navigate = useNavigate();
  const { fetchCurrentUser } = useUser();

  const [formData, setFormData] = useState({
    detail: "",
  });

  // const handleSubmit = async (e: React.SyntheticEvent) => {
  //   e.preventDefault();

  //   const target = e.target as typeof e.target & {
  //     detail: { value: string };
  //   };

  //   const token = cookies.get(TOKEN_NAME);
  //   const detail = target.detail.value;

  //   if (detail) {
  //     const params: any = {
  //       token,
  //       signature: detail,
  //     };

  //     const res = await req(params);

  //     if (res?.data?.code === 1) {
  //       toast.success(<p style={{ fontWeight: 900 }}>{res?.data?.msg}</p>);
  //       fetchCurrentUser();
  //       setTimeout(() => {
  //         navigate("/user/center");
  //       }, 1000);
  //     } else {
  //       toast.error(<p style={{ fontWeight: 900 }}>{res?.data?.msg}</p>);
  //     }
  //   }
  // };

  const handleSubmitFrom = async () => {
    const token = cookies.get(TOKEN_NAME);
    const detail = formData?.detail;

    try {
      if (detail) {
        const params: any = {
          token,
          signature: detail,
        };

        const res = await req(params);

        if (res?.data?.code === 1) {
          toast.success(<p style={{ fontWeight: 900 }}>{res?.data?.msg}</p>);
          fetchCurrentUser();
          setTimeout(() => {
            navigate("/user/center");
          }, 1000);
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
      <Header title={t("editProfileInfo")} />
      <div className="editProfileContainer">
        <div className={styles.editProfileForm}>
          {/* <form onSubmit={handleSubmit}> */}
          <div>
            <TextArea
              label={t("editProfileDesc")}
              layout="vertical"
              name="detail"
              type="textarea"
              placeholder={t("personalInfo")}
              rows={10}
              onChange={handleChange}
            />
            <Button
              title={t("revise")}
              type="primary-gradient"
              fontSize="small"
              style={{ marginTop: "15px" }}
              buttonType="submit"
              onClick={handleSubmitFrom}
            />
          </div>
          {/* </form> */}
        </div>
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

export default EditProfile;
