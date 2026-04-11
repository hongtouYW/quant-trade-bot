package com.dj.user.model.response;

public class SummaryData {
    private SummaryDataDetails now;
    private SummaryDataDetails downline;
    private SummaryDataDetails team;

    public SummaryDataDetails getNow() {
        return now;
    }

    public void setNow(SummaryDataDetails now) {
        this.now = now;
    }

    public SummaryDataDetails getDownline() {
        return downline;
    }

    public void setDownline(SummaryDataDetails downline) {
        this.downline = downline;
    }

    public SummaryDataDetails getTeam() {
        return team;
    }

    public void setTeam(SummaryDataDetails team) {
        this.team = team;
    }

    public int getNewDirect() {
        if (now == null || now.getRecruit() == null || now.getRecruit().getNewRecruit() == null) {
            return 0;
        }
        return now.getRecruit().getNewRecruit().getTotalcount();
    }

    public double getFirstBonusAmount() {
        if (now == null || now.getVip() == null || now.getVip().getFirstbonus() == null) {
            return 0.0;
        }
        return now.getVip().getFirstbonus().getTotalamount();
    }

    public int getFirstBonusCount() {
        if (now == null || now.getVip() == null || now.getVip().getFirstbonus() == null) {
            return 0;
        }
        return now.getVip().getFirstbonus().getTotalcount();
    }

    public double getDepositAmount() {
        if (now == null || now.getDeposit() == null) {
            return 0.0;
        }
        return now.getDeposit().getTotalamount();
    }

    public int getDepositCount() {
        if (now == null || now.getDeposit() == null) {
            return 0;
        }
        return now.getDeposit().getTotalcount();
    }

    public double getWithdrawAmount() {
        if (now == null || now.getWithdraw() == null) {
            return 0.0;
        }
        return now.getWithdraw().getTotalamount();
    }

    public int getWithdrawCount() {
        if (now == null || now.getWithdraw() == null) {
            return 0;
        }
        return now.getWithdraw().getTotalcount();
    }

    public double getTotalBetAmount() {
        if (downline == null || downline.getGame() == null) {
            return 0.0;
        }
        return downline.getGame().getBetamount();
    }

    public double getRewardAmount() {
        if (now == null || now.getVip() == null) {
            return 0.0;
        }
        return now.getVip().getReward();
    }

    public double getWinLossAmount() {
        if (downline == null || downline.getGame() == null) {
            return 0.0;
        }
        return downline.getGame().getWinloss();
    }

    public double getSalesAmount() {
        if (now == null || now.getVip() == null || now.getVip().getSales() == null) {
            return 0.0;
        }
        return now.getVip().getSales().getTotalamount();
    }

    public double getCommissionAmount() {
        if (now == null || now.getVip() == null || now.getVip().getCommission() == null) {
            return 0.0;
        }
        return now.getVip().getCommission().getTotalamount();
    }

    public int getTeamCount() {
        if (team == null || team.getRecruit() == null || team.getRecruit().getMain() == null) {
            return 0;
        }
        return team.getRecruit().getMain().getTotalcount();
    }

    public int getDownlineCount() {
        if (team == null || team.getRecruit() == null || team.getRecruit().getDownline() == null) {
            return 0;
        }
        return team.getRecruit().getDownline().getTotalcount();
    }

    public double getTeamSales() {
        if (team == null || team.getRecruit() == null || team.getRecruit().getMain() == null) {
            return 0.0;
        }
        return team.getRecruit().getMain().getTotalsales();
    }

    public double getDownlineSales() {
        if (team == null || team.getRecruit() == null || team.getRecruit().getDownline() == null) {
            return 0.0;
        }
        return team.getRecruit().getDownline().getTotalsales();
    }

    public double getTeamCommission() {
        if (team == null || team.getRecruit() == null || team.getRecruit().getMain() == null) {
            return 0.0;
        }
        return team.getRecruit().getMain().getTotalcommission();
    }

    public double getDownlineCommission() {
        if (team == null || team.getRecruit() == null || team.getRecruit().getDownline() == null) {
            return 0.0;
        }
        return team.getRecruit().getDownline().getTotalcommission();
    }

    public double getAccumulatedCommission() {
        if (team == null || team.getRecruit() == null || team.getRecruit().getAccumulate() == null) {
            return 0.0;
        }
        return team.getRecruit().getAccumulate().getTotalcommission();
    }

    public double getTeamRedeem() {
        if (team == null || team.getRecruit() == null || team.getRecruit().getMain() == null) {
            return 0.0;
        }
        return team.getRecruit().getMain().getReward();
    }

    public double getAccumulatedTopUp() {
        if (team == null || team.getRecruit() == null || team.getRecruit().getAccumulate() == null) {
            return 0.0;
        }
        return team.getRecruit().getAccumulate().getTotaldeposit();
    }

    public double getAccumulatedWithdraw() {
        if (team == null || team.getRecruit() == null || team.getRecruit().getAccumulate() == null) {
            return 0.0;
        }
        return team.getRecruit().getAccumulate().getTotalwithdraw();
    }

    public double getAccumulatedRedeem() {
        if (team == null || team.getRecruit() == null || team.getRecruit().getAccumulate() == null) {
            return 0.0;
        }
        return team.getRecruit().getAccumulate().getReward();
    }

    public double getAccumulatedBet() {
        if (team == null || team.getRecruit() == null || team.getRecruit().getAccumulate() == null) {
            return 0.0;
        }
        return team.getRecruit().getAccumulate().getBetamount();
    }

    public double getAccumulatedWinLoss() {
        if (team == null || team.getRecruit() == null || team.getRecruit().getAccumulate() == null) {
            return 0.0;
        }
        return team.getRecruit().getAccumulate().getWinloss();
    }
}
