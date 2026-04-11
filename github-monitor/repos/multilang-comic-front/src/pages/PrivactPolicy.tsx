import Divider from "../components/Divider";
import { useTranslation } from "react-i18next";

const PrivactPolicy = () => {
  const { t } = useTranslation();
  const systemName = import.meta.env.VITE_SYSTEM_NAME;

  return (
    <div className="bg-[#fafafa] max-xs:px-4">
      <div className="max-w-screen-xl mx-auto">
        <h2 className="text-lg font-medium py-4 max-xs:text-base">
          {t("privacyPolicy.privacyPolicy", { systemName: systemName })}
        </h2>
        <div className="bg-white rounded-lg p-6 shadow-md mb-6 max-xs:text-justify max-xs:text-sm max-xs:break-words">
          <p>{t("privacyPolicy.privacyPolicyDescription1", { systemName: systemName })}</p>
          <Divider />
          <div className="mt-6">
            <p className="text-lg font-medium max-xs:text-base">
              {t("privacyPolicy.privacyPolicy1", { systemName: systemName })}
            </p>
            <p className="max-xs:text-sm">{t("privacyPolicy.privacyPolicy1Desc1", { systemName: systemName })}</p>
            <ul className="list-disc mt-2 space-y-4 ml-6 max-xs:text-sm">
              <li>
                <p>{t("privacyPolicy.privacyPolicy1Point1", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("privacyPolicy.privacyPolicy1Point2", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("privacyPolicy.privacyPolicy1Point3", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("privacyPolicy.privacyPolicy1Point4", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("privacyPolicy.privacyPolicy1Point5", { systemName: systemName })}</p>
              </li>
            </ul>
          </div>
          <Divider />
          <div className="mt-6">
            <p className="text-lg font-medium max-xs:text-base">
              {t("privacyPolicy.privacyPolicy2", { systemName: systemName })}
            </p>
            <p>{t("privacyPolicy.privacyPolicy2Desc1", { systemName: systemName })}</p>
            <ul className="list-disc mt-2 space-y-4 ml-6 max-xs:text-sm">
              <li>
                <p>{t("privacyPolicy.privacyPolicy2Point1", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("privacyPolicy.privacyPolicy2Point2", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("privacyPolicy.privacyPolicy2Point3", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("privacyPolicy.privacyPolicy2Point4", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("privacyPolicy.privacyPolicy2Point5", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("privacyPolicy.privacyPolicy2Point6", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("privacyPolicy.privacyPolicy2Point7", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("privacyPolicy.privacyPolicy2Point8", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("privacyPolicy.privacyPolicy2Point9", { systemName: systemName })}</p>
              </li>
            </ul>
            <p className="mt-4">
              {t("privacyPolicy.privacyPolicy2Desc2", { systemName: systemName })}
            </p>
          </div>
          <Divider />
          <div className="mt-6">
            <p className="text-lg font-medium max-xs:text-base">
              {t("privacyPolicy.privacyPolicy3", { systemName: systemName })}
            </p>
            <p className="mt-2 max-xs:text-sm">
              {t("privacyPolicy.privacyPolicy3Desc1", { systemName: systemName })}
            </p>
            <ul className="list-disc mt-2 space-y-4 ml-6 max-xs:text-sm">
              <li>
                <p>{t("privacyPolicy.privacyPolicy3Point1", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("privacyPolicy.privacyPolicy3Point2", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("privacyPolicy.privacyPolicy3Point3", { systemName: systemName })}</p>
              </li>
            </ul>
            <p className="mt-4 max-xs:text-sm">
              {t("privacyPolicy.privacyPolicy3Desc2", { systemName: systemName })}
            </p>
            <ul className="list-disc mt-2 space-y-4 ml-6 max-xs:text-sm">
                <li>
                    <p>{t("privacyPolicy.privacyPolicy3Desc2Point1", { systemName: systemName })}</p>
                </li>
                <li>
                    <p>{t("privacyPolicy.privacyPolicy3Desc2Point2", { systemName: systemName })}</p>
                </li>
                <li>
                    <p>{t("privacyPolicy.privacyPolicy3Desc2Point3", { systemName: systemName })}</p>
                </li>
            </ul>
          </div>
          <Divider />
          <div className="mt-6">
            <p className="text-lg font-medium max-xs:text-base">
              {t("privacyPolicy.privacyPolicy4", { systemName: systemName })}
            </p>
            <p className="mt-4 max-xs:text-sm">
              {t("privacyPolicy.privacyPolicy4Desc1", { systemName: systemName })}
            </p>
            <p className="mt-4 max-xs:text-sm">
              {t("privacyPolicy.privacyPolicy4Desc2", { systemName: systemName })}
            </p>
          </div>
          <Divider />
          <div className="mt-6">
            <p className="text-lg font-medium max-xs:text-base">
              {t("privacyPolicy.privacyPolicy5", { systemName: systemName })}
            </p>
            <p className="mt-2 max-xs:text-sm">
              {t("privacyPolicy.privacyPolicy5Desc1", { systemName: systemName })}
            </p>
            <ul className="list-disc mt-2 space-y-4 ml-6">
              <li>
                <p>{t("privacyPolicy.privacyPolicy5Point1", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("privacyPolicy.privacyPolicy5Point2", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("privacyPolicy.privacyPolicy5Point3", { systemName: systemName })}</p>
              </li>
            </ul>
          </div>
          <Divider />
          <div className="mt-6">
            <p className="text-lg font-medium max-xs:text-base">
              {t("privacyPolicy.privacyPolicy6", { systemName: systemName })}
            </p>
            <p className="mt-2 max-xs:text-sm">
              {t("privacyPolicy.privacyPolicy6Desc1", { systemName: systemName })}
            </p>
          </div>
          <Divider />
          <div className="mt-6">
            <p className="text-lg font-medium max-xs:text-base">
              {t("privacyPolicy.privacyPolicy7", { systemName: systemName })}
            </p>
            <p className="mt-2 max-xs:text-sm">
              {t("privacyPolicy.privacyPolicy7Desc1", { systemName: systemName })}
            </p>
          </div>
          <Divider />
          <div className="mt-6">
            <p className="text-lg font-medium max-xs:text-base">
              {t("privacyPolicy.privacyPolicy8", { systemName: systemName })}
            </p>
            <p className="mt-2 max-xs:text-sm">
              {t("privacyPolicy.privacyPolicy8Desc1", { systemName: systemName })}
            </p>
            <p className="text-lg font-medium mt-2 max-xs:text-sm">
              {t("privacyPolicy.privacyPolicy8Desc2", { systemName: systemName })}
            </p>
            <p className="mt-2 max-xs:text-sm">
              {t("privacyPolicy.privacyPolicy8Point1", { systemName: systemName })}
            </p>
            <p className="mt-2 max-xs:text-sm">
              {t("privacyPolicy.privacyPolicy8Point2", { systemName: systemName })}
            </p>
            <p className="mt-2 max-xs:text-sm">
              {t("privacyPolicy.privacyPolicy8Point3", { systemName: systemName })}
            </p>
          </div>
          <Divider />
          <div className="mt-6">
            <p className="text-lg font-medium max-xs:text-base">
              {t("privacyPolicy.privacyPolicy9", { systemName: systemName })}
            </p>
            <p className="mt-2 max-xs:text-sm">
              {t("privacyPolicy.privacyPolicy9Desc1", { systemName: systemName })}
            </p>
            <ol className="list-decimal mt-2 space-y-4 ml-6">
              <li>
                <p>{t("privacyPolicy.privacyPolicy9Point1", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("privacyPolicy.privacyPolicy9Point2", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("privacyPolicy.privacyPolicy9Point3", { systemName: systemName })}</p>
              </li>
            </ol>
            <p className="mt-4 max-xs:text-sm">
              {t("privacyPolicy.privacyPolicy9Desc2", { systemName: systemName })}
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};

export default PrivactPolicy;
