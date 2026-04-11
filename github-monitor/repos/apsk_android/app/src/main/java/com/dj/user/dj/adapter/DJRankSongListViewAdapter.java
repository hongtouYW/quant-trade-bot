package com.dj.user.dj.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;

import com.dj.user.R;
import com.dj.user.model.Song;

public class DJRankSongListViewAdapter extends DJCustomAdapter<Song> {

    private class ViewHolder {
        private LinearLayout itemPanel;
        private TextView rankTextView;
    }

    public DJRankSongListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_rank_song, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.rankTextView = convertView.findViewById(R.id.textView_rank);

            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final Song song = getItem(position);
        if (song != null) {
            viewHolder.rankTextView.setText(String.valueOf(position + 1));
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, song));
        }
        return convertView;
    }
}

