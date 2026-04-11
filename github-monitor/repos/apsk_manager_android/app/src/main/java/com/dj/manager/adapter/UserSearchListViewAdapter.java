package com.dj.manager.adapter;

import android.content.Context;
import android.text.Spannable;
import android.text.SpannableString;
import android.text.style.ForegroundColorSpan;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.core.content.ContextCompat;

import com.dj.manager.R;
import com.dj.manager.enums.UserStatus;
import com.dj.manager.model.response.Member;
import com.dj.manager.util.DateFormatUtils;
import com.dj.manager.util.FormatUtils;

public class UserSearchListViewAdapter extends CustomAdapter<Member> {

    private String keyword;

    private class ViewHolder {
        private LinearLayout itemPanel;
        private View statusView;
        private TextView idTextView, createdFromTextView, joinDateTextView, statusTextView;
    }

    public UserSearchListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_user, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.idTextView = convertView.findViewById(R.id.textView_id);
            viewHolder.createdFromTextView = convertView.findViewById(R.id.textView_created_from);
            viewHolder.joinDateTextView = convertView.findViewById(R.id.textView_date);
            viewHolder.statusView = convertView.findViewById(R.id.view_status);
            viewHolder.statusTextView = convertView.findViewById(R.id.textView_status);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final Member member = getItem(position);
        if (member != null) {
            viewHolder.idTextView.setTextColor(ContextCompat.getColor(context, R.color.gray_7B7B7B));
            setHighlightedText(viewHolder.idTextView, FormatUtils.formatMsianPhone(member.getPhone()));

            viewHolder.createdFromTextView.setText(member.isCreatedByShop() ? context.getString(R.string.user_list_created_shop) : context.getString(R.string.user_list_created_online));
            viewHolder.joinDateTextView.setText(DateFormatUtils.formatIsoDate(member.getCreated_on(), DateFormatUtils.FORMAT_YYYY_MM_DD_HH_MM_A));

            UserStatus status = member.getUserStatus();
            viewHolder.statusTextView.setText(status.getTitle());
            viewHolder.statusTextView.setTextColor(ContextCompat.getColor(context, status.getColorResId()));
            if (status == UserStatus.ACTIVE) {
                viewHolder.statusView.setBackgroundResource(R.drawable.bg_dot_green);
                viewHolder.statusTextView.setTextColor(ContextCompat.getColor(context, R.color.white_FFFFFF));
            } else if (status == UserStatus.BLOCKED) {
                viewHolder.statusView.setBackgroundResource(R.drawable.bg_dot_gray);
            } else if (status == UserStatus.DELETED) {
                viewHolder.statusView.setBackgroundResource(R.drawable.bg_dot_red);
            }
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, member));
        }
        return convertView;
    }

    private void setHighlightedText(TextView textView, String fullText) {
        if (keyword == null || keyword.isEmpty()) {
            textView.setText(fullText);
            return;
        }
        String fullTextNoSpace = fullText.replace(" ", "").toLowerCase();
        String lowerKeyword = keyword.replace(" ", "").toLowerCase();

        int startNoSpace = fullTextNoSpace.indexOf(lowerKeyword);
        if (startNoSpace < 0) {
            textView.setText(fullText);
            return;
        }
        int realStart = mapIndexToWithSpaces(fullText, startNoSpace);
        int realEnd = mapIndexToWithSpaces(fullText, startNoSpace + lowerKeyword.length());

        SpannableString spannable = new SpannableString(fullText);
        int highlightColor = context.getResources().getColor(R.color.white_FFFFFF);
        spannable.setSpan(new ForegroundColorSpan(highlightColor),
                realStart, realEnd,
                Spannable.SPAN_EXCLUSIVE_EXCLUSIVE);

        textView.setText(spannable);
    }

    private int mapIndexToWithSpaces(String withSpaces, int noSpaceIndex) {
        int count = 0;
        for (int i = 0; i < withSpaces.length(); i++) {
            if (withSpaces.charAt(i) != ' ') {
                if (count == noSpaceIndex) return i;
                count++;
            }
        }
        return withSpaces.length();
    }

    public void setKeyword(@Nullable String keyword) {
        this.keyword = keyword;
    }
}

