package com.dj.shop.activity;

import android.Manifest;
import android.bluetooth.BluetoothAdapter;
import android.bluetooth.BluetoothClass;
import android.bluetooth.BluetoothDevice;
import android.bluetooth.BluetoothSocket;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.content.pm.PackageManager;
import android.location.LocationManager;
import android.os.Build;
import android.os.Bundle;
import android.provider.Settings;
import android.util.Log;
import android.view.View;
import android.widget.LinearLayout;
import android.widget.Toast;

import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.appcompat.app.AlertDialog;
import androidx.core.app.ActivityCompat;

import com.dj.shop.R;
import com.dj.shop.activity.success.SuccessActivity;
import com.dj.shop.adapter.OnPrinterActionListener;
import com.dj.shop.adapter.PrinterListViewAdapter;
import com.dj.shop.databinding.ActivityPrinterBinding;
import com.dj.shop.databinding.ViewToolbarTextBinding;
import com.dj.shop.model.Printer;
import com.dj.shop.model.SuccessConfigFactory;
import com.dj.shop.util.PrinterUtil;
import com.google.gson.Gson;

import java.io.IOException;
import java.util.ArrayList;
import java.util.List;
import java.util.UUID;

public class PrinterActivity extends BaseActivity {
    private static final int REQUEST_ENABLE_BT = 1;
    private static final int REQUEST_PERMISSIONS = 2;
    private static final int REQUEST_ENABLE_LOCATION = 3;

    private ActivityResultLauncher<Intent> enableBluetoothLauncher;
    private ActivityResultLauncher<Intent> enableGPSLauncher;

    private BluetoothAdapter bluetoothAdapter;
    private BluetoothSocket currentSocket;
    Printer connectedPrinter;

    private ActivityPrinterBinding binding;
    private ViewToolbarTextBinding toolbarBinding;
    PrinterListViewAdapter printerListViewAdapter;
    LinearLayout dataPanel, noDataPanel;
    final List<Printer> printerList = new ArrayList<>();

