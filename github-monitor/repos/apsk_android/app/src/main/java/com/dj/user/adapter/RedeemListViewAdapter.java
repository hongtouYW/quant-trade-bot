package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;

import com.dj.user.R;
import com.dj.user.model.response.Redemption;
import com.dj.user.util.DateFormatUtils;

public class RedeemListViewAdapter extends CustomAdapter<Redemption> {
    private OnRedeemClickListener onRedeemClickListener;

    private class ViewHolder {
        private LinearLayout itemPanel;
        private TextView descTextView, dateTextView, redeemTextView, statusTextView;
    }

    public interface OnRedeemClickListener {
        void onRedeemClick(int position, Redemption item);
    }

    public void setOnRedeemClickListener(OnRedeemClickListener listener) {
        this.onRedeemClickListener = listener;
    }

    public RedeemListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_redemption, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.descTextView = convertView.findViewById(R.id.textView_desc);
            viewHolder.dateTextView = convertView.findViewById(R.id.textView_date);
            viewHolder.redeemTextView = convertView.findViewById(R.id.textView_redeem);
            viewHolder.statusTextView = convertView.findViewById(R.id.textView_status);

            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final Redemption redemption = getItem(position);
        if (redemption != null) {
            viewHolder.descTextView.setText(redemption.getTemplate());
            viewHolder.dateTextView.setText(DateFormatUtils.formatIsoDate(redemption.isRedeemed() ? redemption.getCreated_on() : redemption.getExpired_on(), DateFormatUtils.FORMAT_DD_MMM_YYYY_HH_MM_A));
            viewHolder.redeemTextView.setVisibility(redemption.isRedeemed() ? View.GONE : View.VISIBLE);
            viewHolder.redeemTextView.setOnClickListener(view -> {
                if (onRedeemClickListener != null) {
                    onRedeemClickListener.onRedeemClick(position, redemption);
                }
            });
            viewHolder.statusTextView.setVisibility(redemption.isRedeemed() ? View.VISIBLE : View.GONE);
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, redemption));
        }
        return convertView;
    }
}

