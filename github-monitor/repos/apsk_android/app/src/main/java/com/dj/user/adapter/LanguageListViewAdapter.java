package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.appcompat.widget.SwitchCompat;

import com.dj.user.R;
import com.dj.user.activity.mine.setting.LanguageActivity;
import com.dj.user.model.ItemLanguage;

public class LanguageListViewAdapter extends CustomAdapter<ItemLanguage> {
    private int selectedPosition = 0;

    private class ViewHolder {
        private LinearLayout itemPanel;
        private ImageView iconImageView;
        private TextView titleTextView;
        private SwitchCompat switchCompat;
    }

    public LanguageListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_language, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.iconImageView = convertView.findViewById(R.id.imageView_icon);
            viewHolder.titleTextView = convertView.findViewById(R.id.textView_title);
            viewHolder.switchCompat = convertView.findViewById(R.id.switchCompat);

            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final ItemLanguage language = getItem(position);
        if (language != null) {
            viewHolder.iconImageView.setImageResource(R.drawable.ic_general_language);
            viewHolder.titleTextView.setText(language.getTitle());

            // Avoid triggering listener during state restore
            viewHolder.switchCompat.setOnCheckedChangeListener(null);
            viewHolder.switchCompat.setChecked(position == selectedPosition);

            viewHolder.switchCompat.setOnCheckedChangeListener((buttonView, isChecked) -> {
                if (isChecked) {
                    selectedPosition = position;
                    notifyDataSetChanged();
                    ((LanguageActivity) context).setLocale(language.getCode());
                } else {
                    // Prevent deselecting the only selected item
                    if (selectedPosition == position) {
                        viewHolder.switchCompat.setChecked(true);
                    }
                }
            });

            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, language));
        }
        return convertView;
    }

    public void setSelectedPosition(int selectedPosition) {
        this.selectedPosition = selectedPosition;
        notifyDataSetChanged();
    }

    public ItemLanguage getSelectedLanguage() {
        return getItem(selectedPosition);
    }
}

