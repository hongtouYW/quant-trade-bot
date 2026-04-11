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
import androidx.recyclerview.widget.RecyclerView;

import com.dj.user.R;
import com.dj.user.model.ItemCategory;
import com.dj.user.util.FormatUtils;

import java.util.ArrayList;
import java.util.List;

public class YxiCategoryRecyclerViewAdapter extends RecyclerView.Adapter<YxiCategoryRecyclerViewAdapter.ViewHolder> {

    private Context context;
    private List<ItemCategory> categoryList;
    private int selectedPosition = 0;

    private OnItemClickListener onItemClickListener;

    public interface OnItemClickListener {
        void onItemClick(int position, ItemCategory category);
    }

    public void setOnItemClickListener(OnItemClickListener onItemClickListener) {
        this.onItemClickListener = onItemClickListener;
    }

    public YxiCategoryRecyclerViewAdapter(Context context) {
        this.context = context;
    }

    @NonNull
    @Override
    public ViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        View view = LayoutInflater.from(parent.getContext()).inflate(R.layout.item_recycler_view_category, parent, false);
        return new ViewHolder(view);
    }

    @Override
    public void onBindViewHolder(@NonNull ViewHolder holder, int position) {
        final ItemCategory category = categoryList.get(position);
        if (category != null) {
//            if (category.getIconResourceId() == 0 && category.getSelectedIconResourceId() == 0) {
//                if (selectedPosition == position) {
//                    holder.iconImageView.setImageResource(position == 0 ? R.drawable.ic_game_all_selected : R.drawable.ic_game_selected);
//                } else {
//                    holder.iconImageView.setImageResource(position == 0 ? R.drawable.ic_game_all : R.drawable.ic_game);
//                }
//            } else {
//            holder.iconImageView.setImageResource(selectedPosition == position ? category.getSelectedIconResourceId() : category.getIconResourceId());
//            }
            holder.iconImageView.setImageResource(category.getIconResourceId());
            holder.titleTextView.setText(category.getTitle());
            holder.titleTextView.setTextColor(ContextCompat.getColor(context, selectedPosition == position ? R.color.white_FFFFFF : R.color.gray_A49A81));
            holder.titleTextView.setSelected(true);
            holder.itemPanel.setBackgroundResource(selectedPosition == position ? R.drawable.bg_gradient_selected : R.drawable.bg_gradient_cat);

            LinearLayout.LayoutParams params = new LinearLayout.LayoutParams(FormatUtils.dpToPx(context, 72), FormatUtils.dpToPx(context, 54));
            if (position == getItemCount() - 1) {
                params.setMargins(FormatUtils.dpToPx(context, 8), 0, FormatUtils.dpToPx(context, 8), 0);
            } else {
                params.setMargins(FormatUtils.dpToPx(context, 8), 0, 0, 0);
            }
            holder.itemView.setLayoutParams(params);
            holder.itemView.setOnClickListener(view -> onItemClickListener.onItemClick(position, category));
        }
    }

    @Override
    public int getItemCount() {
        return categoryList == null ? 0 : categoryList.size();
    }

    public void addCategoryList(List<ItemCategory> categoryList) {
        if (this.categoryList == null) {
            this.categoryList = new ArrayList<>();
        }
        this.categoryList.addAll(categoryList);
        notifyDataSetChanged();
    }

    public void clearList() {
        if (this.categoryList != null) {
            this.categoryList.clear();
        }
        notifyDataSetChanged();
    }

    public void setSelectedPosition(int position) {
        this.selectedPosition = position;
        notifyDataSetChanged();
    }

    class ViewHolder extends RecyclerView.ViewHolder {

        private LinearLayout itemPanel;
        private ImageView iconImageView;
        private TextView titleTextView;

        ViewHolder(View itemView) {
            super(itemView);
            itemPanel = itemView.findViewById(R.id.panel_item);
            iconImageView = itemView.findViewById(R.id.imageView_icon);
            titleTextView = itemView.findViewById(R.id.textView_title);
        }
    }
}
