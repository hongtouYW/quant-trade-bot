import Divider from "../components/Divider";
import { useTranslation } from "react-i18next";

const RefundAndCancellation = () => {
  const { t } = useTranslation();
  const systemEmail = import.meta.env.VITE_SYSTEM_EMAIL;

  return (
    <div className="bg-[#fafafa] max-xs:px-4">
      <div className="max-w-screen-xl mx-auto">
        <h2 className="text-lg font-medium py-4">
          {t("refundAndCancellation.refundAndCancellation")}
        </h2>
        <div className="bg-white rounded-lg p-6 shadow-md mb-6 max-xs:text-justify">
          <p>
            {t("refundAndCancellation.refundAndCancellationDescription1")}
          </p>
          <Divider />
          <div className="mt-6">
            <p className="text-lg font-medium">
              {t("refundAndCancellation.refundAndCancellation1")}
            </p>
            <ul className="list-disc mt-2 space-y-4 ml-6">
              <li>
                <p>
                  {t("refundAndCancellation.refundAndCancellation1Point1")}
                </p>
              </li>
              <li>
                <p>
                  {t("refundAndCancellation.refundAndCancellation1Point2")}
                </p>
              </li>
            </ul>
          </div>
          <Divider />
          <div className="mt-6">
            <p className="text-lg font-medium">
              {t("refundAndCancellation.refundAndCancellation2")}
            </p>
            <p>
              {t("refundAndCancellation.refundAndCancellation2Desc1")}
            </p>
            <p className="my-2">
              {t("refundAndCancellation.refundAndCancellation2Desc2")}
            </p>
            <ul className="list-disc mt-2 space-y-4 ml-6">
              <li>
                <p>{t("refundAndCancellation.refundAndCancellation2Point1")}</p>
              </li>
              <li>
                <p>{t("refundAndCancellation.refundAndCancellation2Point2")}</p>
              </li>
              <li>
                <p>{t("refundAndCancellation.refundAndCancellation2Point3")}</p>
              </li>
            </ul>
          </div>
          <Divider />
          <div className="mt-6">
            <p className="text-lg font-medium">
              {t("refundAndCancellation.refundAndCancellation3")}
            </p>
            <p>
              {t("refundAndCancellation.refundAndCancellation3Desc1", { systemEmail: systemEmail })}
            </p>
            <ul className="list-disc mt-2 space-y-4 ml-6">
              <li>
                <p>{t("refundAndCancellation.refundAndCancellation3Point1")}</p>
              </li>
              <li>
                <p>{t("refundAndCancellation.refundAndCancellation3Point2")}</p>
              </li>
              <li>
                <p>{t("refundAndCancellation.refundAndCancellation3Point3")}</p>
              </li>
              <li>
                <p>{t("refundAndCancellation.refundAndCancellation3Point4")}</p>
              </li>
            </ul>
            <p>
              {t("refundAndCancellation.refundAndCancellation3Desc2")}
            </p>
          </div>
          <Divider />
          <div className="mt-6">
            <p className="text-lg font-medium">
              {t("refundAndCancellation.refundAndCancellation4")}
            </p>
            <ul className="list-disc mt-2 space-y-4 ml-6">
              <li>
                <p>{t("refundAndCancellation.refundAndCancellation4Point1")}</p>
              </li>
              <li>
                <p>
                  {t("refundAndCancellation.refundAndCancellation4Point2")}
                </p>
              </li>
            </ul>
          </div>
          <Divider />
          <div className="mt-6">
            <p className="text-lg font-medium">
              {t("refundAndCancellation.refundAndCancellation5")}
            </p>
            <ul className="list-disc mt-2 space-y-4 ml-6">
              <li>
                <p>
                  {t("refundAndCancellation.refundAndCancellation5Point1")}
                </p>
              </li>
              <li>
                <p>
                  {t("refundAndCancellation.refundAndCancellation5Point2")}
                </p>
              </li>
              <li>
                <p>
                  {t("refundAndCancellation.refundAndCancellation5Point3")}
                </p>
              </li>
            </ul>
          </div>
          <Divider />
          <div className="mt-6">
            <p className="text-lg font-medium">
              {t("refundAndCancellation.refundAndCancellation6")}
            </p>
            <ul className="list-disc mt-2 space-y-4 ml-6">
              <li>
                <p>
                  {t("refundAndCancellation.refundAndCancellation6Point1", { systemEmail: systemEmail })}
                </p>
              </li>
              <li>
                <p>
                  {t("refundAndCancellation.refundAndCancellation6Point2")}
                </p>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  );
};

export default RefundAndCancellation;
