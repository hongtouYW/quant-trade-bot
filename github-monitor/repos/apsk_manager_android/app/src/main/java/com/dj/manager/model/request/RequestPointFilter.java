package com.dj.manager.model.request;

public class RequestPointFilter {
    private String manager_id;
    private String list;
    private String transaction;
    private String status;
    private String startdate;
    private String enddate;

    public RequestPointFilter(String manager_id) {
        this.manager_id = manager_id;
    }

    public String getList() {
        return list;
    }

    public void setList(String list) {
        this.list = list;
    }

    public String getTransaction() {
        return transaction;
    }

    public void setTransaction(String transaction) {
        this.transaction = transaction;
    }

    public String getStatus() {
        return status;
    }

    public void setStatus(String status) {
        this.status = status;
    }

    public String getStartdate() {
        return startdate;
    }

    public void setStartdate(String startdate) {
        this.startdate = startdate;
    }

    public String getEnddate() {
        return enddate;
    }

    public void setEnddate(String enddate) {
        this.enddate = enddate;
    }

    public boolean shouldSerializeTransaction() {
        return transaction != null && !transaction.isEmpty();
    }

    public boolean shouldSerializeStatus() {
        return status != null && !status.isEmpty();
    }

    public boolean shouldSerializeStartDate() {
        return startdate != null && !startdate.isEmpty();
    }

    public boolean shouldSerializeEndDate() {
        return enddate != null && !enddate.isEmpty();
    }
}
