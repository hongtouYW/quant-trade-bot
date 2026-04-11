// Import Swiper styles
import "swiper/css";
import "swiper/css/pagination";
import "swiper/css/navigation";
// import required modules
import { Autoplay, Pagination } from "swiper/modules";

import { Swiper, SwiperClass, SwiperSlide } from "swiper/react";

import Image from "../../components/Image/Image";

import styles from "./Banner.module.css";
import { FC, useEffect, useState } from "react";
import { BannerType } from "../../utils/type";
import { useNavigate } from "react-router";

interface IBanner {
  slides: Array<any>;
}

const Banner: FC<IBanner> = ({ slides }) => {
  const navigate = useNavigate();
  const [swiperRef, setSwiperRef] = useState<SwiperClass>();

  const handleNavigate = (slide: any) => {
    if (slide) {
      if (slide.url) {
        window.location.href = slide.url;
      } else {
        navigate(`/video/list?actor_id=${slide.aid}`);
      }
    }
    // if (slide && slide.url) {
    //   window.location.href = slide.url;
    // } else {
    //   slide && navigate(`/video/list?actor_id=${slide.aid}`);
    // }
  };
  useEffect(() => {
    if (slides && slides.length > 0) {
      swiperRef && swiperRef?.autoplay.start();
    }
  }, [slides]);

  return (
    <div>
      <Swiper
        spaceBetween={10}
        centeredSlides={true}
        autoplay={{
          disableOnInteraction: false,
        }}
        loop={true}
        slidesPerView="auto"
        // initialSlide={1}
        grabCursor={true}
        // pagination={{
        //   enabled: true,
        //   clickable: true,
        // }}
        modules={[Autoplay, Pagination]}
        className={styles.bannerSwiper}
        onSwiper={setSwiperRef}
      >
        {slides.map((slide: BannerType) => (
          <SwiperSlide
            key={slide.id}
            className={styles.bannerSwiperSlide}
            onClick={() => handleNavigate(slide)}
          >
            <Image
              srcValue={slide.thumb}
              className={styles.bannerSwiperImg}
              layout="horizontal"
              imageSize="1200x500"
            />
          </SwiperSlide>
        ))}
      </Swiper>
    </div>
  );
};

export default Banner;
