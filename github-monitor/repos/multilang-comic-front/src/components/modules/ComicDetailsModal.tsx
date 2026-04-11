import { useTranslation } from "react-i18next";
import Modal from "../Modal";
import { toFmt } from "../../utils/utils";

const ComicDetailsModal = ({
  open,
  onClose,
  comicInfo,
}: {
  open: boolean;
  onClose: () => void;
  comicInfo: any;
}) => {
  const { t } = useTranslation();

  return (
    <>
      <Modal open={open} width={810} className="bg-white rounded-2xl">
        <div className="px-4 py-6">
          <h3 className="text-xl font-semibold leading-7">{t("common.comicDetails")}</h3>

          <div className="mt-6 flex flex-col gap-6 overflow-y-auto max-h-[800px]">
            <div className="flex flex-col gap-1">
              <p className="text-greyscale-900 font-semibold leading-4">
                {t("common.comicName")}
              </p>
              <p className="text-greyscale-500 font-normal leading-4">
                {comicInfo?.comic?.title}
              </p>
            </div>

            <div className="flex flex-col gap-1">
              <p className="text-greyscale-900 font-semibold leading-4">
                {t("common.author")}
              </p>
              <p className="text-greyscale-500 font-normal leading-4">
                {comicInfo?.comic?.auther}
              </p>
            </div>

            <div className="flex flex-col gap-1">
              <p className="text-greyscale-900 font-semibold leading-4">
                {t("common.description")}
              </p>
              <p className="text-greyscale-500 font-normal leading-4">
                {comicInfo?.comic?.desc}
              </p>
            </div>

            <div className="flex flex-col gap-1">
              <p className="text-greyscale-900 font-semibold leading-4">
                {t("common.updatedDate")}
              </p>
              <p className="text-greyscale-500 font-normal leading-4">
                {toFmt((comicInfo?.comic?.update_time || 0) * 1000 || 0, "DD/MM/YYYY")}
              </p>
            </div>

            <div className="flex flex-col gap-1">
              <p className="text-greyscale-900 font-semibold leading-4">
                {t("common.latestUpdate1")}
              </p>
              <p className="text-greyscale-500 font-normal leading-4">
                {comicInfo?.comic?.last_chapter_title}
              </p>
            </div>

            <div className="flex flex-col gap-1">
              <p className="text-greyscale-900 font-semibold leading-4">
                {t("common.genre")}
              </p>
              <div className="mt-1">
                <p className="text-xs text-greyscale-600 py-[6px] px-2 bg-greyscale-200 rounded-md w-max">
                  #{comicInfo?.comic?.ticai_name}
                </p>
              </div>
            </div>

            <div className="flex flex-col gap-1">
              <p className="text-greyscale-900 font-semibold leading-4">
                {t("common.keywords")}
              </p>
              <div className="mt-1">
                <p className="text-xs text-greyscale-600 py-[6px] px-2 bg-greyscale-200 rounded-md w-max max-w-[280px] truncate">
                  #{comicInfo?.comic?.keyword}
                </p>
              </div>
            </div>
          </div>

          <div className="mt-6">
            <button className="bg-primary text-white px-10 py-2 rounded-xl w-full cursor-pointer" onClick={onClose}>{t("common.close")}</button>
          </div>
        </div>
      </Modal>
    </>
  );
};

export default ComicDetailsModal;
