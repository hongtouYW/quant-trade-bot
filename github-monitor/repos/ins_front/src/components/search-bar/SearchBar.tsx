import { FC, useState } from "react";
import styles from "./SearchBar.module.css";
import { useTranslation } from "react-i18next";
import u from "../../utils/utils";

interface ISearchBar {
  handleSearch: (e: any) => void;
  options: Array<any>;
}

const SearchBar: FC<ISearchBar> = ({ handleSearch, options }) => {
  const { t } = useTranslation();

  const [formData, setFormData] = useState({
    inputValue: "",
    selectValue: options[0].value,
  });

  const handleChange = (e: any) => {
    const { name, value } = e.target;
    setFormData((prevState) => ({ ...prevState, [name]: value }));
  };

  const submitForm = () => {
    handleSearch(formData);
  };
  return (
    // <form className={styles.searchBarForm} onSubmit={handleSearch}>
    <div className={styles.searchBarForm}>
      <div
        className={styles.searchBarContainer}
        style={{
          padding: u.isMobile() ? "3px 3px 3px 12px" : "5px 15px",
        }}
      >
        <select
          name="selectValue"
          className={styles.searchBarSelect}
          onChange={handleChange}
        >
          {options.map((option: any, index: any) => (
            <option value={option.value} key={index}>
              {t(option.locale)}
            </option>
          ))}
        </select>
        <input
          type="text"
          name="inputValue"
          className={styles.searchBarInput}
          onChange={handleChange}
        />
        {u.isMobile() ? (
          <button
            className={styles.searchText}
            type="submit"
            onClick={submitForm}
          >
            {t("search")}
          </button>
        ) : (
          <button
            className={styles.searchIcon}
            type="submit"
            onClick={submitForm}
          >
            <img src="/search.svg" alt="" width={16} height={15} />
          </button>
        )}
      </div>
    </div>
  );
};

export default SearchBar;
