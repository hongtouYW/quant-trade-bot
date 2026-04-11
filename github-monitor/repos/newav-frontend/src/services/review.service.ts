import type { ApiResponse } from "@/types/api-response.ts";
import type {
  Review,
  ReviewListPayload,
  SubmitReviewPayload,
  SubmitReviewResponse,
  LikeReviewPayload,
  EditReviewPayload,
  EditReviewResponse,
  DeleteReviewPayload,
  DeleteReviewResponse,
} from "@/types/review.types.ts";
import axios from "@/lib/axios";

export const fetchReviewList = async (
  payload: ReviewListPayload,
  signal?: AbortSignal,
): Promise<ApiResponse<Review[]>> => {
  const response = await axios.post("/rating/list", payload, { signal });
  return response.data;
};

export const submitReview = async (
  payload: SubmitReviewPayload,
  signal?: AbortSignal,
): Promise<ApiResponse<SubmitReviewResponse>> => {
  const response = await axios.post("/rating/submit", payload, { signal });
  return response.data;
};

export const likeReview = async (
  payload: LikeReviewPayload,
  signal?: AbortSignal,
): Promise<ApiResponse<string>> => {
  const response = await axios.post("/rating/like", payload, { signal });
  return response.data;
};

export const editReview = async (
  payload: EditReviewPayload,
  signal?: AbortSignal,
): Promise<ApiResponse<EditReviewResponse>> => {
  const response = await axios.post("/rating/edit", payload, { signal });
  return response.data;
};

export const deleteReview = async (
  payload: DeleteReviewPayload,
  signal?: AbortSignal,
): Promise<ApiResponse<DeleteReviewResponse>> => {
  const response = await axios.post("/rating/delete", payload, { signal });
  return response.data;
};
