import { FC, useState } from "react";
import styles from "./Pagination.module.css";
import { useTranslation } from "react-i18next";

interface IPagination {
  active: number;
  size: number;
  step: number;
  total: number;
  limit: number;
  className?: string;
  onClickHandler: (val: any) => void;
}

const Pagination: FC<IPagination> = ({
  active,
  size,
  step,
  total,
  limit,
  className,
  onClickHandler,
}) => {
  const { t } = useTranslation();
  const [goToPage, setGoToPage] = useState("");
  const showingNumbers = step * 2 + 1;
  let startNumber = 2;
  let startArrayNumber = step;

  let needStartDots = false;
  let needEndDots = false;

  if (active > step) {
    startArrayNumber = active - step;

    needStartDots = active > step + startNumber ? true : false;
  }

  if (size > showingNumbers) {
    needEndDots = size > active + step + 1 ? true : false;

    if (size < active + step + 1) {
      startArrayNumber = size - showingNumbers;
    }
  }

  let contentNumber;

  const handleInputOnChange = (e: any) => {
    const value = e?.target?.value;
    setGoToPage(value);
  };

  return (
    <div className={className}>
      <div className={styles.paginationInfo}>
        <p>
          {/* 商品中 */}
          <span className={styles.paginationNumSize}>
            {`${active * limit - limit + 1}`} -
            {` ${size === active ? total : active * limit}`}
          </span>
          <span>{` ${t("of")} ${total} ${t("items")}`}</span>
          {/* タイトルを表示 */}
        </p>
      </div>
      {total > 0 && (
        <div className={styles.paginationContainer}>
          <ul className={styles.pagination}>
            {active > 1 ? (
              <li
                className={`${styles.pageItem} ${styles.pageItemArrow}`}
                onClick={() => onClickHandler(active - 1)}
              >
                {/* {t("previousPage")} */}
                &#8249;
              </li>
            ) : (
              <li
                className={`${styles.pageItem} ${styles.pageItemArrow} ${styles.disabled}`}
              >
                {/* {t("previousPage")} */}
                &#8249;
              </li>
            )}
            {size > showingNumbers + startNumber ? (
              <>
                <li
                  onClick={(e) => onClickHandler(e.currentTarget.textContent)}
                  className={`${styles.pageItem} ${
                    active === 1 && `${styles.active}`
                  }`}
                >
                  1
                </li>

                {needStartDots && <span className={styles.dot}>...</span>}
                {[...Array(showingNumbers)].map((_, index) => {
                  const number = (contentNumber = needStartDots
                    ? startArrayNumber
                    : startNumber);
                  startNumber++;
                  startArrayNumber++;
                  return (
                    <li
                      key={index}
                      className={`${styles.pageItem} ${
                        active === contentNumber && `${styles.active}`
                      }`}
                      onClick={(e) =>
                        onClickHandler(e.currentTarget.textContent)
                      }
                    >
                      {number}
                    </li>
                  );
                })}
                {needEndDots && <span className={styles.dot}>...</span>}
                <li
                  className={`${styles.pageItem} ${
                    active === size && `${styles.active}`
                  }`}
                  onClick={(e) => onClickHandler(e.currentTarget.textContent)}
                >
                  {size}
                </li>
              </>
            ) : (
              ((startArrayNumber = 1),
              [...Array(size)].map((_, index) => (
                <li
                  key={index}
                  className={` ${styles.pageItem} ${
                    active === startArrayNumber && `${styles.active}`
                  }`}
                  onClick={(e) => onClickHandler(e.currentTarget.textContent)}
                >
                  {startArrayNumber++}
                </li>
              )))
            )}
            {active < size ? (
              <li
                className={`${styles.pageItem} ${styles.pageItemArrow}`}
                onClick={() => onClickHandler(active + 1)}
              >
                {/* {t("nextPage")} */}
                &#8250;
              </li>
            ) : (
              <li
                className={`${styles.pageItem} ${styles.pageItemArrow} ${styles.disabled}`}
              >
                {/* {t("nextPage")} */}
                &#8250;
              </li>
            )}
          </ul>
          <div className="goToContainer">
            <input
              type="number"
              className={styles.paginationInput}
              onChange={handleInputOnChange}
              value={goToPage}
            />
            <button
              className={styles.paginationGoBtn}
              onClick={() => {
                onClickHandler(goToPage);
                setGoToPage("");
              }}
            >
              前往
            </button>
          </div>
        </div>
      )}
    </div>
  );
};

export default Pagination;
