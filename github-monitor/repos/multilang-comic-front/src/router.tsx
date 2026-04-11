import { createBrowserRouter } from "react-router";
import Home from "./pages/Home";
import SharedLayout from "./pages/SharedLayout";
import Ranking from "./pages/Ranking";
import Weekly from "./pages/Weekly";
import New from "./pages/New";
import Finished from "./pages/Finished";
import Account from "./pages/user/Account";
import Topup from "./pages/user/Topup";
import Book from "./pages/user/Book";
import Subscription from "./pages/user/Subscription";
import Content from "./pages/Content";
import Free from "./pages/Free";
import Vip from "./pages/Vip";
import Favourite from "./pages/user/Favourite";
import History from "./pages/user/History";
import Chapter from "./pages/Chapter";
import { Offers } from "./pages/user/Offers";
import Search from "./pages/Search";
import UserShardLayout from "./pages/user/UserShardLayout";
import CustomerService from "./pages/user/CustomerService";
import TermsAndServices from "./pages/TermsAndServices";
import RefundAndCancellation from "./pages/RefundAndCancellation";
import PrivactPolicy from "./pages/PrivactPolicy";
import NotFound from "./pages/NotFound";
import EditMyProfile from "./pages/user/EditMyProfile";
import EditPassword from "./pages/user/EditPassword";
import Redeem from "./pages/user/Redeem";
import Genres from "./pages/Genres";
import Feedback from "./pages/user/Feedback";

export const router = createBrowserRouter([
  {
    path: "/",
    element: <SharedLayout />,
    children: [
      {
        index: true,
        element: <Home />,
      },
      {
        path: "/ranking",
        element: <Ranking />,
      },
      {
        path: "/weekly",
        element: <Weekly />,
      },
      {
        path: "/new",
        element: <New />,
      },
      {
        path: "/finished",
        element: <Finished />,
      },
      {
        path: "/genres",
        element: <Genres />,
      },
      {
        path: "/free",
        element: <Free />,
      },
      {
        path: "/vip",
        element: <Vip />,
      },
      {
        path: "/content/:comicId",
        element: <Content />,
      },
      // {
      //   path: "/chapter/:id",
      //   element: <Chapter />,
      // },
      {
        path: "/search",
        element: <Search />,
      },
      {
        path: "/terms-services",
        element: <TermsAndServices />,
      },
      {
        path: "/refund-cancellation",
        element: <RefundAndCancellation />,
      },
      {
        path: "/privacy-policy",
        element: <PrivactPolicy />,
      },
      {
        path: "/user",
        element: <UserShardLayout />,
        children: [
          {
            path: "account",
            element: <Account />,
          },
          {
            path: "redeem",
            element: <Redeem />,
          },
          {
            path: "edit-my-profile",
            element: <EditMyProfile />,
          },
          {
            path: "edit-password",
            element: <EditPassword />,
          },
          {
            path: "topup",
            element: <Topup />,
          },
          {
            path: "book",
            element: <Book />,
          },
          {
            path: "subscription",
            element: <Subscription />,
          },
          {
            path: "favourite",
            element: <Favourite />,
          },
          {
            path: "history",
            element: <History />,
          },
          {
            path: "offers",
            element: <Offers />,
          },
          {
            path: "cs",
            element: <CustomerService />,
          },
          {
            path: "feedback",
            element: <Feedback />,
          },
        ],
      },
    ],
  },
  {
    path: "/terms-of-services",
    element: <TermsAndServices />,
  },
  {
    path: "/chapter/:id",
    element: <Chapter />,
  },
  {
    path: "*",
    element: <NotFound />,
  },
]);
