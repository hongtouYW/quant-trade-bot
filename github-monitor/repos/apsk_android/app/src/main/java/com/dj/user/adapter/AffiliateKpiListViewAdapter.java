package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;

import com.dj.user.R;
import com.dj.user.model.response.CommissionSummary;
import com.dj.user.util.FormatUtils;

import java.util.Locale;

public class AffiliateKpiListViewAdapter extends CustomAdapter<CommissionSummary> {
    private class ViewHolder {
        private LinearLayout itemPanel;
        private TextView dateTextView, totalCommissionTextView, totalMemberTextView;
    }

    public AffiliateKpiListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_affiliate_kpi, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.dateTextView = convertView.findViewById(R.id.textView_date);
            viewHolder.totalCommissionTextView = convertView.findViewById(R.id.textView_total_commission);
            viewHolder.totalMemberTextView = convertView.findViewById(R.id.textView_total_member);

            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final CommissionSummary kpi = getItem(position);
        if (kpi != null) {
            viewHolder.dateTextView.setText(kpi.getDate());
            viewHolder.totalCommissionTextView.setText(String.format(context.getString(R.string.template_rm_s), FormatUtils.formatAmount(kpi.getTotal_commission())));
            viewHolder.totalMemberTextView.setText(String.format(Locale.ENGLISH, context.getString(R.string.template_d_pax), kpi.getTotal_people()));
        }
        return convertView;
    }
}

