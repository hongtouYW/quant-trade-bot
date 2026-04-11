package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.BaseAdapter;
import android.widget.ImageView;
import android.widget.RelativeLayout;
import android.widget.TextView;

import com.dj.user.R;
import com.dj.user.model.ItemGrid;

import java.util.List;

public class ProfileActionGridItemAdapter extends BaseAdapter {

    private final Context context;
    private final List<ItemGrid> items;

    public ProfileActionGridItemAdapter(Context context, List<ItemGrid> items) {
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
            row = inflater.inflate(R.layout.item_grid_profile_action, parent, false);
        }

        RelativeLayout itemPanel = row.findViewById(R.id.panel_item);
        ImageView iconImageView = row.findViewById(R.id.imageView);
        TextView titleTextView = row.findViewById(R.id.textView);
        RelativeLayout tagPanel = row.findViewById(R.id.panel_tag);
        TextView tagTextView = row.findViewById(R.id.textView_tag);

        ItemGrid item = getItem(position);
        iconImageView.setImageResource(item.getImageResId());
        titleTextView.setText(item.getTitle());
        tagTextView.setText(item.getPromoTag());
        tagPanel.setVisibility(item.getPromoTag().isEmpty() ? View.GONE : View.VISIBLE);

        return row;
    }
}
