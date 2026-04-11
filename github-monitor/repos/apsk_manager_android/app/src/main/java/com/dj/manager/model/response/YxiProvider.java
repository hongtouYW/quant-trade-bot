package com.dj.manager.model.response;

public class YxiProvider {
    private long provider_id;
    private long gameplatform_id;
    private long providertarget_id;
    private String provider_name;
    private String provider_category;
    private String icon;
    private String download;
    private int status;
    private int delete;
    private String created_on;
    private String updated_on;
    private boolean isSelected;

    public String getProvider_id() {
        return String.valueOf(provider_id);
    }

    public void setProvider_id(long provider_id) {
        this.provider_id = provider_id;
    }

    public String getGameplatform_id() {
        return String.valueOf(gameplatform_id);
    }

    public void setGameplatform_id(int gameplatform_id) {
        this.gameplatform_id = gameplatform_id;
    }

    public String getProvidertarget_id() {
        return String.valueOf(providertarget_id);
    }

    public void setProvidertarget_id(long providertarget_id) {
        this.providertarget_id = providertarget_id;
    }

    public String getProvider_name() {
        return provider_name;
    }

    public void setProvider_name(String provider_name) {
        this.provider_name = provider_name;
    }

    public String getProvider_category() {
        return provider_category;
    }

    public void setProvider_category(String provider_category) {
        this.provider_category = provider_category;
    }

    public String getIcon() {
        return icon;
    }

    public void setIcon(String icon) {
        this.icon = icon;
    }

    public String getDownload() {
        return download;
    }

    public void setDownload(String download) {
        this.download = download;
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
