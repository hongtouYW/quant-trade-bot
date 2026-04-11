package com.dj.user.util;

import com.dj.user.model.response.Yxi;
import com.google.gson.JsonDeserializationContext;
import com.google.gson.JsonDeserializer;
import com.google.gson.JsonElement;
import com.google.gson.JsonParseException;

import java.lang.reflect.Type;
import java.util.ArrayList;
import java.util.List;

public class YxiListDeserializer implements JsonDeserializer<List<Yxi>> {
    @Override
    public List<Yxi> deserialize(JsonElement json, Type typeOfT, JsonDeserializationContext context)
            throws JsonParseException {
        List<Yxi> list = new ArrayList<>();
        if (json.isJsonArray()) {
            for (JsonElement e : json.getAsJsonArray()) {
                list.add(context.deserialize(e, Yxi.class));
            }
        } else if (json.isJsonObject()) {
            list.add(context.deserialize(json, Yxi.class));
        }
        return list;
    }
}
