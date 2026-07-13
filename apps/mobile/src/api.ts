import type {
  FeedPaginationMeta,
  FeedResponse,
  Interaction,
  InteractionRequest,
  InteractionResponse,
  InteractionType,
  LaravelValidationError,
  Post,
  RankingDetails,
  SearchPost,
  SearchResponse,
  UserSummary,
} from './types';

type ApiErrorKind = 'configuration' | 'http' | 'network' | 'response';

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly kind: ApiErrorKind,
    public readonly status?: number,
    public readonly validationErrors?: LaravelValidationError['errors'],
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

const rawApiBaseUrl = process.env.EXPO_PUBLIC_API_BASE_URL?.trim() ?? '';
const apiToken = process.env.EXPO_PUBLIC_API_TOKEN?.trim() ?? '';
const apiBaseUrl = rawApiBaseUrl.replace(/\/+$/, '');

/** Return a user-facing message when required Expo environment values are absent. */
export function getConfigurationError(): string | null {
  if (!apiBaseUrl && !apiToken) {
    return 'Set EXPO_PUBLIC_API_BASE_URL and EXPO_PUBLIC_API_TOKEN in apps/mobile/.env.';
  }

  if (!apiBaseUrl) {
    return 'Set EXPO_PUBLIC_API_BASE_URL in apps/mobile/.env.';
  }

  if (!apiToken) {
    return 'Set EXPO_PUBLIC_API_TOKEN to a local Sanctum token in apps/mobile/.env.';
  }

  return null;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function isNumber(value: unknown): value is number {
  return typeof value === 'number' && Number.isFinite(value);
}

function isNullableString(value: unknown): value is string | null {
  return typeof value === 'string' || value === null;
}

function isUserSummary(value: unknown): value is UserSummary {
  return isRecord(value) && isNumber(value.id) && typeof value.name === 'string';
}

function isRankingDetails(value: unknown): value is RankingDetails {
  return (
    isRecord(value) &&
    isNumber(value.score) &&
    isNumber(value.authenticity) &&
    isNumber(value.relationship_depth) &&
    isNumber(value.semantic_similarity) &&
    isNumber(value.time_decay)
  );
}

function isPost(value: unknown): value is Post {
  if (
    !isRecord(value) ||
    !isNumber(value.id) ||
    !isUserSummary(value.user) ||
    typeof value.text !== 'string' ||
    !isNullableString(value.image_url) ||
    !isNumber(value.authenticity_score) ||
    typeof value.embedding_status !== 'string' ||
    !isNullableString(value.created_at) ||
    !isNullableString(value.updated_at)
  ) {
    return false;
  }

  return (
    (value.ranking === undefined || isRankingDetails(value.ranking)) &&
    (value.semantic_similarity === undefined || isNumber(value.semantic_similarity))
  );
}

function isFeedMeta(value: unknown): value is FeedPaginationMeta {
  return (
    isRecord(value) &&
    isNumber(value.current_page) &&
    isNumber(value.per_page) &&
    isNumber(value.total) &&
    isNumber(value.last_page) &&
    typeof value.has_more_pages === 'boolean' &&
    typeof value.semantic_ranking_available === 'boolean'
  );
}

function isFeedResponse(value: unknown): value is FeedResponse {
  return (
    isRecord(value) &&
    Array.isArray(value.data) &&
    value.data.every(isPost) &&
    isFeedMeta(value.meta)
  );
}

function isSearchPost(value: unknown): value is SearchPost {
  return isPost(value) && isNumber(value.semantic_similarity);
}

function isSearchResponse(value: unknown): value is SearchResponse {
  return isRecord(value) && Array.isArray(value.data) && value.data.every(isSearchPost);
}

function isInteractionType(value: unknown): value is InteractionType {
  return value === 'view' || value === 'reaction' || value === 'reply';
}

function isInteraction(value: unknown): value is Interaction {
  return (
    isRecord(value) &&
    isNumber(value.id) &&
    isNumber(value.user_id) &&
    isNumber(value.post_id) &&
    isInteractionType(value.type) &&
    isNullableString(value.created_at)
  );
}

function isInteractionResponse(value: unknown): value is InteractionResponse {
  return isRecord(value) && isInteraction(value.data);
}

function parseJson(text: string): unknown {
  if (!text.trim()) {
    return null;
  }

  try {
    return JSON.parse(text) as unknown;
  } catch {
    return null;
  }
}

function getValidationErrors(value: unknown): Record<string, string[]> | undefined {
  if (!isRecord(value) || !isRecord(value.errors)) {
    return undefined;
  }

  const entries = Object.entries(value.errors).filter(
    (entry): entry is [string, string[]] =>
      Array.isArray(entry[1]) && entry[1].every((message) => typeof message === 'string'),
  );

  return entries.length > 0 ? Object.fromEntries(entries) : undefined;
}

function getErrorMessage(value: unknown, fallback: string): string {
  return isRecord(value) && typeof value.message === 'string' && value.message.trim()
    ? value.message
    : fallback;
}

async function requestJson<T>(
  path: string,
  init: RequestInit,
  isExpectedResponse: (value: unknown) => value is T,
): Promise<T> {
  const configurationError = getConfigurationError();

  if (configurationError) {
    throw new ApiError(configurationError, 'configuration');
  }

  let response: Response;

  try {
    response = await fetch(`${apiBaseUrl}${path}`, {
      ...init,
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${apiToken}`,
        ...init.headers,
      },
    });
  } catch (error: unknown) {
    if (error instanceof Error && error.name === 'AbortError') {
      throw error;
    }

    throw new ApiError('Unable to reach the API. Check the server and device URL.', 'network');
  }

  const body = parseJson(await response.text());

  if (!response.ok) {
    if (response.status === 401) {
      throw new ApiError(
        'Your local Sanctum token is invalid or expired. Generate a new token and update apps/mobile/.env.',
        'http',
        401,
      );
    }

    throw new ApiError(
      getErrorMessage(body, `The API request failed with status ${response.status}.`),
      'http',
      response.status,
      getValidationErrors(body),
    );
  }

  if (!isExpectedResponse(body)) {
    throw new ApiError('The API returned an unexpected response.', 'response', response.status);
  }

  return body;
}

/** Retrieve one page from Laravel's personalized feed. */
export function fetchFeed(page: number, signal?: AbortSignal): Promise<FeedResponse> {
  return requestJson(`/feed?page=${encodeURIComponent(String(page))}`, { method: 'GET', signal }, isFeedResponse);
}

/** Search indexed posts while allowing the caller to cancel stale queries. */
export function searchPosts(query: string, signal: AbortSignal): Promise<SearchResponse> {
  return requestJson(
    `/search?q=${encodeURIComponent(query)}`,
    { method: 'GET', signal },
    isSearchResponse,
  );
}

/** Record one reaction interaction for the authenticated local user. */
export function createInteraction(postId: number, signal?: AbortSignal): Promise<InteractionResponse> {
  const body: InteractionRequest = { post_id: postId, type: 'reaction' };

  return requestJson(
    '/interactions',
    {
      method: 'POST',
      signal,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    },
    isInteractionResponse,
  );
}
