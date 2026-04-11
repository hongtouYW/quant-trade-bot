package com.dj.user.enums;

public enum Language {
    CHINESE("zh", "简体中文"),
    MALAY("ms", "Malay"),
    ENGLISH("en", "English"),
    THAI("th", "Thai"),
    VIET("vi", "Vietnamese"),
    BURMESE("my", "Burmese");

    private final String value;
    private final String title;

    Language(String value, String title) {
        this.value = value;
        this.title = title;
    }

    public String getValue() {
        return value;
    }

    public String getTitle() {
        return title;
    }

    public static Language fromValue(String value) {
        for (Language type : Language.values()) {
            if (type.value.equalsIgnoreCase(value)) {
                return type;
            }
        }
        throw new IllegalArgumentException("Invalid Language value: " + value);
    }
}
