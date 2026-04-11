package com.dj.user.model.response;

public class PaymentType {
    private int id;
    private int iconResId;
    private String title;

    private long payment_id;
    private String payment_name;
    private String icon;
    private String amount_type;
    private String type;
    private String min_amount;
    private String max_amount;
    private int status;
    private int delete;
    private String created_on;
    private String updated_on;
    private boolean isSelected;

    // TODO: 12/09/2025 To remove
    public PaymentType(int id, int iconResId, String title) {
        this.id = id;
        this.iconResId = iconResId;
        this.title = title;
    }

    public int getId() {
        return id;
    }

    public void setId(int id) {
        this.id = id;
    }

    public int getIconResId() {
        return iconResId;
    }

    public void setIconResId(int iconResId) {
        this.iconResId = iconResId;
    }

    public String getTitle() {
        return title;
    }

    public void setTitle(String title) {
        this.title = title;
    }

    public String getPayment_id() {
        return String.valueOf(payment_id);
    }

    public void setPayment_id(long payment_id) {
        this.payment_id = payment_id;
    }

    public String getPayment_name() {
        return payment_name;
    }

    public void setPayment_name(String payment_name) {
        this.payment_name = payment_name;
    }

    public String getIcon() {
        return icon;
    }

    public void setIcon(String icon) {
        this.icon = icon;
    }

    public String getAmount_type() {
        return amount_type;
    }

    public void setAmount_type(String amount_type) {
        this.amount_type = amount_type;
    }

    public String getType() {
        return type;
    }

    public void setType(String type) {
        this.type = type;
    }

    public double getMin_amount() {
        try {
            double amount = Double.parseDouble(min_amount);
            return amount > 0 ? amount : 1.0;
        } catch (NumberFormatException | NullPointerException e) {
            return 1.0;
        }
    }

    public void setMin_amount(String min_amount) {
        this.min_amount = min_amount;
    }

    public double getMax_amount() {
        try {
            return Double.parseDouble(max_amount);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setMax_amount(String max_amount) {
        this.max_amount = max_amount;
    }

    public int getStatus() {
        return status;
    }

    public void setStatus(int status) {
        this.status = status;
    }

    public int getDelete() {
        return delete;
    }

    public void setDelete(int delete) {
        this.delete = delete;
    }

    public String getCreated_on() {
        return created_on;
    }

    public void setCreated_on(String created_on) {
        this.created_on = created_on;
    }

    public String getUpdated_on() {
        return updated_on;
    }

    public void setUpdated_on(String updated_on) {
        this.updated_on = updated_on;
    }

    public boolean isSelected() {
        return isSelected;
    }

    public void setSelected(boolean selected) {
        isSelected = selected;
    }
}
