package com.dj.manager.model.response;

import com.dj.manager.util.FormatUtils;
import com.dj.manager.util.StringUtil;

public class Point {
    private long gamepoint_id;
    private long shop_id;
    private long gamemember_id;
    private String type;
    private String ip;
    private String amount;
    private String before_balance;
    private String after_balance;
    private String start_on;
    private String end_on;
    private int status;
    private int delete;
    private String created_on;
    private String updated_on;
    private Shop shop;
    private Player gamemember;

    public String getGamepoint_id() {
        return String.valueOf(gamepoint_id);
    }

    public void setGamepoint_id(long gamepoint_id) {
        this.gamepoint_id = gamepoint_id;
    }

    public String getShop_id() {
        return String.valueOf(shop_id);
    }

    public void setShop_id(long shop_id) {
        this.shop_id = shop_id;
    }

    public String getGamemember_id() {
        return String.valueOf(gamemember_id);
    }

    public void setGamemember_id(long gamemember_id) {
        this.gamemember_id = gamemember_id;
    }

    public String getType() {
        return type;
    }

    public void setType(String type) {
        this.type = type;
    }

    public String getIp() {
        return ip;
    }

    public void setIp(String ip) {
        this.ip = ip;
    }

    public double getAmount() {
        try {
            return Double.parseDouble(amount.replaceAll(",", ""));
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setAmount(String amount) {
        this.amount = amount;
    }

    public double getBefore_balance() {
        try {
            return Double.parseDouble(before_balance.replaceAll(",", ""));
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setBefore_balance(String before_balance) {
        this.before_balance = before_balance;
    }

    public double getAfter_balance() {
        try {
            return Double.parseDouble(after_balance.replaceAll(",", ""));
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setAfter_balance(String after_balance) {
        this.after_balance = after_balance;
    }

    public String getStart_on() {
        return start_on;
    }

    public void setStart_on(String start_on) {
        this.start_on = start_on;
    }

    public String getEnd_on() {
        return end_on;
    }

    public void setEnd_on(String end_on) {
        this.end_on = end_on;
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

    public Shop getShop() {
        return shop;
    }

    public void setShop(Shop shop) {
        this.shop = shop;
    }

    public Player getGamemember() {
        return gamemember;
    }

    public void setGamemember(Player gamemember) {
        this.gamemember = gamemember;
    }

    public boolean isCreatedByShop() {
        return !StringUtil.isNullOrEmpty(getShop_id()) && shop != null;
    }

    public String getShopName() {
        if (shop == null) {
            return "-";
        }
        return !StringUtil.isNullOrEmpty(shop.getShop_name()) ? shop.getShop_name() : "-";
    }

    public double getShopBalance() {
        if (shop == null) {
            return 0.0;
        }
        return shop.getBalance();
    }

    public String getPlayerLogin() {
        if (gamemember == null) {
            return "-";
        }
        return !StringUtil.isNullOrEmpty(gamemember.getLogin()) ? gamemember.getLogin() : "-";
    }

    public Member getMember() {
        if (gamemember == null || gamemember.getMember() == null) {
            return null;
        }
        return gamemember.getMember();
    }

    public String getPhoneNumber() {
        if (gamemember == null || gamemember.getMember() == null) {
            return "-";
        }
        return !StringUtil.isNullOrEmpty(gamemember.getMember().getPhone()) ? FormatUtils.formatMsianPhone(gamemember.getMember().getPhone()) : "-";
    }
}
