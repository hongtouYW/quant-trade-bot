import { useTranslation } from "react-i18next";
import { NavLink } from "react-router";

const Footer = () => {
  const { t } = useTranslation();

  return (
    <>
      <footer className="bg-[#252525] px-4 py-6 relative z-[5] xl:px-[108px]">
        <div className="max-w-screen-xl mx-auto">
          {/* Body */}
          <div className="flex flex-col items-center text-center lg:text-left">
            <div className="flex-1 pb-6">
              <img
                className="mx-auto w-max max-w-[118px] h-[50px] object-contain lg:h-[57px] lg:mx-0"
                src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/${import.meta.env.VITE_LOGO_URL || "logo-2.png"
                  }`}
                alt="logo"
              />
              <div className="mt-5 w-full lg:mt-4">
                <p className="text-greyscale-200 text-sm leading-4 lg:leading-6">
                  {t("footer.warning")}
                </p>
                {/* <p className="text-[#EEEEEE] text-sm font-light mb-5 max-xs:text-justify">
                  {t("footer.warning1")}
                </p> */}
              </div>
            </div>
            {/* <div className="flex-1 flex justify-end gap-[100px] max-sm:gap-[30px] max-xs:flex-col max-xs:gap-6">
              <div className="max-xs:border-b-2 max-xs:border-white/30 max-xs:pb-6">
                <p className="text-[#EEEEEE] text-lg font-medium mb-3 max-xs:mt-4">
                  {t("footer.label")}
                </p>
                <div className="text-white grid grid-cols-3 gap-2 cursor-pointer max-xs:flex max-xs:flex-wrap">
                  {tags?.map((item) => (
                    <NavLink to={`/finished?type=7&tag=${item.id}`} key={item.id}>
                      <p className="text-sm px-4 py-2 bg-white/10 rounded-full text-center text-[#FDF1E1] truncate max-w-[90px]">
                        {item.name}
                      </p>
                    </NavLink>
                  ))}
                </div>
              </div>
              <div>
                <p className="text-[#EEEEEE] text-lg font-medium mb-3">
                  {t("footer.recommend")}
                </p>
                <div className="grid grid-cols-4 gap-1 max-sm:grid-cols-3 max-sm:gap-1 max-xs:flex max-xs:gap-3 max-xs:overflow-x-auto">
                  {adsLists.map((item: any) => {
                    return (
                      <NavLink
                        to={item?.url || "#"}
                        key={item.id}
                        target="_blank"
                      >
                        <div
                          className="flex flex-col justify-center items-center gap-1 max-xs:w-[48px]"
                          key={item.id}
                        >
                          <Image
                            className="w-12 h-12 max-sm:w-10 max-sm:h-10"
                            src={item?.img}
                            alt="post"
                          />
                          <p className="text-white text-sm max-sm:text-xs">
                            {item.title}
                          </p>
                        </div>
                      </NavLink>
                    );
                  })}
                </div>
              </div>
            </div> */}
          </div>
          {/* Footer */}
          <div className="flex flex-col-reverse items-center justify-between border-t border-greyscale-700 pt-6 lg:flex-row">
            <p className="text-white/80 text-xs text-center mt-4 lg:text-sm lg:mt-0">
              © All rights reserved {new Date().getFullYear()}
            </p>
            <div className="flex items-center gap-6 max-xs:gap-4 max-xs:overflow-x-auto max-xs:justify-center">
              <NavLink to="/user/cs">
                <p className="text-greyscale-50 text-sm max-xs:min-w-max max-xs:w-full hover:text-greyscale-50 transition-all duration-300">
                  {t("user.contactUs")}
                </p>
              </NavLink>
              <NavLink to="/terms-services">
                <p className="text-greyscale-50 text-sm max-xs:min-w-max max-xs:w-full hover:text-greyscale-50 transition-all duration-300">
                  {t("termsAndServices.serviceTerms")}
                </p>
              </NavLink>
              {/* <NavLink to="/refund-cancellation">
                <p className="text-greyscale-50 text-sm max-xs:min-w-max max-xs:w-full hover:text-greyscale-50 transition-all duration-300">
                  {t("refundAndCancellation.refundAndCancellation")}
                </p>
              </NavLink> */}
              <NavLink to="/privacy-policy">
                <p className="text-greyscale-50 text-sm max-xs:min-w-max max-xs:w-full hover:text-greyscale-50 transition-all duration-300">
                  {t("privacyPolicy.privacyPolicy")}
                </p>
              </NavLink>
            </div>
          </div>
        </div>
      </footer>
    </>
  );
};

export default Footer;
