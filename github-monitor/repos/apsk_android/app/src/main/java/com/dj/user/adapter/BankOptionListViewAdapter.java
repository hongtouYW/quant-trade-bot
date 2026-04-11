package com.dj.user.adapter;

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
import androidx.annotation.Nullable;

import com.dj.user.R;
import com.dj.user.model.response.Bank;
import com.dj.user.util.StringUtil;
import com.squareup.picasso.Picasso;

import java.util.ArrayList;
import java.util.List;

public class BankOptionListViewAdapter extends CustomAdapter<Bank> implements Filterable {
    public interface FilterListener {
        void onFilterResult(boolean isEmpty);
    }

    List<Bank> originalList;
    List<Bank> filteredList;
    FilterListener filterListener;

    private class ViewHolder {
        private LinearLayout itemPanel;
        private ImageView iconImageView;
        private TextView bankTextView;
    }

    public BankOptionListViewAdapter(Context context) {
        super(context);
    }

    public void setFilterListener(@Nullable FilterListener listener) {
        this.filterListener = listener;
    }

    public void setData(@Nullable List<Bank> data) {
        this.originalList = data;
        this.filteredList = new ArrayList<>(data);
        notifyDataSetChanged();
    }

    @Override
    public int getCount() {
        return filteredList != null ? filteredList.size() : 0;
    }

    @Override
    public Bank getItem(int position) {
        return filteredList != null ? filteredList.get(position) : null;
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_bank_option, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.iconImageView = convertView.findViewById(R.id.imageView_icon);
            viewHolder.bankTextView = convertView.findViewById(R.id.textView_title);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final Bank bank = getItem(position);
        if (bank != null) {
            String icon = bank.getIcon();
            if (!StringUtil.isNullOrEmpty(icon)) {
                if (!icon.startsWith("http")) {
                    icon = String.format(context.getString(R.string.template_s_s), context.getString(R.string.image_base_url), icon);
                }
                Picasso.get().load(icon).centerInside().fit()
                        .placeholder(R.drawable.ic_top_up_online)
                        .into(viewHolder.iconImageView);
            } else {
                viewHolder.iconImageView.setImageResource(R.drawable.ic_top_up_online); // TODO: 05/08/2025
            }
            viewHolder.bankTextView.setText(bank.getBank_name());
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, bank));
        }
        return convertView;
    }

    @Override
    public Filter getFilter() {
        return new Filter() {
            @Override
            protected FilterResults performFiltering(CharSequence constraint) {
                String keyword = constraint.toString().toLowerCase();
                List<Bank> resultList = new ArrayList<>();
                if (keyword.isEmpty()) {
                    resultList = originalList;
                } else {
                    for (Bank item : originalList) {
                        if (item.getBank_name().toLowerCase().contains(keyword)) {
                            resultList.add(item);
                        }
                    }
                }

                FilterResults results = new FilterResults();
                results.values = resultList;
                return results;
            }

            @Override
            protected void publishResults(CharSequence constraint, FilterResults results) {
                filteredList = (List<Bank>) results.values;
                notifyDataSetChanged();

                if (filterListener != null) {
                    filterListener.onFilterResult(filteredList == null || filteredList.isEmpty());
                }
            }
        };
    }
}

