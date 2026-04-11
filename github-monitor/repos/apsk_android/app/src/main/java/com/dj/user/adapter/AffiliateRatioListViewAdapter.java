package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;

import com.dj.user.R;
import com.dj.user.model.ItemRatio;

public class AffiliateRatioListViewAdapter extends CustomAdapter<ItemRatio> {

    private class ViewHolder {
        private LinearLayout itemPanel, headerPanel, contentPanel;
        private TextView column1TextView, column2TextView, column3TextView;
    }

    public AffiliateRatioListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_affiliate_ratio, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.headerPanel = convertView.findViewById(R.id.panel_header);
            viewHolder.contentPanel = convertView.findViewById(R.id.panel_content);
            viewHolder.column1TextView = convertView.findViewById(R.id.textView_col_1);
            viewHolder.column2TextView = convertView.findViewById(R.id.textView_col_2);
            viewHolder.column3TextView = convertView.findViewById(R.id.textView_col_3);

            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final ItemRatio ratio = getItem(position);
        if (ratio != null) {
            if (ratio.isHeader()) {
                viewHolder.itemPanel.setBackgroundResource(R.drawable.bg_top_rounded_white);
                viewHolder.headerPanel.setVisibility(View.VISIBLE);
                viewHolder.contentPanel.setVisibility(View.GONE);
            } else {
                viewHolder.itemPanel.setBackgroundResource(R.color.blue_122346);
                viewHolder.column1TextView.setText(ratio.getCol1());
                viewHolder.column2TextView.setText(ratio.getCol2());
                viewHolder.column3TextView.setText(ratio.getCol3());
                viewHolder.headerPanel.setVisibility(View.GONE);
                viewHolder.contentPanel.setVisibility(View.VISIBLE);
            }

            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, ratio));
        }
        return convertView;
    }
}

