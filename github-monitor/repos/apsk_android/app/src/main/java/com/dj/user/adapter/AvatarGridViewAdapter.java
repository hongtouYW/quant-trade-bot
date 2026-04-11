package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.BaseAdapter;
import android.widget.ImageView;
import android.widget.LinearLayout;

import com.dj.user.R;
import com.dj.user.model.response.Avatar;
import com.squareup.picasso.Picasso;

import java.util.List;

public class AvatarGridViewAdapter extends BaseAdapter {

    private final Context context;
    private final List<Avatar> items;

    public AvatarGridViewAdapter(Context context, List<Avatar> items) {
        this.context = context;
        this.items = items;
    }

    @Override
    public int getCount() {
        return items.size();
    }

    @Override
    public Avatar getItem(int position) {
        return items.get(position);
    }

    @Override
    public long getItemId(int position) {
        return position;
    }

    @Override
    public View getView(int position, View convertView, ViewGroup parent) {
        ViewHolder holder;
        if (convertView == null) {
            convertView = LayoutInflater.from(context).inflate(R.layout.item_grid_profile_avatar, parent, false);
            holder = new ViewHolder();
            holder.itemPanel = convertView.findViewById(R.id.panel_item);
            holder.imageView = convertView.findViewById(R.id.imageView);
            convertView.setTag(holder);
        } else {
            holder = (ViewHolder) convertView.getTag();
        }

        Avatar item = getItem(position);
        if (holder.imageView.getTag() == null || !holder.imageView.getTag().equals(item.getUrl())) {
            Picasso.get().load(item.getUrl()).centerCrop().fit().into(holder.imageView);
            holder.imageView.setTag(item.getUrl());
        }
        holder.itemPanel.setBackgroundResource(
                item.isSelected() ? R.drawable.bg_avatar_selected : android.R.color.transparent
        );

        return convertView;
    }

    static class ViewHolder {
        LinearLayout itemPanel;
        ImageView imageView;
    }

    public void setSelected(int position) {
        if (position >= 0 && position < items.size()) {
            for (Avatar avatar : items) {
                avatar.setSelected(false);
            }
            items.get(position).setSelected(true);
            notifyDataSetChanged();
        }
    }

    public Avatar getSelected() {
        for (Avatar avatar : items) {
            if (avatar.isSelected()) {
                return avatar;
            }
        }
        return null;
    }
}
