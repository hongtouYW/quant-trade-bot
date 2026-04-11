package com.dj.user.dj.adapter;

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
import com.dj.user.model.ItemSection;

public class DJProfileSectionListViewAdapter extends DJCustomAdapter<ItemSection> {
    private class ViewHolder {
        private LinearLayout itemPanel;
        private ImageView iconImageView, ctaIconImageView;
        private TextView titleTextView, infoTextView;
    }

    public DJProfileSectionListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.dj_item_list_section, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.iconImageView = convertView.findViewById(R.id.imageView_icon);
            viewHolder.titleTextView = convertView.findViewById(R.id.textView_title);
            viewHolder.infoTextView = convertView.findViewById(R.id.textView_info);
            viewHolder.ctaIconImageView = convertView.findViewById(R.id.imageView_cta);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final ItemSection itemSection = getItem(position);
        if (itemSection != null) {
            viewHolder.iconImageView.setImageResource(itemSection.getIconImgResId());
            viewHolder.titleTextView.setText(itemSection.getTitle());
            viewHolder.infoTextView.setText(itemSection.getInfo());
            viewHolder.infoTextView.setTextColor(ContextCompat.getColor(context, R.color.gray_505A7B));
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, itemSection));
        }
        return convertView;
    }
}

