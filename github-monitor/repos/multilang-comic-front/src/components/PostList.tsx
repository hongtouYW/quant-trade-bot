import { useTranslation } from "react-i18next";
import { useNavigate } from "react-router";
import Post from "./Post";

interface PostListProps {
  morePath?: string;
  more?: boolean;
  title?: string;
  list?: any;
  icon?: string;
  showTag?: boolean;
  isGoldTitle?: boolean;
}

const PostList = ({ morePath, more, title, list, showTag, isGoldTitle }: PostListProps) => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  return (
    <div className="py-2">
      <div className="max-w-screen-xl mx-auto">
        <div className="mt-4 flex items-center justify-between h-4">
          <div className="flex items-center gap-[6px]">
            {/* {icon && (
              <img
                className="w-6 h-6 max-sm:w-6 max-sm:h-5 max-xs:w-4 max-xs:h-4"
                src={icon}
                alt="icon"
              />
            )} */}
            <h4 className={`font-semibold text-center ${isGoldTitle ? 'text-[#C29553]' : 'text-greyscale-900'} lg:font-bold lg:text-2xl`}>
              {title}
            </h4>
          </div>
          {more && (
            <div
              className="flex items-center gap-1 cursor-pointer"
              onClick={() => {
                if (morePath) {
                  navigate(morePath);
                }
              }}
            >
              <p className="text-primary-dark text-lg font-medium max-sm:text-sm">
                {t("common.more")}
              </p>
              <img
                src={`${import.meta.env.VITE_INDEX_DOMAIN
                  }/assets/images/icon-cheveron-right.svg`}
                alt="right"
                className="max-sm:w-4 max-sm:h-4"
              />
            </div>
          )}
        </div>
        <div className="flex gap-1.5 overflow-x-auto scrollbar-hide py-4 -mr-4 pr-4 lg:gap-6 xl:grid xl:grid-cols-6">
          {list?.map((item: any, i: number) => (
            <Post
              key={item.id}
              item={item}
              fixedHeight={true}
              showTag={showTag}
              postDivClassName={`${i > 5 ? 'xl:hidden' : ''}`}
            />
          ))}
          {list && list.length < 6 &&
            Array.from({ length: 6 - list.length }).map((_, i) => (
              <div key={`placeholder-${i}`} className="w-full" />
            ))
          }
        </div>
      </div>
    </div>
  );
};

export default PostList;
