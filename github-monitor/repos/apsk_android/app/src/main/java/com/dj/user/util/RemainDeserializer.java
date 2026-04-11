package com.dj.user.util;

import com.dj.user.model.response.Remain;
import com.google.gson.JsonDeserializationContext;
import com.google.gson.JsonDeserializer;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.google.gson.JsonParseException;

import java.lang.reflect.Type;

public class RemainDeserializer implements JsonDeserializer<Remain> {
    @Override
    public Remain deserialize(JsonElement json, Type typeOfT, JsonDeserializationContext context) throws JsonParseException {
        Remain remain = new Remain();
        if (json == null || json.isJsonNull()) {
            return remain;
        }
        if (json.isJsonObject()) {
            JsonObject obj = json.getAsJsonObject();
            remain.setDays(obj.has("days") ? obj.get("days").getAsInt() : 0);
            remain.setHours(obj.has("hours") ? obj.get("hours").getAsInt() : 0);
            remain.setMinutes(obj.has("minutes") ? obj.get("minutes").getAsInt() : 0);
        } else if (json.isJsonPrimitive()) {
            // If the API returns a string instead of an object
            remain.setGeneral(json.getAsString());
        }
        return remain;
    }
}
