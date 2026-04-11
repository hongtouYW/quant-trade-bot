import { useEffect, useState } from "react";
import Header from "../../components/header/Header";

import styles from "./Order.module.css";
import useAxios from "../../../../hooks/useAxios";

import Cookies from "universal-cookie";
import { TOKEN_NAME } from "../../../../utils/constant";
import { OrderType } from "../../../../utils/type";
import { useTranslation } from "react-i18next";

const cookies = new Cookies();

const Order = () => {
  const { t } = useTranslation();
  const { req } = useAxios("vip/myOrder", "post");
  const [orderList, setOrderList] = useState([]);

  const handleGetMyOrderList = async (pageNum: number = 1) => {
    try {
      const token = cookies.get(TOKEN_NAME);
      const params = {
        token,
        page: pageNum,
        limit: 12,
      };
      const res = await req(params);

      if (res.data.code === 1) {
        const list = res?.data?.data?.data;

        setOrderList(list);
      }
    } catch (err) {
      console.log(err);
    }
  };

  useEffect(() => {
    handleGetMyOrderList();
  }, []);
  return (
    <>
      <Header title={t("purchaseHistory")} />
      <div className={styles.orderListTable}>
        <table className={styles.orderTable}>
          <thead>
            <tr>
              <th>{t("orderNumber")}</th>
              <th>{t("type")}</th>
              <th>{t("amount")}</th>
              <th>{t("createdTime")}</th>
              <th>{t("paymentStatus")}</th>
            </tr>
          </thead>
          <tbody>
            {orderList.map((order: OrderType, index: number) => {
              return (
                <tr key={index}>
                  <td>{order.order_sn}</td>
                  <td>{order.title}</td>
                  <td>{order.money}</td>
                  <td>{order.add_time}</td>
                  <td>{order.status === 1 ? `${t("paid")}` : `${t("unpaid")}`}</td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
      <div className={styles.orderRecord}>
        <div className={styles.orderRecordList}>
          {orderList.map((order: OrderType, index: number) => {
            return (
              <div className={styles.orderRecordItem} key={index}>
                <div className={styles.orderRecordLabel}>
                  <p>{t("orderNumber")}</p>
                  <p>{t("type")}</p>
                  <p>{t("amount")}</p>
                  <p>{t("createdTime")}</p>
                  <p>{t("paymentStatus")}</p>
                </div>
                <div className={styles.orderRecordValue}>
                  <p>{order.order_sn}</p>
                  <p>{order.title}</p>
                  <p>{order.money}</p>
                  <p>{order.add_time}</p>
                  <p>{order.status === 1 ? `${t("paid")}` : `${t("unpaid")}`}</p>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </>
  );
};

export default Order;
