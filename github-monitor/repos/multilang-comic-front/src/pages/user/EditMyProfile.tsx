import { useNavigate } from "react-router";
import Input from "../../components/Input";
import { useUser } from "../../contexts/user.context";
import { useState } from "react";
import { useTranslation } from "react-i18next";
import type { APIResponseType } from "../../api/type";
import { http } from "../../api";
import { API_ENDPOINTS } from "../../api/api-endpoint";
import { toast } from "react-toastify";

const EditMyProfile = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { userInfo } = useUser();

  const [isError, setIsError] = useState({
    nickname: false,
    // email: false,
  });

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setIsError((prev) => ({
      ...prev,
      [e.target.name]: e.target.value ? false : true,
    }));
  };

  const handleSubmit = async (e: React.SyntheticEvent) => {
    e.preventDefault();
    const formData = new FormData(e.target as HTMLFormElement);
    const data = Object.fromEntries(formData);
    // console.log("data", data);

    if (!data.nickname) {
      setIsError((prev) => ({
        ...prev,
        nickname: true,
      }));
      return;
    }

    try {
      const params = {
        token: userInfo?.token,
        nickname: data.nickname,
        email: userInfo?.email,
      };
      const res = await http.post<APIResponseType>(
        API_ENDPOINTS.userChangeInfo,
        params
      );
      if (res?.data?.code === 1) {
        toast.success(res?.data?.msg);
      } else {
        toast.error(res?.data?.msg);
      }
    } catch (error) {
      console.error("error", error);
    }
  };

  return (
    <div>
      {/* Header */}
      <div className="flex items-center gap-4 px-4 py-[10px] border-b border-greyscale-200 fixed top-0 left-0 right-0 bg-white h-12">
        <img
          src="/assets/images/icon-cheveron-left.svg"
          alt="arrow-left"
          className="w-6 h-6 cursor-pointer"
          onClick={() => navigate("/user/account")}
        />
        <p className="font-semibold">Edit Name</p>
      </div>
      <div className="p-4 mt-14">
        <form onSubmit={handleSubmit}>
          <Input
            initialValue={userInfo?.nickname}
            name="nickname"
            type="text"
            className={`shadow-[0_2px_6px_0_#1018280F] border  rounded-[6px] py-2 px-3 ${
              isError.nickname ? "border-red-500" : "!border-greyscale-200"
            }`}
            showCount
            maxLength={20}
            onChange={handleChange}
          />
          {isError.nickname && (
            <p className="text-red-500 text-sm">
              {t("common.thisFieldIsRequired")}
            </p>
          )}
          <button
            type="submit"
            className="bg-primary text-white rounded-[6px] py-2 px-4 mt-6 w-full"
          >
            {t("common.save")}
          </button>
        </form>
      </div>
    </div>
  );
};

export default EditMyProfile;
