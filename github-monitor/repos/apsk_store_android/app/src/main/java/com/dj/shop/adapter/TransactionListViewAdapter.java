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
import com.dj.shop.model.response.Transaction;
import com.dj.shop.util.DateFormatUtils;
import com.dj.shop.util.FormatUtils;
import com.dj.shop.util.StringUtil;

import java.util.ArrayList;
import java.util.List;

public class TransactionListViewAdapter extends CustomAdapter<Transaction> implements Filterable {
    public interface FilterListener {
        void onFilterResult(boolean isEmpty);
    }

    List<Transaction> originalList;
    List<Transaction> filteredList;
    FilterListener filterListener;

    class ViewHolder {
        LinearLayout itemPanel;
        View dividerView;
        TextView titleTextView, idTextView, yxiTextView, dateTimeTextView, amountTextView, balanceTextView;
        ImageView typeImageView;
    }

    public TransactionListViewAdapter(@NonNull Context context) {
        super(context);
    }

    public void setFilterListener(@Nullable FilterListener listener) {
        this.filterListener = listener;
    }

    public void setData(@Nullable List<Transaction> data) {
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
    public Transaction getItem(int position) {
        return filteredList != null ? filteredList.get(position) : null;
    }

    @Override
    public long getItemId(int position) {
        return filteredList != null ? filteredList.get(position).hashCode() : null;
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_transaction, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.typeImageView = convertView.findViewById(R.id.imageView_type);
            viewHolder.titleTextView = convertView.findViewById(R.id.textView_title);
            viewHolder.idTextView = convertView.findViewById(R.id.textView_id);
            viewHolder.dividerView = convertView.findViewById(R.id.view_divider);
            viewHolder.yxiTextView = convertView.findViewById(R.id.textView_yxi);
            viewHolder.dateTimeTextView = convertView.findViewById(R.id.textView_date);
            viewHolder.amountTextView = convertView.findViewById(R.id.textView_amount);
            viewHolder.balanceTextView = convertView.findViewById(R.id.textView_balance);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final Transaction transaction = getItem(position);
        if (transaction != null) {
            TransactionType type = transaction.getTransactionType();
            viewHolder.typeImageView.setImageResource(type.getIconResId());
            viewHolder.titleTextView.setText(type.getTitleResId());
            if (type == TransactionType.YXI_TOP_UP || type == TransactionType.YXI_WITHDRAWAL) {
                viewHolder.dividerView.setVisibility(!StringUtil.isNullOrEmpty(transaction.getYxiName()) ? View.VISIBLE : View.GONE);
                viewHolder.yxiTextView.setVisibility(View.VISIBLE);
                viewHolder.yxiTextView.setText(transaction.getYxiName());
            } else {
                viewHolder.dividerView.setVisibility(View.GONE);
                viewHolder.yxiTextView.setVisibility(View.GONE);
            }
            viewHolder.dateTimeTextView.setText(DateFormatUtils.formatIsoDate(transaction.getCreated_on(), DateFormatUtils.FORMAT_YYYY_MM_DD));
            if (transaction.getTransactiontype().equalsIgnoreCase("member")) {
                viewHolder.idTextView.setText(transaction.getMember_id());
            } else if (transaction.getTransactiontype().equalsIgnoreCase("game")) {
                viewHolder.idTextView.setText(transaction.getPlayerId());
            } else if (transaction.getTransactiontype().equalsIgnoreCase("shop")) {
                viewHolder.idTextView.setText(transaction.getManager_id());
            }
            String symbol = type.getSymbol();
            String amountStr = FormatUtils.formatAmount(Math.abs(transaction.getAmount()));
            if (transaction.getAmount() > 0) {
                symbol = "+";
                if (type == TransactionType.WITHDRAWAL || type == TransactionType.YXI_WITHDRAWAL || type == TransactionType.MANAGER_SETTLEMENT) {
                    symbol = "-";
                }
            } else if (transaction.getAmount() < 0) {
                symbol = "-";
            } else {
                symbol = "";
            }
            viewHolder.amountTextView.setText(String.format(context.getString(R.string.template_s_s), symbol, amountStr));
            viewHolder.amountTextView.setTextColor(ContextCompat.getColor(context, type.getColorResId()));
            viewHolder.balanceTextView.setText(String.format(context.getString(R.string.template_bracket_s), FormatUtils.formatAmount(transaction.getAfter_balance())));
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, transaction));
        }
        return convertView;
    }

    @Override
    public Filter getFilter() {
        return new Filter() {
            @Override
            protected FilterResults performFiltering(CharSequence constraint) {
                String keyword = constraint.toString().toLowerCase();
                List<Transaction> resultList = new ArrayList<>();
                if (originalList == null || originalList.isEmpty()) {
                    FilterResults results = new FilterResults();
                    results.values = resultList;
                    return results;
                }
                if (keyword.isEmpty()) {
                    resultList = new ArrayList<>(originalList);
                } else {
                    for (Transaction item : originalList) {
                        TransactionType transactionType = item.getTransactionType();
                        String memberId = item.getMember_id() != null ? item.getMember_id().toLowerCase() : "";
                        String gameMemberId = item.getGamemember_id() != null ? item.getGamemember_id().toLowerCase() : "";
                        String title = transactionType != null ? context.getString(transactionType.getTitleResId()).toLowerCase() : "";
                        if (memberId.contains(keyword) || gameMemberId.contains(keyword) || title.contains(keyword)) {
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
                filteredList = (List<Transaction>) results.values;
                notifyDataSetChanged();

                if (filterListener != null) {
                    filterListener.onFilterResult(filteredList == null || filteredList.isEmpty());
                }
            }
        };
    }
}

