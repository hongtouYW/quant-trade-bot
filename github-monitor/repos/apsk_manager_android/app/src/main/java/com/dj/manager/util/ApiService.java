package com.dj.manager.util;

import com.dj.manager.model.request.RequestBlockedData;
import com.dj.manager.model.request.RequestChangePassword;
import com.dj.manager.model.request.RequestData;
import com.dj.manager.model.request.RequestDataArea;
import com.dj.manager.model.request.RequestDataState;
import com.dj.manager.model.request.RequestDeleteData;
import com.dj.manager.model.request.RequestLogin;
import com.dj.manager.model.request.RequestMemberChangePassword;
import com.dj.manager.model.request.RequestMemberProfile;
import com.dj.manager.model.request.RequestMemberReason;
import com.dj.manager.model.request.RequestMemberYxiPlayer;
import com.dj.manager.model.request.RequestNotificationRead;
import com.dj.manager.model.request.RequestPaymentType;
import com.dj.manager.model.request.RequestPlayerDelete;
import com.dj.manager.model.request.RequestPlayerPassword;
import com.dj.manager.model.request.RequestPlayerProfile;
import com.dj.manager.model.request.RequestPointFilter;
import com.dj.manager.model.request.RequestProfile;
import com.dj.manager.model.request.RequestRefresh;
import com.dj.manager.model.request.RequestSearch;
import com.dj.manager.model.request.RequestShopNew;
import com.dj.manager.model.request.RequestShopProfile;
import com.dj.manager.model.request.RequestShopReason;
import com.dj.manager.model.request.RequestStatusData;
import com.dj.manager.model.request.RequestSystemLog;
import com.dj.manager.model.request.RequestTransaction;
import com.dj.manager.model.request.RequestUpdateFirebase;
import com.dj.manager.model.request.RequestUpdateMemberPhone;
import com.dj.manager.model.request.RequestUpdateShopAmount;
import com.dj.manager.model.request.RequestUpdateShopPassword;
import com.dj.manager.model.request.RequestUpdateShopPermission;
import com.dj.manager.model.request.RequestVersion;
import com.dj.manager.model.request.RequestYxiLog;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Country;
import com.dj.manager.model.response.Manager;
import com.dj.manager.model.response.Member;
import com.dj.manager.model.response.Notification;
import com.dj.manager.model.response.PaymentType;
import com.dj.manager.model.response.Player;
import com.dj.manager.model.response.Point;
import com.dj.manager.model.response.Shop;
import com.dj.manager.model.response.ShopPin;
import com.dj.manager.model.response.SystemLog;
import com.dj.manager.model.response.SystemLogFilter;
import com.dj.manager.model.response.Transaction;
import com.dj.manager.model.response.Version;
import com.dj.manager.model.response.YxiProvider;

import java.util.List;

import retrofit2.Call;
import retrofit2.http.Body;
import retrofit2.http.Headers;
import retrofit2.http.POST;

public interface ApiService {

    @POST("manager/version")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Version>> checkVersion(@Body RequestVersion request);

