package com.dj.manager.adapter;

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
import androidx.core.content.ContextCompat;

import com.dj.manager.R;
import com.dj.manager.enums.TransactionType;
import com.dj.manager.model.response.Transaction;
import com.dj.manager.util.DateFormatUtils;
import com.dj.manager.util.FormatUtils;
import com.dj.manager.util.StringUtil;

import java.util.ArrayList;
import java.util.List;

public class TransactionListViewAdapter extends CustomAdapter<Transaction> implements Filterable {
    public interface FilterListener {
        void onFilterResult(boolean isEmpty);
    }

    private List<Transaction> originalList;
    private List<Transaction> filteredList;
    private FilterListener filterListener;

    private class ViewHolder {
        private LinearLayout itemPanel;
        private View dividerView;
        private TextView titleTextView, idTextView, yxiTextView, dateTimeTextView, amountTextView, balanceTextView;
        private ImageView typeImageView;
    }

    public TransactionListViewAdapter(Context context) {
        super(context);
    }

    public void setFilterListener(FilterListener listener) {
        this.filterListener = listener;
    }


    public void setData(List<Transaction> data) {
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
            viewHolder.titleTextView.setText(type.getTitle());
            if (type == TransactionType.YXI_TOP_UP || type == TransactionType.YXI_WITHDRAWAL) {
                viewHolder.dividerView.setVisibility(!StringUtil.isNullOrEmpty(transaction.getProviderName()) ? View.VISIBLE : View.GONE);
                viewHolder.yxiTextView.setVisibility(View.VISIBLE);
                viewHolder.yxiTextView.setText(transaction.getProviderName());
            } else {
                viewHolder.dividerView.setVisibility(View.GONE);
                viewHolder.yxiTextView.setVisibility(View.GONE);
            }
            viewHolder.dateTimeTextView.setText(DateFormatUtils.formatIsoDate(transaction.getCreated_on(), DateFormatUtils.FORMAT_YYYY_MM_DD));
            if (transaction.getTransactiontype().equalsIgnoreCase("member")) {
                viewHolder.idTextView.setText(FormatUtils.formatMsianPhone(transaction.getPhone()));
            } else if (transaction.getTransactiontype().equalsIgnoreCase("game")) {
                viewHolder.idTextView.setText(transaction.getPlayerLogin());
            } else if (transaction.getTransactiontype().equalsIgnoreCase("shop")) {
                viewHolder.idTextView.setText(transaction.getPrefixmanager());
            }
            String symbol;
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
                        if (String.valueOf(item.getCredit_id()).toLowerCase().contains(keyword)) {
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

