package com.dj.manager.util;

import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.SystemLog;
import com.dj.manager.model.response.Token;
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

public class SystemLogListDeserializer implements JsonDeserializer<BaseResponse<List<SystemLog>>> {

    @Override
    public BaseResponse<List<SystemLog>> deserialize(JsonElement json, Type typeOfT, JsonDeserializationContext context)
            throws JsonParseException {

        JsonObject root = json.getAsJsonObject();
        List<SystemLog> list = new ArrayList<>();
        Gson gson = new Gson();

        JsonElement dataElement = root.get("data");

        if (dataElement != null && !dataElement.isJsonNull()) {
            if (dataElement.isJsonObject()) {
                JsonObject dataObj = dataElement.getAsJsonObject();
                for (Map.Entry<String, JsonElement> entry : dataObj.entrySet()) {
                    SystemLog systemLog = gson.fromJson(entry.getValue(), SystemLog.class);
                    list.add(systemLog);
                }
            } else if (dataElement.isJsonArray()) {
                JsonArray dataArray = dataElement.getAsJsonArray();
                for (JsonElement element : dataArray) {
                    SystemLog systemLog = gson.fromJson(element, SystemLog.class);
                    list.add(systemLog);
                }
            }
        }

        BaseResponse<List<SystemLog>> response = new BaseResponse<>();
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
        return response;
    }
}
