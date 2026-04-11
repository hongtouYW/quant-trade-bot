import { Navigate, createBrowserRouter, redirect } from "react-router-dom";
import SharedLayout from "./pages/SharedLayout";
import Home from "./pages/home/Home";

import UserSharedLayout from "./pages/user/UserSharedLayout";
import Center from "./pages/user/pages/center/Center";
import Identify from "./pages/user/pages/identify/Identify";
import RedeemCode from "./pages/user/pages/redeemCode/RedeemCode";
import Subscribe from "./pages/user/pages/subscribe/Subscribe";
import Collect from "./pages/user/pages/collect/Collect";
import Password from "./pages/user/pages/password/Password";
import Order from "./pages/user/pages/order/Order";
import Feedback from "./pages/user/pages/feedback/Feedback";
import Notice from "./pages/user/pages/notice/Notice";
import Actor from "./pages/actor/Actor";
import VideoInfo from "./pages/video/info/VideoInfo";
import Publisher from "./pages/publisher/Publisher";
import Tag from "./pages/tag/Tag";
import Register from "./pages/register/Register";
import Login from "./pages/login/Login";
import EditProfile from "./pages/user/pages/editProfile/EditProfile";
import Vip from "./pages/user/pages/vip/Vip";
import ReviewList from "./pages/review/list/ReviewList";
// import { VideoList } from "./pages/video/list/VideoList";
import ReviewInfo from "./pages/review/info/ReviewInfo";
import ActorTrend from "./pages/actor/trend/ActorTrend";
import Author from "./pages/author/Author";
import VideoList from "./pages/video/list/VideoList";

export const greenRouter = createBrowserRouter([
  {
    path: "/",
    element: <SharedLayout />,
    children: [
      { index: true, element: <Home /> },
      { path: "/video/list", element: <VideoList /> },
      { path: "/video/info", element: <VideoInfo /> },
      { path: "/actor/index", element: <Actor /> },
      { path: "/actor/index/:menu", element: <Actor /> },
      { path: "/actor/trend", element: <ActorTrend /> },
      { path: "/author/index", element: <Author /> },
      { path: "/publisher/index", element: <Publisher /> },
      { path: "/tag/index", element: <Tag /> },
      { path: "/user/register", element: <Register /> },
      { path: "/user/login", element: <Login /> },
      { path: "/review/index", element: <ReviewList /> },
      { path: "/review/info", element: <ReviewInfo /> },
      {
        path: "*",
        element: <Navigate to="/" replace />,
      },
    ],
  },
  {
    path: "/user",
    element: <UserSharedLayout />,
    children: [
      {
        index: true,
        loader: async () => redirect("/user/center"),
      },
      { path: "center", element: <Center /> },
      { path: "identify", element: <Identify /> },
      { path: "redeemcode", element: <RedeemCode /> },
      { path: "subscribe", element: <Subscribe /> },
      { path: "collect", element: <Collect /> },
      { path: "password", element: <Password /> },
      { path: "order", element: <Order /> },
      { path: "feedback", element: <Feedback /> },
      { path: "notice", element: <Notice /> },
      { path: "edit", element: <EditProfile /> },
      { path: "vip", element: <Vip /> },
    ],
  },
]);

export const router = createBrowserRouter([
  {
    path: "/",
    element: <SharedLayout />,
    children: [
      { index: true, element: <Home /> },
      { path: "/video/list", element: <VideoList /> },
      { path: "/video/info", element: <VideoInfo /> },
      { path: "/actor/index", element: <Actor /> },
      { path: "/actor/index/:menu", element: <Actor /> },
      { path: "/actor/trend", element: <ActorTrend /> },
      { path: "/author/index", element: <Author /> },
      { path: "/publisher/index", element: <Publisher /> },
      { path: "/tag/index", element: <Tag /> },
      { path: "/user/register", element: <Register /> },
      { path: "/user/login", element: <Login /> },
      // { path: "/review/index", element: <ReviewList /> },
      { path: "/review/info", element: <ReviewInfo /> },
      {
        path: "*",
        element: <Navigate to="/" replace />,
      },
    ],
  },
  {
    path: "/user",
    element: <UserSharedLayout />,
    children: [
      {
        index: true,
        loader: async () => redirect("/user/center"),
      },
      { path: "center", element: <Center /> },
      { path: "identify", element: <Identify /> },
      { path: "redeemcode", element: <RedeemCode /> },
      { path: "subscribe", element: <Subscribe /> },
      { path: "collect", element: <Collect /> },
      { path: "password", element: <Password /> },
      { path: "order", element: <Order /> },
      { path: "feedback", element: <Feedback /> },
      { path: "notice", element: <Notice /> },
      { path: "edit", element: <EditProfile /> },
      { path: "vip", element: <Vip /> },
    ],
  },
]);
