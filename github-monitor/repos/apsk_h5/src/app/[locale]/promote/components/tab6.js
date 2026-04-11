"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import HomeSideBar from "@/components/home/HomeSideBar";

export default function ReturnRatioTab() {
  const t = useTranslations();
  const [activeId, setActiveId] = useState("hot");

  // mock table data
  const tableData = [
    { valid: "0", performance: "1.00", rebate: "25.00" },
    { valid: "0", performance: "3.00", rebate: "30.00" },
    { valid: "0", performance: "6.00", rebate: "35.00" },
    { valid: "0", performance: "10.00", rebate: "40.00" },
    { valid: "0", performance: "20.00", rebate: "45.00" },
    { valid: "0", performance: "40.00", rebate: "50.00" },
    { valid: "0", performance: "60.00", rebate: "55.00" },
    { valid: "0", performance: "80.00", rebate: "60.00" },
    { valid: "0", performance: "100.00", rebate: "65.00" },
    { valid: "0", performance: "150.00", rebate: "70.00" },
    { valid: "0", performance: "300.00", rebate: "80.00" },
    { valid: "0", performance: "500.00", rebate: "90.00" },
    { valid: "0", performance: "800.00", rebate: "100.00" },
    { valid: "0", performance: "1000.00", rebate: "150.00" },
    { valid: "0", performance: "1500.00", rebate: "200.00" },
  ];

  return (
    <div className="flex">
      {/* Left sidebar */}
      <HomeSideBar activeId={activeId} onSelect={(it) => setActiveId(it.id)} />

      {/* Right content */}
      <div className="flex-1 overflow-x-auto rounded-t-xl ml-2 mr-2  bg-[#122346]">
        {/* Table header */}
        <div className="grid grid-cols-3 bg-[white] border-b border-white/10 text-center text-sm font-medium">
          <div className="py-3 text-[#6F8EFF]">{t("returnRatio.valid")}</div>
          <div className="py-3 text-[#6F8EFF]">
            {t("returnRatio.performance")}
          </div>
          <div className="py-3 text-[#6F8EFF]">{t("returnRatio.rebate")}</div>
        </div>

        {/* Table rows */}
        {tableData.map((row, idx) => (
          <div
            key={idx}
            className="grid grid-cols-3 border-b border-white/10 text-center text-sm"
          >
            <div className="py-3">{row.valid}</div>
            <div className="py-3">{row.performance}</div>
            <div className="py-3 font-semibold text-[#FFC000]">
              {row.rebate}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
