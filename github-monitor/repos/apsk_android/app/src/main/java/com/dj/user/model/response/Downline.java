package com.dj.user.model.response;

import com.dj.user.util.StringUtil;

import java.util.List;

public class Downline {
    private long recruit_id;
    private long member_id;
    private String title;
    private String member_login;
    private String member_name;
    private long upline;
    private String invitecode;
    private int status;
    private int delete;
    private String created_on;
    private String updated_on;
    private Member member;
    private List<Downline> downline;
    private String registered_on;

    public long getRecruit_id() {
        return recruit_id;
    }

    public void setRecruit_id(long recruit_id) {
        this.recruit_id = recruit_id;
    }

    public String getMember_id() {
        return String.valueOf(member_id);
    }

    public void setMember_id(long member_id) {
        this.member_id = member_id;
    }

    public String getTitle() {
        return title;
    }

    public void setTitle(String title) {
        this.title = title;
    }

    public long getUpline() {
        return upline;
    }

    public void setUpline(long upline) {
        this.upline = upline;
    }

    public String getInvitecode() {
        return invitecode;
    }

    public void setInvitecode(String invitecode) {
        this.invitecode = invitecode;
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

    public List<Downline> getDownline() {
        return downline;
    }

    public void setDownline(List<Downline> downline) {
        this.downline = downline;
    }

    public String getRegistered_on() {
        return registered_on;
    }

    public void setRegistered_on(String registered_on) {
        this.registered_on = registered_on;
    }

    public String getMemberName() {
        return !StringUtil.isNullOrEmpty(member_name) ? member_name :
                !StringUtil.isNullOrEmpty(member_login) ? member_login : "";
    }
}
