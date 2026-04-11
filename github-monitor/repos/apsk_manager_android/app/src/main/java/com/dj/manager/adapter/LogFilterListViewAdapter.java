package com.dj.manager.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.Filter;
import android.widget.Filterable;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.core.content.ContextCompat;

import com.dj.manager.R;
import com.dj.manager.activity.log.LogFilterActivity;
import com.dj.manager.enums.SelectionMode;
import com.dj.manager.model.ItemBinder;

import java.util.ArrayList;
import java.util.List;

public class LogFilterListViewAdapter<T> extends CustomAdapter<T> implements Filterable {

    private final ItemBinder<T> binder;
    private boolean isAllSelected = false;
    private SelectionMode selectionMode = SelectionMode.MULTIPLE;
    private final List<T> originalList = new ArrayList<>();
    private final List<T> filteredList = new ArrayList<>();
    private FilterListener filterListener;

    public interface FilterListener {
        void onFilterResult(boolean isEmpty);
    }

    private class ViewHolder {
        private LinearLayout itemPanel;
        private ImageView iconImageView;
        private TextView titleTextView;
    }

    public LogFilterListViewAdapter(Context context, ItemBinder<T> binder) {
        super(context);
        this.binder = binder;
    }

    public void setFilterListener(FilterListener listener) {
        this.filterListener = listener;
    }

    public void setSelectionMode(SelectionMode mode) {
        this.selectionMode = mode;
    }

    @Override
    public List<T> getList() {
        return filteredList;
    }

    public void replaceList(List<T> list) {
        originalList.clear();
        originalList.addAll(list);

        filteredList.clear();
        filteredList.addAll(list);

        notifyDataSetChanged();
    }

    @Override
    public int getCount() {
        return filteredList.size();
    }

    @Override
    public T getItem(int position) {
        return filteredList.get(position);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_log_filter, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.iconImageView = convertView.findViewById(R.id.imageView_icon);
            viewHolder.titleTextView = convertView.findViewById(R.id.textView_title);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final T item = getItem(position);
        if (item != null) {
            viewHolder.iconImageView.setImageResource(binder.isSelected(item) ? R.drawable.ic_radio_ticked : R.drawable.ic_radio);
            viewHolder.titleTextView.setText(binder.getTitle(item));
            viewHolder.titleTextView.setTextColor(ContextCompat.getColor(context, binder.isSelected(item) ? R.color.gold_D4AF37 : R.color.white_FFFFFF));
            if (isAllSelected && !binder.getId(item).equalsIgnoreCase("0")) {
                viewHolder.itemPanel.setAlpha(0.4F);
                viewHolder.itemPanel.setOnClickListener(null);
            } else {
                viewHolder.itemPanel.setAlpha(1F);
                viewHolder.itemPanel.setOnClickListener(view -> {
                    setSelectedItem(item);
                    onItemClickListener.onItemClick(position, item);
                });
            }
        }
        return convertView;
    }

    private void setSelectedItem(T item) {
        String itemId = binder.getId(item);
        if (selectionMode == SelectionMode.SINGLE) {
            for (T t : originalList) {
                binder.setSelected(t, false);
            }
            binder.setSelected(item, true);
        } else {
            // MULTIPLE MODE
            if (itemId.equalsIgnoreCase("0")) {
                boolean newState = !binder.isSelected(item);
                isAllSelected = newState;
                for (T t : originalList) {
                    binder.setSelected(t, newState);
                }
            } else {
                binder.setSelected(item, !binder.isSelected(item));
                if (isAllSelected && !binder.isSelected(item)) {
                    for (T t : originalList) {
                        if (binder.getId(t).equalsIgnoreCase("0")) {
                            binder.setSelected(t, false);
                            break;
                        }
                    }
                    isAllSelected = false;
                }
            }
        }
        ((LogFilterActivity) context).updateTitle(getSelectedCount());
        notifyDataSetChanged();
    }

    public List<T> getSelectedItem() {
        List<T> selectedItems = new ArrayList<>();
        for (T t : originalList) {
            if (binder.isSelected(t)) {
                selectedItems.add(t);
            }
        }
        return selectedItems;
    }

    public int getSelectedCount() {
        int count = 0;
        for (T t : originalList) {
            if (binder.getId(t).equalsIgnoreCase("0")) continue;
            if (binder.isSelected(t)) {
                count++;
            }
        }
        return count;
    }

    public List<String> getSelectedIds() {
        List<String> selectedIds = new ArrayList<>();
        for (T t : originalList) {
            String id = binder.getId(t);
            if ("0".equalsIgnoreCase(id)) continue; // skip "All"
            if (binder.isSelected(t)) {
                selectedIds.add(id);
            }
        }
        return selectedIds;
    }

    @Override
    public Filter getFilter() {
        return new Filter() {
            @Override
            protected FilterResults performFiltering(CharSequence constraint) {
                List<T> results = new ArrayList<>();
                if (constraint == null || constraint.length() == 0) {
                    results.addAll(originalList);
                } else {
                    String lower = constraint.toString().toLowerCase();
                    for (T item : originalList) {
                        if (binder.getTitle(item).toLowerCase().contains(lower)) {
                            results.add(item);
                        }
                    }
                }

                FilterResults filterResults = new FilterResults();
                filterResults.values = results;
                filterResults.count = results.size();
                return filterResults;
            }

            @SuppressWarnings("unchecked")
            @Override
            protected void publishResults(CharSequence constraint, FilterResults results) {
                filteredList.clear();
                filteredList.addAll((List<T>) results.values);
                notifyDataSetChanged();

                if (filterListener != null) {
                    filterListener.onFilterResult(filteredList.isEmpty());
                }
            }
        };
    }
}