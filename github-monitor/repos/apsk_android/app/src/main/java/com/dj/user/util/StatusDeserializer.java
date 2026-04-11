package com.dj.user.util;

import com.google.gson.JsonDeserializationContext;
import com.google.gson.JsonDeserializer;
import com.google.gson.JsonElement;
import com.google.gson.JsonParseException;
import com.google.gson.JsonPrimitive;

import java.lang.reflect.Type;

public class StatusDeserializer implements JsonDeserializer<Integer> {
    @Override
    public Integer deserialize(JsonElement json, Type typeOfT, JsonDeserializationContext context)
            throws JsonParseException {
        try {
            if (json.isJsonPrimitive()) {
                JsonPrimitive prim = json.getAsJsonPrimitive();
                if (prim.isNumber()) {
                    return prim.getAsInt();
                }
                if (prim.isBoolean()) {
                    return prim.getAsBoolean() ? 1 : 0;
                }
                if (prim.isString()) {
                    try {
                        return Integer.parseInt(prim.getAsString());
                    } catch (NumberFormatException ignored) {
                    }
                }
            }
        } catch (Exception ignored) {
        }
        return 0; // fallback
    }
}
