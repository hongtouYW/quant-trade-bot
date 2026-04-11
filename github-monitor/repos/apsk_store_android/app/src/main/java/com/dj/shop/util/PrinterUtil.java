package com.dj.shop.util;

import android.bluetooth.BluetoothSocket;
import android.util.Log;

import com.dj.shop.model.Printer;

import java.io.IOException;
import java.io.OutputStream;

public class PrinterUtil {
    private static PrinterUtil instance;
    private BluetoothSocket socket;
    private Printer printer;

    // ESC/POS commands
    private static final byte[] ESC_ALIGN_LEFT = {0x1B, 0x61, 0x00};
    private static final byte[] ESC_ALIGN_CENTER = {0x1B, 0x61, 0x01};
    private static final byte[] ESC_ALIGN_RIGHT = {0x1B, 0x61, 0x02};

    private static final byte[] ESC_BOLD_ON = {0x1B, 0x45, 0x01};
    private static final byte[] ESC_BOLD_OFF = {0x1B, 0x45, 0x00};

    private static final byte[] ESC_TEXT_NORMAL = {0x1D, 0x21, 0x00};
    private static final byte[] ESC_TEXT_MEDIUM = {0x1D, 0x21, 0x01}; // double height
    private static final byte[] ESC_TEXT_MEDIUM_WIDE = {0x1D, 0x21, 0x10}; // double width
    private static final byte[] ESC_TEXT_LARGE = {0x1D, 0x21, 0x11}; // double height & width

    private PrinterUtil() {
    }

    public static PrinterUtil getInstance() {
        if (instance == null) {
            instance = new PrinterUtil();
        }
        return instance;
    }

    public BluetoothSocket getSocket() {
        return socket;
    }

    public void setSocket(BluetoothSocket socket) {
        this.socket = socket;
    }

    public void setPrinter(Printer printer) {
        this.printer = printer;
    }

    public Printer getPrinter() {
        return printer;
    }

    public boolean isConnected() {
        return socket != null && socket.isConnected();
    }

    private void sendCommand(byte[] command) throws IOException {
        socket.getOutputStream().write(command);
    }

    private void sendText(String text) throws IOException {
        socket.getOutputStream().write(text.getBytes("GBK"));
    }

    public void printTextFormatted(String text, int align, boolean bold, boolean large) {
        if (!isConnected()) {
            Log.e("PrinterUtil", "Printer not connected");
            return;
        }
        try {
            OutputStream os = socket.getOutputStream();
            // Alignment
            if (align == 0) sendCommand(ESC_ALIGN_LEFT);
            else if (align == 1) sendCommand(ESC_ALIGN_CENTER);
            else sendCommand(ESC_ALIGN_RIGHT);
            // Bold
            sendCommand(bold ? ESC_BOLD_ON : ESC_BOLD_OFF);
            // Size
            sendCommand(large ? ESC_TEXT_MEDIUM : ESC_TEXT_NORMAL);
            // Text
            sendText(text + "\n");
            os.flush();
        } catch (IOException e) {
            Log.e("PrinterUtil", "Formatted print failed", e);
        }
    }

    public void printSection(String header, String content) {
        printTextFormatted(header + ":", 0, false, false);  // Header: bold & large
        printTextFormatted(content, 0, true, false);     // Content: normal
    }

    public void feedLines(int count) {
        if (!isConnected()) return;
        try {
            OutputStream os = socket.getOutputStream();
            for (int i = 0; i < count; i++) {
                os.write("\n".getBytes());
            }
            os.flush();
        } catch (IOException e) {
            e.printStackTrace();
        }
    }

}
