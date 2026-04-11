package com.dj.manager.adapter;

import android.content.Context;
import android.graphics.Typeface;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.core.content.ContextCompat;
import androidx.core.content.res.ResourcesCompat;

import com.dj.manager.R;
import com.dj.manager.model.response.Notification;
import com.dj.manager.util.StringUtil;

public class NotificationListViewAdapter extends CustomAdapter<Notification> {

    private class ViewHolder {
        private LinearLayout itemPanel, headerPanel, contentPanel;
        private TextView headerTextView, countTextView, subjectTextView, dateTextView;
    }

    public NotificationListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_notification, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.headerPanel = convertView.findViewById(R.id.panel_header);
            viewHolder.contentPanel = convertView.findViewById(R.id.panel_content);
            viewHolder.headerTextView = convertView.findViewById(R.id.textView_header);
            viewHolder.countTextView = convertView.findViewById(R.id.textView_count);
            viewHolder.subjectTextView = convertView.findViewById(R.id.textView_subject);
            viewHolder.dateTextView = convertView.findViewById(R.id.textView_date);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final Notification notification = getItem(position);
        if (notification != null) {
            if (!StringUtil.isNullOrEmpty(notification.getHeaderTitle())) {
                viewHolder.headerPanel.setVisibility(View.VISIBLE);
                viewHolder.headerTextView.setText(notification.getHeaderTitle());
                viewHolder.countTextView.setText(String.valueOf(notification.getCount()));
                viewHolder.countTextView.setVisibility(notification.getCount() > 0 ? View.VISIBLE : View.GONE);
                viewHolder.contentPanel.setVisibility(View.GONE);
                viewHolder.itemPanel.setBackgroundResource(android.R.color.transparent);
                viewHolder.itemPanel.setOnClickListener(null);
            } else {
                Typeface boldTypeface = ResourcesCompat.getFont(context, R.font.poppins_semi_bold);
                Typeface normalTypeface = ResourcesCompat.getFont(context, R.font.poppins_regular);

                viewHolder.subjectTextView.setText(notification.getNotification_desc());
                viewHolder.dateTextView.setText(notification.getCreatedOnAgoIfWithinDay(context));
                if (notification.getIs_read()) {
                    viewHolder.itemPanel.setBackgroundResource(R.color.gray_232627);
                    viewHolder.subjectTextView.setTypeface(boldTypeface);
                    viewHolder.subjectTextView.setTextColor(ContextCompat.getColor(context, R.color.gray_7E7E7E));
                    viewHolder.dateTextView.setTextColor(ContextCompat.getColor(context, R.color.gray_7E7E7E));
                } else {
                    viewHolder.itemPanel.setBackgroundResource(R.color.gray_464646);
                    viewHolder.subjectTextView.setTypeface(normalTypeface);
                    viewHolder.subjectTextView.setTextColor(ContextCompat.getColor(context, R.color.white_FFFFFF));
                    viewHolder.dateTextView.setTextColor(ContextCompat.getColor(context, R.color.white_FFFFFF));
                }
                viewHolder.headerPanel.setVisibility(View.GONE);
                viewHolder.contentPanel.setVisibility(View.VISIBLE);
                viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, notification));
            }
        }
        return convertView;
    }
}

