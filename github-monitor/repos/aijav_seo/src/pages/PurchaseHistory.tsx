import { useMemo, useState } from "react";
import { useTranslation } from "react-i18next";
import Fuse from "fuse.js";
import {
  createColumnHelper,
  flexRender,
  getCoreRowModel,
  getPaginationRowModel,
  useReactTable,
} from "@tanstack/react-table";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { ArrowUpDown, ListFilter, ListTodo, Search } from "lucide-react";
import DiamondIcon from "@/assets/diamond-icon.png";
import type {
  TabConfig,
  Transaction,
  TransactionType,
} from "@/types/transaction.types";
import { useVipOrders } from "@/hooks/plan/useVipOrders";
import { useSeriesPurchases } from "@/hooks/group/useSeriesPurchases";
import { useVideoPurchases } from "@/hooks/video/useVideoPurchases";
import { useNavigate } from "react-router";

const columnHelper = createColumnHelper<Transaction>();

export default function PurchaseHistory() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [selected, setSelected] = useState<TransactionType>("recharge");
  const [searchValue, setSearchValue] = useState("");

  const { data: vipOrdersData, isPending: vipLoading } = useVipOrders();
  const { data: seriesData, isPending: seriesLoading } = useSeriesPurchases();
  const { data: videosData, isPending: videosLoading } = useVideoPurchases();

  const tabConfigs: TabConfig[] = [
    {
      key: "recharge",
      label: t("purchase_history.recharge"),
      secondColumnLabel: t("purchase_history.table.package"),
    },
    {
      key: "series",
      label: t("purchase_history.series"),
      secondColumnLabel: t("purchase_history.table.series_name"),
    },
    {
      key: "video",
      label: t("purchase_history.video"),
      secondColumnLabel: t("purchase_history.table.video_title"),
    },
  ];

  const filteredData = useMemo(() => {
    let transactions: Transaction[] = [];

    switch (selected) {
      case "recharge": {
        const orders = vipOrdersData?.data?.data || [];
        transactions = orders.map(
          (order): Transaction => ({
            id: order.order_sn,
            time: order.add_time,
            orderNumber: order.order_sn,
            amount: order.money,
            status:
              order.status === 1
                ? "completed"
                : order.status === 0
                  ? "pending"
                  : "failed",
            paymentMethod: "ALIPAY",
            type: "recharge",
            package: order.title,
          }),
        );
        break;
      }
      case "series": {
        const series = seriesData?.data?.data || [];
        transactions = series.map(
          (item): Transaction => ({
            id: item.id.toString(),
            time: "",
            orderNumber: item.id.toString(),
            amount: item.amount,
            status: "completed",
            paymentMethod: "ALIPAY",
            type: "series",
            seriesName: item.title,
            seriesId: item.id.toString(),
          }),
        );
        break;
      }
      case "video": {
        const videos = videosData?.data?.data || [];
        transactions = videos.map(
          (video): Transaction => ({
            id: video.id.toString(),
            time: video.publish_date,
            orderNumber: video.id.toString(),
            amount: Number(video.video_point),
            status: "completed",
            paymentMethod: "ALIPAY",
            type: "video",
            videoTitle: video.title,
            videoId: video.id.toString(),
          }),
        );
        break;
      }
    }

    // Return all transactions if search is empty
    if (!searchValue.trim()) {
      return transactions;
    }

    // Fuzzy search based on selected tab
    const fuseOptions = {
      includeScore: true,
      threshold: 0.4,
      minMatchCharLength: 1,
    };

    let keys: string[] = [];
    if (selected === "recharge") {
      keys = ["package", "orderNumber", "time"];
    } else if (selected === "series") {
      keys = ["seriesName", "orderNumber"];
    } else if (selected === "video") {
      keys = ["videoTitle", "orderNumber"];
    }

    const fuse = new Fuse(transactions, { ...fuseOptions, keys });
    return fuse.search(searchValue).map((result) => result.item);
  }, [selected, vipOrdersData, seriesData, videosData, searchValue]);

  // Get current tab config
  const currentTabConfig = tabConfigs.find((config) => config.key === selected);

  // Define columns with dynamic second column
  const columns = useMemo(
    () => [
      ...(selected === "recharge"
        ? [
            columnHelper.accessor("time", {
              header: t("purchase_history.table.time"),
              cell: (info) => (
                <div className="text-xs sm:text-sm text-muted-foreground">
                  {info.getValue()}
                </div>
              ),
              size: 30,
            }),
            columnHelper.accessor("orderNumber", {
              header: t("purchase_history.table.order_number"),
              cell: (info) => (
                <div className="text-xs sm:text-sm font-medium truncate">
                  {info.getValue()}
                </div>
              ),
              size: 30,
            }),
          ]
        : []),
      columnHelper.display({
        id: "secondColumn",
        header: currentTabConfig?.secondColumnLabel || "",
        cell: ({ row }) => {
          const transaction = row.original;
          let content = "";
          let isClickable = false;
          let navigateTo = "";

          switch (transaction.type) {
            case "recharge": {
              const rechargeTransaction = transaction as Transaction & { package: string };
              content = rechargeTransaction.package;
              break;
            }
            case "series": {
              const seriesTransaction = transaction as Transaction & { seriesName: string; seriesId: string };
              content = seriesTransaction.seriesName;
              isClickable = true;
              navigateTo = `/series/${seriesTransaction.seriesId}`;
              break;
            }
            case "video": {
              const videoTransaction = transaction as Transaction & { videoTitle: string; videoId: string };
              content = videoTransaction.videoTitle;
              isClickable = true;
              navigateTo = `/watch/${videoTransaction.videoId}`;
              break;
            }
          }

          if (isClickable) {
            return (
              <div
                className="text-xs sm:text-sm text-primary hover:text-primary/80 cursor-pointer hover:underline line-clamp-2"
                onClick={() => navigate(navigateTo)}
              >
                {content}
              </div>
            );
          }

          return (
            <div className="text-xs sm:text-sm line-clamp-2">{content}</div>
          );
        },
        size: 50,
      }),
      columnHelper.accessor("amount", {
        header: t("purchase_history.table.price"),
        cell: (info) => {
          const transaction = info.row.original;
          const amount = info.getValue();

          if (transaction.type === "series") {
            return (
              <div className="text-xs sm:text-sm font-medium flex items-center gap-1 whitespace-nowrap">
                <img src={DiamondIcon} alt="Diamond" className="w-4 h-4" />
                {amount}
              </div>
            );
          }

          return (
            <div className="text-xs sm:text-sm font-medium whitespace-nowrap">
              ¥{amount}
            </div>
          );
        },
        size: 20,
      }),
      // Hidden columns - can be shown later
      /*
      columnHelper.accessor("status", {
        header: t("purchase_history.table.status"),
        cell: (info) => {
          const status = info.getValue();
          const getStatusBadge = () => {
            switch (status) {
              case "completed":
                return (
                  <Badge className="bg-green-100 text-green-700 hover:bg-green-100">
                    {t("purchase_history.status.completed")}
                  </Badge>
                );
              case "pending":
                return (
                  <Badge className="bg-yellow-100 text-yellow-700 hover:bg-yellow-100">
                    {t("purchase_history.status.pending")}
                  </Badge>
                );
              case "failed":
                return (
                  <Badge className="bg-red-100 text-red-700 hover:bg-red-100">
                    {t("purchase_history.status.failed")}
                  </Badge>
                );
              default:
                return <Badge variant="secondary">{status}</Badge>;
            }
          };

          return getStatusBadge();
        },
      }),
      columnHelper.accessor("paymentMethod", {
        header: t("purchase_history.table.payment_method"),
        cell: (info) => (
          <div className="text-sm text-muted-foreground">{info.getValue()}</div>
        ),
      }),
      */
    ],
    [currentTabConfig, t, navigate, selected],
  );

  const table = useReactTable({
    data: filteredData,
    columns,
    getCoreRowModel: getCoreRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    initialState: {
      pagination: {
        pageSize: 10,
      },
    },
  });

  return (
    <div className="bg-background text-foreground transition-colors">
      {/* Header */}
      <header className="border-b px-4">
        <div className="flex h-14 items-center justify-between gap-4">
          <div className="flex flex-1 items-center gap-2 text-base font-bold">
            <ListTodo className="sm:-ms-1" size={20} aria-hidden="true" />
            <span>{t("purchase_history.title")}</span>
          </div>
        </div>
      </header>

      {/* Tab Navigation */}
      <div className="border-b px-4">
        <div className="flex py-3 items-center justify-between gap-4">
          <div className="flex flex-1 items-center gap-2 text-base font-bold">
            <fieldset className="space-y-4">
              <RadioGroup
                className="grid grid-cols-3 gap-2 sm:grid-flow-col sm:gap-3 sm:grid-cols-none"
                value={selected}
                onValueChange={(value) => setSelected(value as TransactionType)}
              >
                {tabConfigs.map((config) => (
                  <label
                    key={config.key}
                    className="border-input has-data-[state=checked]:bg-primary/50 has-data-[state=checked]:text-white has-focus-visible:border-ring has-focus-visible:ring-ring/50 relative flex cursor-pointer flex-col items-center gap-3 rounded-full border px-2 py-3 text-center shadow-xs transition-[color,box-shadow] outline-none has-focus-visible:ring-[3px] has-data-disabled:cursor-not-allowed has-data-disabled:opacity-50"
                  >
                    <RadioGroupItem
                      id={config.key}
                      value={config.key}
                      className="sr-only after:absolute after:inset-0"
                    />
                    <p className="px-2 sm:px-8.5 text-xs sm:text-sm leading-none font-medium">
                      {config.label}
                    </p>
                  </label>
                ))}
              </RadioGroup>
            </fieldset>
          </div>
        </div>
      </div>

      {/* Content */}
      <div className="mt-6 space-y-4">
        {/* Search and Filter Bar */}
        <div className="px-4">
          <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div className="flex items-center space-x-2">
              <Button variant="ghost" size="sm">
                <ListFilter size={16} />
              </Button>
              <Button variant="ghost" size="sm">
                <ArrowUpDown size={16} />
              </Button>
            </div>
            <div className="relative w-full sm:w-auto">
              <Input
                id="search"
                className="peer h-8 pe-8 ps-3 rounded-full bg-muted/80 dark:bg-muted/30 placeholder:text-muted-foreground/70 text-foreground w-full sm:w-64 transition-colors"
                placeholder={t("purchase_history.search_placeholder")}
                type="search"
                value={searchValue}
                onChange={(e) => setSearchValue(e.target.value)}
              />
              <div className="text-muted-foreground pointer-events-none absolute inset-y-0 end-0 flex items-center justify-center pe-3 peer-disabled:opacity-50">
                <Search size={16} />
              </div>
            </div>
          </div>
        </div>

        {/* Table */}
        <div className="px-4">
          <div className="border border-border rounded-lg overflow-hidden bg-card text-card-foreground shadow-sm transition-colors">
            <table className="w-full table-fixed bg-card">
              <thead className="bg-muted/50">
                {table.getHeaderGroups().map((headerGroup) => (
                  <tr key={headerGroup.id}>
                    {headerGroup.headers.map((header) => (
                      <th
                        key={header.id}
                        className="px-2 sm:px-4 py-3 text-left text-xs sm:text-sm font-medium text-muted-foreground border-b border-border/60"
                        style={{
                          width: header.column.columnDef.size
                            ? `${header.column.columnDef.size}%`
                            : "auto",
                        }}
                      >
                        {header.isPlaceholder
                          ? null
                          : flexRender(
                              header.column.columnDef.header,
                              header.getContext(),
                            )}
                      </th>
                    ))}
                  </tr>
                ))}
              </thead>
              <tbody>
                {(() => {
                  const isLoading =
                    (selected === "recharge" && vipLoading) ||
                    (selected === "series" && seriesLoading) ||
                    (selected === "video" && videosLoading);

                  if (isLoading) {
                    return (
                      <tr>
                        <td colSpan={columns.length} className="px-4 py-16">
                          <div className="flex flex-col items-center justify-center text-center">
                            <div className="w-8 h-8 border-4 border-border/60 border-t-primary rounded-full animate-spin mb-4"></div>
                            <p className="text-muted-foreground">Loading...</p>
                          </div>
                        </td>
                      </tr>
                    );
                  }

                  if (filteredData.length === 0) {
                    return (
                      <tr>
                        <td
                          colSpan={columns.length}
                          className="px-4 py-8 sm:py-16"
                        >
                          <div className="flex flex-col items-center justify-center text-center">
                            <div className="w-12 h-12 sm:w-16 sm:h-16 bg-muted rounded-full flex items-center justify-center mb-3 sm:mb-4">
                              <ListTodo className="w-6 h-6 sm:w-8 sm:h-8 text-muted-foreground" />
                            </div>
                            <h3 className="text-sm sm:text-lg font-medium text-foreground mb-1 sm:mb-2">
                              {t("purchase_history.empty.title")}
                            </h3>
                            <p className="text-xs sm:text-base text-muted-foreground max-w-sm px-4">
                              {t("purchase_history.empty.description")}
                            </p>
                          </div>
                        </td>
                      </tr>
                    );
                  }

                  return table.getRowModel().rows.map((row) => (
                    <tr key={row.id} className="border-b border-border/40">
                      {row.getVisibleCells().map((cell) => (
                        <td key={cell.id} className="px-2 sm:px-4 py-3">
                          {flexRender(
                            cell.column.columnDef.cell,
                            cell.getContext(),
                          )}
                        </td>
                      ))}
                    </tr>
                  ));
                })()}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}
