package com.dj.manager.adapter;

import android.content.Context;
import android.graphics.Typeface;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.BaseAdapter;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.core.content.ContextCompat;
import androidx.core.content.res.ResourcesCompat;

import com.dj.manager.R;
import com.dj.manager.model.ItemMonth;

import java.util.List;

public class MonthGridItemAdapter extends BaseAdapter {

    private final Context context;
    private final List<ItemMonth> items;
    private OnMonthClickListener listener;

    public MonthGridItemAdapter(Context context, List<ItemMonth> items) {
        this.context = context;
        this.items = items;
    }

    public void setOnMonthClickListener(OnMonthClickListener listener) {
        this.listener = listener;
    }

    @Override
    public int getCount() {
        return items.size();
    }

    @Override
    public ItemMonth getItem(int position) {
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
            row = inflater.inflate(R.layout.item_grid_month, parent, false);
        }

        LinearLayout itemPanel = row.findViewById(R.id.panel_item);
        TextView titleTextView = row.findViewById(R.id.textView);

        ItemMonth item = getItem(position);

        Typeface boldTypeface = ResourcesCompat.getFont(context, R.font.poppins_semi_bold);
        Typeface normalTypeface = ResourcesCompat.getFont(context, R.font.poppins_regular);
        titleTextView.setText(item.getTitle());
        titleTextView.setTextColor(ContextCompat.getColor(context, item.isSelected() ? R.color.black_000000 : R.color.white_FFFFFF));
        titleTextView.setTypeface(item.isSelected() ? boldTypeface : normalTypeface);
        if (!item.isEnabled()) {
            titleTextView.setAlpha(0.4f);
            titleTextView.setEnabled(false);
        } else {
            titleTextView.setAlpha(1f);
            titleTextView.setEnabled(true);
        }
        itemPanel.setBackgroundResource(item.isSelected() ? R.drawable.bg_selected_month : android.R.color.transparent);
        if (item.isEnabled()) {
            itemPanel.setOnClickListener(v -> {
                if (listener != null) {
                    listener.onMonthClick(item);
                }
            });
        } else {
            itemPanel.setOnClickListener(null); // disable
        }
        return row;
    }

    public void setSelectedMonth(int monthId) {
        for (ItemMonth item : items) {
            item.setSelected(item.getId() == monthId);
        }
        notifyDataSetChanged();
    }

    public interface OnMonthClickListener {
        void onMonthClick(ItemMonth month);
    }
}