    @POST("manager/country/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Country>>> getCountryList(@Body RequestData request);

    @POST("manager/country/select")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> getStateList(@Body RequestDataState request);

    @POST("manager/state/select")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> getAreaList(@Body RequestDataArea request);

    @POST("manager/login")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Manager>> login(@Body RequestLogin request);

    @POST("manager/logout")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> logout();

    @POST("manager/refresh")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> refreshToken(@Body RequestRefresh request);

    @POST("manager/firebase/device")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Manager>> updateFirebaseToken(@Body RequestUpdateFirebase request);

    @POST("manager/dashboard")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Manager>> getManagerStat(@Body RequestProfile request);

    @POST("manager/changepassword")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Manager>> changePassword(@Body RequestChangePassword request);

    @POST("manager/shop/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Shop>>> getShopList(@Body RequestProfile request);

    @POST("manager/shop/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Shop>>> getShopList(@Body RequestStatusData request);

    @POST("manager/shop/new")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Shop>> createNewShop(@Body RequestShopNew request);

    @POST("manager/shop/changepassword")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Shop>> updateShopPassword(@Body RequestUpdateShopPassword request);

    @POST("manager/shop/pin")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<ShopPin>> pinShop(@Body RequestShopProfile request);

    @POST("manager/shop/unpin")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> unpinShop(@Body RequestShopProfile request);

    @POST("manager/shop/open")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Shop>> openShop(@Body RequestShopProfile request);

    @POST("manager/shop/close")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Shop>> closeShop(@Body RequestShopProfile request);

    @POST("manager/shop/close/reason")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Shop>> submitCloseShopReason(@Body RequestShopReason request);

    @POST("manager/shop/permission")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Shop>> updateShopPermission(@Body RequestUpdateShopPermission request);

    @POST("manager/shop/amount")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Shop>> updateShopAmount(@Body RequestUpdateShopAmount request);

    @POST("manager/shop/clear")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> clearShopAmount(@Body RequestShopProfile request);

    @POST("manager/shop/view")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Shop>> getShopProfile(@Body RequestShopProfile request);

    @POST("manager/shop/password")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> getShopPassword(@Body RequestShopProfile request);

    @POST("manager/shop/transaction/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Transaction>>> getShopTransactionList(@Body RequestTransaction request);

    @POST("manager/member/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Member>>> getMemberList(@Body RequestProfile request);

    @POST("manager/member/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Member>>> getMemberList(@Body RequestStatusData request);

    @POST("manager/member/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Member>>> getMemberList(@Body RequestBlockedData request);

    @POST("manager/member/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Member>>> getMemberList(@Body RequestDeleteData request);

    @POST("manager/member/view")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Member>> getMemberProfile(@Body RequestMemberProfile request);

    @POST("manager/member/password")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> getMemberPassword(@Body RequestMemberProfile request);

    @POST("manager/member/edit/phone")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Member>> editMemberPhone(@Body RequestUpdateMemberPhone request);

    @POST("manager/member/changepassword")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Member>> changeMemberPassword(@Body RequestMemberChangePassword request);

    @POST("manager/member/block")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Member>> blockMember(@Body RequestMemberProfile request);

    @POST("manager/member/unblock")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Member>> unblockMember(@Body RequestMemberProfile request);

    @POST("manager/member/block/reason")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Member>> submitBlockMemberReason(@Body RequestMemberReason request);

    @POST("manager/member/delete")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Member>> deleteMember(@Body RequestMemberProfile request);

    @POST("manager/member/delete/reason")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Member>> submitDeleteMemberReason(@Body RequestMemberReason request);

    @POST("manager/game/provider/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<YxiProvider>>> getYxiProviderList(@Body RequestProfile request);

    @POST("manager/player/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Player>>> getPlayerList(@Body RequestMemberYxiPlayer request);

    @POST("manager/player/view")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Player>> getPlayerProfile(@Body RequestPlayerProfile request);

    @POST("manager/player/add")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> addPlayer(@Body RequestMemberYxiPlayer request);

    @POST("manager/player/password")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> getPlayerPassword(@Body RequestPlayerPassword request);

    @POST("manager/player/password/reset")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Player>> resetPlayerPassword(@Body RequestPlayerProfile request);

    @POST("manager/player/delete")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> deletePlayer(@Body RequestPlayerDelete request);

    @POST("manager/notification/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Notification>>> getNotificationList(@Body RequestProfile request);

    @POST("manager/notification/read")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Notification>> markNotificationRead(@Body RequestNotificationRead request);

    @POST("manager/point/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Point>>> getPointList(@Body RequestPointFilter request);

    @POST("manager/log/search/filter")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<SystemLogFilter>> getSystemLogFilterList(@Body RequestSystemLog request);

    @POST("manager/log/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<SystemLog>>> getSystemLogList(@Body RequestSystemLog request);

    @POST("manager/player/log/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Transaction>>> getYxiLogList(@Body RequestYxiLog request);

    @POST("manager/search")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> searchMemberShop(@Body RequestSearch request);

    @POST("manager/payment/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<PaymentType>>> getPaymentTypeList(@Body RequestPaymentType request);
}
