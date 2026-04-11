import { useTranslation } from "react-i18next";
import Image from "../../components/Image";
import useComicDetail from "../../hooks/useComicDetail";
import { useParams } from "react-router";

export const ClassicImage = () => {
  const { t } = useTranslation();
  const { comicId: id } = useParams();
  const { data: comicInfo } = useComicDetail({ mid: id || "" });

  return (
    <div className="flex flex-col gap-3 mt-6 max-xs:mt-3 max-xs:gap-2">
      <div className="flex items-center justify-between gap-2">
        <p className="text-xl font-medium">{t("common.classicImage")}</p>
      </div>
      <div className="flex gap-3 overflow-x-auto h-[220px]">
        {comicInfo?.comic?.manhua_highlights?.length &&
        comicInfo?.comic?.manhua_highlights?.length > 0 ? (
          comicInfo?.comic?.manhua_highlights?.map((item: any) => (
            <div key={item.id} className="w-[130px] min-w-[130px] h-[180px]">
              {/* <img
                className="w-full h-full object-cover rounded-md"
                src={`${config?.image_host}${item.img}`}
                alt={item.name}
              /> */}
              <Image
                className="w-full h-full object-cover rounded-md"
                src={item?.img}
                alt={item.name}
              />
            </div>
          ))
        ) : (
          <div className="w-full h-full flex items-center justify-center">
            <p className="text-gray-500">{t("common.comingSoon")}</p>
          </div>
        )}
      </div>
    </div>
  );
};
