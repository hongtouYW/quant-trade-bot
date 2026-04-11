package com.dj.user.model.response;

public class InvitationSummary {
    private String invitecode;
    private String qr;
    private int totaldownline;
    private int totalcredit;
    private String totalcommission;

    public String getInvitecode() {
        return invitecode;
    }

    public void setInvitecode(String invitecode) {
        this.invitecode = invitecode;
    }

    public String getQr() {
        return qr;
    }

    public void setQr(String qr) {
        this.qr = qr;
    }

    public int getTotaldownline() {
        return totaldownline;
    }

    public void setTotaldownline(int totaldownline) {
        this.totaldownline = totaldownline;
    }

    public int getTotalcredit() {
        return totalcredit;
    }

    public void setTotalcredit(int totalcredit) {
        this.totalcredit = totalcredit;
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
}
