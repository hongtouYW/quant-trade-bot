package com.dj.user.model;

import android.view.View;

public class ItemSupport {
    private int iconRes;
    private View.OnClickListener listener;

    public ItemSupport(int iconRes, View.OnClickListener listener) {
        this.iconRes = iconRes;
        this.listener = listener;
    }

    public int getIconRes() {
        return iconRes;
    }

    public void setIconRes(int iconRes) {
        this.iconRes = iconRes;
    }

    public View.OnClickListener getListener() {
        return listener;
    }

    public void setListener(View.OnClickListener listener) {
        this.listener = listener;
    }
}