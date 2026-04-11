package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.core.content.ContextCompat;

import com.dj.user.R;
import com.dj.user.model.ItemCategory;

public class ItemCategoryListViewAdapter extends CustomAdapter<ItemCategory> {
    private int selectedPosition = 0;

    private class ViewHolder {
        private LinearLayout itemPanel;
        private ImageView iconImageView;
        private TextView titleTextView;
    }

    public ItemCategoryListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_category, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.iconImageView = convertView.findViewById(R.id.imageView_icon);
            viewHolder.titleTextView = convertView.findViewById(R.id.textView_title);

            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final ItemCategory category = getItem(position);
        if (category != null) {
            if (category.getIconResourceId() == 0 && category.getSelectedIconResourceId() == 0) {
                if (selectedPosition == position) {
                    viewHolder.iconImageView.setImageResource(position == 0 ? R.drawable.ic_game_all_selected : R.drawable.ic_game_selected);
                } else {
                    viewHolder.iconImageView.setImageResource(position == 0 ? R.drawable.ic_game_all : R.drawable.ic_game);
                }
            } else {
                viewHolder.iconImageView.setImageResource(selectedPosition == position ? category.getSelectedIconResourceId() : category.getIconResourceId());
            }
            viewHolder.titleTextView.setText(category.getTitle());
            viewHolder.titleTextView.setTextColor(ContextCompat.getColor(context, selectedPosition == position ? R.color.white_FFFFFF : R.color.black_000000));
            viewHolder.titleTextView.setSelected(true);
            viewHolder.itemPanel.setBackgroundResource(selectedPosition == position ? R.drawable.bg_category_item_active : R.drawable.bg_category_item);
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, category));
        }
        return convertView;
    }

    public ItemCategory getSelectedCategory() {
        return getItem(selectedPosition);
    }

    public void setSelectedPosition(int position) {
        this.selectedPosition = position;
        notifyDataSetChanged();
    }
}

