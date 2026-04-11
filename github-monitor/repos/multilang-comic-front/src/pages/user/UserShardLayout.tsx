import { Navigate, Outlet } from "react-router";
import Cookies from "universal-cookie";
import { COOKIE_NAME } from "../../utils/constant";
const cookies = new Cookies();

const UserShardLayout = () => {
  if (!cookies.get(COOKIE_NAME) && !['/user/cs', '/user/feedback'].includes(location.pathname)) {
    return <Navigate to="/" />;
  }
  return (
    <>
      <Outlet />
    </>
  );
};

export default UserShardLayout;
