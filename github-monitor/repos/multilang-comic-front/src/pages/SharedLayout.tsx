import { Outlet, useLocation } from "react-router";
import Navbar from "../components/Navbar";
import Footer from "../components/Footer";
import { useEffect } from "react";
import { useTranslation } from "react-i18next";
import { Slide, ToastContainer } from "react-toastify";
import FloatButton from "../components/FloatButton";
import LoginModal from "../components/modules/LoginModal";
import RegisterModal from "../components/modules/RegisterModal";
import LogoutModal from "../components/modules/LogoutModal";
import { NoticeModal } from "../components/modules/NoticeModal";
import { Confirm18Modal } from "../components/modules/Confirm18Modal";
import { useUser } from "../contexts/user.context";

const SharedLayout = () => {
  const { i18n } = useTranslation();
  const location = useLocation();
  const { isOpenConfirm18Modal, setIsOpenConfirm18Modal } = useUser();

  useEffect(() => {
    const language = localStorage.getItem("language");
    if (language) {
      i18n.changeLanguage(language);
    }
  }, []);

  // 如何localstorage中没有age，则自动弹出confirm18 modal
  useEffect(() => {
    if (!sessionStorage.getItem("age") && !location.pathname.includes("/terms-of-services")) {
      setIsOpenConfirm18Modal(true);
    }
  }, [setIsOpenConfirm18Modal, location]);

  // 列表滚动
  useEffect(() => {
    if (isOpenConfirm18Modal) {
      document.body.style.overflow = "hidden";
    } else {
      document.body.style.overflow = "auto";
    }
    // 👇 cleanup on unmount
    return () => {
      document.body.style.overflow = "auto";
    };
  }, [isOpenConfirm18Modal]);

  // Scroll to top on route change
  useEffect(() => {
    window.scrollTo(0, 0);
  }, [location.pathname]);

  return (
    <div className="h-lvh">
      <div className="h-full min-h-screen flex flex-col">
        <Navbar />
        <div id="main-content" className="flex-1 mt-14 lg:mt-[100px]">
          <Outlet />
        </div>
        <Footer />
      </div>
      <ToastContainer
        position="top-right"
        theme="colored"
        autoClose={1500}
        hideProgressBar={true}
        pauseOnHover
        className="toast_notification"
        transition={Slide}
        closeButton={true}
      />
      <FloatButton />
      <Confirm18Modal />
      <LoginModal />
      <RegisterModal />
      <LogoutModal />
      <NoticeModal />
    </div>
  );
};

export default SharedLayout;
