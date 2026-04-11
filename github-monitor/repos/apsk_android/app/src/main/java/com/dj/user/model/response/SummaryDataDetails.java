package com.dj.user.model.response;

public class SummaryDataDetails {
    private SummaryDataDetailsMain recruit;
    private SummaryDataDetailsMain vip;
    private SummaryDataDetailsInfo deposit;
    private SummaryDataDetailsInfo withdraw;
    private SummaryDataDetailsInfo game;

    public SummaryDataDetailsMain getRecruit() {
        return recruit;
    }

    public void setRecruit(SummaryDataDetailsMain recruit) {
        this.recruit = recruit;
    }

    public SummaryDataDetailsMain getVip() {
        return vip;
    }

    public void setVip(SummaryDataDetailsMain vip) {
        this.vip = vip;
    }

    public SummaryDataDetailsInfo getDeposit() {
        return deposit;
    }

    public void setDeposit(SummaryDataDetailsInfo deposit) {
        this.deposit = deposit;
    }

    public SummaryDataDetailsInfo getWithdraw() {
        return withdraw;
    }

    public void setWithdraw(SummaryDataDetailsInfo withdraw) {
        this.withdraw = withdraw;
    }

    public SummaryDataDetailsInfo getGame() {
        return game;
    }

    public void setGame(SummaryDataDetailsInfo game) {
        this.game = game;
    }
}
