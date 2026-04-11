package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;

import com.dj.user.R;
import com.dj.user.model.response.Commission;
import com.dj.user.util.FormatUtils;

public class AffiliateCommissionListViewAdapter extends CustomAdapter<Commission> {
    private class ViewHolder {
        private LinearLayout itemPanel;
        private TextView memberNameTextView, memberIdTextView, topUpTextView, topUpDateTextView, commissionTextView;
    }

    public AffiliateCommissionListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_affiliate_commision, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.memberNameTextView = convertView.findViewById(R.id.textView_member_name);
            viewHolder.memberIdTextView = convertView.findViewById(R.id.textView_member_id);
            viewHolder.topUpTextView = convertView.findViewById(R.id.textView_top_up);
            viewHolder.topUpDateTextView = convertView.findViewById(R.id.textView_top_up_date);
            viewHolder.commissionTextView = convertView.findViewById(R.id.textView_commission);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final Commission commission = getItem(position);
        if (commission != null) {
            viewHolder.memberNameTextView.setText(FormatUtils.formatMsianPhone(commission.getMemberName()));
            viewHolder.memberIdTextView.setText(commission.getMember_id());
            viewHolder.topUpTextView.setText(String.format(context.getString(R.string.template_rm_s), FormatUtils.formatAmount(commission.getSales_amount())));
            viewHolder.topUpDateTextView.setText(commission.getPerformance_date());
            viewHolder.commissionTextView.setText(String.format(context.getString(R.string.template_rm_s), FormatUtils.formatAmount(commission.getCommission_amount())));
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, commission));
        }
        return convertView;
    }
}

