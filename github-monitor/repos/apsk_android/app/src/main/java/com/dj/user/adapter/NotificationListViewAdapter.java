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
import com.dj.user.model.response.Notification;
import com.dj.user.util.DateFormatUtils;

public class NotificationListViewAdapter extends CustomAdapter<Notification> {
    private class ViewHolder {
        private LinearLayout itemPanel;
        private TextView subjectTextView, dateTextView;
        private ImageView readImageView;
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
            viewHolder.subjectTextView = convertView.findViewById(R.id.textView_subject);
            viewHolder.dateTextView = convertView.findViewById(R.id.textView_date);
            viewHolder.readImageView = convertView.findViewById(R.id.imageView_read);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final Notification notification = getItem(position);
        if (notification != null) {
            viewHolder.subjectTextView.setText(notification.getTitle());
            viewHolder.dateTextView.setText(DateFormatUtils.formatIsoDate(notification.getCreated_on(), DateFormatUtils.FORMAT_DD_MM_YYYY_HH_MM_A));
            viewHolder.readImageView.setVisibility(notification.getIs_read() ? View.GONE : View.VISIBLE);
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, notification));
        }
        return convertView;
    }
}

