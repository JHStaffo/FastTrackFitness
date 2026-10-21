import { useFonts } from 'expo-font';
import { Oswald_500Medium } from '@expo-google-fonts/oswald';
import { Inter_400Regular, Inter_600SemiBold } from '@expo-google-fonts/inter';
import { StatusBar } from 'expo-status-bar';
import { Image, Pressable, SafeAreaView, ScrollView, StyleSheet, Text, View } from 'react-native';
import { colors, fonts } from './src/theme';

// Sample data until the app signs in and reads the program server.
const today = { points: 6, max: 8 };
const week = { points: 29, required: 56, days: [7, 7, 7, 6, null, null, null] };
const habits = [
  { label: 'Meals logged', source: 'You log it', points: 3 },
  { label: 'Sleep 6+ hours', source: 'From your wearable', points: 1 },
  { label: 'Workout 30+ minutes', source: 'From your wearable', points: 1 },
  { label: '10,000 steps', source: 'From your wearable', points: 0 },
  { label: '64 oz of water', source: 'You log it', points: 1 },
  { label: 'Meal plan', source: 'You log it', points: 0 },
];
const DAY_NAMES = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

export default function App() {
  const [loaded] = useFonts({ Oswald_500Medium, Inter_400Regular, Inter_600SemiBold });
  if (!loaded) return null;

  return (
    <SafeAreaView style={styles.safe}>
      <StatusBar style="dark" />
      <View style={styles.topbar}>
        <Image source={require('./assets/logo.png')} style={styles.logo} resizeMode="contain" />
        <Text style={styles.appName}>FITNESS</Text>
      </View>
      <ScrollView contentContainerStyle={{ paddingBottom: 32 }}>
        <View style={styles.band}>
          <Text style={styles.hello}>Hi, Sam</Text>
          <View style={styles.bandStats}>
            <Stat label="TODAY" value={`${today.points} / ${today.max}`} />
            <Stat label="THIS WEEK" value={`${week.points} / ${week.required}`} />
            <Stat label="RANK" value="#3" />
          </View>
        </View>

        <View style={styles.body}>
          <View style={styles.card}>
            <Text style={styles.h2}>Weekly gift card</Text>
            <View style={styles.progress}>
              <View style={[styles.progressFill, { width: `${(week.points / week.required) * 100}%` }]} />
            </View>
            <Text style={styles.hint}>
              {week.required - week.points} points to go for this week's $10 gift card.
            </Text>
            <View style={styles.weekStrip}>
              {week.days.map((p, i) => (
                <View key={i} style={[styles.weekDay, p === today.max && styles.weekDayFull, i === 3 && styles.weekDayToday]}>
                  <Text style={styles.weekDayName}>{DAY_NAMES[i]}</Text>
                  <Text style={styles.weekDayPts}>{p ?? '–'}</Text>
                </View>
              ))}
            </View>
          </View>

          <View style={styles.card}>
            <Text style={styles.h2}>Today's habits</Text>
            {habits.map((h, i) => (
              <View key={h.label} style={[styles.habit, i === habits.length - 1 && { borderBottomWidth: 0 }]}>
                <View style={[styles.mark, h.points > 0 && styles.markMet]}>
                  <Text style={styles.markText}>{h.points > 0 ? '✓' : ''}</Text>
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={styles.habitLabel}>{h.label}</Text>
                  <Text style={styles.habitSource}>{h.source}</Text>
                </View>
                <Text style={styles.habitPts}>{h.points}</Text>
              </View>
            ))}
            <Pressable style={styles.btn}>
              <Text style={styles.btnText}>LOG MEALS AND WATER</Text>
            </Pressable>
          </View>

          <View style={styles.card}>
            <Text style={styles.h2}>My wearable</Text>
            <Text style={styles.habitLabel}>Apple Health <Text style={styles.pill}> Connected </Text></Text>
            <Text style={styles.habitSource}>Last synced 12 minutes ago</Text>
          </View>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <View>
      <Text style={styles.statLabel}>{label}</Text>
      <Text style={styles.statValue}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.card },
  topbar: {
    flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 16, paddingVertical: 10,
    backgroundColor: colors.card, borderBottomWidth: 4, borderBottomColor: colors.red,
  },
  logo: { width: 104, height: 44 },
  appName: { fontFamily: fonts.heading, fontSize: 18, letterSpacing: 1, color: colors.navy },
  band: { backgroundColor: colors.navy, paddingHorizontal: 16, paddingVertical: 22 },
  hello: { fontFamily: fonts.heading, fontSize: 30, color: '#fff' },
  bandStats: { flexDirection: 'row', gap: 28, marginTop: 12 },
  statLabel: { fontFamily: fonts.heading, fontSize: 12, letterSpacing: 0.6, color: '#b9c6d2' },
  statValue: { fontFamily: fonts.heading, fontSize: 28, color: '#fff' },
  body: { backgroundColor: colors.bg, padding: 16, gap: 16 },
  card: {
    backgroundColor: colors.card, borderRadius: 16, padding: 18,
    shadowColor: '#14283c', shadowOpacity: 0.08, shadowRadius: 2, shadowOffset: { width: 0, height: 1 }, elevation: 1,
  },
  h2: { fontFamily: fonts.heading, fontSize: 21, color: colors.ink, marginBottom: 10 },
  hint: { fontFamily: fonts.body, fontSize: 14, color: colors.muted, marginTop: 10 },
  progress: { height: 10, backgroundColor: colors.bg, borderRadius: 5, overflow: 'hidden' },
  progressFill: { height: '100%', backgroundColor: colors.teal, borderRadius: 5 },
  weekStrip: { flexDirection: 'row', gap: 5, marginTop: 12 },
  weekDay: { flex: 1, alignItems: 'center', paddingVertical: 6, borderRadius: 8, backgroundColor: colors.bg },
  weekDayFull: { backgroundColor: colors.tealTint },
  weekDayToday: { borderWidth: 2, borderColor: colors.red },
  weekDayName: { fontFamily: fonts.body, fontSize: 11, color: colors.muted },
  weekDayPts: { fontFamily: fonts.heading, fontSize: 17, color: colors.navy },
  habit: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 10, borderBottomWidth: 1, borderBottomColor: colors.line },
  mark: { width: 28, height: 28, borderRadius: 14, backgroundColor: colors.bg, alignItems: 'center', justifyContent: 'center' },
  markMet: { backgroundColor: colors.teal },
  markText: { color: '#fff', fontFamily: fonts.bodyBold },
  habitLabel: { fontFamily: fonts.body, fontSize: 16, color: colors.ink },
  habitSource: { fontFamily: fonts.body, fontSize: 12, color: colors.muted, marginTop: 2 },
  habitPts: { fontFamily: fonts.heading, fontSize: 18, color: colors.navy },
  btn: { backgroundColor: colors.red, borderRadius: 10, paddingVertical: 12, alignItems: 'center', marginTop: 14 },
  btnText: { fontFamily: fonts.heading, color: '#fff', fontSize: 16, letterSpacing: 0.8 },
  pill: { fontFamily: fonts.bodyBold, fontSize: 12, color: '#155c33', backgroundColor: '#e3f4ea' },
});
