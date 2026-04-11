package com.dj.shop.adapter;

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

import com.dj.shop.R;
import com.dj.shop.model.response.Member;
import com.dj.shop.util.FormatUtils;

public class SearchListViewAdapter extends CustomAdapter<Member> {

    private String keyword;

    private class ViewHolder {
        private LinearLayout itemPanel;
        private TextView phoneTextView;
    }

    public SearchListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_search, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.phoneTextView = convertView.findViewById(R.id.textView_phone);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final Member member = getItem(position);
        if (member != null) {
            setHighlightedText(viewHolder.phoneTextView, FormatUtils.formatMsianPhone(member.getPhone()));
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

    @Nullable
    public String getKeyword() {
        return keyword;
    }

    public void setKeyword(@Nullable String keyword) {
        this.keyword = keyword;
    }
}

