package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;

import com.dj.user.R;
import com.dj.user.model.response.Feedback;

public class FeedbackListViewAdapter extends CustomAdapter<Feedback> {
    private class ViewHolder {
        private LinearLayout itemPanel;
        private TextView titleTextView;
    }

    public FeedbackListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_feedback, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.titleTextView = convertView.findViewById(R.id.textView_title);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final Feedback feedback = getItem(position);
        if (feedback != null) {
            viewHolder.titleTextView.setText(feedback.getTitle());
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, feedback));
        }
        return convertView;
    }
}

