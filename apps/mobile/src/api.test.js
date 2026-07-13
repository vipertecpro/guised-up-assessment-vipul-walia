const feedResponse = {
  data: [
    {
      id: 12,
      user: { id: 2, name: 'Maya Demo' },
      text: 'A thoughtful post.',
      image_url: null,
      authenticity_score: 0.8,
      embedding_status: 'ready',
      created_at: '2026-07-13T10:00:00.000Z',
      updated_at: '2026-07-13T10:00:00.000Z',
      ranking: {
        score: 0.7,
        authenticity: 0.8,
        relationship_depth: 0.5,
        semantic_similarity: 0.6,
        time_decay: 0.9,
      },
    },
  ],
  meta: {
    current_page: 1,
    per_page: 20,
    total: 1,
    last_page: 1,
    has_more_pages: false,
    semantic_ranking_available: true,
  },
};

function response(status, body) {
  return {
    ok: status >= 200 && status < 300,
    status,
    text: jest.fn().mockResolvedValue(body === null ? '' : JSON.stringify(body)),
  };
}

describe('mobile API client', () => {
  beforeEach(() => {
    jest.resetModules();
    process.env.EXPO_PUBLIC_API_BASE_URL = 'http://127.0.0.1:8000/api/';
    process.env.EXPO_PUBLIC_API_TOKEN = 'local-test-token';
    global.fetch = jest.fn();
  });

  afterEach(() => {
    delete process.env.EXPO_PUBLIC_API_BASE_URL;
    delete process.env.EXPO_PUBLIC_API_TOKEN;
  });

  it('normalizes the base URL and sends the authenticated feed request', async () => {
    global.fetch.mockResolvedValue(response(200, feedResponse));
    const { fetchFeed } = require('./api');

    await expect(fetchFeed(1)).resolves.toEqual(feedResponse);
    expect(global.fetch).toHaveBeenCalledWith(
      'http://127.0.0.1:8000/api/feed?page=1',
      expect.objectContaining({
        method: 'GET',
        headers: expect.objectContaining({
          Accept: 'application/json',
          Authorization: 'Bearer local-test-token',
        }),
      }),
    );
  });

  it('sends the exact reaction payload and JSON content type', async () => {
    const interactionResponse = {
      data: {
        id: 4,
        user_id: 1,
        post_id: 12,
        type: 'reaction',
        created_at: '2026-07-13T12:00:00.000Z',
      },
    };
    global.fetch.mockResolvedValue(response(201, interactionResponse));
    const { createInteraction } = require('./api');

    await expect(createInteraction(12)).resolves.toEqual(interactionResponse);
    expect(global.fetch).toHaveBeenCalledWith(
      'http://127.0.0.1:8000/api/interactions',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({ post_id: 12, type: 'reaction' }),
        headers: expect.objectContaining({ 'Content-Type': 'application/json' }),
      }),
    );
  });

  it('turns 401 and empty success responses into safe errors', async () => {
    const { fetchFeed } = require('./api');
    global.fetch.mockResolvedValueOnce(response(401, { message: 'Unauthenticated.' }));

    let authenticationError;
    try {
      await fetchFeed(1);
    } catch (error) {
      authenticationError = error;
    }

    expect(authenticationError).toMatchObject({ status: 401 });
    expect(authenticationError.message).not.toContain('local-test-token');

    global.fetch.mockResolvedValue(response(200, null));
    await expect(fetchFeed(1)).rejects.toThrow('unexpected response');
  });
});
