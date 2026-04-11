package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;

import com.dj.user.R;
import com.dj.user.model.response.Friend;
import com.dj.user.util.DateFormatUtils;
import com.dj.user.util.FormatUtils;

public class AffiliateCodeFriendListViewAdapter extends CustomAdapter<Friend> {
    private class ViewHolder {
        private LinearLayout itemPanel;
        private TextView memberIdTextView, invitationCodeTextView, dateTextView, inviteCountTextView, tradedCountTextView, amountTextView;
    }

    public AffiliateCodeFriendListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_affiliate_code_friend, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.memberIdTextView = convertView.findViewById(R.id.textView_member_id);
            viewHolder.invitationCodeTextView = convertView.findViewById(R.id.textView_invitation_code);
            viewHolder.dateTextView = convertView.findViewById(R.id.textView_date);
            viewHolder.inviteCountTextView = convertView.findViewById(R.id.textView_invite_count);
            viewHolder.tradedCountTextView = convertView.findViewById(R.id.textView_traded_count);
            viewHolder.amountTextView = convertView.findViewById(R.id.textView_amount);

            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final Friend friend = getItem(position);
        if (friend != null) {
            viewHolder.memberIdTextView.setText(friend.getMember_id());
            viewHolder.invitationCodeTextView.setText(friend.getReferralCode());
            viewHolder.dateTextView.setText(DateFormatUtils.formatIsoDate(friend.getCreated_on(), DateFormatUtils.FORMAT_YYYY_MM_DD_HH_MM_SS_DASHED));
            viewHolder.inviteCountTextView.setText(String.valueOf(friend.getInvitecount()));
            viewHolder.tradedCountTextView.setText(String.valueOf(friend.getCreditcount()));
            viewHolder.amountTextView.setText(String.format(context.getString(R.string.template_rm_s), FormatUtils.formatAmount(friend.getCreditamount())));
        }
        return convertView;
    }
}

