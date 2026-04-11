package com.dj.manager.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.Filter;
import android.widget.Filterable;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.core.content.ContextCompat;

import com.dj.manager.R;
import com.dj.manager.enums.PlayerStatus;
import com.dj.manager.model.response.Player;
import com.dj.manager.util.FormatUtils;

import java.util.ArrayList;
import java.util.List;

public class PlayerListViewAdapter extends CustomAdapter<Player> implements Filterable {
    public interface FilterListener {
        void onFilterResult(boolean isEmpty);
    }

    private List<Player> originalList;
    private List<Player> filteredList;
    private FilterListener filterListener;

    private class ViewHolder {
        private LinearLayout itemPanel;
        private View statusView;
        private TextView idTextView, yxiBalanceTextView, statusTextView;
    }

    public PlayerListViewAdapter(Context context) {
        super(context);
    }

    public void setFilterListener(FilterListener listener) {
        this.filterListener = listener;
    }

    public void setData(List<Player> data) {
        this.originalList = data;
        this.filteredList = new ArrayList<>(data);
        notifyDataSetChanged();
    }

    @Override
    public int getCount() {
        return filteredList != null ? filteredList.size() : 0;
    }

    @Override
    public Player getItem(int position) {
        return filteredList != null ? filteredList.get(position) : null;
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_player, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.idTextView = convertView.findViewById(R.id.textView_id);
            viewHolder.yxiBalanceTextView = convertView.findViewById(R.id.textView_yxi_balance);
            viewHolder.statusView = convertView.findViewById(R.id.view_status);
            viewHolder.statusTextView = convertView.findViewById(R.id.textView_status);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final Player player = getItem(position);
        if (player != null) {
            viewHolder.idTextView.setText(player.getLoginId());
            viewHolder.yxiBalanceTextView.setText(FormatUtils.formatAmount(player.getBalance()));

            PlayerStatus status = player.getPlayerStatus();
            viewHolder.statusTextView.setText(status.getTitle());
            viewHolder.statusTextView.setTextColor(ContextCompat.getColor(context, status.getColorResId()));
            if (status == PlayerStatus.ACTIVE) {
                viewHolder.statusView.setBackgroundResource(R.drawable.bg_dot_green);
                viewHolder.statusTextView.setTextColor(ContextCompat.getColor(context, R.color.white_FFFFFF));
            } else if (status == PlayerStatus.BLOCKED) {
                viewHolder.statusView.setBackgroundResource(R.drawable.bg_dot_gray);
            } else if (status == PlayerStatus.DELETED) {
                viewHolder.statusView.setBackgroundResource(R.drawable.bg_dot_red);
            }

            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, player));
        }
        return convertView;
    }

    @Override
    public Filter getFilter() {
        return new Filter() {
            @Override
            protected FilterResults performFiltering(CharSequence constraint) {
                String keyword = constraint.toString().toLowerCase();
                List<Player> resultList = new ArrayList<>();
                if (keyword.isEmpty()) {
                    resultList = originalList;
                } else {
                    for (Player item : originalList) {
                        if (item.getGamemember_id().toLowerCase().contains(keyword) ||
                                item.getLogin().toLowerCase().contains(keyword)) {
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
                filteredList = (List<Player>) results.values;
                notifyDataSetChanged();

                if (filterListener != null) {
                    filterListener.onFilterResult(filteredList == null || filteredList.isEmpty());
                }
            }
        };
    }
}

