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
import com.dj.manager.enums.UserStatus;
import com.dj.manager.model.response.Member;
import com.dj.manager.util.DateFormatUtils;
import com.dj.manager.util.FormatUtils;

import java.util.ArrayList;
import java.util.List;

public class UserListViewAdapter extends CustomAdapter<Member> implements Filterable {
    public interface FilterListener {
        void onFilterResult(boolean isEmpty);
    }

    private List<Member> originalList;
    private List<Member> filteredList;
    private FilterListener filterListener;

    private class ViewHolder {
        private LinearLayout itemPanel;
        private View statusView;
        private TextView idTextView, createdFromTextView, joinDateTextView, statusTextView;
    }

    public UserListViewAdapter(Context context) {
        super(context);
    }

    public void setFilterListener(FilterListener listener) {
        this.filterListener = listener;
    }

    public void setData(List<Member> data) {
        this.originalList = data;
        this.filteredList = new ArrayList<>(data);
        notifyDataSetChanged();
    }

    @Override
    public int getCount() {
        return filteredList != null ? filteredList.size() : 0;
    }

    @Override
    public Member getItem(int position) {
        return filteredList != null ? filteredList.get(position) : null;
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_user, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.idTextView = convertView.findViewById(R.id.textView_id);
            viewHolder.createdFromTextView = convertView.findViewById(R.id.textView_created_from);
            viewHolder.joinDateTextView = convertView.findViewById(R.id.textView_date);
            viewHolder.statusView = convertView.findViewById(R.id.view_status);
            viewHolder.statusTextView = convertView.findViewById(R.id.textView_status);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final Member member = getItem(position);
        if (member != null) {
            viewHolder.idTextView.setText(FormatUtils.formatMsianPhone(member.getPhone()));
            viewHolder.createdFromTextView.setText(member.isCreatedByShop() ? context.getString(R.string.user_list_created_shop) : context.getString(R.string.user_list_created_online));
            viewHolder.joinDateTextView.setText(DateFormatUtils.formatIsoDate(member.getCreated_on(), DateFormatUtils.FORMAT_YYYY_MM_DD_HH_MM_A));

            UserStatus status = member.getUserStatus();
            viewHolder.statusTextView.setText(status.getTitle());
            viewHolder.statusTextView.setTextColor(ContextCompat.getColor(context, status.getColorResId()));
            if (status == UserStatus.ACTIVE) {
                viewHolder.statusView.setBackgroundResource(R.drawable.bg_dot_green);
                viewHolder.statusTextView.setTextColor(ContextCompat.getColor(context, R.color.white_FFFFFF));
            } else if (status == UserStatus.BLOCKED) {
                viewHolder.statusView.setBackgroundResource(R.drawable.bg_dot_gray);
            } else if (status == UserStatus.DELETED) {
                viewHolder.statusView.setBackgroundResource(R.drawable.bg_dot_red);
            }
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, member));
        }
        return convertView;
    }

    @Override
    public Filter getFilter() {
        return new Filter() {
            @Override
            protected FilterResults performFiltering(CharSequence constraint) {
                String keyword = constraint.toString().toLowerCase();
                List<Member> resultList = new ArrayList<>();
                if (keyword.isEmpty()) {
                    resultList = originalList;
                } else {
                    for (Member item : originalList) {
                        if (item == null) continue;
                        String memberId = item.getMember_id() == null ? "" : item.getMember_id().toLowerCase();
                        String prefix = item.getPrefix() == null ? "" : item.getPrefix().toLowerCase();
                        String phone = item.getPhone() == null ? "" : item.getPhone().toLowerCase();
                        String memberName = item.getMember_name() == null ? "" : item.getMember_name().toLowerCase();
                        if (memberId.contains(keyword) || prefix.contains(keyword) || phone.contains(keyword) || memberName.contains(keyword)) {
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
                filteredList = (List<Member>) results.values;
                notifyDataSetChanged();

                if (filterListener != null) {
                    filterListener.onFilterResult(filteredList == null || filteredList.isEmpty());
                }
            }
        };
    }
}

