import Modal from "../Modal";
import { useUser } from "../../contexts/user.context";
import { useResourceContext } from "../../contexts/resource.context";

import { useNavigate } from "react-router";
import {
  fetchData,
  formatImgUrl,
  imgDecrypt,
  isMobile,
} from "../../utils/utils";
import { useEffect, useState } from "react";

export const NoticeModal = () => {
  const navigate = useNavigate();
  const { notice } = useResourceContext();
  const { userInfo, setIsOpenUserAuthModal } = useUser();

  const [imageUrl, setImageUrl] = useState<string>(
    `${import.meta.env.VITE_INDEX_DOMAIN}//assets/images/default-image-2.png`
  );

  const { isOpenNoticeModal, setIsOpenNoticeModal } = useUser();

  // console.log("notice", notice[0]);

  const closeModal = () => {
    setIsOpenNoticeModal(false);
    localStorage.setItem('noticeModal_closedAt', Date.now().toString());
  };

  const fmtImageUrl = async (src: string) => {
    const imageUrlKey =
      import.meta.env.VITE_STATIC_IMAGE_HOST || "https://toonmh.a791243y.com/";
    // console.log("imageUrlKey", imageUrlKey);

    const imgSrc = await formatImgUrl(imageUrlKey, src, true);
    if (imgSrc) {
      return setImageUrl(imgSrc);
    }

    if (imageUrlKey !== "" && src !== "") {
      const encryptUrls = `${imageUrlKey}/${src}.txt`;

      const res = await fetchData(encryptUrls);

      let __decrypted = "";
      if (res) {
        __decrypted = res.indexOf("data") >= 0 ? res : imgDecrypt(res);
        return setImageUrl(__decrypted);
      }
    }
  };

  useEffect(() => {
    if (notice?.[0]?.image) {
      fmtImageUrl(isMobile() ? notice[0].mobile_image : notice[0].image);
    }
  }, [notice]);

  if (notice?.length <= 0) {
    return null;
  }
  // console.log("imageUrl", imageUrl);

  return (
    <>
      {notice?.length > 0 && (
        <Modal open={isOpenNoticeModal} width={400}>
          <div className="relative w-full h-full">
            <span
              className="text-[30px] font-medium leading-[20px] cursor-pointer text-white absolute top-4 right-4"
              onClick={closeModal}
            >
              &times;
            </span>
            <div
              className="w-full h-full"
              onClick={() => {
                if (!userInfo?.token && notice[0].request_login == 1) {
                  setIsOpenUserAuthModal({
                    open: true,
                    type: "login",
                  });
                  closeModal();
                  return;
                }
                navigate(notice[0].url);
                closeModal();
              }}
            >
              <img
                src={imageUrl}
                alt={notice[0].title}
                className="w-full h-full object-cover"
              />
            </div>
          </div>
        </Modal>
      )}
    </>
  );
};
