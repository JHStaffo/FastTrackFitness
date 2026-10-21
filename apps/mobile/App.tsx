import { StatusBar } from 'expo-status-bar';
import { SafeAreaView, ScrollView, StyleSheet, Text, View } from 'react-native';
import { DEFAULT_RULES, evaluateWeeklyReward, scoreDay, type DayActivity } from '@ftf/scoring';

// Sample day until Apple Health and Fitbit sync are connected.
const SAMPLE_WEEK: DayActivity[] = ['2027-01-10', '2027-01-11', '2027-01-12', '2027-01-13'].map((date) => ({
  date,
  mealsLogged: 3,
  sleepMinutes: 400,
  workouts: [{ start: `${date}T17:00:00Z`, end: `${date}T17:35:00Z` }],
  steps: 9_400,
  waterOz: 64,
  mealPlanConfirmed: true,
}));

export default function App() {
  const scores = SAMPLE_WEEK.map((d) => scoreDay(d, DEFAULT_RULES));
  const today = scores[scores.length - 1];
  const week = evaluateWeeklyReward(scores, '2027-01-13', DEFAULT_RULES);

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView contentContainerStyle={styles.content}>
        <Text style={styles.title}>Fast Track Fitness</Text>
        <Text style={styles.muted}>Sample data until your wearable is connected.</Text>
        <View style={styles.card}>
          <Text style={styles.label}>Today</Text>
          <Text style={styles.value}>
            {today?.dailyPoints ?? 0} / {DEFAULT_RULES.daily.maxPointsPerDay}
          </Text>
        </View>
        <View style={styles.card}>
          <Text style={styles.label}>This week</Text>
          <Text style={styles.value}>
            {week.points} / {week.pointsRequired}
          </Text>
          <Text style={styles.muted}>{week.pointsRequired - week.points} points to the $10 gift card</Text>
        </View>
      </ScrollView>
      <StatusBar style="auto" />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#fafafa' },
  content: { padding: 16, gap: 12 },
  title: { fontSize: 24, fontWeight: '600' },
  muted: { color: '#71717a', fontSize: 13 },
  card: { backgroundColor: '#fff', borderRadius: 12, padding: 16, borderWidth: 1, borderColor: '#e4e4e7' },
  label: { color: '#71717a', fontSize: 12 },
  value: { fontSize: 28, fontWeight: '600', marginTop: 4 },
});
