package com.dj.user.adapter;

import android.content.Context;
import android.widget.BaseAdapter;

import java.util.ArrayList;
import java.util.List;

public abstract class CustomAdapter<T> extends BaseAdapter {

    private List<T> list;

    final Context context;
    OnItemClickListener onItemClickListener;

    CustomAdapter(Context context) {
        this.context = context;
    }

    public List<T> getList() {
        return this.list == null ? new ArrayList<>() : this.list;
    }

    public void addList(List<T> list) {
        if (this.list == null) {
            this.list = new ArrayList<>();
        }
        this.list.addAll(list);
        this.notifyDataSetChanged();
    }

    public void replaceList(List<T> list) {
        this.list = new ArrayList<>();
        this.list.addAll(list);
        this.notifyDataSetChanged();
    }

    public void removeList() {
        this.list = new ArrayList<>();
        this.notifyDataSetChanged();
    }

    public void setOnItemClickListener(OnItemClickListener onItemClickListener) {
        this.onItemClickListener = onItemClickListener;
    }

    @Override
    public int getCount() {
        return list == null ? 0 : list.size();
    }

    @Override
    public T getItem(int position) {
        return list == null ? null : list.get(position);
    }

    @Override
    public long getItemId(int position) {
        return list == null ? 0 : list.get(position).hashCode();
    }

    public interface OnItemClickListener {
        void onItemClick(int position, Object object);
    }
}
