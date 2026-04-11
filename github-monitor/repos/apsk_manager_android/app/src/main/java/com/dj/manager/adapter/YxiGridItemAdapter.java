package com.dj.manager.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.BaseAdapter;
import android.widget.Filter;
import android.widget.Filterable;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;

import com.dj.manager.R;
import com.dj.manager.model.response.YxiProvider;
import com.dj.manager.util.StringUtil;
import com.squareup.picasso.Callback;
import com.squareup.picasso.Picasso;

import java.util.ArrayList;
import java.util.List;

public class YxiGridItemAdapter extends BaseAdapter implements Filterable {
    public interface FilterListener {
        void onFilterResult(boolean isEmpty);
    }

    private final Context context;
    private List<YxiProvider> originalItems;
    private List<YxiProvider> filteredItems;
    private GameFilter filter;
    private FilterListener filterListener;
    private int selectedPosition = -1;

    public YxiGridItemAdapter(Context context) {
        this.context = context;
    }

    public void setFilterListener(FilterListener listener) {
        this.filterListener = listener;
    }

    public void setData(List<YxiProvider> yxiProviderList) {
        if (yxiProviderList == null) {
            yxiProviderList = new ArrayList<>();
        }
        this.originalItems = yxiProviderList;
        this.filteredItems = new ArrayList<>(yxiProviderList);
        notifyDataSetChanged();
    }

    @Override
    public int getCount() {
        return filteredItems == null ? 0 : filteredItems.size();
    }

    @Override
    public YxiProvider getItem(int position) {
        return (filteredItems != null && position >= 0 && position < filteredItems.size()) ? filteredItems.get(position) : null;
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
            row = inflater.inflate(R.layout.item_grid_yxi, parent, false);
        }
        LinearLayout itemPanel = row.findViewById(R.id.panel_item);
        ImageView yxiImageView = row.findViewById(R.id.imageView);
        TextView label = row.findViewById(R.id.textView);

        YxiProvider item = getItem(position);
        if (item != null) {
            String icon = item.getIcon();
            if (!StringUtil.isNullOrEmpty(icon)) {
                if (!icon.startsWith("http")) {
                    icon = String.format(context.getString(R.string.template_s_s), context.getString(R.string.image_base_url), icon);
                }
                Picasso.get().load(icon).centerCrop().fit().into(yxiImageView, new Callback() {
                    @Override
                    public void onSuccess() {

                    }

                    @Override
                    public void onError(Exception e) {
                        yxiImageView.setImageResource(R.drawable.img_provider_default);
                    }
                });
            } else {
                yxiImageView.setImageResource(R.drawable.img_provider_default);
            }
            label.setText(item.getProvider_name());
            label.setSelected(true);
            itemPanel.setBackgroundResource(item.isSelected() ? R.drawable.bg_game_selection : android.R.color.transparent);
            itemPanel.setOnClickListener(v -> {
                for (YxiProvider yxiProvider : filteredItems) {
                    yxiProvider.setSelected(false);
                }
                item.setSelected(true);
                notifyDataSetChanged();
            });
        }
        return row;
    }

    public YxiProvider getSelectedYxiProvider() {
        for (YxiProvider yxiProvider : filteredItems) {
            if (yxiProvider.isSelected()) {
                return yxiProvider;
            }
        }
        return null;
    }

    @Override
    public Filter getFilter() {
        if (filter == null) {
            filter = new GameFilter();
        }
        return filter;
    }

    private class GameFilter extends Filter {
        @Override
        protected FilterResults performFiltering(CharSequence constraint) {
            FilterResults results = new FilterResults();
            List<YxiProvider> filteredList;

            if (constraint == null || constraint.length() == 0) {
                filteredList = new ArrayList<>(originalItems);
            } else {
                String filterString = constraint.toString().toLowerCase().trim();
                filteredList = new ArrayList<>();

                for (YxiProvider yxiProvider : originalItems) {
                    if (yxiProvider.getProvider_name() != null &&
                            yxiProvider.getProvider_name().toLowerCase().contains(filterString)) {
                        filteredList.add(yxiProvider);
                    }
                }
            }

            results.values = filteredList;
            results.count = filteredList.size();
            return results;
        }

        @Override
        protected void publishResults(CharSequence constraint, FilterResults results) {
            filteredItems = (List<YxiProvider>) results.values;
            selectedPosition = -1;
            notifyDataSetChanged();

            if (filterListener != null) {
                filterListener.onFilterResult(filteredItems == null || filteredItems.isEmpty());
            }
        }
    }
}
