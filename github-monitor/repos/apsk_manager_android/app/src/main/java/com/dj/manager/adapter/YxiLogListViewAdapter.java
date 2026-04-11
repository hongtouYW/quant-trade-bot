package com.dj.manager.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;

import com.dj.manager.R;
import com.dj.manager.model.response.Transaction;
import com.dj.manager.util.DateFormatUtils;
import com.dj.manager.util.FormatUtils;
import com.dj.manager.util.StringUtil;
import com.squareup.picasso.Callback;
import com.squareup.picasso.Picasso;

public class YxiLogListViewAdapter extends CustomAdapter<Transaction> {

    private class ViewHolder {
        private LinearLayout itemPanel;
        private ImageView yxiImageView;
        private TextView yxiTextView, betTextView, winTextView, beginTextView, endTextView, timeTextView, dateTextView;
    }

    public YxiLogListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_log_yxi, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.yxiImageView = convertView.findViewById(R.id.imageView_yxi);
            viewHolder.yxiTextView = convertView.findViewById(R.id.textView_yxi);
            viewHolder.betTextView = convertView.findViewById(R.id.textView_bet);
            viewHolder.winTextView = convertView.findViewById(R.id.textView_win);
            viewHolder.beginTextView = convertView.findViewById(R.id.textView_begin);
            viewHolder.endTextView = convertView.findViewById(R.id.textView_end);
            viewHolder.timeTextView = convertView.findViewById(R.id.textView_time);
            viewHolder.dateTextView = convertView.findViewById(R.id.textView_date);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final Transaction transaction = getItem(position);
        if (transaction != null) {
            String icon = transaction.getProviderIcon();
            if (!StringUtil.isNullOrEmpty(icon)) {
                if (!icon.startsWith("http")) {
                    icon = String.format(context.getString(R.string.template_s_s), context.getString(R.string.image_base_url), icon);
                }
                Picasso.get().load(icon).centerCrop().fit().into(viewHolder.yxiImageView, new Callback() {
                    @Override
                    public void onSuccess() {

                    }

                    @Override
                    public void onError(Exception e) {
                        viewHolder.yxiImageView.setImageResource(R.drawable.img_provider_default);
                    }
                });
            } else {
                viewHolder.yxiImageView.setImageResource(R.drawable.img_provider_default);
            }
            viewHolder.yxiTextView.setText(transaction.getProviderYxiName());
            viewHolder.betTextView.setText(String.format(context.getString(R.string.yxi_log_bet), FormatUtils.formatAmount(transaction.getBetamount())));
            viewHolder.winTextView.setText(String.format(context.getString(R.string.yxi_log_win), FormatUtils.formatAmount(transaction.getWinloss())));
            viewHolder.beginTextView.setText(String.format(context.getString(R.string.yxi_log_begin), FormatUtils.formatAmount(transaction.getBefore_balance())));
            viewHolder.endTextView.setText(String.format(context.getString(R.string.yxi_log_end), FormatUtils.formatAmount(transaction.getAfter_balance())));

            viewHolder.timeTextView.setText(DateFormatUtils.formatIsoDate(transaction.getCreated_on(), DateFormatUtils.FORMAT_HH_MM_A));
            viewHolder.dateTextView.setText(DateFormatUtils.formatIsoDate(transaction.getCreated_on(), DateFormatUtils.FORMAT_YYYY_MM_DD));
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, transaction));
        }
        return convertView;
    }
}

