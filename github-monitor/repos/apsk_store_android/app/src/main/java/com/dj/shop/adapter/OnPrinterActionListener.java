package com.dj.shop.adapter;

import com.dj.shop.model.Printer;

public interface OnPrinterActionListener {
    void onConnect(Printer printer);

    void onDisconnect(Printer printer);

    void onItemClick(int position, Printer printer);
}