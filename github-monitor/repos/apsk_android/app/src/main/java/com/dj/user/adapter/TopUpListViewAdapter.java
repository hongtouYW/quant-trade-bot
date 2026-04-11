package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;

import com.dj.user.R;
import com.dj.user.model.response.PaymentType;

public class TopUpListViewAdapter extends CustomAdapter<PaymentType> {

    private class ViewHolder {
        private LinearLayout itemPanel;
        private ImageView iconImageView;
        private TextView titleTextView;
    }

    public TopUpListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_top_up, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.iconImageView = convertView.findViewById(R.id.imageView_icon);
            viewHolder.titleTextView = convertView.findViewById(R.id.textView_title);

            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final PaymentType paymentType = getItem(position);
        if (paymentType != null) {
            viewHolder.iconImageView.setImageResource(paymentType.getIconResId());
            viewHolder.titleTextView.setText(paymentType.getTitle());
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, paymentType));
        }
        return convertView;
    }
}

