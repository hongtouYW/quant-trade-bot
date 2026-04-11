package com.dj.shop.adapter;

import android.content.Context;
import android.graphics.Color;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.core.content.ContextCompat;

import com.dj.shop.R;
import com.dj.shop.model.response.FeedbackType;

public class FeedbackTypeListViewAdapter extends CustomAdapter<FeedbackType> {

    public FeedbackTypeListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_feedback_type, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.titleTextView = convertView.findViewById(R.id.textView_title);
            viewHolder.descTextView = convertView.findViewById(R.id.textView_desc);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final FeedbackType feedbackType = getItem(position);
        if (feedbackType != null) {
            viewHolder.titleTextView.setText(feedbackType.getTitle());
            viewHolder.descTextView.setText(feedbackType.getFeedback_desc());
            if (feedbackType.isSelected()) {
                viewHolder.itemPanel.setBackgroundColor(ContextCompat.getColor(context, R.color.black_112240));
            } else {
                viewHolder.itemPanel.setBackgroundColor(Color.TRANSPARENT);
            }
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, feedbackType));
        }
        return convertView;
    }

    private class ViewHolder {
        private LinearLayout itemPanel;
        private TextView titleTextView, descTextView;
    }
}

