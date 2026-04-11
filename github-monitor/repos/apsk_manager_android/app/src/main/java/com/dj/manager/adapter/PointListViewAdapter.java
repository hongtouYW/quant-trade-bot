package com.dj.manager.adapter;

import android.content.Context;
import android.graphics.Paint;
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
import com.dj.manager.enums.PointType;
import com.dj.manager.model.response.Point;
import com.dj.manager.util.DateFormatUtils;
import com.dj.manager.util.FormatUtils;

import java.util.ArrayList;
import java.util.List;

public class PointListViewAdapter extends CustomAdapter<Point> implements Filterable {
    public interface FilterListener {
        void onFilterResult(boolean isEmpty);
    }

    private List<Point> originalList;
    private List<Point> filteredList;
    private FilterListener filterListener;

    private class ViewHolder {
        private LinearLayout itemPanel;
        private ImageView typeImageView;
        private TextView typeTextView, amountTextView, statusTextView, idTextView, phoneTextView, dateTimeTextView, operatedByTextView;
    }

    public PointListViewAdapter(Context context) {
        super(context);
    }

    public void setFilterListener(FilterListener listener) {
        this.filterListener = listener;
    }

    public void setData(List<Point> data) {
        this.originalList = data;
        this.filteredList = new ArrayList<>(data);
        notifyDataSetChanged();
    }

    @Override
    public int getCount() {
        return filteredList != null ? filteredList.size() : 0;
    }

    @Override
    public Point getItem(int position) {
        return filteredList != null ? filteredList.get(position) : null;
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_point, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.typeImageView = convertView.findViewById(R.id.imageView_type);
            viewHolder.typeTextView = convertView.findViewById(R.id.textView_type);
            viewHolder.amountTextView = convertView.findViewById(R.id.textView_amount);
            viewHolder.statusTextView = convertView.findViewById(R.id.textView_status);
            viewHolder.idTextView = convertView.findViewById(R.id.textView_member_id);
            viewHolder.phoneTextView = convertView.findViewById(R.id.textView_member_phone);
            viewHolder.dateTimeTextView = convertView.findViewById(R.id.textView_date_time);
            viewHolder.operatedByTextView = convertView.findViewById(R.id.textView_operated_by);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final Point point = getItem(position);
        if (point != null) {
            PointType pointType = PointType.fromValue(point.getType());
            viewHolder.typeTextView.setText(pointType.getTitle());
            viewHolder.typeImageView.setImageResource(pointType.getIconResId());

            double amount = point.getAmount();
            String amountStr = FormatUtils.formatAmount(Math.abs(amount));
            viewHolder.amountTextView.setText(String.format(context.getString(R.string.template_s_s), pointType.getSymbol(), amountStr));
            viewHolder.amountTextView.setTextColor(ContextCompat.getColor(context, pointType.getColorResId()));
            if (point.getStatus() == 0) {
                viewHolder.amountTextView.setPaintFlags(viewHolder.amountTextView.getPaintFlags() | Paint.STRIKE_THRU_TEXT_FLAG);
            }
            viewHolder.statusTextView.setVisibility(point.getStatus() == 0 ? View.VISIBLE : View.GONE);
            viewHolder.idTextView.setText(point.getPlayerLogin());
            viewHolder.phoneTextView.setText(point.getPhoneNumber());
            viewHolder.dateTimeTextView.setText(DateFormatUtils.formatIsoDate(point.getCreated_on(), DateFormatUtils.FORMAT_YYYY_MM_DD_HH_MM_A));
            viewHolder.operatedByTextView.setText(point.isCreatedByShop() ? point.getShopName() : context.getString(R.string.point_details_operated_by_user));
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, point));
        }
        return convertView;
    }

    @Override
    public Filter getFilter() {
        return new Filter() {
            @Override
            protected FilterResults performFiltering(CharSequence constraint) {
                String keyword = constraint.toString().toLowerCase();
                List<Point> resultList = new ArrayList<>();
                if (keyword.isEmpty()) {
                    resultList = originalList;
                } else {
                    for (Point item : originalList) {
                        if (item.getShopName().toLowerCase().contains(keyword) ||
                                item.getPhoneNumber().toLowerCase().contains(keyword) ||
                                item.getPlayerLogin().toLowerCase().contains(keyword)) {
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
                filteredList = (List<Point>) results.values;
                notifyDataSetChanged();

                if (filterListener != null) {
                    filterListener.onFilterResult(filteredList == null || filteredList.isEmpty());
                }
            }
        };
    }
}

