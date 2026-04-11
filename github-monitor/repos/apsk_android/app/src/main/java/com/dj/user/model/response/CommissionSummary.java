package com.dj.user.model.response;

public class CommissionSummary {
    private String date;
    private String total_commission;
    private int total_people;

    public String getDate() {
        return date;
    }

    public void setDate(String date) {
        this.date = date;
    }

    public double getTotal_commission() {
        try {
            return Double.parseDouble(total_commission);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setTotal_commission(String total_commission) {
        this.total_commission = total_commission;
    }

    public int getTotal_people() {
        return total_people;
    }

    public void setTotal_people(int total_people) {
        this.total_people = total_people;
    }
}
