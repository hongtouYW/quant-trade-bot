package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;

import com.dj.user.R;
import com.dj.user.model.response.FriendCommission;
import com.dj.user.util.DateFormatUtils;
import com.dj.user.util.FormatUtils;

public class AffiliateCodeRewardListViewAdapter extends CustomAdapter<FriendCommission> {
    private class ViewHolder {
        private LinearLayout itemPanel;
        private TextView dateTextView, memberIdTextView, amountTextView;
    }

    public AffiliateCodeRewardListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_affiliate_code_reward, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.dateTextView = convertView.findViewById(R.id.textView_date);
            viewHolder.memberIdTextView = convertView.findViewById(R.id.textView_member_id);
            viewHolder.amountTextView = convertView.findViewById(R.id.textView_amount);

            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final FriendCommission friendCommission = getItem(position);
        if (friendCommission != null) {
            viewHolder.dateTextView.setText(DateFormatUtils.formatIsoDate(friendCommission.getCreated_on(), DateFormatUtils.FORMAT_YYYY_MM_DD_HH_MM_SS_DASHED));
            viewHolder.memberIdTextView.setText(friendCommission.getMember_id());
            viewHolder.amountTextView.setText(String.format(context.getString(R.string.template_rm_s), FormatUtils.formatAmount(friendCommission.getAmount())));
        }
        return convertView;
    }
}

