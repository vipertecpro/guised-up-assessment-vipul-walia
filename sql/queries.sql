-- D1: Return the 10 users who performed the most view, reply, or reaction events
-- in the last seven days. interactions_type_created_at_index and
-- interactions_user_id_created_at_index support the scan, and users_pkey supports the join.
SELECT
    users.id AS user_id,
    users.name,
    users.email,
    COUNT(*) FILTER (WHERE interactions.type = 'view') AS view_count,
    COUNT(*) FILTER (WHERE interactions.type = 'reply') AS reply_count,
    COUNT(*) FILTER (WHERE interactions.type = 'reaction') AS reaction_count,
    COUNT(*) AS total_interactions
FROM interactions
INNER JOIN users
    ON users.id = interactions.user_id
WHERE interactions.created_at >= CURRENT_TIMESTAMP - INTERVAL '7 days'
  AND interactions.type IN ('view', 'reply', 'reaction')
GROUP BY users.id, users.name, users.email
ORDER BY total_interactions DESC, user_id ASC
LIMIT 10;

-- D2: Return recent posts from authors the input user has interacted with,
-- ranking authors by unweighted event frequency. Replace 1 with the desired user
-- ID. interactions_user_id_post_id_created_at_index, posts_pkey, and
-- posts_user_id_created_at_index support the actor, target, and recent-author joins.
WITH parameters AS (
    SELECT 1::BIGINT AS user_id
),
relationship_strength AS (
    SELECT
        target_posts.user_id AS author_id,
        COUNT(*) AS interaction_frequency
    FROM parameters
    INNER JOIN interactions
        ON interactions.user_id = parameters.user_id
    INNER JOIN posts AS target_posts
        ON target_posts.id = interactions.post_id
    GROUP BY target_posts.user_id
)
SELECT
    recent_posts.id AS post_id,
    authors.id AS author_id,
    authors.name AS author_name,
    recent_posts.text AS post_text,
    recent_posts.created_at AS post_created_at,
    relationship_strength.interaction_frequency
FROM relationship_strength
INNER JOIN users AS authors
    ON authors.id = relationship_strength.author_id
INNER JOIN posts AS recent_posts
    ON recent_posts.user_id = relationship_strength.author_id
   AND recent_posts.created_at >= CURRENT_TIMESTAMP - INTERVAL '30 days'
ORDER BY
    relationship_strength.interaction_frequency DESC,
    recent_posts.created_at DESC,
    recent_posts.id DESC;

-- D3: Return posts with more than 100 view events and no reaction events.
-- replies remain distinct and do not disqualify a post.
-- interactions_post_id_type_index and posts_pkey support the aggregation.
SELECT
    posts.id AS post_id,
    posts.user_id AS author_id,
    COUNT(interactions.id) FILTER (WHERE interactions.type = 'view') AS view_count,
    posts.created_at
FROM posts
LEFT JOIN interactions
    ON interactions.post_id = posts.id
GROUP BY posts.id, posts.user_id, posts.created_at
HAVING COUNT(interactions.id) FILTER (WHERE interactions.type = 'view') > 100
   AND COUNT(interactions.id) FILTER (WHERE interactions.type = 'reaction') = 0
ORDER BY view_count DESC, post_id ASC;

-- D4: Identify potential-spam users who created more than 20 posts in the last
-- 24 hours. This is a review signal, not a confirmed-spam classification.
-- posts_created_at_index, posts_user_id_created_at_index, and users_pkey support it.
SELECT
    users.id AS user_id,
    users.name,
    users.email,
    COUNT(posts.id) AS post_count
FROM users
INNER JOIN posts
    ON posts.user_id = users.id
WHERE posts.created_at >= CURRENT_TIMESTAMP - INTERVAL '24 hours'
GROUP BY users.id, users.name, users.email
HAVING COUNT(posts.id) > 20
ORDER BY post_count DESC, user_id ASC;
