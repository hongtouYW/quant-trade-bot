import React, { useEffect, useState } from "react";
import Input from "../../../components/Input";
import { Country, State } from "country-state-city";
import { useTranslation } from "react-i18next";

const PaymentForm = ({
  onChangePaymentFormValue,
  isCreditCardFormError,
  setIsCreditCardFormError,
}: {
  onChangePaymentFormValue: (form: any) => void;
  isCreditCardFormError: any;
  setIsCreditCardFormError: (form: any) => void;
}) => {
  const { t } = useTranslation();
  const [paymentForm, setPaymentForm] = useState({
    card_ccno: "",
    card_month_year: "",
    card_ccvv: "",
    bill_fullname: "",
    bill_address: "",
    bill_country: "",
    bill_state: "",
    bill_city: "",
    bill_zip: "",
    bill_email: "",
    bill_phone: "",
    card_type: "dc",
  });

  // const [isCreditCardFormError, setIsCreditCardFormError] = useState({
  //   card_ccno: false,
  //   card_month_year: false,
  //   card_ccvv: false,
  //   bill_fullname: false,
  //   bill_address: false,
  //   bill_country: false,
  //   bill_state: false,
  //   bill_city: false,
  //   bill_zip: false,
  //   bill_email: false,
  // });

  const [countries, setCountries] = useState([]);
  const [states, setStates] = useState([]);
  const [selectedCountry, setSelectedCountry] = useState<any>(null);
  const [selectedState, setSelectedState] = useState<any>("");
  const [searchCountry, setSearchCountry] = useState("");
  const [searchState, setSearchState] = useState("");
  const [openCountry, setOpenCountry] = useState(false);
  const [openState, setOpenState] = useState(false);

  const isExpired = (expiry: string) => {
    // Expecting format MM/YY
    if (!/^\d{2}\/\d{2}$/.test(expiry)) return true;

    const [mm, yy] = expiry.split("/").map(Number);
    const currentYear = new Date().getFullYear() % 100; // Last two digits
    const currentMonth = new Date().getMonth() + 1; // Month 1–12

    // Check valid month
    if (mm < 1 || mm > 12) return true;

    // Expired check
    if (yy < currentYear) return true;
    if (yy === currentYear && mm < currentMonth) return true;

    return false; // Not expired
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const name = e.target.name;
    let value = e.target.value;

    if (name === "card_ccno") {
      value = value.replace(/\D/g, "").substring(0, 16);
      value = value.replace(/(\d{4})(?=\d)/g, "$1 ");
      if (value.length === 19) {
        setIsCreditCardFormError({
          ...isCreditCardFormError,
          card_ccno: false,
        });
      }
    }

    if (name === "card_month_year") {
      value = value.replace(/\D/g, "").substring(0, 4);
      // console.log("value", value);
      if (value.length > 2) value = value.replace(/(\d{2})(\d{1,2})/, "$1/$2");
      if (value.length === 5) {
        setIsCreditCardFormError({
          ...isCreditCardFormError,
          card_month_year: isExpired(value),
        });
      }
    }

    if (name === "card_ccvv") {
      value = value.replace(/\D/g, "").substring(0, 3);
      if (value.length === 3) {
        setIsCreditCardFormError({
          ...isCreditCardFormError,
          card_ccvv: false,
        });
      }
    }

    setPaymentForm((prev) => ({ ...prev, [name]: value }));
    onChangePaymentFormValue({ ...paymentForm, [name]: value });
  };

  const handleOnBlur = (e: React.FocusEvent<HTMLInputElement>) => {
    const { name, value } = e.target;

    switch (name) {
      case "card_ccno":
        if (value.length === 19) {
          setIsCreditCardFormError({
            ...isCreditCardFormError,
            card_ccno: false,
          });
        } else {
          setIsCreditCardFormError({
            ...isCreditCardFormError,
            card_ccno: true,
          });
        }
        break;
      case "card_month_year":
        if (value.length === 5) {
          setIsCreditCardFormError({
            ...isCreditCardFormError,
            card_month_year: false,
          });
        } else {
          setIsCreditCardFormError({
            ...isCreditCardFormError,
            card_month_year: true,
          });
        }
        break;
      case "card_ccvv":
        if (value.length === 3) {
          setIsCreditCardFormError({
            ...isCreditCardFormError,
            card_ccvv: false,
          });
        } else {
          setIsCreditCardFormError({
            ...isCreditCardFormError,
            card_ccvv: true,
          });
        }
        break;
      default:
        if (name && !value) {
          setIsCreditCardFormError({
            ...isCreditCardFormError,
            [name]: true,
          });
        } else {
          setIsCreditCardFormError({
            ...isCreditCardFormError,
            [name]: false,
          });
        }
        break;
    }
  };

  const filteredCountries = countries.filter((country: any) =>
    country?.name?.toLowerCase().includes(searchCountry.toLowerCase())
  );

  const filteredStates = states.filter((state: any) =>
    state?.name?.toLowerCase().includes(searchState.toLowerCase())
  );

  // Load countries
  useEffect(() => {
    const allCountries: any = Country.getAllCountries().map((c) => ({
      name: c.name,
      code: c.isoCode,
    }));
    setCountries(allCountries);
  }, []);

  // Load states when country selected
  useEffect(() => {
    if (selectedCountry) {
      const allStates: any = State.getStatesOfCountry(selectedCountry.code).map(
        (s) => ({
          name: s.name,
          code: s.isoCode,
        })
      );
      setStates(allStates);
    } else {
      setStates([]);
    }
  }, [selectedCountry]);

  return (
    <>
      <div className="my-4 w-full">
        <div className="flex items-center gap-1 mb-4">
          <p className="font-medium">{t("user.creditCardInformation")}</p>
        </div>
        <div className="flex space-x-4">
          {/* Credit */}
          <label className="flex items-center space-x-2 cursor-pointer">
            <input
              type="radio"
              name="card_type"
              value="cc"
              className="appearance-none h-5 w-5 border border-greyscale-500 rounded-full checked:bg-white checked:border-primary-dark checked:border-[6px] focus:outline-none"
              onChange={handleChange}
            />
            <span>Credit</span>
          </label>

          {/* Debit (default) */}
          <label className="flex items-center space-x-2 cursor-pointer">
            <input
              type="radio"
              name="card_type"
              value="dc"
              defaultChecked
              className="appearance-none h-5 w-5 border border-greyscale-500 rounded-full checked:bg-white checked:border-primary-dark checked:border-[6px] focus:outline-none"
              onChange={handleChange}
            />
            <span>Debit</span>
          </label>
        </div>
        <div className="my-4 w-full">
          <Input
            label={t("user.cardNumber")}
            type="text"
            name="card_ccno"
            placeholder="1234 1234 1234 1234"
            value={paymentForm.card_ccno}
            onChange={handleChange}
            labelClassName="font-normal"
            className={isCreditCardFormError.card_ccno ? "border-red-500" : ""}
            addonAfterIcon={
              <div className="flex items-center gap-1">
                <img
                  className="w-10"
                  src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-visa.svg`}
                  alt="visa"
                />
                <img
                  className="w-10"
                  src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-master-card.svg`}
                  alt="mastercard"
                />
              </div>
            }
            onBlur={handleOnBlur}
          />
          {isCreditCardFormError.card_ccno && (
            <p className="text-red-500 text-sm">Card number is required</p>
          )}
          <div className="grid grid-cols-2 gap-2 my-2 w-full">
            <div className="w-full">
              <Input
                label={t("user.expiryDate")}
                type="text"
                name="card_month_year"
                placeholder="MM/YY"
                value={paymentForm.card_month_year}
                onChange={handleChange}
                onBlur={handleOnBlur}
                labelClassName="font-normal"
                className={
                  isCreditCardFormError.card_month_year ? "border-red-500" : ""
                }
              />
              {isCreditCardFormError.card_month_year && (
                <p className="text-red-500 text-sm">
                  Expiry date is required or expired
                </p>
              )}
            </div>
            <div className="w-full">
              <Input
                label={t("user.cvv")}
                type="text"
                name="card_ccvv"
                placeholder="123"
                value={paymentForm.card_ccvv}
                onChange={handleChange}
                onBlur={handleOnBlur}
                labelClassName="font-normal"
                className={
                  isCreditCardFormError.card_ccvv ? "border-red-500" : ""
                }
              />
              {isCreditCardFormError.card_ccvv && (
                <p className="text-red-500 text-sm">CVV is required</p>
              )}
            </div>
          </div>
        </div>
      </div>
      <div className="my-4 w-full">
        <div className="flex items-center gap-1 mb-4">
          <p className="font-medium">{t("user.billingInformation")}</p>
        </div>
        <div>
          <div className="mb-2">
            <Input
              label={t("user.fullName")}
              type="text"
              name="bill_fullname"
              placeholder={t("user.fullName")}
              labelClassName="font-normal"
              onChange={handleChange}
              onBlur={handleOnBlur}
              className={
                isCreditCardFormError.bill_fullname ? "border-red-500" : ""
              }
            />
            {isCreditCardFormError.bill_fullname && (
              <p className="text-red-500 text-sm">Full name is required</p>
            )}
          </div>
          <div className="mb-2">
            <Input
              label={t("user.billingAddress")}
              type="text"
              name="bill_address"
              placeholder={t("user.billingAddress")}
              labelClassName="font-normal"
              onChange={handleChange}
              onBlur={handleOnBlur}
              className={
                isCreditCardFormError.bill_address ? "border-red-500" : ""
              }
            />
            {isCreditCardFormError.bill_address && (
              <p className="text-red-500 text-sm">Address is required</p>
            )}
          </div>
          <div className="mb-2">
            <div className="relative">
              <label className="text-sm mb-1 lg:text-base">
                {t("user.country")}
              </label>
              <div
                className={`border border-greyscale-500 rounded-lg px-3 py-[10px] bg-white cursor-pointer flex items-center justify-between ${
                  isCreditCardFormError.bill_country
                    ? "border-red-500"
                    : "border-greyscale-500"
                }`}
                onClick={() => {
                  setOpenCountry(!openCountry);
                  setOpenState(false); // <-- close state dropdown when country dropdown toggles
                }}
              >
                <span className="text-sm">
                  {selectedCountry?.name || (
                    <span className="text-gray-400">
                      {t("user.selectCountry")}
                    </span>
                  )}
                </span>
                <svg
                  className={`w-4 h-4 ml-2 transition-transform duration-200 ${
                    openCountry ? "transform rotate-180" : ""
                  }`}
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2"
                  viewBox="0 0 24 24"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M19 9l-7 7-7-7"
                  ></path>
                </svg>
              </div>
              {isCreditCardFormError.bill_country && (
                <p className="text-red-500 text-sm">Country is required</p>
              )}
              {openCountry && (
                <div className="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg">
                  <input
                    type="text"
                    placeholder="Search country..."
                    className="w-full border-b px-3 py-2 outline-none"
                    value={searchCountry}
                    onChange={(e) => setSearchCountry(e.target.value)}
                    onBlur={handleOnBlur}
                    name="bill_country"
                  />

                  <ul className="max-h-48 overflow-auto text-sm">
                    {filteredCountries.map((country: any, idx: any) => (
                      <li
                        key={idx}
                        className="px-3 py-2 hover:bg-gray-100 cursor-pointer"
                        onClick={() => {
                          setSelectedCountry(country);
                          setSelectedState("");
                          setOpenCountry(false);
                          setSearchCountry("");
                          setPaymentForm((prev) => ({
                            ...prev,
                            bill_country: country.name,
                          }));
                          onChangePaymentFormValue({
                            ...paymentForm,
                            bill_country: country.name,
                          });
                        }}
                      >
                        {country.name}
                      </li>
                    ))}
                    {filteredCountries.length === 0 && (
                      <li className="px-3 py-2 text-gray-400">No results</li>
                    )}
                  </ul>
                </div>
              )}
            </div>
          </div>
          <div className="relative flex-1 hidden max-xs:block max-xs:mb-2">
            <label className="text-sm mb-1 lg:text-base">
              {t("user.state")}
            </label>
            <div
              className={`border rounded-lg px-3 py-2 flex items-center justify-between ${
                isCreditCardFormError.bill_state
                  ? "border-red-500"
                  : "border-greyscale-500"
              } ${
                selectedCountry
                  ? "bg-white cursor-pointer"
                  : "bg-gray-100 cursor-not-allowed text-gray-400"
              }`}
              onClick={() => selectedCountry && setOpenState(!openState)}
            >
              <span className="text-sm">
                {selectedState?.name || (
                  <span className="text-gray-400">
                    {t("user.selectState")}
                  </span>
                )}
              </span>
              <svg
                className={`w-4 h-4 ml-2 transition-transform duration-200 ${
                  openState ? "transform rotate-180" : ""
                }`}
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                viewBox="0 0 24 24"
                xmlns="http://www.w3.org/2000/svg"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  d="M19 9l-7 7-7-7"
                ></path>
              </svg>
            </div>
            {isCreditCardFormError.bill_state && (
              <p className="text-red-500 text-sm">State is required</p>
            )}

            {/* Dropdown only shows if country selected */}
            {openState && selectedCountry && (
              <div className="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg">
                <input
                  type="text"
                  placeholder="Search state..."
                  className="w-full border-b px-3 py-2 outline-none"
                  value={searchState}
                  onChange={(e) => setSearchState(e.target.value)}
                  name="bill_state"
                />
                <ul className="max-h-48 overflow-auto text-sm">
                  {filteredStates.map((state: any, idx: any) => (
                    <li
                      key={idx}
                      className="px-3 py-2 hover:bg-gray-100 cursor-pointer"
                      onClick={() => {
                        // console.log("state", state);
                        setSelectedState(state);
                        setOpenState(false);
                        setSearchState("");
                        setPaymentForm((prev) => ({
                          ...prev,
                          bill_state: state.name,
                        }));
                        onChangePaymentFormValue({
                          ...paymentForm,
                          bill_state: state.name,
                        });
                      }}
                    >
                      {state.name}
                    </li>
                  ))}
                  {filteredStates.length === 0 && (
                    <li className="px-3 py-2 text-gray-400">No results</li>
                  )}
                </ul>
              </div>
            )}
          </div>
          <div className="mb-2 grid grid-cols-2 gap-2">
            <div className="relative max-xs:hidden">
              <label className="text-sm mb-1 lg:text-base">
                {t("user.state")}
              </label>
              <div
                className={`mt-1 border rounded-lg px-4 py-3 flex items-center justify-between ${
                  isCreditCardFormError.bill_state
                    ? "border-red-500"
                    : "border-greyscale-500"
                } ${
                  selectedCountry
                    ? "bg-white cursor-pointer"
                    : "bg-gray-100 cursor-not-allowed text-gray-400"
                }`}
                onClick={() => selectedCountry && setOpenState(!openState)}
              >
                <span className="text-sm">
                  {selectedState?.name || (
                    <span className="text-gray-400">
                      {t("user.selectState")}
                    </span>
                  )}
                </span>
                <svg
                  className={`w-4 h-4 ml-2 transition-transform duration-200 ${
                    openState ? "transform rotate-180" : ""
                  }`}
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2"
                  viewBox="0 0 24 24"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M19 9l-7 7-7-7"
                  ></path>
                </svg>
              </div>
              {isCreditCardFormError.bill_state && (
                <p className="text-red-500 text-sm">State is required</p>
              )}
              {openState && selectedCountry && (
                <div className="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg">
                  <input
                    type="text"
                    placeholder="Search state..."
                    className="w-full border-b px-3 py-2 outline-none"
                    value={searchState}
                    onChange={(e) => setSearchState(e.target.value)}
                    name="bill_state"
                  />
                  <ul className="max-h-48 overflow-auto text-sm">
                    {filteredStates.map((state: any, idx: any) => (
                      <li
                        key={idx}
                        className="px-3 py-2 hover:bg-gray-100 cursor-pointer"
                        onClick={() => {
                          // console.log("state", state);
                          setSelectedState(state);
                          setOpenState(false);
                          setSearchState("");
                          setPaymentForm((prev) => ({
                            ...prev,
                            bill_state: state.name,
                          }));
                          onChangePaymentFormValue({
                            ...paymentForm,
                            bill_state: state.name,
                          });
                        }}
                      >
                        {state.name}
                      </li>
                    ))}
                    {filteredStates.length === 0 && (
                      <li className="px-3 py-2 text-gray-400">No results</li>
                    )}
                  </ul>
                </div>
              )}
            </div>
            <div className="w-full">
              <Input
                label={t("user.city")}
                type="text"
                name="bill_city"
                placeholder={t("user.city")}
                labelClassName="font-normal"
                className={
                  isCreditCardFormError.bill_city ? "border-red-500" : ""
                }
                onChange={handleChange}
                onBlur={handleOnBlur}
              />
              {isCreditCardFormError.bill_city && (
                <p className="text-red-500 text-sm">City is required</p>
              )}
            </div>
            <div className="w-full">
              <Input
                label={t("user.postalCode")}
                type="number"
                name="bill_zip"
                placeholder={t("user.postalCode")}
                labelClassName="font-normal"
                className={`number-appearance-none ${
                  isCreditCardFormError.bill_zip ? "border-red-500" : ""
                }`}
                onChange={handleChange}
                onBlur={handleOnBlur}
              />
              {isCreditCardFormError.bill_zip && (
                <p className="text-red-500 text-sm">Postal code is required</p>
              )}
            </div>
          </div>
          <div className="mb-2 grid grid-cols-2 gap-2">
            <div className="w-full">
              <Input
                label={t("user.email1")}
                type="text"
                name="bill_email"
                placeholder={t("user.email1")}
                labelClassName="font-normal"
                className={
                  isCreditCardFormError.bill_email ? "border-red-500" : ""
                }
                onChange={handleChange}
                onBlur={handleOnBlur}
              />
              {isCreditCardFormError.bill_email && (
                <p className="text-red-500 text-sm">Email is required</p>
              )}
            </div>
            <div className="w-full">
              <Input
                label={t("user.phoneNumber")}
                type="number"
                name="bill_phone"
                placeholder={t("user.phoneNumber")}
                labelClassName="font-normal"
                className={`number-appearance-none ${
                  isCreditCardFormError.bill_phone ? "border-red-500" : ""
                }`}
                onChange={handleChange}
                onBlur={handleOnBlur}
              />
              {isCreditCardFormError.bill_phone && (
                <p className="text-red-500 text-sm">Phone number is required</p>
              )}
            </div>
          </div>
        </div>
      </div>
    </>
  );
};

export default PaymentForm;
