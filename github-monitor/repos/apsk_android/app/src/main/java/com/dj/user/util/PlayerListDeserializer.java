package com.dj.user.util;

import com.dj.user.model.response.Player;
import com.google.gson.JsonDeserializationContext;
import com.google.gson.JsonDeserializer;
import com.google.gson.JsonElement;
import com.google.gson.JsonParseException;

import java.lang.reflect.Type;
import java.util.ArrayList;
import java.util.List;

public class PlayerListDeserializer implements JsonDeserializer<List<Player>> {
    @Override
    public List<Player> deserialize(JsonElement json, Type typeOfT, JsonDeserializationContext context)
            throws JsonParseException {
        List<Player> list = new ArrayList<>();
        if (json.isJsonArray()) {
            for (JsonElement e : json.getAsJsonArray()) {
                list.add(context.deserialize(e, Player.class));
            }
        } else if (json.isJsonObject()) {
            list.add(context.deserialize(json, Player.class));
        }
        return list;
    }
}
