import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';

import { ApiError, createInteraction, fetchFeed, getConfigurationError, searchPosts } from './api';
import { PostCard } from './components/PostCard';
import type { FeedPaginationMeta, Post } from './types';

type FirstPageMode = 'initial' | 'retry' | 'refresh';

function messageFor(error: unknown): string {
  return error instanceof ApiError ? error.message : 'Something went wrong. Please try again.';
}

function appendUniquePosts(current: Post[], incoming: Post[]): Post[] {
  const knownIds = new Set(current.map((post) => post.id));
  const uniqueIncoming = incoming.filter((post) => {
    if (knownIds.has(post.id)) {
      return false;
    }

    knownIds.add(post.id);
    return true;
  });

  return [...current, ...uniqueIncoming];
}

interface StateMessageProps {
  title: string;
  message: string;
  actionLabel?: string;
  onAction?: () => void;
  loading?: boolean;
}

function StateMessage({ title, message, actionLabel, onAction, loading = false }: StateMessageProps) {
  return (
    <View style={styles.stateContainer}>
      {loading ? <ActivityIndicator color="#2E5BFF" size="large" /> : null}
      <Text style={styles.stateTitle}>{title}</Text>
      <Text style={styles.stateMessage}>{message}</Text>
      {actionLabel && onAction ? (
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={actionLabel}
          onPress={onAction}
          style={({ pressed }) => [styles.primaryButton, pressed && styles.primaryButtonPressed]}
        >
          <Text style={styles.primaryButtonText}>{actionLabel}</Text>
        </Pressable>
      ) : null}
    </View>
  );
}

