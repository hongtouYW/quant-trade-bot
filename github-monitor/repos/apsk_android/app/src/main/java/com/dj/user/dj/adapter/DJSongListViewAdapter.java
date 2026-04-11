package com.dj.user.dj.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;

import androidx.annotation.NonNull;

import com.dj.user.R;
import com.dj.user.model.Song;

public class DJSongListViewAdapter extends DJCustomAdapter<Song> {

    private class ViewHolder {
        private LinearLayout itemPanel;
    }

    public DJSongListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_song, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);

            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final Song song = getItem(position);
        if (song != null) {
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, song));
        }
        return convertView;
    }
}

