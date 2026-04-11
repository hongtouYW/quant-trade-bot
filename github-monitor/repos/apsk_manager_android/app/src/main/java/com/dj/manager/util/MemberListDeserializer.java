package com.dj.manager.util;

import com.dj.manager.model.response.Member;
import com.google.gson.JsonDeserializationContext;
import com.google.gson.JsonDeserializer;
import com.google.gson.JsonElement;
import com.google.gson.JsonParseException;

import java.lang.reflect.Type;
import java.util.ArrayList;
import java.util.List;

public class MemberListDeserializer implements JsonDeserializer<List<Member>> {
    @Override
    public List<Member> deserialize(JsonElement json, Type typeOfT, JsonDeserializationContext context)
            throws JsonParseException {
        List<Member> list = new ArrayList<>();
        if (json.isJsonArray()) {
            for (JsonElement e : json.getAsJsonArray()) {
                list.add(context.deserialize(e, Member.class));
            }
        } else if (json.isJsonObject()) {
            list.add(context.deserialize(json, Member.class));
        }
        return list;
    }
}
