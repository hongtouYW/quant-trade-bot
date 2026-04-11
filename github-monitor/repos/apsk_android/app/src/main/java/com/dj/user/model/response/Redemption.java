package com.dj.user.model.response;

public class Redemption {
    private String type;
    private int vip_id;
    private int lvl;
    private String template;
    private String created_on;
    private String expired_on;
    private int status;

    public String getType() {
        return type;
    }

    public void setType(String type) {
        this.type = type;
    }

    public int getVip_id() {
        return vip_id;
    }

    public void setVip_id(int vip_id) {
        this.vip_id = vip_id;
    }

    public int getLvl() {
        return lvl;
    }

    public void setLvl(int lvl) {
        this.lvl = lvl;
    }

    public String getTemplate() {
        return template;
    }

    public void setTemplate(String template) {
        this.template = template;
    }

    public String getCreated_on() {
        return created_on;
    }

    public void setCreated_on(String created_on) {
        this.created_on = created_on;
    }

    public String getExpired_on() {
        return expired_on;
    }

    public void setExpired_on(String expired_on) {
        this.expired_on = expired_on;
    }

    public int getStatus() {
        return status;
    }

    public void setStatus(int status) {
        this.status = status;
    }

    public boolean isRedeemed() {
        return status == 1;
    }
}
