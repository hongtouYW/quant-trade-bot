package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.RelativeLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;

import com.dj.user.R;
import com.dj.user.enums.VIPType;
import com.dj.user.model.response.VIP;
import com.dj.user.util.FormatUtils;

import java.util.Locale;

public class VIPListViewAdapter extends CustomAdapter<VIP> {
    private int vipLevel;
    private VIPType type;
    private int page;

    private class ViewHolder {
        private LinearLayout itemPanel, headerPanel, contentPanel;
        private RelativeLayout progressPanel;
        private ProgressBar progressBar;
        private ImageView iconImageView;
        private TextView targetTextView, progressTargetTextView, rewardTextView;
    }

    public VIPListViewAdapter(Context context, int vipLevel, VIPType type, int page) {
        super(context);
        this.vipLevel = vipLevel;
        this.type = type;
        this.page = page;
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_vip, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.headerPanel = convertView.findViewById(R.id.panel_header);
            viewHolder.contentPanel = convertView.findViewById(R.id.panel_content);
            viewHolder.progressPanel = convertView.findViewById(R.id.panel_progress);
            viewHolder.progressBar = convertView.findViewById(R.id.progressBar);
            viewHolder.iconImageView = convertView.findViewById(R.id.imageView_icon);
            viewHolder.targetTextView = convertView.findViewById(R.id.textView_target);
            viewHolder.progressTargetTextView = convertView.findViewById(R.id.textView_progress_target);
            viewHolder.rewardTextView = convertView.findViewById(R.id.textView_reward);

            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final VIP vip = getItem(position);
        if (vip != null) {
            if (vip.isHeader()) {
                viewHolder.headerPanel.setVisibility(View.VISIBLE);
                viewHolder.contentPanel.setVisibility(View.GONE);
            } else {
                String vipName = "vip_" + vip.getLvl();
                int resId = context.getResources().getIdentifier(vipName, "drawable", context.getPackageName());
                viewHolder.iconImageView.setImageResource(resId);
                // TODO: 26/08/2025  
                if (vip.isCurrent(vipLevel)) {
                    int progress = (int) ((vip.getScore() * 100.0f) / vip.getMax_amount());
                    viewHolder.progressBar.setProgress(progress);
                    if (page == 0) {
                        viewHolder.progressTargetTextView.setText(String.format(Locale.ENGLISH, "%s/%s", FormatUtils.formatInteger((int) vip.getScore()), FormatUtils.formatInteger((int) vip.getMax_amount())));
                    } else {
                        viewHolder.progressTargetTextView.setText(String.format(Locale.ENGLISH, "%s/%s", FormatUtils.formatInteger((int) vip.getScore()), FormatUtils.formatInteger((int) vip.getMax_amount())));
//                        viewHolder.progressTargetTextView.setText(String.format(Locale.ENGLISH, "还剩 %d 天可领取", vip.getDayToRedeem()));
                    }
                    viewHolder.progressPanel.setVisibility(View.VISIBLE);
                    viewHolder.targetTextView.setVisibility(View.GONE);
                } else {
                    viewHolder.targetTextView.setText(FormatUtils.formatInteger((int) vip.getMax_amount()));

                    viewHolder.progressPanel.setVisibility(View.GONE);
                    viewHolder.targetTextView.setVisibility(View.VISIBLE);
                }
                switch (type) {
                    case GENERAL:
                        viewHolder.rewardTextView.setText(FormatUtils.formatAmount(vip.getFirstbonus()));
                        break;
                    case WEEKLY:
                        viewHolder.rewardTextView.setText(FormatUtils.formatAmount(vip.getWeeklybonus()));
                        break;
                    case MONTHLY:
                        viewHolder.rewardTextView.setText(FormatUtils.formatAmount(vip.getMonthlybonus()));
                        break;
                    case VIP:
                        viewHolder.rewardTextView.setText(context.getString(R.string.placeholder_amount));
                        break;
                }
                viewHolder.headerPanel.setVisibility(View.GONE);
                viewHolder.contentPanel.setVisibility(View.VISIBLE);
            }

            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, vip));
        }
        return convertView;
    }

    public void updateVipLevel(int vipLevel) {
        this.vipLevel = vipLevel;
    }
}

