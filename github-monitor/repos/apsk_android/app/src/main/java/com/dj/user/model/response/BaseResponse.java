package com.dj.user.model.response;

import java.util.List;

public class BaseResponse<T> {
    private T data; // Only present when status == true
    private String error;
    private String message;
    private int status;
    private Token token; // Optional, may be null
    private String encode_sign;

    // For refresh token response (directly in root)
    private String access_token;
    private String refresh_token;
    private String token_type;
    private String expires_in;
    private String refresh_expires_in;

    // +1111 user login
    private boolean needbinding;

    // Reset
    private long member_id;

    // Avatar
    private List<Avatar> images;
    private String avatar;

    // Player top up / withdraw
    private List<Transaction> credit;
    private Pagination creditpagination;
    private Member member;
    private List<Player> player;
    private List<Transaction> point;
    private Pagination pointpagination;
    private List<Transaction> history;
    private Pagination historypagination;

    // Top up
    private String url;
    // Withdraw QR
    private String qr;

    // Add player
    private String player_login;
    private String player_pass;

    // Yxi
    private List<Yxi> game;
    private String downloadurl;
    private Deeplink deeplink;

    // Player password
    private String password;

    // Downline
    private List<Downline> alldownline;

    // Transfer point
    private Player playerfrom;
    private Player playerto;
    private Transaction pointfrom;
    private Transaction pointto;

    private double totalamount;

    // Referral
    private String referralCode; // qr

    // VIP redeem distance
    private Remain remain;

    // Support
    private String support;
    private String telegramsupport;
    private String whatsappsupport;

    // Upline
    private List<Member> upline;

    // Withdraw
    private double charge;
    private boolean has_game;

    // Login testing
    private String otpcode;

    public T getData() {
        return data;
    }

    public void setData(T data) {
        this.data = data;
    }

    public String getError() {
        return error;
    }

    public void setError(String error) {
        this.error = error;
    }

    public String getMessage() {
        return message;
    }

    public void setMessage(String message) {
        this.message = message;
    }

    public int getStatus() {
        return status;
    }

    public void setStatus(int status) {
        this.status = status;
    }

    public boolean isStatus() {
        return status == 1 || status == 200;
    }

    public Token getToken() {
        return token;
    }

    public void setToken(Token token) {
        this.token = token;
    }

    public String getEncode_sign() {
        return encode_sign;
    }

    public void setEncode_sign(String encode_sign) {
        this.encode_sign = encode_sign;
    }

    public String getAccess_token() {
        return access_token;
    }

    public void setAccess_token(String access_token) {
        this.access_token = access_token;
    }

    public String getRefresh_token() {
        return refresh_token;
    }

    public void setRefresh_token(String refresh_token) {
        this.refresh_token = refresh_token;
    }

    public String getToken_type() {
        return token_type;
    }

    public void setToken_type(String token_type) {
        this.token_type = token_type;
    }

    public String getExpires_in() {
        return expires_in;
    }

    public void setExpires_in(String expires_in) {
        this.expires_in = expires_in;
    }

    public String getRefresh_expires_in() {
        return refresh_expires_in;
    }

    public void setRefresh_expires_in(String refresh_expires_in) {
        this.refresh_expires_in = refresh_expires_in;
    }

    public boolean isNeedbinding() {
        return needbinding;
    }

    public void setNeedbinding(boolean needbinding) {
        this.needbinding = needbinding;
    }

    public String getMember_id() {
        return String.valueOf(member_id);
    }

    public void setMember_id(long member_id) {
        this.member_id = member_id;
    }

    public List<Avatar> getImages() {
        return images;
    }

    public void setImages(List<Avatar> images) {
        this.images = images;
    }

    public String getAvatar() {
        return avatar;
    }

    public void setAvatar(String avatar) {
        this.avatar = avatar;
    }

    public List<Transaction> getCredit() {
        return credit;
    }

    public void setCredit(List<Transaction> credit) {
        this.credit = credit;
    }

    public Pagination getCreditpagination() {
        return creditpagination;
    }

    public void setCreditpagination(Pagination creditpagination) {
        this.creditpagination = creditpagination;
    }

