package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.LinearLayout;

import androidx.annotation.NonNull;

import com.dj.user.R;
import com.dj.user.model.response.Promotion;
import com.dj.user.util.StringUtil;
import com.squareup.picasso.Callback;
import com.squareup.picasso.Picasso;

public class PromotionListViewAdapter extends CustomAdapter<Promotion> {

    private class ViewHolder {
        private LinearLayout itemPanel;
        private ImageView imageView;
    }

    public PromotionListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_event, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.imageView = convertView.findViewById(R.id.imageView);

            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final Promotion promotion = getItem(position);
        if (promotion != null) {
            String image = promotion.getPhoto();
            if (!StringUtil.isNullOrEmpty(image)) {
                if (!image.startsWith("http")) {
                    image = String.format(context.getString(R.string.template_s_s), context.getString(R.string.image_base_url), image);
                }
                Picasso.get().load(image).into(viewHolder.imageView, new Callback() {
                    @Override
                    public void onSuccess() {
                    }

                    @Override
                    public void onError(Exception e) {
                        viewHolder.imageView.setImageResource(R.drawable.img_promotion_default);
                    }
                });
            } else {
                viewHolder.imageView.setImageResource(R.drawable.img_promotion_default);
            }
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, promotion));
        }
        return convertView;
    }
}

