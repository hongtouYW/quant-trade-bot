package com.dj.user.model.response;

import com.dj.user.util.StringUtil;

public class Commission {
    private long id;
    private long member_id;
    private long downline;
    private long upline;
    private int commissionrank_id;
    private String performance_date;
    private String sales_amount;
    private String commission_amount;
    private String before_balance;
    private String after_balance;
    private String notes;
    private long agent_id;
    private int status;
    private int delete;
    private String created_on;
    private String updated_on;
    private Member mydownline;
    private Member myupline;

    public String getId() {
        return String.valueOf(id);
    }

    public void setId(long id) {
        this.id = id;
    }

    public String getMember_id() {
        return String.valueOf(member_id);
    }

    public void setMember_id(long member_id) {
        this.member_id = member_id;
    }

    public String getDownline() {
        return String.valueOf(downline);
    }

    public void setDownline(long downline) {
        this.downline = downline;
    }

    public String getUpline() {
        return String.valueOf(upline);
    }

    public void setUpline(long upline) {
        this.upline = upline;
    }

    public int getCommissionrank_id() {
        return commissionrank_id;
    }

    public void setCommissionrank_id(int commissionrank_id) {
        this.commissionrank_id = commissionrank_id;
    }

    public String getPerformance_date() {
        return performance_date;
    }

    public void setPerformance_date(String performance_date) {
        this.performance_date = performance_date;
    }

    public double getSales_amount() {
        try {
            return Double.parseDouble(sales_amount);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setSales_amount(String sales_amount) {
        this.sales_amount = sales_amount;
    }

    public double getCommission_amount() {
        try {
            return Double.parseDouble(commission_amount);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setCommission_amount(String commission_amount) {
        this.commission_amount = commission_amount;
    }

    public double getBefore_balance() {
        try {
            return Double.parseDouble(before_balance);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setBefore_balance(String before_balance) {
        this.before_balance = before_balance;
    }

    public double getAfter_balance() {
        try {
            return Double.parseDouble(after_balance);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setAfter_balance(String after_balance) {
        this.after_balance = after_balance;
    }

    public String getNotes() {
        return notes;
    }

    public void setNotes(String notes) {
        this.notes = notes;
    }

    public String getAgent_id() {
        return String.valueOf(agent_id);
    }

    public void setAgent_id(long agent_id) {
        this.agent_id = agent_id;
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

    public Member getMydownline() {
        return mydownline;
    }

    public void setMydownline(Member mydownline) {
        this.mydownline = mydownline;
    }

    public Member getMyupline() {
        return myupline;
    }

    public void setMyupline(Member myupline) {
        this.myupline = myupline;
    }

    public String getMemberName() {
        if (mydownline != null) {
            return !StringUtil.isNullOrEmpty(mydownline.getMember_name()) ? mydownline.getMember_name() :
                    !StringUtil.isNullOrEmpty(mydownline.getMember_login()) ? mydownline.getMember_login() : "-";
        }
        return "-";
    }
}
