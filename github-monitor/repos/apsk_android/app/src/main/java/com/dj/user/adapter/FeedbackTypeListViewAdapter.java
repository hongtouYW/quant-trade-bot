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
import com.dj.user.model.response.FeedbackType;

public class FeedbackTypeListViewAdapter extends CustomAdapter<FeedbackType> {

    private int selectedIndex = -1;

    private class ViewHolder {
        private LinearLayout itemPanel;
        private TextView titleTextView;
        private ImageView radioImageView;
    }

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
            viewHolder.radioImageView = convertView.findViewById(R.id.imageView_radio);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final FeedbackType feedbackType = getItem(position);
        if (feedbackType != null) {
            viewHolder.titleTextView.setText(feedbackType.getTitle());
            viewHolder.radioImageView.setImageResource(feedbackType.isSelected() ? R.drawable.ic_radio_selected : R.drawable.ic_radio_white);
            viewHolder.itemPanel.setOnClickListener(view -> {
                setSelectedIndex(position);
                onItemClickListener.onItemClick(position, feedbackType);
            });
        }
        return convertView;
    }

    public void setSelectedIndex(int index) {
        for (int i = 0; i < getCount(); i++) {
            FeedbackType feedbackType = getItem(i);
            if (feedbackType != null) {
                feedbackType.setSelected(i == index);
            }
        }
        notifyDataSetChanged();
    }

    public FeedbackType getSelectedSubject() {
        for (int i = 0; i < getCount(); i++) {
            FeedbackType feedbackType = getItem(i);
            if (feedbackType != null && feedbackType.isSelected()) {
                return feedbackType;
            }
        }
        return null;
    }

}

