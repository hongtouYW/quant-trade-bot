package com.dj.shop.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.BaseAdapter;
import android.widget.ImageView;
import android.widget.TextView;

import androidx.annotation.NonNull;

import com.dj.shop.R;
import com.dj.shop.model.ItemGrid;

import java.util.List;

public class AccountGridItemAdapter extends BaseAdapter {

    private final Context context;
    private final List<ItemGrid> items;

    public AccountGridItemAdapter(@NonNull Context context, @NonNull List<ItemGrid> items) {
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
            row = inflater.inflate(R.layout.item_grid_acccount, parent, false);
        }

        ImageView image = row.findViewById(R.id.imageView);
        TextView label = row.findViewById(R.id.textView);

        ItemGrid item = getItem(position);
        image.setImageResource(item.imageResId);
        label.setText(item.label);

        return row;
    }
}
