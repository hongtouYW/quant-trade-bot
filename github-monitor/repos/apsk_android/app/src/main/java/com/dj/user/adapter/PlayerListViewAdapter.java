package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;

import com.dj.user.R;
import com.dj.user.model.response.Player;
import com.dj.user.util.FormatUtils;

public class PlayerListViewAdapter extends CustomAdapter<Player> {

    private class ViewHolder {
        private LinearLayout itemPanel;
        private TextView nameTextView, idTextView, pointTextView;
    }

    public PlayerListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_player, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.nameTextView = convertView.findViewById(R.id.textView_name);
            viewHolder.idTextView = convertView.findViewById(R.id.textView_id);
            viewHolder.pointTextView = convertView.findViewById(R.id.textView_point);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final Player player = getItem(position);
        if (player != null) {
            viewHolder.nameTextView.setText(player.getName());
            viewHolder.idTextView.setText(String.format("ID: %s", player.getGamemember_id()));
            viewHolder.pointTextView.setText(FormatUtils.formatAmount(player.getBalance()));
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, player));
        }
        return convertView;
    }
}

