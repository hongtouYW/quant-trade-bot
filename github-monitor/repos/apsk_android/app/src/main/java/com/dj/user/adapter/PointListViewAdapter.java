package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.core.content.ContextCompat;

import com.dj.user.R;
import com.dj.user.enums.PointTransactionType;
import com.dj.user.enums.TransactionStatus;
import com.dj.user.model.response.Transaction;
import com.dj.user.util.DateFormatUtils;
import com.dj.user.util.FormatUtils;

public class PointListViewAdapter extends CustomAdapter<Transaction> {

    private class ViewHolder {
        private LinearLayout itemPanel;
        private TextView titleTextView, statusTextView, dateTimeTextView;
        private ImageView typeImageView;
    }

    public PointListViewAdapter(Context context) {
        super(context);
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
            viewHolder.statusTextView = convertView.findViewById(R.id.textView_status);
            viewHolder.dateTimeTextView = convertView.findViewById(R.id.textView_date);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final Transaction transaction = getItem(position);
        if (transaction != null) {
            PointTransactionType type = PointTransactionType.fromValue(transaction.getType());
            TransactionStatus status = TransactionStatus.fromValue(transaction.getStatus());
            if (type != null) {
                viewHolder.typeImageView.setImageResource(type.getIconResId());
                viewHolder.titleTextView.setText(String.format(context.getString(R.string.template_s_s), context.getString(type.getTitle()), FormatUtils.formatAmount(transaction.getAmount())));
            }
            viewHolder.dateTimeTextView.setText(DateFormatUtils.formatIsoDate(transaction.getCreated_on(), DateFormatUtils.FORMAT_DD_MMM_YYYY_HH_MM_A));
            viewHolder.statusTextView.setText(status.getTitle());
            viewHolder.statusTextView.setTextColor(ContextCompat.getColor(context, status.getColorResId()));
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, transaction));
        }
        return convertView;
    }
}

