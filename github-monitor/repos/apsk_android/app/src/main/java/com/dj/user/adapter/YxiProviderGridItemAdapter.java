package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.BaseAdapter;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;

import com.dj.user.R;
import com.dj.user.model.response.YxiProvider;
import com.dj.user.util.StringUtil;
import com.squareup.picasso.Callback;
import com.squareup.picasso.Picasso;

import java.util.ArrayList;
import java.util.List;

public class YxiProviderGridItemAdapter extends BaseAdapter {
    private final Context context;
    private List<YxiProvider> yxiProviderList;

    private OnYxiProviderClickListener listener;

    public YxiProviderGridItemAdapter(Context context) {
        this.context = context;
    }

    public void setData(List<YxiProvider> yxiProviderList) {
        if (yxiProviderList == null) {
            yxiProviderList = new ArrayList<>();
        }
        this.yxiProviderList = yxiProviderList;
        notifyDataSetChanged();
    }

    public void setOnYxiProviderClickListener(OnYxiProviderClickListener listener) {
        this.listener = listener;
    }

    @Override
    public int getCount() {
        return yxiProviderList == null ? 0 : yxiProviderList.size();
    }

    @Override
    public YxiProvider getItem(int position) {
        return (yxiProviderList != null && position >= 0 && position < yxiProviderList.size()) ? yxiProviderList.get(position) : null;
    }

    @Override
    public long getItemId(int position) {
        return position;
    }

    @Override
    public View getView(int position, View convertView, ViewGroup parent) {
        View row = convertView;
        if (row == null) {
            LayoutInflater inflater = LayoutInflater.from(context);
            row = inflater.inflate(R.layout.item_grid_yxi_provider, parent, false);
        }
        LinearLayout itemPanel = row.findViewById(R.id.panel_item);
        ImageView yxiImageView = row.findViewById(R.id.imageView);
        ImageView favImageView = row.findViewById(R.id.imageView_fav);
        TextView label = row.findViewById(R.id.textView);

        YxiProvider item = getItem(position);
        if (item != null) {
            label.setText(item.getProvider_name());
            String icon = item.getIcon();
            if (!StringUtil.isNullOrEmpty(icon)) {
                if (!icon.startsWith("http")) {
                    icon = String.format(context.getString(R.string.template_s_s), context.getString(R.string.image_base_url), icon);
                }
                Picasso.get().load(icon).into(yxiImageView, new Callback() {
                    @Override
                    public void onSuccess() {

                    }

                    @Override
                    public void onError(Exception e) {
                        yxiImageView.setImageResource(R.drawable.img_provider_default);
                    }
                });
            } else {
                yxiImageView.setImageResource(R.drawable.img_provider_default);
            }
            favImageView.setVisibility(item.isBookmark() ? View.VISIBLE : View.GONE);
            itemPanel.setOnClickListener(v -> {
                if (listener != null) {
                    listener.onYxiProviderClick(item);
                }
            });
        }
        return row;
    }

    public void updateFavStatus(String yxiProviderId, boolean isFav, Long yxiBookmarkId) {
        if (yxiProviderList == null) return;
        for (int i = 0; i < yxiProviderList.size(); i++) {
            YxiProvider yxiProvider = yxiProviderList.get(i);
            if (yxiProvider.getProvider_id().equalsIgnoreCase(yxiProviderId)) {
                yxiProvider.setProviderbookmark_id(yxiBookmarkId != null ? yxiBookmarkId : 0);
                yxiProvider.setIsBookmark(isFav ? 1 : 0);
                notifyDataSetChanged();
                break;
            }
        }
    }

    public List<YxiProvider> getData() {
        return yxiProviderList;
    }

    public interface OnYxiProviderClickListener {
        void onYxiProviderClick(YxiProvider yxiProvider);
    }
}
