package com.dj.shop.util;

import com.dj.shop.model.response.BaseResponse;
import com.dj.shop.model.response.Pagination;
import com.dj.shop.model.response.Token;
import com.dj.shop.model.response.Transaction;
import com.google.gson.Gson;
import com.google.gson.JsonArray;
import com.google.gson.JsonDeserializationContext;
import com.google.gson.JsonDeserializer;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.google.gson.JsonParseException;

import java.lang.reflect.Type;
import java.util.ArrayList;
import java.util.List;
import java.util.Map;

public class TransactionListDeserializer implements JsonDeserializer<BaseResponse<List<Transaction>>> {

    @Override
    public BaseResponse<List<Transaction>> deserialize(JsonElement json, Type typeOfT, JsonDeserializationContext context)
            throws JsonParseException {

        JsonObject root = json.getAsJsonObject();
        List<Transaction> list = new ArrayList<>();
        Gson gson = new Gson();

        JsonElement dataElement = root.get("data");

        if (dataElement != null && !dataElement.isJsonNull()) {
            if (dataElement.isJsonObject()) {
                JsonObject dataObj = dataElement.getAsJsonObject();
                for (Map.Entry<String, JsonElement> entry : dataObj.entrySet()) {
                    Transaction transaction = gson.fromJson(entry.getValue(), Transaction.class);
                    list.add(transaction);
                }
            } else if (dataElement.isJsonArray()) {
                JsonArray dataArray = dataElement.getAsJsonArray();
                for (JsonElement element : dataArray) {
                    Transaction transaction = gson.fromJson(element, Transaction.class);
                    list.add(transaction);
                }
            }
        }

        BaseResponse<List<Transaction>> response = new BaseResponse<>();
        response.setData(list);
        response.setStatus(root.get("status").getAsBoolean());
        response.setMessage(root.get("message").getAsString());
        response.setEncode_sign(root.get("encode_sign").getAsString());

        if (root.has("error")) {
            response.setError(root.get("error").getAsString());
        }
        if (root.has("token") && root.get("token").isJsonObject()) {
            Token token = gson.fromJson(root.get("token"), Token.class);
            response.setToken(token);
        }
        if (root.has("pagination") && root.get("pagination").isJsonObject()) {
            Pagination pagination = gson.fromJson(root.get("pagination"), Pagination.class);
            response.setPagination(pagination);
        }
        return response;
    }
}
