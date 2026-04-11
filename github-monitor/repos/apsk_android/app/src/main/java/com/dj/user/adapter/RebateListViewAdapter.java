package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;

import androidx.annotation.NonNull;

import com.dj.user.R;
import com.dj.user.model.Rebate;

public class RebateListViewAdapter extends CustomAdapter<Rebate> {

    private class ViewHolder {
        private LinearLayout itemPanel;
    }

    public RebateListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_rebate, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);

            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final Rebate rebate = getItem(position);
        if (rebate != null) {
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, rebate));
        }
        return convertView;
    }
}

