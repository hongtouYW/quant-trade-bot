import { Outlet } from "react-router";
import Nabvar from "../components/navbar/Navbar";

import styles from "./SharedLayout.module.css";
import { useEffect, useState } from "react";
import Modal from "../components/modal/Modal";
import IdentifyCard from "../components/identifyCard/IdentifyCard";
import { useUser } from "../contexts/user.context";
import FloatButton from "../components/floatButton/FloatButton";
import Footer from "../components/footer/Footer";

const SharedLayout = () => {
  const { currentUser } = useUser();
  const [isOpenIdentityDialog, setIsOpenIdentityDialog] = useState(false);

  const handleCloseIdentifyCard = () => {
    setIsOpenIdentityDialog(false);
    localStorage.setItem("isShow_identity", "0");
  };

  useEffect(() => {
    const isShow = localStorage.getItem("isShow_identity");
    if (isShow === "1") {
      setIsOpenIdentityDialog(true);
    }
  }, [currentUser]);

  return (
    <>
      <Nabvar />
      <div className="main-wrapper">
        <Outlet />
        {isOpenIdentityDialog && (
          <Modal type="custom">
            <div
              className={`${styles.identifyDialogContainer} ${
                isOpenIdentityDialog === true ? "show" : "hide"
              }`}
            >
              <div className={styles.identifyContainer}>
                <button
                  className={styles.identifyContainerClose}
                  onClick={handleCloseIdentifyCard}
                >
                  <img src="/close.png" alt="close" />
                </button>
                <IdentifyCard />
              </div>
            </div>
          </Modal>
        )}
        <FloatButton />
      </div>
      <Footer />
    </>
  );
};

export default SharedLayout;
