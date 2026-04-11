import { useRef } from "react";

import { Swiper, SwiperSlide } from "swiper/react";
import { Autoplay, Pagination, Navigation } from "swiper/modules";
import Skeleton from "react-loading-skeleton";
import Image from "./Image";
import { NavLink } from "react-router";
import { isMobile } from "../utils/utils";

const Banner = ({
  pagination = false,
  banners = [],
}: {
  pagination?: boolean;
  banners?: any[];
}) => {
  const navigationPrevRef = useRef(null);
  const navigationNextRef = useRef(null);

  return (
    <div className="max-w-screen-xl mx-auto relative my-5 max-xs:my-0">
      {/* <div
        ref={navigationPrevRef}
        className="absolute -left-12 max-sm:left-2 max-xs:-left-2 top-1/2 -translate-y-1/2 z-10 max-xs:hidden"
      >
        <div className="w-[35px] max-sm:w-[25px]">
          <img src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-arrow-left.svg`} alt="left" />
        </div>
      </div> */}
      {/* <div
        ref={navigationNextRef}
        className="absolute -right-14 max-sm:right-2 max-xs:-right-2 top-1/2 -translate-y-1/2 z-10 max-xs:hidden"
      >
        <div className="w-[35px] max-sm:w-[25px]">
          <img src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-arrow-right.svg`} alt="left" />
        </div>
      </div> */}
      <div className="bannerSwiper w-full mx-auto xs:px-4">
        <Swiper
          spaceBetween={10}
          autoplay={{
            disableOnInteraction: false,
            delay: 5000,
          }}
          slidesPerView={isMobile() ? 1 : 3}
          pagination={{
            enabled: pagination,
            clickable: true,
          }}
          navigation={{
            prevEl: navigationPrevRef.current,
            nextEl: navigationNextRef.current,
          }}
          onBeforeInit={(swiper: any) => {
            swiper.params.navigation.prevEl = navigationPrevRef.current;
            swiper.params.navigation.nextEl = navigationNextRef.current;
          }}
          modules={[Autoplay, Pagination, Navigation]}
        >
          {banners?.length > 0 ? (
            banners?.map((item: any) => (
              <SwiperSlide key={item.id}>
                <NavLink
                  to={
                    item?.mid > 0 ? `/content/${item?.mid}` : item?.link || ""
                  }
                  // target="_blank"
                >
                  <div className="w-full h-full">
                    {/* <img
                    className="rounded-2xl h-full w-full object-cover"
                    src={`${config?.image_host}${item.image}`}
                    alt="banner"
                  /> */}
                    <Image
                      className="rounded-2xl h-full w-full object-cover max-xs:rounded-none max-h-[280px]"
                      src={item?.image}
                      alt="banner"
                      blurBg={false}
                    />
                  </div>
                </NavLink>
              </SwiperSlide>
            ))
          ) : (
            <div className="flex items-center justify-center gap-4">
              {Array.from({ length: isMobile() ? 1 : 3 }).map(
                (_, index: number) => {
                  return (
                    <div
                      className="w-[400px] h-[200px] rounded-2xl"
                      key={index}
                    >
                      <Skeleton className="w-[400px] h-[200px] rounded-2xl" />
                    </div>
                  );
                }
              )}
            </div>
          )}
        </Swiper>
      </div>
    </div>
  );
};

export default Banner;
