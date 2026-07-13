import { useEffect, useState } from 'react';
import { ActivityIndicator, Image, Pressable, StyleSheet, Text, View } from 'react-native';

import type { Post } from '../types';
import { timeAgo } from '../utils/timeAgo';

interface PostCardProps {
  post: Post;
  isReacting: boolean;
  isReacted: boolean;
  reactionError?: string;
  onReact: (postId: number) => void;
}

function initials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);

  if (parts.length === 0) {
    return '?';
  }

  return parts
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('');
}

export function PostCard({
  post,
  isReacting,
  isReacted,
  reactionError,
  onReact,
}: PostCardProps) {
  const [imageFailed, setImageFailed] = useState(false);

  useEffect(() => {
    setImageFailed(false);
  }, [post.image_url]);

  return (
    <View style={styles.card}>
      <View style={styles.authorRow}>
        <View style={styles.avatar} accessibilityElementsHidden>
          <Text style={styles.avatarText}>{initials(post.user.name)}</Text>
        </View>
        <View style={styles.authorText}>
          <Text style={styles.authorName}>{post.user.name}</Text>
          <Text style={styles.timestamp}>{timeAgo(post.created_at)}</Text>
        </View>
      </View>

      <Text style={styles.postText}>{post.text}</Text>

      {post.image_url && !imageFailed ? (
        <Image
          accessibilityLabel={`Image shared by ${post.user.name}`}
          source={{ uri: post.image_url }}
          style={styles.image}
          resizeMode="cover"
          onError={() => setImageFailed(true)}
        />
      ) : null}

      {post.image_url && imageFailed ? (
        <View style={styles.imageError}>
          <Text style={styles.imageErrorText}>Image unavailable</Text>
        </View>
      ) : null}

      <View style={styles.actionRow}>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={isReacted ? `Reacted to ${post.user.name}'s post` : `React to ${post.user.name}'s post`}
          accessibilityState={{ disabled: isReacted || isReacting, busy: isReacting }}
          disabled={isReacted || isReacting}
          onPress={() => onReact(post.id)}
          style={({ pressed }) => [
            styles.reactionButton,
            isReacted && styles.reactionButtonComplete,
            pressed && !isReacted && styles.reactionButtonPressed,
          ]}
        >
          {isReacting ? <ActivityIndicator color="#2E5BFF" size="small" /> : null}
          <Text style={[styles.reactionText, isReacted && styles.reactionTextComplete]}>
            {isReacting ? 'Reacting…' : isReacted ? 'Reacted' : 'React'}
          </Text>
        </Pressable>
        {reactionError ? <Text style={styles.reactionError}>{reactionError}</Text> : null}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: '#FFFFFF',
    borderColor: '#E1E4E8',
    borderRadius: 18,
    borderWidth: 1,
    marginBottom: 14,
    overflow: 'hidden',
    padding: 18,
  },
  authorRow: {
    alignItems: 'center',
    flexDirection: 'row',
  },
  avatar: {
    alignItems: 'center',
    backgroundColor: '#E9EEFF',
    borderRadius: 22,
    height: 44,
    justifyContent: 'center',
    width: 44,
  },
  avatarText: {
    color: '#2E5BFF',
    fontSize: 15,
    fontWeight: '700',
  },
  authorText: {
    flex: 1,
    marginLeft: 12,
  },
  authorName: {
    color: '#171A1F',
    fontSize: 16,
    fontWeight: '700',
  },
  timestamp: {
    color: '#707780',
    fontSize: 13,
    marginTop: 2,
  },
  postText: {
    color: '#262A30',
    fontSize: 16,
    lineHeight: 24,
    marginTop: 16,
  },
  image: {
    aspectRatio: 16 / 10,
    backgroundColor: '#EEF0F3',
    borderRadius: 12,
    marginTop: 16,
    width: '100%',
  },
  imageError: {
    alignItems: 'center',
    backgroundColor: '#F2F3F5',
    borderRadius: 12,
    justifyContent: 'center',
    marginTop: 16,
    minHeight: 72,
  },
  imageErrorText: {
    color: '#707780',
    fontSize: 13,
  },
  actionRow: {
    alignItems: 'center',
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
    marginTop: 14,
  },
  reactionButton: {
    alignItems: 'center',
    alignSelf: 'flex-start',
    borderColor: '#C8D3FF',
    borderRadius: 12,
    borderWidth: 1,
    flexDirection: 'row',
    gap: 8,
    justifyContent: 'center',
    minHeight: 44,
    minWidth: 96,
    paddingHorizontal: 16,
  },
  reactionButtonComplete: {
    backgroundColor: '#EEF2FF',
    borderColor: '#EEF2FF',
  },
  reactionButtonPressed: {
    backgroundColor: '#F3F6FF',
  },
  reactionText: {
    color: '#2E5BFF',
    fontSize: 14,
    fontWeight: '700',
  },
  reactionTextComplete: {
    color: '#30446F',
  },
  reactionError: {
    color: '#B42318',
    flexShrink: 1,
    fontSize: 13,
    lineHeight: 18,
  },
});
