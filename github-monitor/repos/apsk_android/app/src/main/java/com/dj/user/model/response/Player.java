package com.dj.user.model.response;

import com.dj.user.util.StringUtil;

public class Player {
    private long gamemember_id;
    private long member_id;
    private long game_id;
    private long gameplatform_id;
    private long shop_id;
    private String uid;
    private String loginId;
    private String login;
    private String pass;
    private String name;
    private String balance;
    private int status;
    private int delete;
    private String created_on;
    private String updated_on;
    private Member member;
    private Yxi game;
    private YxiPlatform gameplatform;
    private YxiProvider provider;
    private double transferAmount;

    public String getGamemember_id() {
        return String.valueOf(gamemember_id);
    }

    public void setGamemember_id(long gamemember_id) {
        this.gamemember_id = gamemember_id;
    }

    public String getMember_id() {
        return String.valueOf(member_id);
    }

    public void setMember_id(long member_id) {
        this.member_id = member_id;
    }

    public String getGame_id() {
        return String.valueOf(game_id);
    }

    public void setGame_id(long game_id) {
        this.game_id = game_id;
    }

    public long getGameplatform_id() {
        return gameplatform_id;
    }

    public void setGameplatform_id(long gameplatform_id) {
        this.gameplatform_id = gameplatform_id;
    }

    public String getShop_id() {
        return String.valueOf(shop_id);
    }

    public void setShop_id(long shop_id) {
        this.shop_id = shop_id;
    }

    public String getUid() {
        return uid;
    }

    public void setUid(String uid) {
        this.uid = uid;
    }

    public String getLoginId() {
        return loginId;
    }

    public void setLoginId(String loginId) {
        this.loginId = loginId;
    }

    public String getLogin() {
        return login;
    }

    public void setLogin(String login) {
        this.login = login;
    }

    public String getPass() {
        return pass;
    }

    public void setPass(String pass) {
        this.pass = pass;
    }

    public String getName() {
        return name;
    }

    public void setName(String name) {
        this.name = name;
    }

    public double getBalance() {
        try {
            return Double.parseDouble(balance);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public String getBalanceStr() {
        return balance;
    }

    public void setBalance(String balance) {
        this.balance = balance;
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

    public Member getMember() {
        return member;
    }

    public void setMember(Member member) {
        this.member = member;
    }

    public Yxi getGame() {
        return game;
    }

    public void setGame(Yxi yxi) {
        this.game = yxi;
    }

    public YxiPlatform getGameplatform() {
        return gameplatform;
    }

    public void setGameplatform(YxiPlatform gameplatform) {
        this.gameplatform = gameplatform;
    }

    public YxiProvider getProvider() {
        return provider;
    }

    public void setProvider(YxiProvider provider) {
        this.provider = provider;
    }

    public double getTransferAmount() {
        return transferAmount;
    }

    public void setTransferAmount(double transferAmount) {
        this.transferAmount = transferAmount;
    }

    public double getMemberBalance() {
        if (member == null) {
            return 0.00;
        }
        return member.getBalance();
    }

    public String getYxiProviderName() {
        if (provider == null) {
            return "-";
        }
        return !StringUtil.isNullOrEmpty(provider.getProvider_name()) ? provider.getProvider_name() : "-";
    }
}
