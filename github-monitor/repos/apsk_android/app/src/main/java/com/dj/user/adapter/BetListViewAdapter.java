package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;

import com.dj.user.R;
import com.dj.user.model.response.Transaction;
import com.dj.user.util.DateFormatUtils;
import com.dj.user.util.FormatUtils;

public class BetListViewAdapter extends CustomAdapter<Transaction> {

    private class ViewHolder {
        private LinearLayout itemPanel;
        private TextView yxiTextView, dateTextView, betTextView, winTextView, beginTextView, endTextView;
    }

    public BetListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_bet, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.yxiTextView = convertView.findViewById(R.id.textView_yxi);
            viewHolder.dateTextView = convertView.findViewById(R.id.textView_date);
            viewHolder.betTextView = convertView.findViewById(R.id.textView_bet);
            viewHolder.winTextView = convertView.findViewById(R.id.textView_win);
            viewHolder.beginTextView = convertView.findViewById(R.id.textView_begin);
            viewHolder.endTextView = convertView.findViewById(R.id.textView_end);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final Transaction transaction = getItem(position);
        if (transaction != null) {
            viewHolder.yxiTextView.setText(transaction.getProviderYxiName());
            viewHolder.dateTextView.setText(DateFormatUtils.formatIsoDate(transaction.getEnddt(), DateFormatUtils.FORMAT_DD_MMM_YYYY_HH_MM_A));
            viewHolder.betTextView.setText(String.format(context.getString(R.string.transaction_history_bet), FormatUtils.formatAmount(transaction.getBetamount())));
            viewHolder.winTextView.setText(String.format(context.getString(R.string.transaction_history_win), FormatUtils.formatAmount(transaction.getWinloss())));
            viewHolder.beginTextView.setText(String.format(context.getString(R.string.transaction_history_begin), FormatUtils.formatAmount(transaction.getBefore_balance())));
            viewHolder.endTextView.setText(String.format(context.getString(R.string.transaction_history_end), FormatUtils.formatAmount(transaction.getAfter_balance())));
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, transaction));
        }
        return convertView;
    }
}

