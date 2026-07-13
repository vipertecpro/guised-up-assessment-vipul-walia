import { StatusBar } from 'expo-status-bar';
import { SafeAreaView, StyleSheet } from 'react-native';

import { FeedScreen } from './src/FeedScreen';

export default function App() {
  return (
    <SafeAreaView style={styles.safeArea}>
      <FeedScreen />
      <StatusBar style="dark" />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: '#F6F7F9',
  },
});
