# Friends Hangout — Social Meetup, Friends Directory & Task Planner

A modern, full-stack web application designed for social meetup planning, friend contact management with qualities and behavior notes, task/responsibility assignment, venue location mapping, and interactive date voting.

---

## 🌟 New Features & Enhancements

### 1. 👥 Friends Contact Directory (Dosto Ki Directory)
- **Full CRUD Management**:
  - **Add Friend**: Enter Name, Phone/WhatsApp, Email, Location (*Kaha rehta hai*), Profession (*Kia karta hai*), Qualities (*Special skills e.g. Car Driver, BBQ Master, Guitarist*), and Behavior & Habits (*e.g. Always on time, calm vibe*).
  - **Update Friend**: Edit all details directly from an interactive modal.
  - **Delete Friend**: Remove friend with confirmation and auto-cleanup.
- **Direct Contact Links**: 1-click **WhatsApp** chat and phone call buttons.
- **Category / Circle Filtering**: Filter by *Best Friend*, *Close Friend*, *College Friend*, *Work Friend*, *Gym Buddy*, or *Neighborhood*.
- **Search Bar**: Instant search across names, phone numbers, locations, occupations, or qualities.

### 2. 📋 Task & Responsibility Assignment (Task Assigned Karna Onko)
- Assign specific duties to specific friends for each hangout (e.g., *"Bring portable BBQ grill"* assigned to *Usman*, *"Bring Bluetooth speaker & playlist"* assigned to *Hamza*).
- Interactive checklist with real-time checkbox toggles (*Pending* ↔ *Completed*).
- Progress bar on hangout cards showing completed vs total tasks.
- Add new tasks directly during hangout creation or dynamically inside the hangout detail modal!

### 3. 🎯 Kaha, Kio & Kese Structure (Hangout Detail Pillars)
- **Kaha (Where)**: Venue name, street address, and Leaflet interactive map with custom pin and Google Maps directions link.
- **Kio (Why / Purpose)**: The main reason or celebration (e.g., *"Weekend celebration to unwind and celebrate Bilal's promotion"*).
- **Kese (How & Logistics)**: Meeting point, carpooling arrangement (who is driving), estimated budget per head, and what to bring.

### 4. 🗳️ Date & Time Voting Polls & Live Chat
- Multi-option scheduling poll for friends to vote on the best time slot.
- Live group chat per hangout with automatic 3-second sync.

---

## 🗄️ Database Tables (`friend-app` in MySQL)

The app automatically initializes and seeds all tables in MySQL (`127.0.0.1:3306`, user `root`):

| Table | Purpose |
| :--- | :--- |
| `friends` | Friend contacts, phone, location, occupation, qualities & behavior notes |
| `hangout_tasks` | Assigned tasks/responsibilities linked to hangouts and friends |
| `hangout_invited_friends`| Track which friends are invited/added to each hangout |
| `hangouts` | Meetup title, Kaha (venue), Kio (purpose), Kese (logistics), date & cover |
| `users` | User credentials & active personas |
| `hangout_attendees` | RSVP tracking (`attending`, `maybe`, `declined`) |
| `hangout_polls` | Scheduling questions tied to hangouts |
| `hangout_poll_options` | Available date/time slots |
| `hangout_poll_votes` | Friend votes on poll options |
| `hangout_messages` | Real-time chat messages per hangout |
| `hangout_activities` | Timeline activity log |
| `notifications` | In-app notification alerts |

> SQL Schema file: [schema.sql](file:///c:/laragon/www/friend-app/schema.sql)

---

## 🚀 How to Run Locally in Laragon

1. **Start Laragon**:
   - Click **"Start All"** in Laragon (Apache & MySQL).
2. **Open in Browser**:
   ```
   http://localhost/friend-app/
   ```
3. **Reseed Demo Data**:
   - Click **"Reset Sample Data"** in the footer at any time to restore realistic starter friends and hangouts.
