package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;

import com.dj.user.R;
import com.dj.user.model.response.Country;

public class CountryListViewAdapter extends CustomAdapter<Country> {

    private class ViewHolder {
        private LinearLayout itemPanel;
        private TextView titleTextView;
        private ImageView radioImageView;
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
            viewHolder.radioImageView = convertView.findViewById(R.id.imageView_radio);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final Country country = getItem(position);
        if (country != null) {
            viewHolder.titleTextView.setText(String.format(context.getString(R.string.template_s_space_plus_s), country.getCountry_name(), country.getPhone_code()));
            viewHolder.radioImageView.setImageResource(country.isSelected() ? R.drawable.ic_radio_selected : R.drawable.ic_radio);
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, country));
        }
        return convertView;
    }
}

