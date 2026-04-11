package com.dj.user.model.response;

public class SummaryDataDetailsInfo {
    private String totalamount;
    private int totalcount;
    private String totalbetamount;
    private String winloss;
    private String totalsales;
    private String totalcommission;
    private String reward;
    private String totaldeposit;
    private String totalwithdraw;
    private String betamount;

    public double getTotalamount() {
        try {
            return Double.parseDouble(totalamount);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setTotalamount(String totalamount) {
        this.totalamount = totalamount;
    }

    public int getTotalcount() {
        return totalcount;
    }

    public void setTotalcount(int totalcount) {
        this.totalcount = totalcount;
    }

    public double getTotalbetamount() {
        try {
            return Double.parseDouble(totalbetamount);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setTotalbetamount(String totalbetamount) {
        this.totalbetamount = totalbetamount;
    }

    public double getWinloss() {
        try {
            return Double.parseDouble(winloss);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setWinloss(String winloss) {
        this.winloss = winloss;
    }

    public double getTotalsales() {
        try {
            return Double.parseDouble(totalsales);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setTotalsales(String totalsales) {
        this.totalsales = totalsales;
    }

    public double getTotalcommission() {
        try {
            return Double.parseDouble(totalcommission);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setTotalcommission(String totalcommission) {
        this.totalcommission = totalcommission;
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

    public double getTotaldeposit() {
        try {
            return Double.parseDouble(totaldeposit);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setTotaldeposit(String totaldeposit) {
        this.totaldeposit = totaldeposit;
    }

    public double getTotalwithdraw() {
        try {
            return Double.parseDouble(totalwithdraw);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setTotalwithdraw(String totalwithdraw) {
        this.totalwithdraw = totalwithdraw;
    }

    public double getBetamount() {
        try {
            return Double.parseDouble(betamount);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setBetamount(String betamount) {
        this.betamount = betamount;
    }
}
