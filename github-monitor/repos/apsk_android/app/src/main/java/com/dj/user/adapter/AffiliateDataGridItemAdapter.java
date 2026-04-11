package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.BaseAdapter;
import android.widget.RelativeLayout;
import android.widget.TextView;

import androidx.core.content.ContextCompat;

import com.dj.user.R;
import com.dj.user.model.ItemGrid;

import java.util.List;

public class AffiliateDataGridItemAdapter extends BaseAdapter {

    private final Context context;
    private final List<ItemGrid> items;

    public AffiliateDataGridItemAdapter(Context context, List<ItemGrid> items) {
        this.context = context;
        this.items = items;
    }

    @Override
    public int getCount() {
        return items.size();
    }

    @Override
    public ItemGrid getItem(int position) {
        return items.get(position);
    }

    @Override
    public long getItemId(int position) {
        return position;
    }

    @Override
    public View getView(int position, View convertView, ViewGroup parent) {
        View row = convertView;
        if (row == null) {
            LayoutInflater inflater = LayoutInflater.from(context);
            row = inflater.inflate(R.layout.item_grid_affiliate_data, parent, false);
        }

        RelativeLayout itemPanel = row.findViewById(R.id.panel_item);
        TextView titleTextView = row.findViewById(R.id.textView_title);
        TextView valueTextView = row.findViewById(R.id.textView_value);

        ItemGrid item = getItem(position);
        titleTextView.setText(item.getTitle());
        valueTextView.setText(item.getValue());
        valueTextView.setTextColor(ContextCompat.getColor(context, item.isValueInOrange() ? R.color.orange_F8AF07 : R.color.white_FFFFFF));

        return row;
    }
}
