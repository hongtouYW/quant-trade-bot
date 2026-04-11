package com.dj.manager.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.core.content.ContextCompat;

import com.dj.manager.R;
import com.dj.manager.model.response.Area;

public class AreaListViewAdapter extends CustomAdapter<Area> {

    private class ViewHolder {
        private LinearLayout itemPanel;
        private TextView titleTextView;
    }

    public AreaListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_country, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.titleTextView = convertView.findViewById(R.id.textView_title);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final Area area = getItem(position);
        if (area != null) {
            viewHolder.titleTextView.setText(area.getArea_name());
            if (area.isSelected()) {
                viewHolder.itemPanel.setBackgroundColor(ContextCompat.getColor(context, R.color.black_232627));
                viewHolder.titleTextView.setTextColor(ContextCompat.getColor(context, R.color.gold_D4AF37));
            } else {
                viewHolder.itemPanel.setBackgroundColor(ContextCompat.getColor(context, android.R.color.transparent));
                viewHolder.titleTextView.setTextColor(ContextCompat.getColor(context, R.color.white_FFFFFF));
            }
            viewHolder.itemPanel.setOnClickListener(view -> {
                updateSelection(position);
                if (onItemClickListener != null) {
                    onItemClickListener.onItemClick(position, area);
                }
            });
        }
        return convertView;
    }

    public void updateSelection(int selectedPosition) {
        for (int i = 0; i < getCount(); i++) {
            Area area = getItem(i);
            if (area != null) {
                area.setSelected(i == selectedPosition);
            }
        }
        notifyDataSetChanged();
    }

    public int getSelectedPosition() {
        for (int i = 0; i < getCount(); i++) {
            Area area = getItem(i);
            if (area != null && area.isSelected()) {
                return i;
            }
        }
        return 0; // None selected
    }
}

