// import UserWrapper from "../../components/UserWrapper";
import { useTranslation } from "react-i18next";
import { useConfig } from "../../contexts/config.context";
import { useEffect } from "react";
import { useNavigate } from "react-router";

const CustomerService = () => {
  const { t } = useTranslation();
  const { config } = useConfig();
  const systemEmail = import.meta.env.VITE_SYSTEM_EMAIL;
  const navigate = useNavigate();

  useEffect(() => {
    window.scrollTo(0, 0);
  }, []);

  return (
    <div className="max-w-screen-xl mx-auto h-full lg:flex lg:items-center lg:justify-center">
      <div className="flex items-center p-4 w-full text-greyscale-900 fixed top-0 bg-white h-14 leading-6 lg:hidden">
        <img
          src="/assets/images/icon-cheveron-left.svg"
          alt="arrow-left"
          className="w-6 h-6 cursor-pointer"
          onClick={() => navigate("/")}
        />
        <p className="font-semibold text-center w-full ">{t("user.contactUs")}</p>
      </div>

      <div className="flex flex-col items-center gap-6 mt-4 mb-9 lg:flex-row">
        <div className="flex-1 flex justify-center w-full px-4">
          <div className="w-full text-center max-w-md xl:max-w-xl">
            <h4 className="hidden font-bold text-[28px] leading-9 lg:block">
              {t("user.needHelp")} {t("user.weAreOnline")}
            </h4>
            <div className="lg:hidden">
              <h4 className="font-bold text-[28px] leading-9">
                {t("user.needHelp")}<br />
                {t("user.weAreOnline")}
              </h4>
            </div>
            <div className="mt-4">
              <p className="text-[24px] max-xs:text-sm font-semibold">
                {t("user.customerEmail")}
                {systemEmail}
              </p>

              <p className="text-[24px] max-xs:text-sm font-semibold">
                {t("user.onlineOperationHours")}
              </p>
            </div>
            <button
              className="mt-6 bg-primary text-sm text-greyscale-50 font-semibold w-full py-3 rounded-xl cursor-pointer"
              onClick={() => window.open(config.customer_url, "_blank")}
            >
              {t("user.onlineService")}
            </button>
            <p className="mt-2 text-sm text-greyscale-500 text-center">
              {t("user.weWillAnswerYourQuestions")}
            </p>
          </div>
        </div>
        <div className="flex-1 px-4 max-w-md xl:max-w-xl">
          <div className="mx-auto">
            <img src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/cs-content.png`} alt="cs" />
          </div>
        </div>
      </div>
    </div>
    // <UserWrapper>
    //   <div className="max-xs:px-2">
    //     <div className="flex justify-between items-center border-b-2 border-greyscale-200 pb-4 max-xs:pb-2 max-xs:border-none">
    //       <h4 className="font-medium text-lg max-xs:text-base">
    //         {t("user.customerCenter")}
    //       </h4>
    //     </div>
    //     <div className="flex flex-col items-center justify-center py-10 border-2 rounded-2xl border-[#D8ECFE] my-4 max-xs:px-4 max-xs:my-2">
    //       <img
    //         className="w-[80px] h-[80px] my-4"
    //         src="/assets/images/customer-service.png"
    //         alt="customer-service"
    //       />
    //       <p className="font-medium text-red-500">
    //         {t("user.customerService")}
    //       </p>
    //       <p className="font-medium">official@18toon.vip</p>
    //       <button
    //         className="bg-primary text-white w-[200px] py-2 rounded-full mt-4 mb-2 cursor-pointer max-xs:w-full"
    //         onClick={() => window.open(config.customer_url, "_blank")}
    //       >
    //         {t("user.contactNow")}
    //       </button>
    //       <p className="text-xs text-greyscale-500">
    //         {t("user.customerService2")}
    //       </p>
    //     </div>
    //   </div>
    // </UserWrapper>
  );
};

export default CustomerService;
