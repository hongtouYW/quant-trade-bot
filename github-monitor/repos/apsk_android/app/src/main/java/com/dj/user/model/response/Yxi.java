package com.dj.user.model.response;

public class Yxi {
    private long game_id;
    private long gameplatform_id;
    private String game_name;
    private String game_desc;
    private String gametarget_id;
    private String tag_id;
    private String android;
    private String ios;
    private String icon;
    private String banner;
    private String api;
    private int type;
    private int status;
    private int delete;
    private String created_on;
    private String updated_on;
    private int isBookmark;
    private Long gamebookmark_id;
    private YxiPlatform gameplatform;
    private GameType game_type;

    private boolean isSelected;

    public String getGame_id() {
        return String.valueOf(game_id);
    }

    public void setGame_id(int game_id) {
        this.game_id = game_id;
    }

    public String getGameplatform_id() {
        return String.valueOf(gameplatform_id);
    }

    public void setGameplatform_id(long gameplatform_id) {
        this.gameplatform_id = gameplatform_id;
    }

    public String getGame_name() {
        return game_name;
    }

    public void setGame_name(String game_name) {
        this.game_name = game_name;
    }

    public String getGame_desc() {
        return game_desc;
    }

    public void setGame_desc(String game_desc) {
        this.game_desc = game_desc;
    }

    public String getGametarget_id() {
        return gametarget_id;
    }

    public void setGametarget_id(String gametarget_id) {
        this.gametarget_id = gametarget_id;
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

    public String getTag_id() {
        return tag_id;
    }

    public void setTag_id(String tag_id) {
        this.tag_id = tag_id;
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

    public String getApi() {
        return api;
    }

    public void setApi(String api) {
        this.api = api;
    }

    public int getType() {
        return type;
    }

    public void setType(int type) {
        this.type = type;
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

    public boolean isBookmark() {
        return isBookmark == 1;
    }

    public void setIsBookmark(int isBookmark) {
        this.isBookmark = isBookmark;
    }

    public String getGamebookmark_id() {
        return String.valueOf(gamebookmark_id);
    }

    public void setGamebookmark_id(Long gamebookmark_id) {
        this.gamebookmark_id = gamebookmark_id;
    }

    public YxiPlatform getGameplatform() {
        return gameplatform;
    }

    public void setGameplatform(YxiPlatform gameplatform) {
        this.gameplatform = gameplatform;
    }

    public GameType getGame_type() {
        return game_type;
    }

    public void setGame_type(GameType game_type) {
        this.game_type = game_type;
    }

    public boolean isSelected() {
        return isSelected;
    }

    public void setSelected(boolean selected) {
        isSelected = selected;
    }

    public boolean isExternalApp() {
        if (game_type == null) {
            return type == 1;
        }
        return game_type.getType_name().equalsIgnoreCase("application");
    }
}
