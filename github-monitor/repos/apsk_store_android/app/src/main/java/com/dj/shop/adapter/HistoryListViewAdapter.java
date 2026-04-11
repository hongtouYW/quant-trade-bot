package com.dj.shop.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.Filter;
import android.widget.Filterable;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.core.content.ContextCompat;

import com.dj.shop.R;
import com.dj.shop.enums.TransactionType;
import com.dj.shop.model.response.History;
import com.dj.shop.util.DateFormatUtils;
import com.dj.shop.util.FormatUtils;
import com.dj.shop.util.StringUtil;

import java.util.ArrayList;
import java.util.List;

public class HistoryListViewAdapter extends CustomAdapter<History> implements Filterable {
    public interface FilterListener {
        void onFilterResult(boolean isEmpty);
    }

    private static final int TYPE_TRANSACTION = 0;
    private static final int TYPE_USER = 1;

    List<History> originalList;
    List<History> filteredList;
    FilterListener filterListener;

    class ViewHolder {
        LinearLayout itemPanel;
        View dividerView;
        TextView titleTextView, idTextView, yxiTextView, dateTimeTextView, amountTextView, balanceTextView;
        ImageView typeImageView;
    }

    public HistoryListViewAdapter(@NonNull Context context) {
        super(context);
    }

    public void setFilterListener(@Nullable FilterListener listener) {
        this.filterListener = listener;
    }

    public void setData(@Nullable List<History> data) {
        if (originalList == null) {
            originalList = new ArrayList<>();
        }
        originalList.addAll(data);
        filteredList = new ArrayList<>(originalList);
        notifyDataSetChanged();
    }

    public void clear() {
        if (originalList != null) {
            originalList.clear();
        }
        if (filteredList != null) {
            filteredList.clear();
        }
        notifyDataSetChanged();
    }

    @Override
    public int getCount() {
        return filteredList != null ? filteredList.size() : 0;
    }

    @Override
    public History getItem(int position) {
        return filteredList != null ? filteredList.get(position) : null;
    }

    @Override
    public long getItemId(int position) {
        return filteredList != null ? filteredList.get(position).hashCode() : null;
    }

    @Override
    public int getViewTypeCount() {
        return 2;
    }