export function FeedScreen() {
  const configurationError = useMemo(() => getConfigurationError(), []);
  const [posts, setPosts] = useState<Post[]>([]);
  const [feedMeta, setFeedMeta] = useState<FeedPaginationMeta | null>(null);
  const [initialLoading, setInitialLoading] = useState(configurationError === null);
  const [initialError, setInitialError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);
  const [refreshError, setRefreshError] = useState<string | null>(null);
  const [paginationLoading, setPaginationLoading] = useState(false);
  const [paginationError, setPaginationError] = useState<string | null>(null);
  const [searchInput, setSearchInput] = useState('');
  const [searchResults, setSearchResults] = useState<Post[]>([]);
  const [searchLoading, setSearchLoading] = useState(false);
  const [searchError, setSearchError] = useState<string | null>(null);
  const [searchRetryKey, setSearchRetryKey] = useState(0);
  const [reactingIds, setReactingIds] = useState<Set<number>>(() => new Set());
  const [reactedIds, setReactedIds] = useState<Set<number>>(() => new Set());
  const [reactionErrors, setReactionErrors] = useState<Map<number, string>>(() => new Map());

  const mountedRef = useRef(true);
  const didRequestInitialRef = useRef(false);
  const firstPageControllerRef = useRef<AbortController | null>(null);
  const paginationControllerRef = useRef<AbortController | null>(null);
  const searchControllerRef = useRef<AbortController | null>(null);
  const searchSequenceRef = useRef(0);
  const loadedPagesRef = useRef<Set<number>>(new Set());
  const reactingIdsRef = useRef<Set<number>>(new Set());
  const reactedIdsRef = useRef<Set<number>>(new Set());
  const reactionControllersRef = useRef<Map<number, AbortController>>(new Map());

  const trimmedQuery = searchInput.trim();
  const isSearchMode = trimmedQuery.length > 0;

  const loadFirstPage = useCallback(async (mode: FirstPageMode) => {
    if (configurationError || firstPageControllerRef.current) {
      return;
    }

    const controller = new AbortController();
    firstPageControllerRef.current = controller;

    if (mode === 'refresh') {
      paginationControllerRef.current?.abort();
      paginationControllerRef.current = null;
      setPaginationLoading(false);
      setRefreshing(true);
      setRefreshError(null);
      setPaginationError(null);
    } else {
      setInitialLoading(true);
      setInitialError(null);
    }

    try {
      const response = await fetchFeed(1, controller.signal);

      if (!mountedRef.current || controller.signal.aborted) {
        return;
      }

      setPosts(response.data);
      setFeedMeta(response.meta);
      setRefreshError(null);
      setPaginationError(null);
      loadedPagesRef.current = new Set([1]);
    } catch (error: unknown) {
      if (!mountedRef.current || controller.signal.aborted) {
        return;
      }

      if (mode === 'refresh') {
        setRefreshError(messageFor(error));
      } else {
        setInitialError(messageFor(error));
      }
    } finally {
      if (firstPageControllerRef.current === controller) {
        firstPageControllerRef.current = null;
      }

      if (mountedRef.current) {
        mode === 'refresh' ? setRefreshing(false) : setInitialLoading(false);
      }
    }
  }, [configurationError]);

  useEffect(() => {
    mountedRef.current = true;

    if (!didRequestInitialRef.current && !configurationError) {
      didRequestInitialRef.current = true;
      void loadFirstPage('initial');
    }

    return () => {
      mountedRef.current = false;
      firstPageControllerRef.current?.abort();
      paginationControllerRef.current?.abort();
      searchControllerRef.current?.abort();
      reactionControllersRef.current.forEach((controller) => controller.abort());
      reactionControllersRef.current.clear();
    };
  }, [configurationError, loadFirstPage]);

  useEffect(() => {
    searchControllerRef.current?.abort();
    searchSequenceRef.current += 1;
    const sequence = searchSequenceRef.current;

    if (!isSearchMode || configurationError) {
      setSearchLoading(false);
      setSearchError(null);
      setSearchResults([]);
      return;
    }

    setSearchLoading(true);
    setSearchError(null);
    setSearchResults([]);

    const timeout = setTimeout(() => {
      const controller = new AbortController();
      searchControllerRef.current = controller;

      void searchPosts(trimmedQuery, controller.signal)
        .then((response) => {
          if (mountedRef.current && sequence === searchSequenceRef.current && !controller.signal.aborted) {
            setSearchResults(response.data);
          }
        })
        .catch((error: unknown) => {
          if (mountedRef.current && sequence === searchSequenceRef.current && !controller.signal.aborted) {
            setSearchError(messageFor(error));
            setSearchResults([]);
          }
        })
        .finally(() => {
          if (searchControllerRef.current === controller) {
            searchControllerRef.current = null;
          }

          if (mountedRef.current && sequence === searchSequenceRef.current && !controller.signal.aborted) {
            setSearchLoading(false);
          }
        });
    }, 350);

    return () => {
      clearTimeout(timeout);
      searchControllerRef.current?.abort();
    };
  }, [configurationError, isSearchMode, searchRetryKey, trimmedQuery]);

  const loadNextPage = useCallback(async (isRetry = false) => {
    if (
      isSearchMode ||
      !feedMeta?.has_more_pages ||
      paginationLoading ||
      (paginationError && !isRetry) ||
      paginationControllerRef.current
    ) {
      return;
    }

    const nextPage = feedMeta.current_page + 1;
    if (loadedPagesRef.current.has(nextPage)) {
      return;
    }

    const controller = new AbortController();
    paginationControllerRef.current = controller;
    setPaginationError(null);
    setPaginationLoading(true);

    try {
      const response = await fetchFeed(nextPage, controller.signal);

      if (!mountedRef.current || controller.signal.aborted) {
        return;
      }

      setPosts((current) => appendUniquePosts(current, response.data));
      setFeedMeta(response.meta);
      loadedPagesRef.current.add(nextPage);
      setPaginationError(null);
    } catch (error: unknown) {
      if (mountedRef.current && !controller.signal.aborted) {
        setPaginationError(messageFor(error));
      }
    } finally {
      if (paginationControllerRef.current === controller) {
        paginationControllerRef.current = null;
      }

      if (mountedRef.current && !controller.signal.aborted) {
        setPaginationLoading(false);
      }
    }
  }, [feedMeta, isSearchMode, paginationError, paginationLoading]);

  const refreshFeed = useCallback(() => {
    if (!isSearchMode && !initialLoading && !refreshing) {
      void loadFirstPage('refresh');
    }
  }, [initialLoading, isSearchMode, loadFirstPage, refreshing]);

  const reactToPost = useCallback(async (postId: number) => {
    if (reactingIdsRef.current.has(postId) || reactedIdsRef.current.has(postId)) {
      return;
    }

    const controller = new AbortController();
    reactingIdsRef.current.add(postId);
    reactionControllersRef.current.set(postId, controller);
    setReactingIds(new Set(reactingIdsRef.current));
    setReactionErrors((current) => {
      const next = new Map(current);
      next.delete(postId);
      return next;
    });

    try {
      await createInteraction(postId, controller.signal);

      if (!mountedRef.current || controller.signal.aborted) {
        return;
      }

      reactedIdsRef.current.add(postId);
      setReactedIds(new Set(reactedIdsRef.current));
    } catch (error: unknown) {
      if (mountedRef.current && !controller.signal.aborted) {
        setReactionErrors((current) => new Map(current).set(postId, messageFor(error)));
      }
    } finally {
      reactingIdsRef.current.delete(postId);
      reactionControllersRef.current.delete(postId);

      if (mountedRef.current) {
        setReactingIds(new Set(reactingIdsRef.current));
      }
    }
  }, []);

  const listData = isSearchMode ? searchResults : posts;

  const renderEmptyState = () => {
    if (configurationError) {
      return <StateMessage title="Configuration needed" message={configurationError} />;
    }

    if (isSearchMode) {
      if (searchLoading) {
        return (
          <StateMessage
            title="Searching connections"
            message="Looking for posts that match the meaning of your phrase."
            loading
          />
        );
      }

      if (searchError) {
        return (
          <StateMessage
            title="Search unavailable"
            message={searchError}
            actionLabel="Retry search"
            onAction={() => setSearchRetryKey((current) => current + 1)}
          />
        );
      }

      return (
        <StateMessage
          title="No matching posts"
          message="Try a different natural-language phrase."
        />
      );
    }

    if (initialLoading) {
      return <StateMessage title="Building your feed" message="Finding posts for you…" loading />;
    }

    if (initialError) {
      return (
        <StateMessage
          title="Feed unavailable"
          message={initialError}
          actionLabel="Retry feed"
          onAction={() => void loadFirstPage('retry')}
        />
      );
    }

    return (
      <StateMessage
        title="Your feed is quiet"
        message="There are no recent posts to show yet. Pull down later to refresh."
      />
    );
  };

  const renderFooter = () => {
    if (isSearchMode) {
      return null;
    }

    if (paginationLoading) {
      return (
        <View style={styles.footerState}>
          <ActivityIndicator color="#2E5BFF" size="small" />
          <Text style={styles.footerText}>Loading more posts…</Text>
        </View>
      );
    }

    if (paginationError) {
      return (
        <View style={styles.paginationError}>
          <Text style={styles.footerErrorText}>{paginationError}</Text>
          <Pressable
            accessibilityRole="button"
            accessibilityLabel="Retry loading more posts"
            onPress={() => void loadNextPage(true)}
            style={styles.footerButton}
          >
            <Text style={styles.footerButtonText}>Retry</Text>
          </Pressable>
        </View>
      );
    }

    return null;
  };

  return (
    <FlatList
      data={listData}
      keyExtractor={(post) => String(post.id)}
      renderItem={({ item }) => (
        <PostCard
          post={item}
          isReacting={reactingIds.has(item.id)}
          isReacted={reactedIds.has(item.id)}
          reactionError={reactionErrors.get(item.id)}
          onReact={reactToPost}
        />
      )}
      ListHeaderComponent={
        <View style={styles.header}>
          <Text style={styles.eyebrow}>GUISED UP</Text>
          <Text style={styles.title}>Real Connections</Text>
          <Text style={styles.subtitle}>Personal stories, shaped around what matters to you.</Text>
          <TextInput
            accessibilityLabel="Search posts using natural language"
            autoCapitalize="sentences"
            autoCorrect
            clearButtonMode="while-editing"
            onChangeText={setSearchInput}
            placeholder="Try “funny travel stories from last week”"
            placeholderTextColor="#858B94"
            returnKeyType="search"
            style={styles.searchInput}
            value={searchInput}
          />
          {isSearchMode && !searchLoading && !searchError ? (
            <Text style={styles.modeLabel}>Results for “{trimmedQuery}”</Text>
          ) : null}
          {!isSearchMode && refreshError ? (
            <View style={styles.refreshErrorRow}>
              <Text style={styles.refreshErrorText}>{refreshError}</Text>
              <Pressable
                accessibilityRole="button"
                accessibilityLabel="Retry refreshing feed"
                onPress={refreshFeed}
                style={styles.refreshRetryButton}
              >
                <Text style={styles.refreshRetryText}>Retry</Text>
              </Pressable>
            </View>
          ) : null}
        </View>
      }
      ListEmptyComponent={renderEmptyState}
      ListFooterComponent={renderFooter}
      contentContainerStyle={styles.listContent}
      keyboardDismissMode="on-drag"
      keyboardShouldPersistTaps="handled"
      onEndReached={() => void loadNextPage()}
      onEndReachedThreshold={0.35}
      refreshControl={
        <RefreshControl
          colors={['#2E5BFF']}
          enabled={!isSearchMode}
          onRefresh={refreshFeed}
          refreshing={!isSearchMode && refreshing}
          tintColor="#2E5BFF"
        />
      }
      showsVerticalScrollIndicator={false}
      style={styles.screen}
    />
  );
}

