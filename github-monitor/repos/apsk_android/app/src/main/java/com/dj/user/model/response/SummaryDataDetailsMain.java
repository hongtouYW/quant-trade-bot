package com.dj.user.model.response;

import com.google.gson.annotations.SerializedName;

public class SummaryDataDetailsMain {
    @SerializedName("new")
    private SummaryDataDetailsInfo newRecruit;
    private SummaryDataDetailsInfo firstbonus;
    private String reward;
    private SummaryDataDetailsInfo sales;
    private SummaryDataDetailsInfo commission;
    private SummaryDataDetailsInfo game;
    private SummaryDataDetailsInfo main;
    private SummaryDataDetailsInfo downline;
    private SummaryDataDetailsInfo accumulate;

    public SummaryDataDetailsInfo getNewRecruit() {
        return newRecruit;
    }

    public void setNewRecruit(SummaryDataDetailsInfo newRecruit) {
        this.newRecruit = newRecruit;
    }

    public SummaryDataDetailsInfo getFirstbonus() {
        return firstbonus;
    }

    public void setFirstbonus(SummaryDataDetailsInfo firstbonus) {
        this.firstbonus = firstbonus;
    }

    public double getReward() {
        try {
            return Double.parseDouble(reward);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setReward(String reward) {
        this.reward = reward;
    }

    public SummaryDataDetailsInfo getSales() {
        return sales;
    }

    public void setSales(SummaryDataDetailsInfo sales) {
        this.sales = sales;
    }

    public SummaryDataDetailsInfo getCommission() {
        return commission;
    }

    public void setCommission(SummaryDataDetailsInfo commission) {
        this.commission = commission;
    }

    public SummaryDataDetailsInfo getGame() {
        return game;
    }

    public void setGame(SummaryDataDetailsInfo game) {
        this.game = game;
    }

    public SummaryDataDetailsInfo getMain() {
        return main;
    }

    public void setMain(SummaryDataDetailsInfo main) {
        this.main = main;
    }

    public SummaryDataDetailsInfo getDownline() {
        return downline;
    }

    public void setDownline(SummaryDataDetailsInfo downline) {
        this.downline = downline;
    }

    public SummaryDataDetailsInfo getAccumulate() {
        return accumulate;
    }

    public void setAccumulate(SummaryDataDetailsInfo accumulate) {
        this.accumulate = accumulate;
    }
}