    @Override
    public int getItemViewType(int position) {
        History history = getItem(position);
        if (history == null) {
            return TYPE_TRANSACTION;
        }
        TransactionType type = history.getTransactionType();
        if (type == TransactionType.USER || type == TransactionType.RANDOM_USER || type == TransactionType.PLAYER_USER) {
            return TYPE_USER;
        }
        return TYPE_TRANSACTION;
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        final History history = getItem(position);
        int viewType = getItemViewType(position);

        if (convertView == null) {
            viewHolder = new ViewHolder();
            if (viewType == TYPE_USER) {
                convertView = LayoutInflater.from(context).inflate(R.layout.item_list_user, parent, false);
            } else {
                convertView = LayoutInflater.from(context).inflate(R.layout.item_list_transaction, parent, false);
            }
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.typeImageView = convertView.findViewById(R.id.imageView_type);
            viewHolder.titleTextView = convertView.findViewById(R.id.textView_title);
            viewHolder.idTextView = convertView.findViewById(R.id.textView_id);
            viewHolder.dividerView = convertView.findViewById(R.id.view_divider);
            viewHolder.yxiTextView = convertView.findViewById(R.id.textView_yxi);
            viewHolder.dateTimeTextView = convertView.findViewById(R.id.textView_date);
            if (viewType == TYPE_TRANSACTION) {
                viewHolder.amountTextView = convertView.findViewById(R.id.textView_amount);
                viewHolder.balanceTextView = convertView.findViewById(R.id.textView_balance);
            }
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }
        if (history != null) {
            TransactionType type = history.getTransactionType();
            viewHolder.typeImageView.setImageResource(type.getIconResId());
            viewHolder.titleTextView.setText(history.getTitle());
            if (type == TransactionType.PLAYER_USER || type == TransactionType.YXI_TOP_UP || type == TransactionType.YXI_WITHDRAWAL) {
                viewHolder.dividerView.setVisibility(!StringUtil.isNullOrEmpty(history.getProvider_name()) ? View.VISIBLE : View.GONE);
                viewHolder.yxiTextView.setVisibility(View.VISIBLE);
                viewHolder.yxiTextView.setText(history.getProvider_name());
            } else {
                viewHolder.dividerView.setVisibility(View.GONE);
                viewHolder.yxiTextView.setVisibility(View.GONE);
            }
            viewHolder.dateTimeTextView.setText(DateFormatUtils.formatIsoDate(history.getCreated_on(), DateFormatUtils.FORMAT_YYYY_MM_DD));
            if (history.getTransactiontype().equalsIgnoreCase("player") || history.getTransactiontype().equalsIgnoreCase("game")) {
                viewHolder.idTextView.setText(history.getName());
            } else {
                if (type == TransactionType.USER || type == TransactionType.RANDOM_USER || type == TransactionType.PLAYER_USER) {
                    viewHolder.idTextView.setText(FormatUtils.formatMsianPhone(history.getPhone()));
                } else if (type == TransactionType.MANAGER_SETTLEMENT) {
                    viewHolder.idTextView.setText(history.getManager_name());
                } else {
                    viewHolder.idTextView.setText(FormatUtils.formatMsianPhone(!StringUtil.isNullOrEmpty(history.getPhone()) ? history.getPhone() : history.getMember_name()));
                }
            }
            if (type == TransactionType.USER || type == TransactionType.RANDOM_USER || type == TransactionType.PLAYER_USER) {
            } else {
                String symbol = type.getSymbol();
                String amountStr = FormatUtils.formatAmount(Math.abs(history.getAmount()));
                if (history.getAmount() > 0) {
                    symbol = "+";
                    if (type == TransactionType.WITHDRAWAL || type == TransactionType.YXI_WITHDRAWAL || type == TransactionType.MANAGER_SETTLEMENT) {
                        symbol = "-";
                    }
                } else if (history.getAmount() < 0) {
                    symbol = "-";
                } else {
                    symbol = "";
                }
                if (viewHolder.amountTextView != null) {
                    viewHolder.amountTextView.setText(String.format(context.getString(R.string.template_s_s), symbol, amountStr));
                    viewHolder.amountTextView.setTextColor(ContextCompat.getColor(context, type.getColorResId()));
                }
            }
//            viewHolder.balanceTextView.setText(String.format(context.getString(R.string.template_bracket_s), FormatUtils.formatAmount(transaction.getAfter_balance())));
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, history));
        }
        return convertView;
    }

    @Override
    public Filter getFilter() {
        return new Filter() {
            @Override
            protected FilterResults performFiltering(CharSequence constraint) {
                String keyword = constraint.toString().toLowerCase();
                List<History> resultList = new ArrayList<>();
                if (originalList == null || originalList.isEmpty()) {
                    FilterResults results = new FilterResults();
                    results.values = resultList;
                    return results;
                }
                if (keyword.isEmpty()) {
                    resultList = new ArrayList<>(originalList);
                } else {
                    for (History item : originalList) {
                        String memberName = item.getMember_name() != null ? item.getMember_name().toLowerCase() : "";
                        String memberPhone = item.getPhone() != null ? item.getPhone().toLowerCase() : "";
                        String playerId = item.getGamemember_id() != null ? item.getGamemember_id().toLowerCase() : "";
                        String playerName = item.getName() != null ? item.getName().toLowerCase() : "";
                        String managerName = item.getManager_name() != null ? item.getManager_name().toLowerCase() : "";
                        if (memberName.contains(keyword) || memberPhone.contains(keyword) || playerId.contains(keyword) ||
                                playerName.contains(keyword) || managerName.contains(keyword)) {
                            resultList.add(item);
                        }
                    }
                }

                FilterResults results = new FilterResults();
                results.values = resultList;
                return results;
            }

            @Override
            protected void publishResults(CharSequence constraint, FilterResults results) {
                filteredList = (List<History>) results.values;
                notifyDataSetChanged();

                if (filterListener != null) {
                    filterListener.onFilterResult(filteredList == null || filteredList.isEmpty());
                }
            }
        };
    }
}

