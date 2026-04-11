package com.dj.user.util;

import com.dj.user.model.response.Transaction;
import com.google.gson.JsonDeserializationContext;
import com.google.gson.JsonDeserializer;
import com.google.gson.JsonElement;
import com.google.gson.JsonParseException;

import java.lang.reflect.Type;
import java.util.ArrayList;
import java.util.List;

public class PointListDeserializer implements JsonDeserializer<List<Transaction>> {
    @Override
    public List<Transaction> deserialize(JsonElement json, Type typeOfT, JsonDeserializationContext context)
            throws JsonParseException {
        List<Transaction> list = new ArrayList<>();
        if (json.isJsonArray()) {
            for (JsonElement e : json.getAsJsonArray()) {
                list.add(context.deserialize(e, Transaction.class));
            }
        } else if (json.isJsonObject()) {
            list.add(context.deserialize(json, Transaction.class));
        }
        return list;
    }
}