    public Member getMember() {
        return member;
    }

    public void setMember(Member member) {
        this.member = member;
    }

    public List<Player> getPlayer() {
        return player;
    }

    public void setPlayer(List<Player> player) {
        this.player = player;
    }

    public List<Transaction> getPoint() {
        return point;
    }

    public void setPoint(List<Transaction> point) {
        this.point = point;
    }

    public Pagination getPointpagination() {
        return pointpagination;
    }

    public void setPointpagination(Pagination pointpagination) {
        this.pointpagination = pointpagination;
    }

    public List<Transaction> getHistory() {
        return history;
    }

    public void setHistory(List<Transaction> history) {
        this.history = history;
    }

    public Pagination getHistorypagination() {
        return historypagination;
    }

    public void setHistorypagination(Pagination historypagination) {
        this.historypagination = historypagination;
    }

    public String getUrl() {
        return url;
    }

    public void setUrl(String url) {
        this.url = url;
    }

    public String getQr() {
        return qr;
    }

    public void setQr(String qr) {
        this.qr = qr;
    }

    public String getPlayer_login() {
        return player_login;
    }

    public void setPlayer_login(String player_login) {
        this.player_login = player_login;
    }

    public String getPlayer_pass() {
        return player_pass;
    }

    public void setPlayer_pass(String player_pass) {
        this.player_pass = player_pass;
    }

    public List<Yxi> getGame() {
        return game;
    }

    public void setGame(List<Yxi> yxi) {
        this.game = yxi;
    }

    public String getDownloadurl() {
        return downloadurl;
    }

    public void setDownloadurl(String downloadurl) {
        this.downloadurl = downloadurl;
    }

    public Deeplink getDeeplink() {
        return deeplink;
    }

    public void setDeeplink(Deeplink deeplink) {
        this.deeplink = deeplink;
    }

    public String getPassword() {
        return password;
    }

    public void setPassword(String password) {
        this.password = password;
    }

    public List<Downline> getAlldownline() {
        return alldownline;
    }

    public void setAlldownline(List<Downline> alldownline) {
        this.alldownline = alldownline;
    }

    public Player getPlayerfrom() {
        return playerfrom;
    }

    public void setPlayerfrom(Player playerfrom) {
        this.playerfrom = playerfrom;
    }

    public Player getPlayerto() {
        return playerto;
    }

    public void setPlayerto(Player playerto) {
        this.playerto = playerto;
    }

    public Transaction getPointfrom() {
        return pointfrom;
    }

    public void setPointfrom(Transaction pointfrom) {
        this.pointfrom = pointfrom;
    }

    public Transaction getPointto() {
        return pointto;
    }

    public void setPointto(Transaction pointto) {
        this.pointto = pointto;
    }

    public double getTotalamount() {
        return totalamount;
    }

    public void setTotalamount(double totalamount) {
        this.totalamount = totalamount;
    }

    public String getReferralCode() {
        return referralCode;
    }

    public void setReferralCode(String referralCode) {
        this.referralCode = referralCode;
    }

    public Remain getRemain() {
        return remain;
    }

    public void setRemain(Remain remain) {
        this.remain = remain;
    }

    public String getSupport() {
        return support;
    }

    public void setSupport(String support) {
        this.support = support;
    }

    public String getTelegramsupport() {
        return telegramsupport;
    }

    public void setTelegramsupport(String telegramsupport) {
        this.telegramsupport = telegramsupport;
    }

    public String getWhatsappsupport() {
        return whatsappsupport;
    }

    public void setWhatsappsupport(String whatsappsupport) {
        this.whatsappsupport = whatsappsupport;
    }

    public List<Member> getUpline() {
        return upline;
    }

    public void setUpline(List<Member> upline) {
        this.upline = upline;
    }

    public double getCharge() {
        return charge;
    }

    public void setCharge(double charge) {
        this.charge = charge;
    }

    public boolean isHas_game() {
        return has_game;
    }

    public void setHas_game(boolean has_game) {
        this.has_game = has_game;
    }

    public String getOtpcode() {
        return otpcode;
    }

    public void setOtpcode(String otpcode) {
        this.otpcode = otpcode;
    }
}
