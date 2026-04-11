package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.recyclerview.widget.DiffUtil;
import androidx.recyclerview.widget.RecyclerView;

import com.dj.user.R;
import com.dj.user.model.response.YxiProvider;
import com.dj.user.util.StringUtil;
import com.squareup.picasso.Picasso;

import java.util.ArrayList;
import java.util.List;
import java.util.Objects;

public class YxiProviderRecyclerAdapter extends RecyclerView.Adapter<YxiProviderRecyclerAdapter.ViewHolder> {
    private final Context context;
    private List<YxiProvider> yxiProviderList = new ArrayList<>();
    private OnYxiProviderClickListener listener;
    private int selectedPosition = -1;

    public YxiProviderRecyclerAdapter(Context context) {
        this.context = context;
        setHasStableIds(true);
    }

    public void setData(List<YxiProvider> newList) {
        if (newList == null) newList = new ArrayList<>();
        selectedPosition = -1;
        List<YxiProvider> finalNewList = newList;
        DiffUtil.DiffResult diffResult = DiffUtil.calculateDiff(new DiffUtil.Callback() {
            @Override
            public int getOldListSize() {
                return yxiProviderList.size();
            }

            @Override
            public int getNewListSize() {
                return finalNewList.size();
            }

            @Override
            public boolean areItemsTheSame(int oldItemPosition, int newItemPosition) {
                YxiProvider oldItem = yxiProviderList.get(oldItemPosition);
                YxiProvider newItem = finalNewList.get(newItemPosition);
                return Objects.equals(oldItem.getProvider_id(), newItem.getProvider_id());
            }

            @Override
            public boolean areContentsTheSame(int oldItemPosition, int newItemPosition) {
                YxiProvider oldItem = yxiProviderList.get(oldItemPosition);
                YxiProvider newItem = finalNewList.get(newItemPosition);
                return Objects.equals(oldItem, newItem);
            }
        });
        yxiProviderList.clear();
        yxiProviderList.addAll(newList);
        diffResult.dispatchUpdatesTo(this);
    }

    public List<YxiProvider> getData() {
        return yxiProviderList;
    }

    public void setOnYxiProviderClickListener(OnYxiProviderClickListener listener) {
        this.listener = listener;
    }

    @NonNull
    @Override
    public ViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        View view = LayoutInflater.from(context).inflate(R.layout.item_grid_yxi_provider, parent, false);
        return new ViewHolder(view);
    }

    @Override
    public void onBindViewHolder(@NonNull ViewHolder holder, int position) {
        YxiProvider item = yxiProviderList.get(position);
        holder.label.setText(item.getProvider_name());

        String icon = item.getIcon();
        if (!StringUtil.isNullOrEmpty(icon)) {
            if (!icon.startsWith("http")) {
                icon = String.format(context.getString(R.string.template_s_s),
                        context.getString(R.string.image_base_url), icon);
            }
            Picasso.get().load(icon)
                    .centerCrop()
                    .fit()
                    .placeholder(R.drawable.img_provider_default)
                    .error(R.drawable.img_provider_default)
                    .into(holder.yxiImageView);
        } else {
            holder.yxiImageView.setImageResource(R.drawable.img_provider_default);
        }
        holder.favImageView.setVisibility(item.isBookmark() ? View.VISIBLE : View.GONE);

        boolean isSelected = (position == selectedPosition);
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
                listener.onYxiProviderClick(item);
            }
        });
    }

    @Override
    public int getItemCount() {
        return yxiProviderList.size();
    }

    @Override
    public long getItemId(int position) {
        try {
            return Long.parseLong(yxiProviderList.get(position).getProvider_id());
        } catch (NumberFormatException e) {
            // fallback to position if ID is not numeric
            return position;
        }
    }

    public void clearSelection() {
        for (YxiProvider yxiProvider : yxiProviderList) {
            yxiProvider.setSelected(false);
        }
        selectedPosition = -1;
        notifyDataSetChanged();
    }

    public void updateFavStatus(String providerId, boolean isFav, Long bookmarkId) {
        for (int i = 0; i < yxiProviderList.size(); i++) {
            YxiProvider provider = yxiProviderList.get(i);
            if (provider.getProvider_id().equalsIgnoreCase(providerId)) {
                provider.setProviderbookmark_id(bookmarkId != null ? bookmarkId : 0);
                provider.setIsBookmark(isFav ? 1 : 0);
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

    public interface OnYxiProviderClickListener {
        void onYxiProviderClick(YxiProvider yxiProvider);
    }
}
