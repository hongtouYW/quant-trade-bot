import { Outlet, useNavigate } from "react-router";
import Navbar from "../../components/navbar/Navbar";

import styles from "./UserSharedLayout.module.css";
import Sidemenu from "./components/sidemenu/Sidemenu";
import { useEffect, useState } from "react";
import Cookies from "universal-cookie";
import { TOKEN_NAME } from "../../utils/constant";

const cookies = new Cookies();

const UserSharedLayout = () => {
  const navigate = useNavigate();
  const [isAuth, setIsAuth] = useState(false);

  useEffect(() => {
    const token = cookies.get(TOKEN_NAME);

    if (!token) {
      setIsAuth(false);
      navigate("/user/login");
    } else {
      setIsAuth(true);
    }
  }, []);

  return (
    <>
      <Navbar />
      <div className="main-wrapper">
        {isAuth && (
          <div className={styles.userContainer}>
            <div className={styles.userSideMenu}>
              <Sidemenu />
            </div>
            <div className={styles.userOutlet}>
              <Outlet />
            </div>
          </div>
        )}
      </div>
    </>
  );
};

export default UserSharedLayout;