    boolean isScanning = false;

    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityPrinterBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        // Setup Bluetooth enable launcher
        enableBluetoothLauncher = registerForActivityResult(
                new ActivityResultContracts.StartActivityForResult(),
                result -> {
                    if (result.getResultCode() == RESULT_OK) {
                        startBluetoothScan();
                    } else {
                        Toast.makeText(this, R.string.profile_setting_printer_bluetooth_permission, Toast.LENGTH_SHORT).show();
                    }
                }
        );
        // Setup GPS enable launcher
        enableGPSLauncher = registerForActivityResult(
                new ActivityResultContracts.StartActivityForResult(),
                result -> {
                    if (isLocationEnabled()) {
                        startBluetoothScan();
                    } else {
                        Toast.makeText(this, R.string.profile_setting_printer_gps_permission, Toast.LENGTH_SHORT).show();
                    }
                }
        );
        bluetoothAdapter = BluetoothAdapter.getDefaultAdapter();
        if (bluetoothAdapter == null) {
            Toast.makeText(this, R.string.profile_setting_printer_bluetooth_not_supported, Toast.LENGTH_SHORT).show();
            finish();
            return;
        }
        setupToolbar();
        setupListView();
        BluetoothSocket savedSocket = PrinterUtil.getInstance().getSocket();
        if (savedSocket != null && savedSocket.isConnected()) {
            currentSocket = savedSocket;
            connectedPrinter = PrinterUtil.getInstance().getPrinter();
        }
        checkPermissionsAndScan();
    }

    private void setupToolbar() {
        toolbarBinding = ViewToolbarTextBinding.bind(binding.toolbar.getRoot());
        toolbarBinding.textViewTitle.setText(R.string.profile_setting_printer_title);
        toolbarBinding.imageViewLeftIcon.setOnClickListener(v -> onBaseBackPressed());

        toolbarBinding.textViewRightAction.setText(R.string.profile_setting_printer_refresh);
        toolbarBinding.textViewRightAction.setOnClickListener(view -> checkPermissionsAndScan());
    }

    private void setupListView() {
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();

        printerListViewAdapter = new PrinterListViewAdapter(this);
        binding.listViewPrinter.setAdapter(printerListViewAdapter);
        printerListViewAdapter.setOnPrinterActionListener(new OnPrinterActionListener() {
            @Override
            public void onConnect(Printer printer) {
                connectToPrinter(printer.getAddress());
            }

            @Override
            public void onDisconnect(Printer printer) {
                disconnectPrinter(printer);
            }

            @Override
            public void onItemClick(int position, Printer printer) {
            }
        });
    }

    private boolean hasBluetoothAndLocationPermissions() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            return ActivityCompat.checkSelfPermission(this, Manifest.permission.BLUETOOTH_SCAN) == PackageManager.PERMISSION_GRANTED &&
                    ActivityCompat.checkSelfPermission(this, Manifest.permission.BLUETOOTH_CONNECT) == PackageManager.PERMISSION_GRANTED;
        } else {
            return ActivityCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED;
        }
    }

    private boolean isLocationEnabled() {
        LocationManager locationManager = (LocationManager) getSystemService(Context.LOCATION_SERVICE);
        return locationManager != null && locationManager.isProviderEnabled(LocationManager.GPS_PROVIDER);
    }

    private void promptEnableGPS() {
        new AlertDialog.Builder(this)
                .setMessage(getString(R.string.profile_setting_printer_gps_permission))
                .setPositiveButton(R.string.profile_setting_printer_gps_on, (dialog, which) -> enableGPSLauncher.launch(new Intent(Settings.ACTION_LOCATION_SOURCE_SETTINGS)))
                .setNegativeButton(R.string.profile_setting_printer_gps_cancel, (dialog, which) -> resetRefreshButton())
                .show();
    }

    private void checkPermissionsAndScan() {
        toolbarBinding.textViewRightAction.setEnabled(false);
        toolbarBinding.textViewRightAction.setAlpha(0.5f);
        if (!hasBluetoothAndLocationPermissions()) {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                ActivityCompat.requestPermissions(this,
                        new String[]{Manifest.permission.BLUETOOTH_SCAN, Manifest.permission.BLUETOOTH_CONNECT},
                        REQUEST_PERMISSIONS);
            } else {
                ActivityCompat.requestPermissions(this,
                        new String[]{Manifest.permission.ACCESS_FINE_LOCATION},
                        REQUEST_PERMISSIONS);
            }
            return;
        }
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.S && !isLocationEnabled()) {
            promptEnableGPS();
            return;
        }
        startBluetoothScan();
    }

    private void startBluetoothScan() {
        if (bluetoothAdapter == null) {
            Toast.makeText(this, getString(R.string.profile_setting_printer_bluetooth_not_supported), Toast.LENGTH_SHORT).show();
            return;
        }
        if (!bluetoothAdapter.isEnabled()) {
            enableBluetoothLauncher.launch(new Intent(BluetoothAdapter.ACTION_REQUEST_ENABLE));
            return;
        }
        if (!isLocationEnabled()) {
            Toast.makeText(this, getString(R.string.profile_setting_printer_gps_permission), Toast.LENGTH_LONG).show();
            enableGPSLauncher.launch(new Intent(Settings.ACTION_LOCATION_SOURCE_SETTINGS));
            return;
        }
        if (isScanning) return;

        isScanning = true;
        toolbarBinding.textViewRightAction.setText(R.string.profile_setting_printer_scanning);
        toolbarBinding.textViewRightAction.setEnabled(false);
        toolbarBinding.textViewRightAction.setAlpha(0.5f);

        printerList.clear();
        printerListViewAdapter.replaceList(printerList);
        try {
            unregisterReceiver(receiver);
        } catch (Exception ignored) {
        }

        IntentFilter filter = new IntentFilter();
        filter.addAction(BluetoothDevice.ACTION_FOUND);
        filter.addAction(BluetoothAdapter.ACTION_DISCOVERY_FINISHED);
        registerReceiver(receiver, filter);

        bluetoothAdapter.startDiscovery();
    }

    private final BroadcastReceiver receiver = new BroadcastReceiver() {
        public void onReceive(Context context, Intent intent) {
            String action = intent.getAction();

            if (BluetoothDevice.ACTION_FOUND.equals(action)) {
                BluetoothDevice device = intent.getParcelableExtra(BluetoothDevice.EXTRA_DEVICE);

                if (device != null && device.getName() != null) {
                    BluetoothClass btClass = device.getBluetoothClass();
                    if (btClass != null && btClass.getMajorDeviceClass() == BluetoothClass.Device.Major.IMAGING) {
                        boolean exists = false;
                        for (Printer p : printerList) {
                            if (p.getAddress().equals(device.getAddress())) {
                                exists = true;
                                break;
                            }
                        }
                        if (!exists) {
                            int status = 0; // default not connected
                            if (connectedPrinter != null && connectedPrinter.getAddress().equals(device.getAddress())) {
                                status = 1; // mark as connected
                            }
                            printerList.add(new Printer(device.getName(), device.getAddress(), status));
                            printerListViewAdapter.replaceList(printerList);
                            if (printerListViewAdapter.isEmpty()) {
                                noDataPanel.setVisibility(View.VISIBLE);
                                dataPanel.setVisibility(View.GONE);
                            } else {
                                noDataPanel.setVisibility(View.GONE);
                                dataPanel.setVisibility(View.VISIBLE);
                            }
                        }
                    }
                }
            } else if (BluetoothAdapter.ACTION_DISCOVERY_FINISHED.equals(action)) {
                isScanning = false;
                resetRefreshButton();

                if (printerList.isEmpty()) {
                    Toast.makeText(context, R.string.profile_setting_printer_no_printer, Toast.LENGTH_SHORT).show();
                } else {
                    Toast.makeText(context, R.string.profile_setting_printer_scan_completed, Toast.LENGTH_SHORT).show();
                }
                if (printerListViewAdapter.isEmpty()) {
                    noDataPanel.setVisibility(View.VISIBLE);
                    dataPanel.setVisibility(View.GONE);
                } else {
                    noDataPanel.setVisibility(View.GONE);
                    dataPanel.setVisibility(View.VISIBLE);
                }
            }
        }
    };

    void resetRefreshButton() {
        toolbarBinding.textViewRightAction.setText(getString(R.string.profile_setting_printer_refresh));
        toolbarBinding.textViewRightAction.setEnabled(true);
        toolbarBinding.textViewRightAction.setAlpha(1f);
    }

    void connectToPrinter(String address) {
        bluetoothAdapter.cancelDiscovery();
        BluetoothDevice device = bluetoothAdapter.getRemoteDevice(address);

        new Thread(() -> {
            try {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S &&
                        ActivityCompat.checkSelfPermission(this, Manifest.permission.BLUETOOTH_CONNECT) != PackageManager.PERMISSION_GRANTED) {
                    runOnUiThread(() -> Toast.makeText(this, getString(R.string.profile_setting_printer_bluetooth_permission), Toast.LENGTH_SHORT).show());
                    return;
                }

                BluetoothSocket socket = device.createRfcommSocketToServiceRecord(
                        UUID.fromString("00001101-0000-1000-8000-00805F9B34FB")
                );
                socket.connect();
                currentSocket = socket;
                connectedPrinter = new Printer(device.getName(), device.getAddress(), 1);

                PrinterUtil.getInstance().setSocket(currentSocket);
                PrinterUtil.getInstance().setPrinter(connectedPrinter);

                runOnUiThread(() -> {
                    updatePrinterStatus(address, 1);
                });

                Bundle bundle = new Bundle();
                bundle.putString("data", new Gson().toJson(SuccessConfigFactory.createConnectPrinterSuccess(PrinterActivity.this)));
                startAppActivity(new Intent(PrinterActivity.this, SuccessActivity.class), bundle, false, false, true);

            } catch (IOException e) {
                Log.e("###", "Failed to connect", e);
                runOnUiThread(() -> Toast.makeText(this, R.string.profile_setting_printer_connect_failed, Toast.LENGTH_SHORT).show());
            }
        }).start();
    }

    void disconnectPrinter(Printer printer) {
        if (currentSocket != null) {
            try {
                currentSocket.close();
                currentSocket = null;
                connectedPrinter = null;
                updatePrinterStatus(printer.getAddress(), 0);
            } catch (IOException e) {
                Log.e("###", "disconnectPrinter: ", e);
            }
        }
    }

    private void updatePrinterStatus(String address, int status) {
        for (Printer p : printerList) {
            if (p.getAddress().equals(address)) {
                p.setStatus(status);
                break;
            }
        }
        printerListViewAdapter.replaceList(printerList);
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, @NonNull String[] permissions, @NonNull int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        if (requestCode == REQUEST_PERMISSIONS) {
            boolean granted = true;
            for (int result : grantResults) {
                if (result != PackageManager.PERMISSION_GRANTED) {
                    granted = false;
                    break;
                }
            }
            if (granted) {
                checkPermissionsAndScan();
            } else {
                Toast.makeText(this, getString(R.string.profile_setting_printer_bluetooth_permission), Toast.LENGTH_SHORT).show();
                resetRefreshButton();
            }
        }
    }

    @Override
    protected void onDestroy() {
        super.onDestroy();
        try {
            bluetoothAdapter.cancelDiscovery();
            unregisterReceiver(receiver);
        } catch (Exception ignored) {
        }
    }
}