package com.dj.user.model.response;

import java.util.List;

public class FindUs {
    private String email;
    private List<String> domains;
    private List<String> homes;

    public String getEmail() {
        return email;
    }

    public void setEmail(String email) {
        this.email = email;
    }

    public List<String> getDomains() {
        return domains;
    }

    public void setDomains(List<String> domains) {
        this.domains = domains;
    }

    public List<String> getHomes() {
        return homes;
    }

    public void setHomes(List<String> homes) {
        this.homes = homes;
    }
}
