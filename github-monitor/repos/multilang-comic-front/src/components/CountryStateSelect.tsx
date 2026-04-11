// import { useState, useEffect } from "react";
// import { Country, State } from "country-state-city";

// export default function CountryStateSelect() {
//   const [countries, setCountries] = useState([]);
//   const [states, setStates] = useState([]);
//   const [selectedCountry, setSelectedCountry] = useState<any>(null);
//   const [selectedState, setSelectedState] = useState<any>("");
//   const [searchCountry, setSearchCountry] = useState("");
//   const [searchState, setSearchState] = useState("");
//   const [openCountry, setOpenCountry] = useState(false);
//   const [openState, setOpenState] = useState(false);

//   // Load countries
//   useEffect(() => {
//     const allCountries: any = Country.getAllCountries().map(c => ({
//       name: c.name,
//       code: c.isoCode,
//     }));
//     setCountries(allCountries);
//   }, []);

//   // Load states when country selected
//   useEffect(() => {
//     if (selectedCountry) {
//       const allStates: any = State.getStatesOfCountry(selectedCountry.code).map(s => ({
//         name: s.name,
//         code: s.isoCode,
//       }));
//       setStates(allStates);
//     } else {
//       setStates([]);
//     }
//   }, [selectedCountry]);

//   const filteredCountries = countries.filter((country) =>
//     country.name.toLowerCase().includes(searchCountry.toLowerCase())
//   );

//   const filteredStates = states.filter((state) =>
//     state.name.toLowerCase().includes(searchState.toLowerCase())
//   );

//   return (
//     <div className="space-y-4 w-64">
//       {/* Country Select */}
//       <div className="relative">
//         <div
//           className="border rounded-lg px-3 py-2 bg-white cursor-pointer"
//           onClick={() => {
//             setOpenCountry(!openCountry);
//             setOpenState(false);  // <-- close state dropdown when country dropdown toggles
//           }}
//         >
//           {selectedCountry?.name || "Select a country"}
//         </div>
//         {openCountry && (
//           <div className="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg">
//             <input
//               type="text"
//               placeholder="Search country..."
//               className="w-full border-b px-3 py-2 outline-none"
//               value={searchCountry}
//               onChange={(e) => setSearchCountry(e.target.value)}
//             />
//             <ul className="max-h-48 overflow-auto">
//               {filteredCountries.map((country: any, idx: any) => (
//                 <li
//                   key={idx}
//                   className="px-3 py-2 hover:bg-gray-100 cursor-pointer"
//                   onClick={() => {
//                     setSelectedCountry(country);
//                     setSelectedState("");
//                     setOpenCountry(false);
//                     setSearchCountry("");
//                   }}
//                 >
//                   {country?.name}
//                 </li>
//               ))}
//               {filteredCountries.length === 0 && (
//                 <li className="px-3 py-2 text-gray-400">No results</li>
//               )}
//             </ul>
//           </div>
//         )}
//       </div>

//       {/* State Select (Always Visible) */}
//       <div className="relative">
//         <div
//           className={`border rounded-lg px-3 py-2 ${
//             selectedCountry ? "bg-white cursor-pointer" : "bg-gray-100 cursor-not-allowed text-gray-400"
//           }`}
//           onClick={() => selectedCountry && setOpenState(!openState)}
//         >
//           {selectedState?.name || "Select a state"}
//         </div>

//         {/* Dropdown only shows if country selected */}
//         {openState && selectedCountry && (
//           <div className="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg">
//             <input
//               type="text"
//               placeholder="Search state..."
//               className="w-full border-b px-3 py-2 outline-none"
//               value={searchState}
//               onChange={(e) => setSearchState(e.target.value)}
//             />
//             <ul className="max-h-48 overflow-auto">
//               {filteredStates.map((state: any, idx: any) => (
//                 <li
//                   key={idx}
//                   className="px-3 py-2 hover:bg-gray-100 cursor-pointer"
//                   onClick={() => {
//                     setSelectedState(state);
//                     setOpenState(false);
//                     setSearchState("");
//                   }}
//                 >
//                   {state?.name}
//                 </li>
//               ))}
//               {filteredStates.length === 0 && (
//                 <li className="px-3 py-2 text-gray-400">No results</li>
//               )}
//             </ul>
//           </div>
//         )}
//       </div>
//     </div>
//   );
// }
