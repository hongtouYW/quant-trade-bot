package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.BaseExpandableListAdapter;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;

import com.dj.user.R;
import com.dj.user.model.response.Player;
import com.dj.user.model.response.YxiProvider;
import com.dj.user.util.FormatUtils;

import java.util.List;
import java.util.Map;

public class TransferExpandableListAdapter extends BaseExpandableListAdapter {

    private Context context;
    private List<YxiProvider> groupList;
    private Map<YxiProvider, List<YxiProvider>> childMap;

    private OnTransferActionListener transferActionListener;

    public interface OnTransferActionListener {
        void onAddClicked(YxiProvider yxiProvider, int position);

        void onMinusClicked(YxiProvider yxiProvider, int position);
    }

    public TransferExpandableListAdapter(Context context, List<YxiProvider> groupList, Map<YxiProvider, List<YxiProvider>> childMap) {
        this.context = context;
        this.groupList = groupList;
        this.childMap = childMap;
    }

    public void setOnTransferActionListener(OnTransferActionListener listener) {
        this.transferActionListener = listener;
    }

    @Override
    public int getGroupCount() {
        return groupList.size();
    }

    @Override
    public int getChildrenCount(int groupPosition) {
        List<YxiProvider> children = childMap.get(groupList.get(groupPosition));
        return (children != null) ? children.size() : 0;
    }

    @Override
    public Object getGroup(int groupPosition) {
        return groupList.get(groupPosition);
    }

    @Override
    public Object getChild(int groupPosition, int childPosition) {
        List<YxiProvider> children = childMap.get(groupList.get(groupPosition));
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
        YxiProvider yxiProvider = (YxiProvider) getGroup(groupPosition);
        Player player = yxiProvider.getThePlayer();
        if (convertView == null) {
            LayoutInflater inflater = LayoutInflater.from(context);
            convertView = inflater.inflate(R.layout.item_group_expandable_transfer, parent, false);
        }
        LinearLayout headerPanel = convertView.findViewById(R.id.panel_header);
        LinearLayout contentPanel = convertView.findViewById(R.id.panel_content);
        TextView yxiTextView = convertView.findViewById(R.id.textView_yxi);
        TextView pointsTextView = convertView.findViewById(R.id.textView_points);
//        ImageView addImageView = convertView.findViewById(R.id.imageView_add);
        TextView transferTextView = convertView.findViewById(R.id.textView_transfer);
        ImageView chevImageView = convertView.findViewById(R.id.imageView_chev);
        View dividerView = convertView.findViewById(R.id.view_divider);

        if (yxiProvider.isHeader()) {
            headerPanel.setVisibility(View.VISIBLE);
            contentPanel.setVisibility(View.GONE);
        } else {
            if (player == null) {
                player = new Player();
                chevImageView.setVisibility(View.INVISIBLE); // TODO: 10/11/2025
            } else {
                chevImageView.setVisibility(View.INVISIBLE); // TODO: 10/11/2025
            }
            player.setTransferAmount(player.getBalance());
            headerPanel.setVisibility(View.GONE);
            contentPanel.setVisibility(View.VISIBLE);

            yxiTextView.setText(yxiProvider.getProvider_name().toUpperCase());
            pointsTextView.setText(FormatUtils.formatAmount(player.getBalance()));
            transferTextView.setText(FormatUtils.formatAmount(player.getTransferAmount()));
            chevImageView.setImageResource(isExpanded ? R.drawable.ic_chev_up_gradient : R.drawable.ic_chev_down_gradient);
//            addImageView.setOnClickListener(view -> {
//                if (transferActionListener != null) {
//                    transferActionListener.onAddClicked(player, groupPosition);
//                }
//            });
//            minusImageView.setOnClickListener(view -> {
//                if (transferActionListener != null) {
//                    transferActionListener.onMinusClicked(player, groupPosition);
//                }
//            });
        }
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
        YxiProvider yxiProvider = (YxiProvider) getChild(groupPosition, childPosition);
        Player player = yxiProvider.getThePlayer();
        if (convertView == null) {
            LayoutInflater inflater = LayoutInflater.from(context);
            convertView = inflater.inflate(R.layout.item_list_expandable_transfer, parent, false);
        }
        TextView yxiTextView = convertView.findViewById(R.id.textView_yxi);
        TextView pointsTextView = convertView.findViewById(R.id.textView_points);
//        ImageView addImageView = convertView.findViewById(R.id.imageView_add);
        TextView transferTextView = convertView.findViewById(R.id.textView_transfer);
//        ImageView minusImageView = convertView.findViewById(R.id.imageView_minus);

        if (player != null) {
            yxiTextView.setText(yxiProvider.getProvider_name().toUpperCase());
            pointsTextView.setText(FormatUtils.formatAmount(player.getBalance()));
            transferTextView.setText(FormatUtils.formatAmount(player.getTransferAmount()));

//            addImageView.setOnClickListener(view -> {
//                if (transferActionListener != null) {
//                    transferActionListener.onAddClicked(yxiProvider, groupPosition);
//                }
//            });
//            minusImageView.setOnClickListener(view -> {
//                if (transferActionListener != null) {
//                    transferActionListener.onMinusClicked(yxiProvider, groupPosition);
//                }
//            });
        }
        return convertView;
    }

    @Override
    public boolean isChildSelectable(int groupPosition, int childPosition) {
        return false;
    }

    public void updateTransferAmount(int position, double newAmount) {
        if (position < 0 || position >= groupList.size()) return;

        YxiProvider yxiProvider = groupList.get(position);
        Player player = yxiProvider.getThePlayer();
        if (!yxiProvider.isHeader() && player != null) {
            player.setTransferAmount(newAmount);
            notifyDataSetChanged();
        }
    }

    public void updateProviderList(List<YxiProvider> newList) {
        if (newList == null || newList.isEmpty()) return;
        for (YxiProvider newProvider : newList) {
            for (YxiProvider oldProvider : groupList) {
                // Compare by ID or name (whichever uniquely identifies the provider)
                if (oldProvider.getProvider_id().equals(newProvider.getProvider_id())) {
                    List<Player> newPlayers = newProvider.getPlayer();
                    List<Player> oldPlayers = oldProvider.getPlayer();
                    if (newPlayers != null && oldPlayers != null) {
                        for (int i = 0; i < Math.min(newPlayers.size(), oldPlayers.size()); i++) {
                            Player newP = newPlayers.get(i);
                            Player oldP = oldPlayers.get(i);
                            if (newP.getBalance() != oldP.getBalance()) {
                                oldP.setBalance(String.valueOf(newP.getBalance()));
                            }
                        }
                    }
                    break;
                }
            }
        }
        notifyDataSetChanged();
    }

}
