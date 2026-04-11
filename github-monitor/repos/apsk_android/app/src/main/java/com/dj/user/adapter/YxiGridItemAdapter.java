package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.AbsListView;
import android.widget.BaseAdapter;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.core.content.ContextCompat;

import com.dj.user.R;
import com.dj.user.model.response.Yxi;
import com.dj.user.util.StringUtil;
import com.squareup.picasso.Callback;
import com.squareup.picasso.Picasso;

import java.util.ArrayList;
import java.util.List;

public class YxiGridItemAdapter extends BaseAdapter {
    static class ViewHolder {
        LinearLayout itemPanel;
        ImageView yxiImageView;
        ImageView favImageView;
        TextView label;
    }

    private final Context context;
    private List<Yxi> yxiList;
    private OnYxiClickListener listener;
    private int selectedPosition = -1;

    public YxiGridItemAdapter(Context context) {
        this.context = context;
    }

    public void setData(List<Yxi> yxiList) {
        if (yxiList == null) {
            yxiList = new ArrayList<>();
        }
        this.yxiList = yxiList;
        notifyDataSetChanged();
    }

    public void setOnYxiClickListener(OnYxiClickListener listener) {
        this.listener = listener;
    }

    @Override
    public int getCount() {
        return yxiList == null ? 0 : yxiList.size();
    }

    @Override
    public Yxi getItem(int position) {
        return (yxiList != null && position >= 0 && position < yxiList.size()) ? yxiList.get(position) : null;
    }

    @Override
    public long getItemId(int position) {
        return position;
    }

    @Override
    public View getView(int position, View convertView, ViewGroup parent) {
        ViewHolder holder;
        if (convertView == null) {
            LayoutInflater inflater = LayoutInflater.from(context);
            convertView = inflater.inflate(R.layout.item_grid_yxi, parent, false);

            holder = new ViewHolder();
            holder.itemPanel = convertView.findViewById(R.id.panel_item);
            holder.yxiImageView = convertView.findViewById(R.id.imageView);
            holder.favImageView = convertView.findViewById(R.id.imageView_fav);
            holder.label = convertView.findViewById(R.id.textView);
            convertView.setTag(holder);
        } else {
            holder = (ViewHolder) convertView.getTag();
        }

        Yxi item = getItem(position);
        if (item != null) {
            String icon = item.getIcon();
            if (!StringUtil.isNullOrEmpty(icon)) {
                if (!icon.startsWith("http")) {
                    icon = String.format(context.getString(R.string.template_s_s), context.getString(R.string.image_base_url), icon);
                }
                Picasso.get().load(icon).centerInside().fit().into(holder.yxiImageView, new Callback() {
                    @Override
                    public void onSuccess() {

                    }

                    @Override
                    public void onError(Exception e) {
                        holder.yxiImageView.setImageResource(R.drawable.img_yxi_default);
                    }
                });
            } else {
                holder.yxiImageView.setImageResource(R.drawable.img_yxi_default);
            }
            holder.label.setText(item.getGame_name());
            holder.label.setTextColor(ContextCompat.getColor(context, item.isSelected() ? R.color.orange_FFD01B : R.color.white_FFFFFF));
            holder.favImageView.setVisibility(item.isBookmark() ? View.VISIBLE : View.GONE);
            holder.itemPanel.setBackgroundResource(item.isSelected() ? R.drawable.bg_yxi_selection : android.R.color.transparent);
            holder.itemPanel.setOnClickListener(v -> {
                int previousSelected = selectedPosition;
                selectedPosition = position;
                // Unselect previous one if visible
                if (previousSelected != -1 && previousSelected != position && parent instanceof AbsListView) {
                    AbsListView gridView = (AbsListView) parent;
                    int first = gridView.getFirstVisiblePosition();
                    int last = gridView.getLastVisiblePosition();

                    if (previousSelected >= first && previousSelected <= last) {
                        View prevView = gridView.getChildAt(previousSelected - first);
                        if (prevView != null && prevView.getTag() instanceof ViewHolder) {
                            ViewHolder prevHolder = (ViewHolder) prevView.getTag();
                            prevHolder.label.setTextColor(ContextCompat.getColor(context, R.color.white_FFFFFF));
                            prevHolder.itemPanel.setBackgroundResource(android.R.color.transparent);
                        }
                    }
                }
                // Select current one
                holder.label.setTextColor(ContextCompat.getColor(context, R.color.orange_FFD01B));
                holder.itemPanel.setBackgroundResource(R.drawable.bg_yxi_selection);
                if (listener != null) {
                    listener.onYxiClick(item);
                }
            });
        }
        return convertView;
    }

    public void updateFavStatus(String yxiId, boolean isFav, Long yxiBookmarkId) {
        if (yxiList == null) return;
        for (int i = 0; i < yxiList.size(); i++) {
            Yxi yxi = yxiList.get(i);
            if (yxi.getGame_id().equalsIgnoreCase(yxiId)) {
                yxi.setGamebookmark_id(yxiBookmarkId);
                yxi.setIsBookmark(isFav ? 1 : 0);
                notifyDataSetChanged();
                break;
            }
        }
    }


    public interface OnYxiClickListener {
        void onYxiClick(Yxi yxi);
    }
}
