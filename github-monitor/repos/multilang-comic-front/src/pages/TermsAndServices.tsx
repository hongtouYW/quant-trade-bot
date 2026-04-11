import Divider from "../components/Divider";
import { useTranslation } from "react-i18next";

const TermsAndServices = () => {
  const { t } = useTranslation();
  const systemName = import.meta.env.VITE_SYSTEM_NAME;
  const systemEmail = import.meta.env.VITE_SYSTEM_EMAIL;

  return (
    <div className="bg-[#fafafa] max-xs:px-4">
      <div className="max-w-screen-xl mx-auto">
        <h2 className="text-lg font-medium py-4 max-xs:text-base">
          {systemName} {t("termsAndServices.serviceTerms")}
        </h2>
        <div className="bg-white rounded-lg p-6 shadow-md mb-6 max-xs:text-justify max-xs:text-sm max-xs:break-words">
          <p>
            {t("termsAndServices.serviceTermsDescription1", {
              systemName: systemName,
            })}
          </p>
          <p className="mt-2">
            {t("termsAndServices.serviceTermsDescription2", { systemName: systemName })}
          </p>
          <Divider />
          <div className="mt-6">
            <p className="text-lg font-medium max-xs:text-base">
              {t("termsAndServices.serviceTerms1", { systemName: systemName })}
            </p>
            <ul className="list-disc mt-2 space-y-4 ml-6 max-xs:text-sm">
              <li>
                <p>{t("termsAndServices.serviceTerms1Point1", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms1Point2", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms1Point3", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms1Point4", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms1Point5", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms1Point6", { systemName: systemName })}</p>
              </li>
            </ul>
          </div>
          <Divider />
          <div className="mt-6">
            <p className="text-lg font-medium max-xs:text-base">
              {t("termsAndServices.serviceTerms2", { systemName: systemName })}
            </p>
            <p className="max-xs:text-sm">
              {t("termsAndServices.serviceTerms2Point1", { systemName: systemName })}
            </p>
          </div>
          <Divider />
          <div className="mt-6">
            <p className="text-lg font-medium max-xs:text-base">
              {t("termsAndServices.serviceTerms3", { systemName: systemName })}
            </p>
            <p>{t("termsAndServices.serviceTerms3Desc", { systemName: systemName })}</p>
            <ul className="list-disc mt-2 space-y-4 ml-6 max-xs:text-sm">
              <li>
                <p>{t("termsAndServices.serviceTerms3Point1", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms3Point2", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms3Point3", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms3Point4", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms3Point5", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms3Point6", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms3Point7", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms3Point8", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms3Point9", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms3Point10", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms3Point11", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms3Point12", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms3Point13", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms3Point14", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms3Point15", { systemName: systemName })}</p>
              </li>
            </ul>
          </div>
          <Divider />
          <div className="mt-6">
            <p className="text-lg font-medium max-xs:text-base">
              {t("termsAndServices.serviceTerms4", { systemName: systemName })}
            </p>
            <p className="max-xs:text-sm">
              {t("termsAndServices.serviceTerms4Desc", { systemName: systemName })}
            </p>
          </div>
          <Divider />
          <div className="mt-6">
            <p className="text-lg font-medium max-xs:text-base">
              {t("termsAndServices.serviceTerms5", { systemName: systemName })}
            </p>
            <p className="max-xs:text-sm">
              {t("termsAndServices.serviceTerms5Desc", { systemName: systemName })}
            </p>
          </div>
          <Divider />
          <div className="mt-6">
            <p className="text-lg font-medium max-xs:text-base">
              {t("termsAndServices.serviceTerms6", { systemName: systemName })}
            </p>
            <p className="max-xs:text-sm">
              {t("termsAndServices.serviceTerms6Desc", { systemName: systemName })}
            </p>
          </div>
          <Divider />
          <div className="mt-6">
            <p className="text-lg font-medium max-xs:text-base">
              {t("termsAndServices.serviceTerms7", { systemName: systemName })}
            </p>
            <p className="max-xs:text-sm">
              {t("termsAndServices.serviceTerms7Desc", { systemName: systemName })}
            </p>
            <ul className="list-disc mt-2 space-y-4 ml-6 max-xs:text-sm">
              <li>
                <p>{t("termsAndServices.serviceTerms7Point1", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms7Point2", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms7Point3", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms7Point4", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms7Point5", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms7Point6", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms7Point7", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms7Point8", { systemName: systemName, systemEmail: systemEmail })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms7Point9", { systemName: systemName })}</p>
              </li>
            </ul>
          </div>
          <Divider />
          <div className="mt-6">
            <p className="text-lg font-medium max-xs:text-base">
              {t("termsAndServices.serviceTerms8", { systemName: systemName })}
            </p>
            <p className="max-xs:text-sm">
              {t("termsAndServices.serviceTerms8Desc", { systemName: systemName })}
            </p>
          </div>
          <Divider />
          <div className="mt-6">
            <p className="text-lg font-medium max-xs:text-base">
              {t("termsAndServices.serviceTerms9", { systemName: systemName })}
            </p>
            <ul className="list-disc mt-2 space-y-4 ml-6 max-xs:text-sm">
              <li>
                <p>{t("termsAndServices.serviceTerms9Point1", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms9Point2", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms9Point3", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms9Point4", { systemName: systemName })}</p>
              </li>
            </ul>
          </div>
          <Divider />
          <div className="mt-6">
            <p className="text-lg font-medium max-xs:text-base">
              {t("termsAndServices.serviceTerms10", { systemName: systemName })}
            </p>
            <ul className="list-disc mt-2 space-y-4 ml-6 max-xs:text-sm">
              <li>
                <p>{t("termsAndServices.serviceTerms10Point1", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms10Point2", { systemName: systemName, systemEmail: systemEmail })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms10Point3", { systemName: systemName })}</p>
              </li>
            </ul>
          </div>
          <Divider />
          <div className="mt-6">
            <p className="text-lg font-medium max-xs:text-base">
              {t("termsAndServices.serviceTerms11", { systemName: systemName })}
            </p>
            <ul className="list-disc mt-2 space-y-4 ml-6 max-xs:text-sm">
              <li>
                <p>{t("termsAndServices.serviceTerms11Point1", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms11Point2", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms11Point3", { systemName: systemName })}</p>
              </li>
              <li>
                <p>{t("termsAndServices.serviceTerms11Point4", { systemName: systemName })}</p>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  );
};

export default TermsAndServices;
