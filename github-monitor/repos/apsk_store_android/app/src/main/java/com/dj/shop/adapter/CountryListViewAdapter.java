package com.dj.shop.adapter;

import android.content.Context;
import android.graphics.Color;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.core.content.ContextCompat;

import com.dj.shop.R;
import com.dj.shop.model.response.Country;

public class CountryListViewAdapter extends CustomAdapter<Country> {

    private class ViewHolder {
        private LinearLayout itemPanel;
        private TextView titleTextView, descTextView;
    }

    public CountryListViewAdapter(Context context) {
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
            viewHolder.descTextView = convertView.findViewById(R.id.textView_desc);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final Country country = getItem(position);
        if (country != null) {
            viewHolder.titleTextView.setText(country.getCountry_name());
            viewHolder.descTextView.setText(String.format("+%s", country.getPhone_code()));
            if (country.isSelected()) {
                viewHolder.itemPanel.setBackgroundColor(ContextCompat.getColor(context, R.color.black_112240));
            } else {
                viewHolder.itemPanel.setBackgroundColor(Color.TRANSPARENT);
            }
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, country));
        }
        return convertView;
    }
}

