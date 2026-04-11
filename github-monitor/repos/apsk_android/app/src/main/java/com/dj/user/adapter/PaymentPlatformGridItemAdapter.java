package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.BaseAdapter;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.core.content.ContextCompat;

import com.dj.user.R;
import com.dj.user.model.response.PaymentType;
import com.dj.user.util.StringUtil;
import com.squareup.picasso.Callback;
import com.squareup.picasso.Picasso;

import java.util.List;

public class PaymentPlatformGridItemAdapter extends BaseAdapter {

    private final Context context;
    private List<PaymentType> paymentTypeList;
    private OnPaymentTypeClickListener listener;

    public PaymentPlatformGridItemAdapter(Context context) {
        this.context = context;
    }

    public void setOnPaymentTypeClickListener(OnPaymentTypeClickListener listener) {
        this.listener = listener;
    }

    public void replaceItems(List<PaymentType> paymentTypeList) {
        this.paymentTypeList = paymentTypeList;
        notifyDataSetChanged();
    }

    @Override
    public int getCount() {
        return paymentTypeList != null ? paymentTypeList.size() : 0;
    }

    @Override
    public PaymentType getItem(int position) {
        return paymentTypeList.get(position);
    }

    @Override
    public long getItemId(int position) {
        return position;
    }

    @Override
    public View getView(int position, View convertView, ViewGroup parent) {
        View row = convertView;
        if (row == null) {
            LayoutInflater inflater = LayoutInflater.from(context);
            row = inflater.inflate(R.layout.item_grid_payment_platform, parent, false);
        }

        LinearLayout itemPanel = row.findViewById(R.id.panel_item);
        ImageView iconImageView = row.findViewById(R.id.imageView_icon);
        TextView titleTextView = row.findViewById(R.id.textView_title);

        PaymentType paymentType = getItem(position);
        if (paymentType != null) {
            String icon = paymentType.getIcon();
            if (!StringUtil.isNullOrEmpty(icon)) {
                if (!icon.startsWith("http")) {
                    icon = String.format(context.getString(R.string.template_s_s), context.getString(R.string.image_base_url), icon);
                }
                Picasso.get().load(icon).into(iconImageView, new Callback() {
                    @Override
                    public void onSuccess() {
                        iconImageView.setVisibility(View.VISIBLE);
                        titleTextView.setVisibility(View.GONE);
                    }

                    @Override
                    public void onError(Exception e) {
                        iconImageView.setVisibility(View.GONE);
                        titleTextView.setVisibility(View.VISIBLE);
                    }
                });
            } else {
                iconImageView.setVisibility(View.GONE);
                titleTextView.setVisibility(View.VISIBLE);
            }
            titleTextView.setText(paymentType.getPayment_name());
            titleTextView.setTextColor(ContextCompat.getColor(context, paymentType.isSelected() ? R.color.black_000000 : R.color.white_FFFFFF));
            itemPanel.setBackgroundResource(paymentType.isSelected() ? R.drawable.bg_button_orange : R.drawable.bg_button_bordered_white);
            itemPanel.setOnClickListener(view -> {
                setSelectedIndex(position);
                if (listener != null) {
                    listener.onPaymentTypeClick(paymentType);
                }
            });
        }
        return row;
    }

    public void setSelectedIndex(int index) {
        for (int i = 0; i < getCount(); i++) {
            PaymentType paymentType = getItem(i);
            if (paymentType != null) {
                paymentType.setSelected(i == index);
            }
        }
        notifyDataSetChanged();
    }

    public interface OnPaymentTypeClickListener {
        void onPaymentTypeClick(PaymentType paymentType);
    }
}
