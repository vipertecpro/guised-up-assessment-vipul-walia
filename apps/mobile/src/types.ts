export interface UserSummary {
  id: number;
  name: string;
}

export interface RankingDetails {
  score: number;
  authenticity: number;
  relationship_depth: number;
  semantic_similarity: number;
  time_decay: number;
}

export interface Post {
  id: number;
  user: UserSummary;
  text: string;
  image_url: string | null;
  authenticity_score: number;
  embedding_status: string;
  created_at: string | null;
  updated_at: string | null;
  ranking?: RankingDetails;
  semantic_similarity?: number;
}

export interface FeedPaginationMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  has_more_pages: boolean;
  semantic_ranking_available: boolean;
}

export interface FeedResponse {
  data: Post[];
  meta: FeedPaginationMeta;
}

export interface SearchPost extends Post {
  semantic_similarity: number;
}

export interface SearchResponse {
  data: SearchPost[];
}

export type InteractionType = 'view' | 'reaction' | 'reply';

export interface InteractionRequest {
  post_id: number;
  type: InteractionType;
}

export interface Interaction {
  id: number;
  user_id: number;
  post_id: number;
  type: InteractionType;
  created_at: string | null;
}

export interface InteractionResponse {
  data: Interaction;
}

export interface LaravelApplicationError {
  message: string;
  code?: string;
}

export interface LaravelValidationError extends LaravelApplicationError {
  errors: Record<string, string[]>;
}
