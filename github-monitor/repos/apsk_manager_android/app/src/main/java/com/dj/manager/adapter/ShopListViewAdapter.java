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
import com.dj.manager.activity.dashboard.DashboardActivity;
import com.dj.manager.activity.shop.ShopManagementActivity;
import com.dj.manager.enums.ShopStatus;
import com.dj.manager.model.response.Shop;
import com.dj.manager.util.FormatUtils;

import java.util.ArrayList;
import java.util.List;

public class ShopListViewAdapter extends CustomAdapter<Shop> implements Filterable {
    public interface FilterListener {
        void onFilterResult(boolean isEmpty);
    }

    private List<Shop> originalList;
    private List<Shop> filteredList;
    private FilterListener filterListener;
    private boolean isDashboard = false;

    private class ViewHolder {
        private LinearLayout itemPanel;
        private View statusView;
        private ImageView pinImageView;
        private TextView shopNickNameTextView, shopNameTextView, statusTextView, userTextView, yxiTextView, balanceTextView, incomeTextView;
    }

    public ShopListViewAdapter(Context context, boolean isDashboard) {
        super(context);
        this.isDashboard = isDashboard;
    }

    public void setFilterListener(FilterListener listener) {
        this.filterListener = listener;
    }

    public void setData(List<Shop> data) {
        this.originalList = data;
        this.filteredList = new ArrayList<>(data);
        notifyDataSetChanged();
    }

    @Override
    public int getCount() {
        return filteredList != null ? filteredList.size() : 0;
    }

    @Override
    public Shop getItem(int position) {
        return filteredList != null ? filteredList.get(position) : null;
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_shop, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.statusView = convertView.findViewById(R.id.view_status);
            viewHolder.shopNickNameTextView = convertView.findViewById(R.id.textView_shop_nickname);
            viewHolder.pinImageView = convertView.findViewById(R.id.imageView_pin);
            viewHolder.shopNameTextView = convertView.findViewById(R.id.textView_shop_name);
            viewHolder.statusTextView = convertView.findViewById(R.id.textView_status);
            viewHolder.userTextView = convertView.findViewById(R.id.textView_user);
            viewHolder.yxiTextView = convertView.findViewById(R.id.textView_yxi);
            viewHolder.balanceTextView = convertView.findViewById(R.id.textView_balance);
            viewHolder.incomeTextView = convertView.findViewById(R.id.textView_income);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final Shop shop = getItem(position);
        if (shop != null) {
            if (!isDashboard && shop.isPinned()) {
                viewHolder.itemPanel.setBackgroundColor(ContextCompat.getColor(context, R.color.black_232627));
            } else {
                viewHolder.itemPanel.setBackgroundColor(ContextCompat.getColor(context, android.R.color.transparent));
            }
            viewHolder.shopNickNameTextView.setText(shop.getShop_name());
            viewHolder.shopNameTextView.setText(shop.getShop_login());
            viewHolder.userTextView.setText(String.valueOf(shop.getTotalmember()));
            viewHolder.yxiTextView.setText(String.valueOf(shop.getTotalplayer()));
            viewHolder.balanceTextView.setText(FormatUtils.formatAmount(shop.getBalance()));

            double income = shop.getTotalincome();
            String incomeStr = FormatUtils.formatAmount(Math.abs(income));
            if (income >= 0) {
                viewHolder.incomeTextView.setText(String.format("+%s", incomeStr));
            } else {
                viewHolder.incomeTextView.setText(String.format("-%s", incomeStr));
            }

            ShopStatus status = shop.getShopStatus();
            viewHolder.statusTextView.setText(status.getTitle());
            if (status == ShopStatus.ACTIVE) {
                viewHolder.statusView.setBackgroundResource(R.drawable.bg_dot_green);
                viewHolder.statusTextView.setTextColor(ContextCompat.getColor(context, R.color.white_FFFFFF));
                viewHolder.itemPanel.setAlpha(1.0F);
            } else if (status == ShopStatus.CLOSED) {
                viewHolder.statusView.setBackgroundResource(R.drawable.bg_dot_gray);
                viewHolder.statusTextView.setTextColor(ContextCompat.getColor(context, status.getColorResId()));
                viewHolder.itemPanel.setAlpha(0.4F);
            }

            viewHolder.pinImageView.setImageResource(shop.isPinned() ? R.drawable.ic_pinned : R.drawable.ic_pin);
            viewHolder.pinImageView.setOnClickListener(view -> {
                if (isDashboard) {
                    ((DashboardActivity) context).pinUnpinShop(shop);
                } else {
                    ((ShopManagementActivity) context).pinUnpinShop(shop);
                }
                shop.setPinned(shop.isPinned() ? 0 : 1);
                notifyDataSetChanged();
            });
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, shop));
        }
        return convertView;
    }

    @Override
    public Filter getFilter() {
        return new Filter() {
            @Override
            protected FilterResults performFiltering(CharSequence constraint) {
                String keyword = constraint.toString().toLowerCase();
                List<Shop> resultList = new ArrayList<>();
                if (keyword.isEmpty()) {
                    resultList = originalList;
                } else {
                    for (Shop item : originalList) {
                        if (item.getShop_name().toLowerCase().contains(keyword)) {
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
                filteredList = (List<Shop>) results.values;
                notifyDataSetChanged();

                if (filterListener != null) {
                    filterListener.onFilterResult(filteredList == null || filteredList.isEmpty());
                }
            }
        };
    }
}

