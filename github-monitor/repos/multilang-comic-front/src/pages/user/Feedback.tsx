import { useState } from "react";
import { useTranslation } from "react-i18next";
import { useNavigate } from "react-router";
import UserWrapper from "../../components/UserWrapper";
import { useUser } from "../../contexts/user.context";
import useUserFeedback from "../../hooks/useUserFeedback";

const MAX_DESCRIPTION_LENGTH = 500;
const DEFAULT_SATISFACTION: 1 | 2 | 3 | null = null;

const Feedback = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { userInfo } = useUser();
  const { mutate, isPending } = useUserFeedback();

  const [selectedSatisfaction, setSelectedSatisfaction] = useState<1 | 2 | 3 | null>(DEFAULT_SATISFACTION);
  const [description, setDescription] = useState<string>("");

  const isSubmitEnabled = selectedSatisfaction && description.trim().length > 0;

  const satisfactions: { id: 1 | 2 | 3; label: string; icon: string }[] = [
    { id: 1, label: t("feedback.satisfied"), icon: "/assets/images/icon-satisfied.svg" },
    { id: 2, label: t("feedback.neutral"), icon: "/assets/images/icon-neutral.svg" },
    { id: 3, label: t("feedback.dissatisfied"), icon: "/assets/images/icon-dissatisfied.svg" },
  ];

  const handleDescriptionChange = (e: React.ChangeEvent<HTMLTextAreaElement>) => {
    const value = e.target.value;
    if (value.length <= MAX_DESCRIPTION_LENGTH) {
      setDescription(value);
    }
  };

  const handleSubmit = () => {
    if (!isSubmitEnabled || isPending) return;

    mutate(
      {
        token: userInfo.token || undefined,
        satisfaction: selectedSatisfaction,
        content: description.trim(),
      },
      {
        onSuccess: (res) => {
          if (res?.data?.code === 1) {
            setSelectedSatisfaction(DEFAULT_SATISFACTION);
            setDescription("");
          }
        },
      }
    );
  };

  return (
    <div className="h-full">
      <UserWrapper>
        <div className="flex items-center p-4 w-full text-greyscale-900 fixed top-0 bg-white h-14 leading-6 lg:hidden z-10">
          <img
            src="/assets/images/icon-cheveron-left.svg"
            alt="arrow-left"
            className="w-6 h-6 cursor-pointer"
            onClick={() => navigate("/")}
          />
          <p className="font-semibold text-center w-full">{t("user.feedbackSuggestions")}</p>
        </div>

        <div className="px-4 lg:px-6">
          <div>
            <h2 className="pt-6 pb-2 text-xl leading-[26px] text-greyscale-900 font-semibold lg:pt-6 lg:pb-2 lg:text-2xl lg:leading-8">
              {t("feedback.title")}
            </h2>

            <p className="text-xs text-greyscale-500 leading-[18px] lg:mt-1 lg:text-sm">
              {t("feedback.description1")}
            </p>

            <p className="mt-4 text-xs text-greyscale-500 leading-[18px] lg:text-sm">
              {t("feedback.description2")}
            </p>
          </div>

          <div>
            <h2 className="py-4 text-greyscale-900 font-semibold lg:py-6 lg:text-xl">
              {t("feedback.feedbackDescription")}
            </h2>

            <div className="relative lg:w-[600px]">
              <textarea
                value={description}
                onChange={handleDescriptionChange}
                maxLength={MAX_DESCRIPTION_LENGTH}
                placeholder={t("feedback.describeIssue")}
                className="w-full h-[160px] py-3 px-4 border border-greyscale-300 rounded-[8px] resize-none text-sm text-greyscale-900 placeholder:text-greyscale-400 focus:outline-none lg:text-base"
              />
              <span className="absolute bottom-3 right-3 text-[12px] text-greyscale-400">
                {description.length}/{MAX_DESCRIPTION_LENGTH}
              </span>
            </div>
          </div>

          <div>
            <h2 className="py-4 text-greyscale-900 font-semibold lg:py-6 lg:text-xl">
              {t("feedback.areYouSatisfied")}
            </h2>

            <div className="pt-4 flex justify-around items-center">
              {satisfactions.map((s) => (
                <div
                  key={s.id}
                  className={`flex flex-col items-center gap-1 cursor-pointer ${selectedSatisfaction === s.id ? 'grayscale-0 text-primary' : 'grayscale text-greyscale-500'}`}
                  onClick={() => setSelectedSatisfaction(s.id)}
                >
                  <img
                    className="w-8 h-8"
                    src={`${import.meta.env.VITE_INDEX_DOMAIN
                      }${s.icon}`}
                    alt={s.label}
                  />
                  <span className="text-sm  lg:text-base">{s.label}</span>
                </div>
              ))}
            </div>
          </div>

          {/* Submit Button */}
          <div className=" bg-white mt-6 mb-60 lg:mb-6">
            <button
              disabled={!isSubmitEnabled || isPending}
              onClick={handleSubmit}
              className={`w-full py-3 rounded-xl text-sm font-semibold
              ${isSubmitEnabled && !isPending
                  ? "bg-primary text-white cursor-pointer"
                  : "bg-greyscale-300 text-greyscale-400 cursor-not-allowed"} lg:w-44`}
            >
              {t("common.submit")}
            </button>
          </div>
        </div>
      </UserWrapper>
    </div>
  );
};

export default Feedback;
