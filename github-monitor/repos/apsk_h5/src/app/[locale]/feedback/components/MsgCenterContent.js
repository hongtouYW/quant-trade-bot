"use client";
import { useEffect, useState, useContext, useMemo } from "react";
import Image from "next/image";
import { IMAGES } from "@/constants/images";

import { toast } from "react-hot-toast"; // ✅ or your existing toast lib
import { UIContext } from "@/contexts/UIProvider";
import { extractError, getMemberInfo } from "@/utils/utility";
import {
  useGetMemberFeedbackListQuery,
  useSendFeedBackMutation,
} from "@/services/feedbackApi";
import { useRouter } from "next/navigation";
import { useMsgCenterStore } from "@/store/zustand/msgCenterStore";
import UISelect from "@/components/shared/DropdownSelect";
import SharedLoading from "@/components/shared/LoadingBar/ShareLoading";
import { useGetFeedbackTypeListQuery } from "@/services/commonApi";

export default function MsgCenterContent({ t }) {
  const { mode, setMode } = useMsgCenterStore();
  const [topic, setTopic] = useState("");
  const [content, setContent] = useState("");
  const [files, setFiles] = useState([]);
  const [info, setInfo] = useState(null);
  const [errors, setErrors] = useState({});
  const maxLen = 500;
  const router = useRouter();

  const [sendFeedback] = useSendFeedBackMutation();
  const { setLoading } = useContext(UIContext);
  // 2️⃣ Use query only after member info is ready
  const { data, isLoading, isFetching, refetch } =
    useGetMemberFeedbackListQuery(info?.member_id, {
      skip: !info?.member_id, // ⛔️ skip until ready
    });

  const { data: feedbackTypeRes, isLoading: feedbackTypeLoading } =
    useGetFeedbackTypeListQuery(
      { member_id: info?.member_id },
      {
        skip: !info?.member_id, // ⛔️ wait until member ready
      },
    );

  useEffect(() => {
    const member = getMemberInfo();
    setInfo(member);
  }, []);

  useEffect(() => {
    const member = getMemberInfo();
    setInfo(member);
  }, []);

  const onPickFiles = (e) => {
    const selected = Array.from(e.target.files || []);
    setFiles(selected);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    const newErrors = {};

    //newErrors.topic = t("msgCenter.validation.topicRequired");
    if (!content.trim())
      newErrors.content = t("msgCenter.validation.contentRequired");
    if (!info?.member_id)
      newErrors.member = t("msgCenter.validation.missingMember");

    if (!topic.trim())
      newErrors.member = t("msgCenter.validation.topicRequired");

    if (!files || files.length === 0)
      newErrors.files = t("msgCenter.validation.imageRequired");

    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors);
      if (newErrors.member) toast.error(newErrors.member);
      else toast.error(t("msgCenter.validation.fillAll") || "请填写所有必填项");
      return;
    }

    setErrors({});
    setLoading(true);

    try {
      const formData = new FormData();
      formData.append("member_id", info.member_id);
      formData.append("feedbacktype_id", topic);
      formData.append("feedback_desc", content);

      // 👇 append photos properly
      files.forEach((file) => {
        // If web File object
        if (file instanceof File) formData.append("photo", file);
        // If React Native file
        else if (file?.uri)
          formData.append("photo", {
            uri: file.uri,
            type: file.type || "image/jpeg",
            name: file.name || `photo_${Date.now()}.webp`,
          });
      });

      const result = await sendFeedback(formData).unwrap();

      if (result?.status) {
        toast.success(t("msgCenter.toast.success") || "反馈已提交！");
        setTopic("");
        setContent("");
        setFiles([]);
        refetch();
      } else {
        toast.error(result?.message || t("msgCenter.toast.error"));
      }
    } catch (err) {
      const result = extractError(err);
      if (result.type === "validation") {
        toast.error(Object.values(result.fieldErrors).join(", "));
      } else {
        toast.error(JSON.stringify(err));
      }
    } finally {
      setLoading(false);
    }
  };

  const topicOptions = useMemo(() => {
    if (!feedbackTypeRes?.data) return [];

    return feedbackTypeRes.data.map((item) => ({
      value: String(item.feedbacktype_id),
      label: item.title,
    }));
  }, [feedbackTypeRes]);

  return (
    <>
      {/* 🔹 Mode Switch */}
      <div className="mt-4 flex items-center gap-3">
        {["create", "mine"].map((m) => {
          const active = mode === m;
          return (
            <button
              key={m}
              onClick={() => setMode(m)}
              className={`flex-1 h-10 rounded-full text-sm font-medium ${
                active
                  ? "text-[#00143D]"
                  : "text-[#F8AF07] border border-[#F8AF07]"
              }`}
              style={
                active
                  ? {
                      background:
                        "linear-gradient(180deg, #F8AF07 0%, #FFFC86 100%)",
                    }
                  : undefined
              }
            >
              {t(`msgCenter.actions.${m}`)}
            </button>
          );
        })}
      </div>

      {/* 🔹 Create Mode */}
      {mode === "create" && (
        <form onSubmit={handleSubmit} className="mt-4 space-y-4">
          {/* Topic */}
          <div>
            <div className="text-sm text-[#9BB2FF]  px-2 ">
              {t("msgCenter.form.topicPlaceholder")}
            </div>

            <div
              className={`flex items-center justify-between px-2 py-3 rounded-lg ${
                errors.topic ? "border border-red-500" : ""
              }`}
            >
              <UISelect
                value={topic}
                placeholder={t("msgCenter.form.topicPlaceholder")}
                onChange={(value) => setTopic(value)}
                options={topicOptions}
                disabled={feedbackTypeLoading}
              />

              {/* <UISelect
                value={topic}
                placeholder={t("msgCenter.form.topicPlaceholder")}
                onChange={(value) => setTopic(value)}
                options={[
                  // { value: "", label: t("msgCenter.form.topicPlaceholder") },
                  { value: "bug", label: t("msgCenter.form.topic_gameSpeed") },
                  { value: "ui", label: t("msgCenter.form.topic_uiSmooth") },
                  {
                    value: "suggest",
                    label: t("msgCenter.form.topic_difficulty"),
                  },
                  { value: "guide", label: t("msgCenter.form.topic_guide") },
                  { value: "other", label: t("msgCenter.form.topic_other") },
                ]}
              /> */}
            </div>
          </div>

          {/* Content */}
          <div
            className={`rounded-xl border ${
              errors.content ? "border-red-500" : "border-white/20"
            } bg-[#0F214F] p-3`}
          >
            <div className="text-sm text-[#9BB2FF] mb-2">
              {t("msgCenter.form.content")}
            </div>
            <textarea
              value={content}
              onChange={(e) => setContent(e.target.value.slice(0, maxLen))}
              rows={6}
              placeholder={t("msgCenter.form.placeholder")}
              className="w-full resize-none bg-transparent outline-none text-sm text-white/90 placeholder:text-white/50"
            />
            <div className="text-right text-xs text-[#F8AF07]/80">
              {content.length}/{maxLen}
            </div>
          </div>

          {/* Upload Area */}
          <div>
            <div className="text-center text-sm mb-2">
              {t("msgCenter.form.mediaTitle")}
            </div>
            <label
              htmlFor="filePick"
              className={`block rounded-2xl bg-[#13255B] px-4 py-6 text-center active:opacity-90 cursor-pointer ${
                errors.files ? "border border-red-500" : ""
              }`}
            >
              <div className="flex flex-col items-center p-5">
                <Image
                  src={IMAGES.iconPhotoUpload}
                  alt="upload"
                  width={64}
                  height={64}
                  className="mb-3"
                />
                <div className="text-xs text-white/70 leading-relaxed">
                  {t("msgCenter.form.mediaNote")}
                </div>
              </div>
            </label>
            <input
              id="filePick"
              type="file"
              className="hidden"
              multiple
              accept="image/*"
              onChange={onPickFiles}
            />

            {files.length > 0 && (
              <div className="mt-3 grid grid-cols-4 gap-2">
                {files.map((f, i) => (
                  <div
                    key={i}
                    className="h-16 rounded-md bg-[#0F214F] flex items-center justify-center text-[10px] text-white/70 px-1 text-center"
                    title={f.name}
                  >
                    {f.type.startsWith("image/") ? "IMG" : "VID"}&nbsp;
                    {Math.round(f.size / 1024)}KB
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Submit */}
          <button
            type="submit"
            className="mt-4 w-full h-12 rounded-full font-semibold text-[#00143D]"
            style={{
              background: "linear-gradient(180deg, #F8AF07 0%, #FFFC86 100%)",
            }}
          >
            {t("msgCenter.actions.submit")}
          </button>
        </form>
      )}

      {/* 🔹 My Feedback Placeholder */}
      {mode === "mine" && (
        <>
          {isLoading ? (
            <SharedLoading />
          ) : data?.data?.length ? (
            <div className="divide-y divide-white/10 mt-2">
              {data.data.map((item) => (
                <div
                  key={item.feedback_id}
                  className="flex items-center justify-between py-4 active:opacity-80"
                  onClick={() => router.push(`/feedback/${item.feedback_id}`)}
                >
                  {/* left icon + title */}
                  <div className="flex items-center space-x-2">
                    <Image
                      src={IMAGES.penLine}
                      alt="pen"
                      width={18}
                      height={18}
                      className="object-contain"
                    />
                    <span className="text-sm">{item.title}</span>
                  </div>

                  {/* right arrow */}
                  <Image
                    src={IMAGES.blueRight}
                    alt="arrow"
                    width={25}
                    height={25}
                    className="object-contain opacity-70"
                  />
                </div>
              ))}
            </div>
          ) : (
            <div className="mt-6 text-center text-white/70 text-sm">
              {t("msgCenter.mine.empty")}
            </div>
          )}
        </>
      )}
    </>
  );
}
