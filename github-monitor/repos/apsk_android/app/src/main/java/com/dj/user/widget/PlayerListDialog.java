package com.dj.user.widget;

import android.app.Dialog;
import android.content.Context;
import android.graphics.Color;
import android.graphics.drawable.ColorDrawable;
import android.view.ViewGroup;
import android.view.Window;
import android.widget.ImageView;
import android.widget.LinearLayout;

import com.dj.user.R;
import com.dj.user.adapter.PlayerListViewAdapter;
import com.dj.user.model.response.Player;
import com.dj.user.util.FormatUtils;

import java.util.ArrayList;
import java.util.List;

public class PlayerListDialog extends Dialog {

    private final Context context;
    private ArrayList<Player> playerList;
    private ImageView favsImageView;
    private LinearLayout addPanel;
    private PlayerListViewAdapter playerListViewAdapter;
    private OnPlayerSelectedListener playerListener;
    private OnAddPlayerListener addPlayerListener;
    private OnFavYxiListener favYxiListener;
    private boolean isFaved = false;

    public interface OnPlayerSelectedListener {
        void onPlayerSelected(Player player, int position);
    }

    public interface OnAddPlayerListener {
        void onAddPlayer();
    }

    public interface OnFavYxiListener {
        void onFavYxi();
    }

    public PlayerListDialog(Context context, boolean isFaved, ArrayList<Player> list, OnPlayerSelectedListener playerListener, OnAddPlayerListener addPlayerListener, OnFavYxiListener favYxiListener) {
        super(context);
        this.context = context;
        this.isFaved = isFaved;
        this.playerList = list;
        this.playerListener = playerListener;
        this.addPlayerListener = addPlayerListener;
        this.favYxiListener = favYxiListener;

        if (getWindow() != null) {
            getWindow().setBackgroundDrawable(new ColorDrawable(Color.TRANSPARENT));
        }
        requestWindowFeature(Window.FEATURE_NO_TITLE);
        setCancelable(false);
        setCanceledOnTouchOutside(false);
        setupDialog();
        setupDialogWidthLarge();
    }

    private void setupDialog() {
        setContentView(R.layout.dialog_player_list);

        ImageView dismissImageView = findViewById(R.id.imageView_dismiss);
        favsImageView = findViewById(R.id.imageView_fav);
        ExpandableHeightListView listView = findViewById(R.id.listView_player);
        addPanel = findViewById(R.id.panel_add);

        playerListViewAdapter = new PlayerListViewAdapter(context);
        playerListViewAdapter.replaceList(playerList);
        playerListViewAdapter.setOnItemClickListener((position, player) -> {
            if (this.playerListener != null) {
                this.playerListener.onPlayerSelected((Player) player, position);
            }
        });
        listView.setAdapter(playerListViewAdapter);
        dismissImageView.setOnClickListener(v -> dismiss());
        favsImageView.setImageResource(this.isFaved ? R.drawable.ic_fav_selected : R.drawable.ic_fav);
        favsImageView.setOnClickListener(v -> {
            if (this.favYxiListener != null) {
                this.favYxiListener.onFavYxi();
            }
        });
        addPanel.setEnabled(playerList.isEmpty());
        addPanel.setAlpha(playerList.isEmpty() ? 1.0F : 0.5F);
        addPanel.setOnClickListener(v -> {
            if (this.addPlayerListener != null) {
                this.addPlayerListener.onAddPlayer();
            }
        });
    }

    public void updatePlayers(List<Player> newPlayers) {
        if (playerListViewAdapter != null) {
            playerListViewAdapter.replaceList(newPlayers);
        }
        addPanel.setEnabled(newPlayers.isEmpty());
        addPanel.setAlpha(newPlayers.isEmpty() ? 1.0F : 0.5F);
    }

    public void updateFavStatus(boolean isFaved) {
        this.isFaved = isFaved;
        favsImageView.setImageResource(isFaved ? R.drawable.ic_fav_selected : R.drawable.ic_fav);
    }

    private void setupDialogWidthLarge() {
        int oneSidePadding = FormatUtils.dpToPx(context, 10);
        int deviceWidth = FormatUtils.getDeviceWidth(context);
        int maxWidthPx = FormatUtils.dpToPx(context, 340); // 340dp converted to pixels
        int dialogWidth = deviceWidth - (oneSidePadding * 2);
//        if (dialogWidth > maxWidthPx) {
//            dialogWidth = maxWidthPx; // limit width to 340dp
//        }
        if (getWindow() != null) {
            getWindow().setLayout(dialogWidth, ViewGroup.LayoutParams.WRAP_CONTENT);
        }
    }
}