const styles = StyleSheet.create({
  screen: {
    backgroundColor: '#F6F7F9',
    flex: 1,
  },
  listContent: {
    flexGrow: 1,
    paddingBottom: 28,
    paddingHorizontal: 18,
  },
  header: {
    paddingBottom: 18,
    paddingTop: 26,
  },
  eyebrow: {
    color: '#2E5BFF',
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 1.4,
  },
  title: {
    color: '#171A1F',
    fontSize: 31,
    fontWeight: '800',
    letterSpacing: -0.7,
    marginTop: 7,
  },
  subtitle: {
    color: '#60666F',
    fontSize: 15,
    lineHeight: 22,
    marginTop: 7,
    maxWidth: 350,
  },
  searchInput: {
    backgroundColor: '#FFFFFF',
    borderColor: '#D9DDE3',
    borderRadius: 14,
    borderWidth: 1,
    color: '#171A1F',
    fontSize: 15,
    marginTop: 20,
    minHeight: 50,
    paddingHorizontal: 16,
    paddingVertical: 13,
  },
  modeLabel: {
    color: '#60666F',
    fontSize: 13,
    marginTop: 11,
  },
  refreshErrorRow: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 8,
    marginTop: 11,
  },
  refreshErrorText: {
    color: '#B42318',
    flex: 1,
    fontSize: 13,
    lineHeight: 18,
  },
  refreshRetryButton: {
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 44,
    paddingHorizontal: 8,
  },
  refreshRetryText: {
    color: '#2E5BFF',
    fontSize: 13,
    fontWeight: '700',
  },
  stateContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 340,
    paddingHorizontal: 24,
    paddingVertical: 42,
  },
  stateTitle: {
    color: '#20242A',
    fontSize: 19,
    fontWeight: '700',
    marginTop: 14,
    textAlign: 'center',
  },
  stateMessage: {
    color: '#666D76',
    fontSize: 15,
    lineHeight: 22,
    marginTop: 7,
    textAlign: 'center',
  },
  primaryButton: {
    alignItems: 'center',
    backgroundColor: '#2E5BFF',
    borderRadius: 12,
    justifyContent: 'center',
    marginTop: 20,
    minHeight: 46,
    paddingHorizontal: 20,
  },
  primaryButtonPressed: {
    backgroundColor: '#2449CC',
  },
  primaryButtonText: {
    color: '#FFFFFF',
    fontSize: 15,
    fontWeight: '700',
  },
  footerState: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 10,
    justifyContent: 'center',
    paddingVertical: 18,
  },
  footerText: {
    color: '#666D76',
    fontSize: 14,
  },
  paginationError: {
    alignItems: 'center',
    paddingHorizontal: 18,
    paddingVertical: 16,
  },
  footerErrorText: {
    color: '#B42318',
    fontSize: 13,
    lineHeight: 18,
    textAlign: 'center',
  },
  footerButton: {
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 4,
    minHeight: 44,
    minWidth: 80,
  },
  footerButtonText: {
    color: '#2E5BFF',
    fontSize: 14,
    fontWeight: '700',
  },
});
