import { useNavigate } from "react-router";
import Image from "./Image";
import { useTranslation } from "react-i18next";
// import { postStatus } from "../utils/enum";
import { humanizeNumber, isMaxMdScreen, isMaxSmScreen } from "../utils/utils";

interface PostProps {
  image: string;
  id: number;
  title: string;
  view?: number;
  vipcanread?: number; // 0=OFF,1=ON
  mhstatus?: number; // 0=连载,1=完结
  age18?: number; // 0=否,1=是
  last_chapter_title?: string;
  showRank?: boolean;
  index?: number;
  max_sort?: number;
  sort?: number;
  issole?: number; //热门:0=NO,1=YES
  new_release?: number; //最新: 0=NO,1=YES
}

const Post = ({
  item,
  fixedHeight,
  className,
  showRank,
  index,
  showTag = true,
  postDivClassName,
}: {
  item: PostProps;
  fixedHeight?: boolean;
  className?: string;
  index?: number;
  showRank?: boolean;
  showTag?: boolean;
  postDivClassName?: string;
}) => {
  const navigate = useNavigate();
  const { t } = useTranslation();

  const getPriorityTag = (item: any) => {
    if (item.xianmian)
      return (
        <p className="text-[10px] text-[#AF52DE] bg-[#EEDCFF] w-max px-[6px] rounded-[4px] border-[0.5px] border-[#AF52DE] max-xs:text-[10px] max-xs:px-1">
          {t("common.free")}
        </p>
      );
    if (item.mhstatus)
      return (
        <p className="text-[10px] text-primary-600 bg-[#FEE0EA] w-max px-[6px] rounded-[4px] border-[0.5px] border-primary-600 max-xs:text-[10px] max-xs:px-1">
          {t("common.finished")}
        </p>
      );
    if (item.issole)
      return (
        <>
          <p className="text-[10px] text-[#FF9500] bg-[#FFE2CB] w-max px-[6px] rounded-[4px] border-[0.5px] border-[#FF9500] flex items-center gap-[3px] max-xs:gap-[2px] max-xs:text-[10px] max-xs:px-1">
            <img
              className="w-2 h-2"
              src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/hot.svg`}
              alt="hot"
            />
            {t("common.hot")}
          </p>
        </>
      );
    if (item.new_release)
      return (
        <p className="text-[10px] text-[#007AFF] bg-[#CBEBFF] w-max px-[6px] rounded-[4px] border-[0.5px] border-[#007AFF] max-xs:text-[10px] max-xs:px-1">
          {t("common.newRelease")}
        </p>
      );

    return null;
  };

  return (
    <div
      className={`border border-white-smoke shadow-[0px_6px_15px_-2px_#10182814] bg-white cursor-pointer min-w-28 w-full max-w-[116px] rounded-lg xs:max-w-[136px] lg:min-w-[174px] lg:max-w-[185px] ${postDivClassName || ''}`}
      onClick={() => navigate(`/content/${item.id}`)}
    >
      {/* 封面 */}
      <div className="relative">
        <div
          className={`w-full ${fixedHeight
            ? `h-[250px] max-sm:h-[160px] max-xs:h-[155px] overflow-hidden`
            : "h-full"
            } ${className}`}
        >
          {/* <img
            className="w-full h-full object-cover rounded-t-xl max-xs:rounded-t-[6px] block"
            src={item.image}
            alt="post1"
          /> */}
          <Image
            className="w-full h-full object-cover rounded-t-xl max-xs:rounded-t-[6px] block"
            src={item.image}
            alt="post1"
            borderRadius="rounded-t-xl max-xs:rounded-t-[6px]"
            size={true}
            imageSize={isMaxSmScreen() ? "240x300" : isMaxMdScreen() ? "270x320" : "400x500" }
            loading="lazy"
          />
          {showRank && (
            <div className="absolute top-0 left-0 w-full h-full flex items-center justify-center">
              <div className="absolute -bottom-2 left-2 z-2 lg:left-5">
                <p
                  className={`text-[40px] font-bold [text-shadow:_-2px_-2px_0_white,_2px_-2px_0_white,_-2px_2px_0_white,_2px_2px_0_white] leading-[40px]
                  ${index === 1 && 'text-[#FE9901]'}
                  ${index === 2 && 'text-[#649CD8]'}
                  ${index === 3 && 'text-[#C37150]'}
                  ${index && index > 3 && 'text-greyscale-600'}
                  `}
                >
                  {index}
                </p>
              </div>
            </div>
          )}
        </div>
        {/* {item.vipcanread === 1 && (
          <p className="absolute top-0 left-0 bg-[#FACC15] px-3 py-1 rounded-tl-lg rounded-br-lg text-xs font-medium max-sm:text-[10px] max-sm:px-2 max-sm:py-[3px] max-xs:rounded-tl-[6px] max-xs:rounded-br-[6px]">
            VIP
          </p>
        )} */}
        {/* {item.mhstatus === 0 && (
          <div className="absolute bottom-0 left-0">
            <img
              className="w-10 h-10 max-sm:w-8 max-sm:h-8"
              src="/assets/images/serial.svg"
              alt="serial"
            />
            <p className="absolute top-1/2 left-1/2 -translate-x-[60%] -translate-y-[50%] w-max text-white text-[8px]">
              {t("common.serial")}
            </p>
          </div>
        )} */}
        {/* {item.age18 === 1 && (
          <p className="absolute top-2 right-2 bg-[#F54336] text-white text-xs font-medium rounded-full px-[6px] py-1 max-xs:text-[10px] max-xs:top-1 max-xs:right-1">
            18
          </p>
        )} */}
      </div>
      {/* 內容 */}
      <div className="py-2 px-3 max-sm:px-2 max-sm:py-1 max-xs:px-[6px] max-xs:py-[6px] max-xs:flex max-xs:flex-col max-xs:gap-1">
        <p className="font-semibold truncate max-sm:text-sm max-xs:leading-[14px] text-[#424242]" title={item.title}>
          {item.title}
        </p>
        <div className="flex items-center gap-2 max-sm:gap-1 pb-[6px] max-xs:pb-[2px]">
          <p className="text-sm text-primary-dark truncate max-sm:text-xs max-xs:leading-xs max-sm:max-w-[75px] max-xs:max-w-[50px]">
            {/* {item.last_chapter_title?.split("-")[0]} */}
            {t("common.ep", { ep: item.max_sort || item?.sort })}
          </p>
          <div className="flex items-center">
            <img
              className="w-5 h-5 max-sm:w-6 max-sm:h-6"
              src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/eye.svg`}
              alt="eye"
            />
            <p className="text-xs text-greyscale-500">
              {item.view ? humanizeNumber(item.view) : "0"}
            </p>
          </div>
        </div>
        {showTag && getPriorityTag(item)}
        {/* <div>
          <p className="text-[8px] leading-[8px] p-[3px] rounded-[4px] bg-primary-100 text-primary-600 w-max border-[0.3px] border-primary-600">
            Finished
          </p>
        </div> */}
      </div>
    </div>
  );
};

export default Post;
