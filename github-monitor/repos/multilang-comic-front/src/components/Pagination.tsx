interface IPagination {
    active: number;
    size: number;
    step: number;
    total: number;
    onClickHandler: (val: any) => void;
  }

  const Pagination = ({
    active,
    size,
    step,
    total,
    onClickHandler,
  }: IPagination) => {
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

    return (
      <>
        {total > 0 && (
          <ul className="flex items-center gap-4 w-max my-6 mx-auto">
            {active > 1 ? (
              <li
                className={`pagination_prevPageItem text-2xl text-primary`}
                onClick={() => onClickHandler(active - 1)}
              >
                &#x2039;
              </li>
            ) : (
              <li
                className={`pagination_prevPageItem text-2xl pagination_disabled`}
              >
                &#x2039;
              </li>
            )}
            {size > showingNumbers + startNumber ? (
              <>
                <li
                  onClick={(e) => onClickHandler(e.currentTarget.textContent)}
                  className={`pagination_pageItem ${
                    active === 1 && "pagination_active"
                  }`}
                >
                  1
                </li>

                {needStartDots && <span>...</span>}
                {[...Array(showingNumbers)].map((_: any, index: any) => {
                  const number = (contentNumber = needStartDots
                    ? startArrayNumber
                    : startNumber);
                  startNumber++;
                  startArrayNumber++;
                  return (
                    <li
                      key={index}
                      className={`pagination_pageItem ${
                        active === contentNumber && "pagination_active"
                      }`}
                      onClick={(e) => onClickHandler(e.currentTarget.textContent)}
                    >
                      {number}
                    </li>
                  );
                })}
                {needEndDots && <span>...</span>}
                <li
                  className={`pagination_pageItem ${
                    active === size && "pagination_active"
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
                  className={`pagination_pageItem ${
                    active === startArrayNumber && "pagination_active"
                  }`}
                  onClick={(e) => onClickHandler(e.currentTarget.textContent)}
                >
                  {startArrayNumber++}
                </li>
              )))
            )}
            {active < size ? (
              <li
                className={`pagination_nextPageItem text-2xl text-primary cursor-pointer`}
                onClick={() => onClickHandler(active + 1)}
              >
                &#8250;
              </li>
            ) : (
              <li
                className={`pagination_nextPageItem text-2xl pagination_disabled`}
              >
                &#8250;
              </li>
            )}
          </ul>
        )}
      </>
    );
  };

  export default Pagination;
