// import { useRef } from "react";
// import { Swiper, SwiperSlide } from "swiper/react";
// import { Navigation } from "swiper/modules";
import { useTranslation } from "react-i18next";
import Image from "../../components/Image";
import useComicDetail from "../../hooks/useComicDetail";
import { useParams } from "react-router";

const CharacterIntroduction = () => {
  const { t } = useTranslation();
  const { comicId: id } = useParams();

  const { data: comicInfo } = useComicDetail({ mid: id || "" });
  return (
    <div className="flex flex-col gap-3 mt-6 max-xs:mt-3 max-xs:gap-2">
      <div className="flex items-center justify-between gap-2">
        <p className="text-xl font-medium">
          {t("common.characterIntroduction")}
        </p>
        {/* <div className="flex items-center gap-1 max-xs:hidden">
          <div ref={navigationPrevRef}>
            <div className="w-[35px] cursor-pointer">
              <img src="/assets/images/icon-arrow-left.svg" alt="left" />
            </div>
          </div>
          <div ref={navigationNextRef}>
            <div className="w-[35px] cursor-pointer">
              <img
                className=""
                src="/assets/images/icon-arrow-right.svg"
                alt="left"
              />
            </div>
          </div>
        </div> */}
      </div>
      {/* <div className="max-xs:hidden">
        <Swiper
          slidesPerView={3.5}
          // modules={[Autoplay, Pagination, Mousewheel]}
          navigation={{
            prevEl: navigationPrevRef.current,
            nextEl: navigationNextRef.current,
          }}
          onBeforeInit={(swiper: any) => {
            swiper.params.navigation.prevEl = navigationPrevRef.current;
            swiper.params.navigation.nextEl = navigationNextRef.current;
          }}
          modules={[Navigation]}
        >
          {actors?.map((item: any) => (
            <SwiperSlide key={item.id}>
              <div className="w-full h-full">
                <img
                  className="w-full h-full object-cover rounded-md"
                  src={`${config?.image_host}${item.name}`}
                  alt={item.name}
                />
              </div>
            </SwiperSlide>
          ))}
        </Swiper>
      </div> */}
      <div className="flex gap-3 overflow-x-auto h-[220px]">
        {comicInfo?.comic?.manhua_actors?.length &&
        comicInfo?.comic?.manhua_actors?.length > 0 ? (
          comicInfo?.comic?.manhua_actors?.map((item: any) => (
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
              <p className="font-medium">{item.name}</p>
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

export default CharacterIntroduction;
