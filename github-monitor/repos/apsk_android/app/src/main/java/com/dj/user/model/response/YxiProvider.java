package com.dj.user.model.response;

import com.dj.user.util.PlayerListDeserializer;
import com.google.gson.annotations.JsonAdapter;

import java.util.List;

public class YxiProvider {
    private long provider_id;
    private long gameplatform_id;
    private long providertarget_id;
    private String provider_name;
    private String provider_category;
    private String android;
    private String ios;
    private String icon;
    private String banner;
    private String download;
    private String platform_type;
    private int status;
    private int delete;
    private String created_on;
    private String updated_on;
    @JsonAdapter(PlayerListDeserializer.class)

    private List<Player> player;
    private int isBookmark;
    private long providerbookmark_id;
    private boolean isHeader;
    private boolean isSelected;

    public YxiProvider(boolean isHeader) {
        this.isHeader = isHeader;
    }

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

    public String getAndroid() {
        return android;
    }

    public void setAndroid(String android) {
        this.android = android;
    }

    public String getIos() {
        return ios;
    }

    public void setIos(String ios) {
        this.ios = ios;
    }

    public String getIcon() {
        return icon;
    }

    public void setIcon(String icon) {
        this.icon = icon;
    }

    public String getBanner() {
        return banner;
    }

    public void setBanner(String banner) {
        this.banner = banner;
    }

    public String getDownload() {
        return download;
    }

    public void setDownload(String download) {
        this.download = download;
    }

    public String getPlatform_type() {
        return platform_type;
    }

    public void setPlatform_type(String platform_type) {
        this.platform_type = platform_type;
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

    public List<Player> getPlayer() {
        return player;
    }

    public void setPlayer(List<Player> player) {
        this.player = player;
    }

    public boolean isBookmark() {
        return isBookmark == 1;
    }

    public void setIsBookmark(int isBookmark) {
        this.isBookmark = isBookmark;
    }

    public String getProviderbookmark_id() {
        return String.valueOf(providerbookmark_id);
    }

    public void setProviderbookmark_id(Long providerbookmark_id) {
        this.providerbookmark_id = providerbookmark_id;
    }

    public boolean isHeader() {
        return isHeader;
    }

    public void setHeader(boolean header) {
        isHeader = header;
    }

    public boolean isSelected() {
        return isSelected;
    }

    public void setSelected(boolean selected) {
        isSelected = selected;
    }

    public boolean isDirectToLobby() {
        if (platform_type == null) {
            return false;
        }
        return platform_type.equalsIgnoreCase("Web-Lobby");
    }

    public boolean isExternalApp() {
        if (platform_type == null) {
            return false;
        }
        return platform_type.toLowerCase().startsWith("app");
    }

    public Player getThePlayer() {
        if (player != null && !player.isEmpty()) {
            return player.get(0);
        }
        return null;
    }
}
