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
import com.dj.user.model.response.Downline;
import com.dj.user.util.DateFormatUtils;
import com.dj.user.util.StringUtil;

public class AffiliateDownlineListViewAdapter extends CustomAdapter<Downline> {
    private class ViewHolder {
        private LinearLayout itemPanel;
        private TextView usernameTextView, idTextView, joinDateTextView, invitationLinkTextView;
        private ImageView copyImageView;
    }

    public AffiliateDownlineListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_affiliate_downline, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.usernameTextView = convertView.findViewById(R.id.textView_username);
            viewHolder.idTextView = convertView.findViewById(R.id.textView_id);
            viewHolder.joinDateTextView = convertView.findViewById(R.id.textView_join_date);
            viewHolder.invitationLinkTextView = convertView.findViewById(R.id.textView_invitation_link);
            viewHolder.copyImageView = convertView.findViewById(R.id.imageView_copy);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final Downline downline = getItem(position);
        if (downline != null) {
            viewHolder.usernameTextView.setText(downline.getMemberName());
            viewHolder.usernameTextView.setVisibility(!StringUtil.isNullOrEmpty(downline.getMemberName()) ? View.VISIBLE : View.GONE);
            viewHolder.idTextView.setText(String.format(context.getString(R.string.affiliate_downline_id), downline.getMember_id()));
            viewHolder.joinDateTextView.setText(String.format(context.getString(R.string.affiliate_downline_join_date), DateFormatUtils.formatIsoDate(downline.getRegistered_on(), DateFormatUtils.FORMAT_DD_MMM_YYYY_HH_MM_A)));
            viewHolder.invitationLinkTextView.setText(String.format(context.getString(R.string.affiliate_downline_invitation_code), downline.getInvitecode()));
            viewHolder.copyImageView.setOnClickListener(view -> StringUtil.copyToClipboard(context, "", downline.getInvitecode()));
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, downline));
        }
        return convertView;
    }
}

