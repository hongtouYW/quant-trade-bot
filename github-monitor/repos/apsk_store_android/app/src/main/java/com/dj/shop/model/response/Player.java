package com.dj.shop.model.response;

import com.dj.shop.util.StringUtil;

public class Player {
    private long gamemember_id;
    private long member_id;
    private long game_id;
    private long gameplatform_id;
    private long provider_id;
    private long shop_id;
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
    private YxiProvider provider;

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

    public String getGameplatform_id() {
        return String.valueOf(gameplatform_id);
    }

    public void setGameplatform_id(long gameplatform_id) {
        this.gameplatform_id = gameplatform_id;
    }

    public String getProvider_id() {
        return String.valueOf(provider_id);
    }

    public void setProvider_id(long provider_id) {
        this.provider_id = provider_id;
    }

    public String getShop_id() {
        return String.valueOf(shop_id);
    }

    public void setShop_id(long shop_id) {
        this.shop_id = shop_id;
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
            return Double.parseDouble(balance.replaceAll(",", ""));
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
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
        if (game == null) {
            return new Yxi();
        }
        return game;
    }

    public void setGame(Yxi game) {
        this.game = game;
    }

    public YxiProvider getProvider() {
        if (provider == null) {
            return new YxiProvider();
        }
        return provider;
    }

    public void setProvider(YxiProvider provider) {
        this.provider = provider;
    }

    public double getMemberBalance() {
        if (member == null) {
            return 0.00;
        }
        return member.getBalance();
    }

    public String getGameName() {
        if (game == null) {
            return "";
        }
        return !StringUtil.isNullOrEmpty(game.getGame_name()) ? game.getGame_name() : "";
    }

    public String getProviderName() {
        if (provider == null) {
            return "";
        }
        return !StringUtil.isNullOrEmpty(provider.getProvider_name()) ? provider.getProvider_name() : "";
    }
}
