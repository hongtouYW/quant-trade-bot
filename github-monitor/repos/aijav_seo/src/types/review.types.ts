export interface Review {
  id: number;
  video_id: number;
  rating: number;
  review: string;
  created_at: string;
  updated_at: string;
  status: number;
  parent_id: number | null;
  user: ReviewUser;
  like_count: number;
  replies: Review[];
}

export interface ReviewUser {
  id: number;
  username: string;
}

export interface ReviewListPayload {
  vid: number;
}

export interface SubmitReviewPayload {
  token?: string;
  vid: number;
  rating: number;
  review: string;
  parent_id?: number;
}

export interface LikeReviewPayload {
  review_id: number;
}

export interface EditReviewPayload {
  review_id: number;
  rating: number;
  review: string;
}

export interface EditReviewResponse {
  editReview: boolean;
  msg: string;
}

export interface DeleteReviewPayload {
  review_id: number;
}

export interface DeleteReviewResponse {
  deleteReview: boolean;
  msg: string;
}

export type SubmitReviewResponse = string;
