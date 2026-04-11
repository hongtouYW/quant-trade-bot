package com.dj.user.adapter;

import android.content.Context;
import android.graphics.Typeface;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.core.content.ContextCompat;
import androidx.core.content.res.ResourcesCompat;

import com.dj.user.R;
import com.dj.user.enums.PromotionCategory;

public class CategoryListViewAdapter extends CustomAdapter<PromotionCategory> {
    private int selectedPosition = 0;

    private class ViewHolder {
        private LinearLayout itemPanel;
        private ImageView iconImageView;
        private TextView titleTextView;
    }

    public CategoryListViewAdapter(Context context) {
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

        final PromotionCategory category = getItem(position);
        if (category != null) {
            if (selectedPosition == position) {
                viewHolder.itemPanel.setBackgroundResource(R.drawable.bg_category_item_active);
                String iconResourceName = category.getIconResourceName() + "_selected";
                int fromResId = context.getResources().getIdentifier(iconResourceName, "drawable", context.getPackageName());
                viewHolder.iconImageView.setImageResource(fromResId);

                Typeface boldTypeface = ResourcesCompat.getFont(context, R.font.pingfang_sc_bold);
                viewHolder.titleTextView.setTypeface(boldTypeface);
                viewHolder.titleTextView.setTextColor(ContextCompat.getColor(context, R.color.white_FFFFFF));
            } else {
                viewHolder.itemPanel.setBackgroundResource(R.drawable.bg_category_item_2);
                String iconResourceName = category.getIconResourceName();
                int fromResId = context.getResources().getIdentifier(iconResourceName, "drawable", context.getPackageName());
                viewHolder.iconImageView.setImageResource(fromResId);

                Typeface normalTypeface = ResourcesCompat.getFont(context, R.font.pingfang_sc_regular);
                viewHolder.titleTextView.setTypeface(normalTypeface);
                viewHolder.titleTextView.setTextColor(ContextCompat.getColor(context, R.color.gray_A49A81));
            }
            viewHolder.titleTextView.setText(category.getTitle());
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, category));
        }
        return convertView;
    }

    public PromotionCategory getSelectedCategory() {
        return getItem(selectedPosition);
    }

    public void setSelectedPosition(int position) {
        this.selectedPosition = position;
        notifyDataSetChanged();
    }

}

