package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.BaseExpandableListAdapter;
import android.widget.ImageView;
import android.widget.TextView;

import com.dj.user.R;
import com.dj.user.model.response.FAQ;

import java.util.List;
import java.util.Map;

public class HelpExpandableListAdapter extends BaseExpandableListAdapter {

    private Context context;
    private List<String> groupList;
    private Map<String, List<FAQ>> childMap;

    public HelpExpandableListAdapter(Context context, List<String> groupList, Map<String, List<FAQ>> childMap) {
        this.context = context;
        this.groupList = groupList;
        this.childMap = childMap;
    }

    @Override
    public int getGroupCount() {
        return groupList.size();
    }

    @Override
    public int getChildrenCount(int groupPosition) {
        List<FAQ> children = childMap.get(groupList.get(groupPosition));
        return (children != null) ? children.size() : 0;
    }

    @Override
    public Object getGroup(int groupPosition) {
        return groupList.get(groupPosition);
    }

    @Override
    public Object getChild(int groupPosition, int childPosition) {
        List<FAQ> children = childMap.get(groupList.get(groupPosition));
        if (children != null && childPosition < children.size()) {
            return children.get(childPosition);
        }
        return null;
    }

    @Override
    public long getGroupId(int groupPosition) {
        return groupPosition;
    }

    @Override
    public long getChildId(int groupPosition, int childPosition) {
        return childPosition;
    }

    @Override
    public boolean hasStableIds() {
        return false;
    }

    @Override
    public View getGroupView(int groupPosition, boolean isExpanded,
                             View convertView, ViewGroup parent) {
        String title = (String) getGroup(groupPosition);
        if (convertView == null) {
            LayoutInflater inflater = LayoutInflater.from(context);
            convertView = inflater.inflate(R.layout.item_group_expandable_help, parent, false);
        }
        TextView textView = convertView.findViewById(R.id.textView_group);
        ImageView iconImageView = convertView.findViewById(R.id.imageView_icon);
        View dividerView = convertView.findViewById(R.id.view_divider);
        textView.setText(title);
        iconImageView.setImageResource(isExpanded ? R.drawable.ic_chev_up_blue : R.drawable.ic_chev_down_blue);
        if (groupPosition == getGroupCount() - 1 && !isExpanded) {
            dividerView.setVisibility(View.GONE);
        } else {
            dividerView.setVisibility(View.VISIBLE);
        }
        return convertView;
    }

    @Override
    public View getChildView(int groupPosition, int childPosition,
                             boolean isLastChild, View convertView, ViewGroup parent) {
        FAQ faq = (FAQ) getChild(groupPosition, childPosition);
        if (convertView == null) {
            LayoutInflater inflater = LayoutInflater.from(context);
            convertView = inflater.inflate(R.layout.item_list_expandable_help, parent, false);
        }
        TextView textView = convertView.findViewById(R.id.textView_child);
        textView.setText(faq.getTitle());
        return convertView;
    }

    @Override
    public boolean isChildSelectable(int groupPosition, int childPosition) {
        return true;
    }
}
