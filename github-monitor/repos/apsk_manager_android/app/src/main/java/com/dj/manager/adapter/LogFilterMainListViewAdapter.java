package com.dj.manager.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.core.content.ContextCompat;

import com.dj.manager.R;
import com.dj.manager.activity.log.LogFilterMainActivity;
import com.dj.manager.activity.log.YxiLogFilterMainActivity;
import com.dj.manager.enums.LogFilterType;
import com.dj.manager.model.ItemFilter;

public class LogFilterMainListViewAdapter extends CustomAdapter<ItemFilter> {

    private boolean isYxi = false;

    private class ViewHolder {
        private LinearLayout itemPanel;
        private ImageView iconImageView;
        private TextView titleTextView, descTextView;
    }

    public LogFilterMainListViewAdapter(Context context, boolean isYxi) {
        super(context);
        this.isYxi = isYxi;
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_log_filter_main, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.iconImageView = convertView.findViewById(R.id.imageView_icon);
            viewHolder.titleTextView = convertView.findViewById(R.id.textView_title);
            viewHolder.descTextView = convertView.findViewById(R.id.textView_desc);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final ItemFilter item = getItem(position);
        if (item != null) {
            LogFilterType logFilterType = item.getLogFilterType();
            viewHolder.iconImageView.setImageResource(item.isSelected() ? R.drawable.ic_radio_selected : R.drawable.ic_radio);
            viewHolder.titleTextView.setText(logFilterType.getTitle());
            viewHolder.descTextView.setText(item.getDesc());
            viewHolder.descTextView.setTextColor(ContextCompat.getColor(context, item.getSelectedIds() != null && !item.getSelectedIds().isEmpty() ? R.color.gold_D4AF37 : R.color.white_FFFFFF));
            viewHolder.descTextView.setOnClickListener(view -> {
                setSelectedItem(item);
                if (isYxi) {
                    ((YxiLogFilterMainActivity) context).navigateToLogFilter(item);
                } else {
                    ((LogFilterMainActivity) context).navigateToLogFilter(item);
                }
            });
            viewHolder.itemPanel.setOnClickListener(view -> {
                setSelectedItem(item);
                onItemClickListener.onItemClick(position, item);
            });
        }
        return convertView;
    }

    public void setSelectedItem(ItemFilter item) {
        for (ItemFilter itemFilter : getList()) {
            itemFilter.setSelected(item.getLogFilterType() == itemFilter.getLogFilterType());
        }
        notifyDataSetChanged();
    }

    public ItemFilter getSelectedItem() {
        for (int i = 0; i < getCount(); i++) {
            ItemFilter f = getItem(i);
            if (f != null && f.isSelected()) {
                return f;
            }
        }
        return null;
    }
}

