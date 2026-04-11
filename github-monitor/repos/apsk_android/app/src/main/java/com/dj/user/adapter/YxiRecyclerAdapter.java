package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.core.content.ContextCompat;
import androidx.recyclerview.widget.DiffUtil;
import androidx.recyclerview.widget.RecyclerView;

import com.dj.user.R;
import com.dj.user.model.response.Yxi;
import com.dj.user.util.StringUtil;
import com.squareup.picasso.Picasso;

import java.util.ArrayList;
import java.util.List;
import java.util.Objects;

public class YxiRecyclerAdapter extends RecyclerView.Adapter<YxiRecyclerAdapter.ViewHolder> {
    private final Context context;
    private List<Yxi> yxiList = new ArrayList<>();
    private OnYxiClickListener listener;
    private int selectedPosition = -1;

    public YxiRecyclerAdapter(Context context) {
        this.context = context;
        setHasStableIds(true);
    }

    public void setData(List<Yxi> newList) {
        if (newList == null) newList = new ArrayList<>();
        List<Yxi> finalNewList = newList;
        DiffUtil.DiffResult diffResult = DiffUtil.calculateDiff(new DiffUtil.Callback() {
            @Override
            public int getOldListSize() {
                return yxiList.size();
            }

            @Override
            public int getNewListSize() {
                return finalNewList.size();
            }

            @Override
            public boolean areItemsTheSame(int oldItemPosition, int newItemPosition) {
                Yxi oldItem = yxiList.get(oldItemPosition);
                Yxi newItem = finalNewList.get(newItemPosition);
                return Objects.equals(oldItem.getGame_id(), newItem.getGame_id());
            }

            @Override
            public boolean areContentsTheSame(int oldItemPosition, int newItemPosition) {
                Yxi oldItem = yxiList.get(oldItemPosition);
                Yxi newItem = finalNewList.get(newItemPosition);
                return Objects.equals(oldItem, newItem);
            }
        });
        yxiList.clear();
        yxiList.addAll(newList);
        diffResult.dispatchUpdatesTo(this);
    }

    public List<Yxi> getData() {
        return yxiList;
    }

    public void setOnYxiClickListener(OnYxiClickListener listener) {
        this.listener = listener;
    }

    @NonNull
    @Override
    public ViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        View view = LayoutInflater.from(context).inflate(R.layout.item_grid_yxi, parent, false);
        return new ViewHolder(view);
    }

    @Override
    public void onBindViewHolder(@NonNull ViewHolder holder, int position) {
        Yxi item = yxiList.get(position);
        String icon = item.getIcon();
        if (!StringUtil.isNullOrEmpty(icon)) {
            if (!icon.startsWith("http")) {
                icon = String.format(context.getString(R.string.template_s_s),
                        context.getString(R.string.image_base_url), icon);
            }
            Picasso.get().load(icon)
                    .placeholder(R.drawable.img_yxi_default)
                    .error(R.drawable.img_yxi_default)
                    .centerCrop().fit()
                    .into(holder.yxiImageView);
        } else {
            holder.yxiImageView.setImageResource(R.drawable.img_yxi_default);
        }
        holder.label.setText(item.getGame_name());
        holder.label.setSelected(true);

        boolean isSelected = (position == selectedPosition);
        holder.label.setTextColor(ContextCompat.getColor(context,
                isSelected ? R.color.orange_FFD01B : R.color.white_FFFFFF));
        holder.favImageView.setVisibility(item.isBookmark() ? View.VISIBLE : View.GONE);
        holder.itemPanel.setBackgroundResource(isSelected ? R.drawable.bg_yxi_selection : android.R.color.transparent);
        holder.itemPanel.setOnClickListener(v -> {
            int currentPos = holder.getAdapterPosition();
            if (currentPos == RecyclerView.NO_POSITION) return;
//            if (selectedPosition == currentPos) return; // already selected
            int previous = selectedPosition;
            selectedPosition = currentPos;
            // Update only changed items
            if (previous != -1) notifyItemChanged(previous);
            notifyItemChanged(currentPos);
            if (listener != null) {
                listener.onYxiClick(item);
            }
        });
    }

    @Override
    public int getItemCount() {
        return yxiList.size();
    }

    @Override
    public long getItemId(int position) {
        try {
            return Long.parseLong(yxiList.get(position).getGame_id());
        } catch (NumberFormatException e) {
            // fallback to position if ID is not numeric
            return position;
        }
    }

    public void clearSelection() {
        for (Yxi yxi : yxiList) {
            yxi.setSelected(false);
        }
        selectedPosition = -1;
        notifyDataSetChanged();
    }

    public void updateFavStatus(String yxiId, boolean isFav, Long bookmarkId) {
        for (int i = 0; i < yxiList.size(); i++) {
            Yxi yxi = yxiList.get(i);
            if (yxi.getGame_id().equalsIgnoreCase(yxiId)) {
                yxi.setGamebookmark_id(bookmarkId != null ? bookmarkId : 0);
                yxi.setIsBookmark(isFav ? 1 : 0);
                notifyItemChanged(i, "favUpdate"); // partial update payload
                break;
            }
        }
    }

    static class ViewHolder extends RecyclerView.ViewHolder {
        LinearLayout itemPanel;
        ImageView yxiImageView, favImageView;
        TextView label;

        ViewHolder(@NonNull View itemView) {
            super(itemView);
            itemPanel = itemView.findViewById(R.id.panel_item);
            yxiImageView = itemView.findViewById(R.id.imageView);
            favImageView = itemView.findViewById(R.id.imageView_fav);
            label = itemView.findViewById(R.id.textView);
        }
    }

    public interface OnYxiClickListener {
        void onYxiClick(Yxi yxi);
    }
}
