<?php
// ==============================================================================
// FRIENDS HANGOUT - SOCIAL MEETUP, FRIENDS DIRECTORY & TASK COORDINATION
// ==============================================================================
require_once __DIR__ . '/db.php';
initDatabase();
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Friends Hangout — Meetup Planner & Friends Directory</title>
    <meta name="description" content="Plan meetups, assign tasks to friends, keep a directory with contact qualities, location, and behavior, and vote on dates.">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                        display: ['Outfit', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            200: '#c7d2fe',
                            300: '#a5b4fc',
                            400: '#818cf8',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            800: '#3730a3',
                            900: '#312e81',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Leaflet Maps CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

    <!-- Lucide Icons CDN with inline SVG fallback -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: rgba(15, 23, 42, 0.4); }
        ::-webkit-scrollbar-thumb { background: rgba(99, 102, 241, 0.35); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(99, 102, 241, 0.7); }
        .glass-panel {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .glass-card {
            background: rgba(30, 41, 59, 0.55);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.06);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .glass-card:hover {
            border-color: rgba(99, 102, 241, 0.4);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.2);
        }
        .glow-button {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #ec4899 100%);
            box-shadow: 0 4px 15px -2px rgba(99, 102, 241, 0.4);
            transition: all 0.2s ease;
        }
        .glow-button:hover {
            box-shadow: 0 6px 20px 0 rgba(99, 102, 241, 0.6);
            transform: translateY(-1px);
        }
        .leaflet-popup-content-wrapper {
            background: #111827 !important;
            color: #f3f4f6 !important;
            border-radius: 12px !important;
            border: 1px solid rgba(99, 102, 241, 0.3) !important;
        }
        .leaflet-popup-tip { background: #111827 !important; }
    </style>
</head>
<body class="h-full bg-[#080c16] text-slate-100 antialiased selection:bg-brand-500 selection:text-white font-sans overflow-x-hidden">
    
    <!-- APPLICATION ROOT CONTAINER -->
    <div id="app" class="min-h-full flex flex-col">
        <!-- Initial Fast Loading Skeleton that vanishes when JS executes -->
        <div id="loading-overlay" class="py-24 flex flex-col items-center justify-center text-center">
            <div class="w-12 h-12 rounded-full border-4 border-brand-500 border-t-transparent animate-spin mb-4"></div>
            <div class="text-sm font-semibold text-slate-300">Loading Friends Hangout on Localhost...</div>
        </div>
    </div>

    <!-- TOAST NOTIFICATION CONTAINER -->
    <div id="toast-container" class="fixed bottom-6 right-6 z-50 pointer-events-none space-y-2"></div>

    <!-- MODAL CONTAINER -->
    <div id="modal-container"></div>

    <!-- NATIVE FAST CLIENT-SIDE APPLICATION JAVASCRIPT (ZERO BABEL DEPENDENCY) -->
    <script>
        // API HELPER
        const api = {
            async get(action, params = {}) {
                try {
                    const query = new URLSearchParams({ action, ...params }).toString();
                    const res = await fetch('api.php?' + query);
                    return await res.json();
                } catch (e) {
                    console.error("API GET error", e);
                    return { success: false, error: e.message };
                }
            },
            async post(action, data = {}) {
                try {
                    const res = await fetch('api.php?action=' + action, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                    return await res.json();
                } catch (e) {
                    console.error("API POST error", e);
                    return { success: false, error: e.message };
                }
            }
        };

        // GLOBAL APPLICATION STATE WITH PERSISTENCE
        const state = {
            currentView: localStorage.getItem('friendApp_currentView') || 'hangouts', // 'hangouts', 'friends', 'map'
            currentUser: null,
            allUsers: [],
            hangouts: [],
            friends: [],
            notifications: [],
            unreadCount: 0,
            activeFilter: localStorage.getItem('friendApp_activeFilter') || 'all',
            searchQuery: '',
            friendCircle: localStorage.getItem('friendApp_friendCircle') || 'all',
            friendSearch: '',
            selectedHangout: null,
            isNotifOpen: false
        };

        // TOAST HELPER
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const el = document.createElement('div');
            el.className = 'flex items-center gap-3 px-5 py-3 rounded-2xl bg-slate-900 border border-brand-500/50 text-white shadow-2xl transition-all duration-300 transform translate-y-2 opacity-0 pointer-events-auto';
            el.innerHTML = `
                <span class="w-2.5 h-2.5 rounded-full ${type === 'success' ? 'bg-emerald-400' : 'bg-rose-500'} animate-ping"></span>
                <span class="text-xs sm:text-sm font-semibold">${escapeHtml(message)}</span>
            `;
            container.appendChild(el);
            setTimeout(() => el.classList.remove('translate-y-2', 'opacity-0'), 10);
            setTimeout(() => {
                el.classList.add('opacity-0', 'translate-y-2');
                setTimeout(() => el.remove(), 300);
            }, 3500);
        }

        // ESCAPE HTML HELPER
        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // ==============================================================================
        // AVATAR POOLS & SMART GENDER DETECTION
        // ==============================================================================
        const AVATAR_POOLS = {
            female: [
                'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=300&q=80',
                'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=300&q=80',
                'https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&w=300&q=80',
                'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=300&q=80',
                'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=300&q=80',
                'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=300&q=80',
                'https://images.unsplash.com/photo-1580489944761-15a19d654956?auto=format&fit=crop&w=300&q=80',
                'https://images.unsplash.com/photo-1567532939604-b6b5b0db2604?auto=format&fit=crop&w=300&q=80',
                'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?auto=format&fit=crop&w=300&q=80',
                'https://images.unsplash.com/photo-1529626455594-4ff0802cfb7e?auto=format&fit=crop&w=300&q=80',
                'https://images.unsplash.com/photo-1508214751196-bcfd4ca60f91?auto=format&fit=crop&w=300&q=80',
                'https://images.unsplash.com/photo-1488426862026-3ee34a7d66df?auto=format&fit=crop&w=300&q=80'
            ],
            male: [
                'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=300&q=80',
                'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=300&q=80',
                'https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?auto=format&fit=crop&w=300&q=80',
                'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&w=300&q=80',
                'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&w=300&q=80',
                'https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?auto=format&fit=crop&w=300&q=80',
                'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?auto=format&fit=crop&w=300&q=80',
                'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&w=300&q=80',
                'https://images.unsplash.com/photo-1501196354995-cbb51c65aaea?auto=format&fit=crop&w=300&q=80',
                'https://images.unsplash.com/photo-1513956589380-bad6acb9b9d4?auto=format&fit=crop&w=300&q=80',
                'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=300&q=80',
                'https://images.unsplash.com/photo-1566492031773-4f4e44671857?auto=format&fit=crop&w=300&q=80'
            ]
        };

        const FEMALE_NAMES_SET = new Set([
            'fatima', 'fatimah', 'zainab', 'ayesha', 'aisha', 'ayesh', 'maryam', 'mariam', 'sara', 'sarah',
            'hira', 'aliza', 'noor', 'amna', 'kinza', 'rabia', 'maham', 'sadia', 'iqra', 'areeba', 'samina',
            'nida', 'zoya', 'sidra', 'bushra', 'anam', 'fariha', 'laiba', 'mehwish', 'shazia', 'rubina',
            'sania', 'javeria', 'mehak', 'dua', 'komal', 'kiran', 'sana', 'maria', 'anum', 'asma', 'sumaira',
            'tuba', 'tooba', 'eman', 'urwa', 'hina', 'mahnoor', 'farah', 'bisma', 'hoor', 'hoorain', 'nazia',
            'shabana', 'parveen', 'bano', 'begum', 'bibi', 'khatoon', 'shehnaz', 'tahira', 'fouzia', 'samra',
            'amber', 'neha', 'sonia', 'aliyah', 'aliya', 'leila', 'layla', 'yasmin', 'lubna', 'naila', 'uzma',
            'tasneem', 'fareeha', 'anila', 'humaira', 'farzana', 'zahida', 'nighat', 'muskan', 'manahil',
            'alishba', 'madiha', 'shanzay', 'aleena', 'alina', 'aqsa', 'iram', 'shaista', 'sundus', 'faiza'
        ]);

        function detectGenderFromName(name) {
            if (!name) return 'male';
            const lower = name.toLowerCase().trim();
            const tokens = lower.split(/[\s,._-]+/);
            for (const t of tokens) {
                if (FEMALE_NAMES_SET.has(t)) return 'female';
                if (t.endsWith('bano') || t.endsWith('bibi') || t.endsWith('begum') || t.endsWith('khatoon')) return 'female';
            }
            return 'male';
        }

        let currentModalGender = 'male';

        function getRandomAvatar(gender) {
            const list = AVATAR_POOLS[gender] || AVATAR_POOLS.male;
            const idx = Math.floor(Math.random() * list.length);
            return list[idx];
        }

        function formatHangoutDate(dateStr, timeStr) {
            if (!dateStr) return 'Date to be decided / In Voting';
            const date = new Date(dateStr + (timeStr ? `T${timeStr}` : 'T00:00:00'));
            const options = { weekday: 'short', month: 'short', day: 'numeric' };
            const formattedDate = date.toLocaleDateString(undefined, options);
            if (!timeStr) return formattedDate;
            const formattedTime = date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
            return `${formattedDate} · ${formattedTime}`;
        }

        function timeAgo(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString.replace(' ', 'T'));
            const now = new Date();
            const diffInSeconds = Math.floor((now - date) / 1000);
            if (diffInSeconds < 60) return 'just now';
            if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
            if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
            return `${Math.floor(diffInSeconds / 86400)}d ago`;
        }

        // ==============================================================================
        // MAIN DATA LOADER
        // ==============================================================================
        async function loadAppData() {
            try {
                const [meRes, hRes, fRes, nRes] = await Promise.all([
                    api.get('me'),
                    api.get('get_hangouts', { filter: state.activeFilter, search: state.searchQuery }),
                    api.get('get_friends', { circle: state.friendCircle, search: state.friendSearch }),
                    api.get('get_notifications')
                ]);

                if (meRes.success) {
                    state.currentUser = meRes.user;
                    state.allUsers = meRes.all_users || [];
                }
                if (hRes.success) state.hangouts = hRes.hangouts || [];
                if (fRes.success) state.friends = fRes.friends || [];
                if (nRes.success) {
                    state.notifications = nRes.notifications || [];
                    state.unreadCount = nRes.unread_count || 0;
                }

                renderApp();

                // Reopen last active hangout modal if user was viewing one
                const lastHangoutId = localStorage.getItem('friendApp_lastHangoutId');
                if (lastHangoutId && !state.selectedHangout) {
                    const exists = state.hangouts.some(h => h.id == lastHangoutId);
                    if (exists) {
                        openHangoutDetail(parseInt(lastHangoutId));
                    }
                }
            } catch (err) {
                console.error("Failed to load app data", err);
                document.getElementById('app').innerHTML = `
                    <div class="p-8 max-w-lg mx-auto text-center glass-card rounded-3xl mt-20">
                        <div class="text-rose-400 text-3xl mb-3">⚠️</div>
                        <h2 class="text-lg font-bold text-white">Database Connection Check</h2>
                        <p class="text-xs text-slate-400 mt-2">Could not reach MySQL database <code>friend-app</code> or Apache in Laragon.</p>
                        <button onclick="location.reload()" class="mt-4 glow-button px-4 py-2 rounded-xl text-white text-xs font-bold">Try Reloading</button>
                    </div>
                `;
            }
        }

        // ==============================================================================
        // RENDER APPLICATION
        // ==============================================================================
        function renderApp() {
            const root = document.getElementById('app');
            root.innerHTML = `
                <!-- TOP NAVBAR -->
                <nav class="sticky top-0 z-40 glass-panel border-b border-white/10 px-4 sm:px-8 py-3">
                    <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
                        
                        <!-- Logo -->
                        <div class="flex items-center gap-3 cursor-pointer" onclick="switchView('hangouts')">
                            <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-brand-600 via-indigo-500 to-pink-500 flex items-center justify-center shadow-lg shadow-brand-500/25 text-white">
                                <i data-lucide="sparkles" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <div class="font-display font-extrabold text-xl tracking-tight bg-gradient-to-r from-white via-indigo-200 to-brand-300 bg-clip-text text-transparent">
                                    Friends Hangout
                                </div>
                                <div class="text-[11px] text-slate-400 font-medium -mt-1 hidden sm:block">
                                    Meetup Planner, Tasks & Friends Directory
                                </div>
                            </div>
                        </div>

                        <!-- Main Navigation Switcher -->
                        <div class="flex items-center gap-1 p-1 bg-slate-800/80 rounded-2xl border border-white/5">
                            <button onclick="switchView('hangouts')" class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition ${state.currentView === 'hangouts' ? 'bg-brand-600 text-white shadow-md' : 'text-slate-400 hover:text-white'}">
                                <i data-lucide="calendar" class="w-4 h-4"></i>
                                <span>Hangouts</span>
                                <span class="px-1.5 py-0.2 rounded-full bg-white/20 text-[10px] hidden md:inline">${state.hangouts.length}</span>
                            </button>

                            <button onclick="switchView('friends')" class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition ${state.currentView === 'friends' ? 'bg-brand-600 text-white shadow-md' : 'text-slate-400 hover:text-white'}">
                                <i data-lucide="users" class="w-4 h-4"></i>
                                <span>Friends Directory</span>
                                <span class="px-1.5 py-0.2 rounded-full bg-emerald-500/30 text-emerald-300 text-[10px] hidden md:inline">${state.friends.length}</span>
                            </button>

                            <button onclick="switchView('map')" class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition ${state.currentView === 'map' ? 'bg-brand-600 text-white shadow-md' : 'text-slate-400 hover:text-white'}">
                                <i data-lucide="map" class="w-4 h-4"></i>
                                <span class="hidden sm:inline">Venue Map</span>
                            </button>
                        </div>

                        <!-- Right Actions -->
                        <div class="flex items-center gap-2.5 sm:gap-3">
                            <button onclick="openFriendModal()" class="hidden sm:flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-white/10 text-xs font-semibold transition">
                                <i data-lucide="user-plus" class="w-4 h-4 text-emerald-400"></i>
                                <span>+ Add Friend</span>
                            </button>

                            <button onclick="openPlanHangoutModal()" class="glow-button flex items-center gap-2 px-3.5 sm:px-4 py-2 rounded-xl text-white font-bold text-xs sm:text-sm tracking-wide">
                                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                                <span class="hidden sm:inline">Plan Hangout</span>
                                <span class="sm:hidden">Plan</span>
                            </button>

                            <!-- Current User Badge (Muhammad Faisal Only) -->
                            <div class="flex items-center gap-2.5 px-3 py-1.5 rounded-2xl bg-slate-800/90 border border-white/10 shadow-sm">
                                <div class="relative">
                                    <img src="${state.currentUser?.avatar_url || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=100&q=80'}" class="w-7 h-7 rounded-full object-cover ring-2 ring-brand-400" />
                                    <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-emerald-400 ring-2 ring-slate-900" title="Online in Quetta"></span>
                                </div>
                                <div class="hidden sm:block text-left">
                                    <div class="font-bold text-xs text-white leading-tight">Muhammad Faisal</div>
                                    <div class="text-[10px] text-emerald-400 font-semibold leading-none">Organizer (Host)</div>
                                </div>
                            </div>
                        </div>

                    </div>
                </nav>

                <!-- VIEW CONTENT -->
                <main class="flex-1 w-full">
                    ${state.currentView === 'hangouts' ? renderHangoutsView() : ''}
                    ${state.currentView === 'friends' ? renderFriendsView() : ''}
                    ${state.currentView === 'map' ? renderMapView() : ''}
                </main>

                <!-- FOOTER -->
                <footer class="border-t border-white/10 glass-panel py-6 px-4 sm:px-8 mt-12">
                    <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-400">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span>Laragon MySQL Stack: <code>friend-app</code> (users, friends, hangouts, tasks)</span>
                        </div>
                        <div class="flex items-center gap-4">
                            <button onclick="resetSampleData()" class="hover:text-brand-300 transition flex items-center gap-1.5">
                                <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                                Reset Sample Demo Data
                            </button>
                            <span>•</span>
                            <span>Friends Hangout &copy; 2026</span>
                        </div>
                    </div>
                </footer>
            `;

            if (window.lucide) window.lucide.createIcons();

            if (state.currentView === 'map') {
                initLeafletCityMap();
            }
        }

        // ==============================================================================
        // VIEW: HANGOUTS & MEETUPS
        // ==============================================================================
        function renderHangoutsView() {
            return `
                <div class="max-w-7xl mx-auto px-4 sm:px-8 py-6 w-full">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 pb-6 border-b border-white/10">
                        <div>
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-brand-500/10 border border-brand-500/20 text-brand-300 text-xs font-semibold mb-2">
                                <span class="w-2 h-2 rounded-full bg-brand-400 animate-ping"></span>
                                Meetup Planner & Tasks
                            </div>
                            <h1 class="font-display text-3xl sm:text-4xl font-extrabold text-white tracking-tight">
                                Plan Hangouts, <span class="bg-gradient-to-r from-brand-400 via-indigo-300 to-pink-400 bg-clip-text text-transparent">Assign Tasks</span> & Coordinate.
                            </h1>
                            <p class="text-xs sm:text-sm text-slate-400 mt-1.5 max-w-2xl leading-relaxed">
                                Know <strong>Kaha</strong> (Where), <strong>Kio</strong> (Why), <strong>Kese</strong> (Logistics), which friends are joining, and assign responsibilities so nobody forgets their part.
                            </p>
                        </div>

                        <div class="flex items-center gap-3">
                            <button onclick="openPlanHangoutModal()" class="glow-button px-5 py-3 rounded-2xl text-white font-bold text-xs sm:text-sm flex items-center gap-2 shadow-lg">
                                <i data-lucide="calendar-plus" class="w-4 h-4"></i>
                                <span>+ Create New Hangout</span>
                            </button>
                            <button onclick="switchView('friends')" class="px-4 py-3 rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-white/10 font-bold text-xs sm:text-sm flex items-center gap-2 transition">
                                <i data-lucide="users" class="w-4 h-4 text-emerald-400"></i>
                                <span>Friends List (${state.friends.length})</span>
                            </button>
                        </div>
                    </div>

                    <!-- Filter & Search Bar -->
                    <div class="mt-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="relative flex-1 max-w-md">
                            <i data-lucide="search" class="absolute left-3.5 top-3 w-4 h-4 text-slate-400"></i>
                            <input 
                                type="text"
                                value="${escapeHtml(state.searchQuery)}"
                                oninput="handleHangoutSearch(this.value)"
                                placeholder="Search by title, venue, or purpose (Kio)..."
                                class="w-full pl-10 pr-4 py-2.5 rounded-2xl bg-slate-800/80 border border-white/10 text-white placeholder-slate-500 text-xs focus:outline-none focus:border-brand-500"
                            />
                        </div>

                        <div class="flex items-center gap-1.5 overflow-x-auto">
                            ${[
                                { id: 'all', label: 'All Meetups' },
                                { id: 'upcoming', label: 'Upcoming' },
                                { id: 'voting', label: '🗳️ In Voting' },
                                { id: 'mine', label: 'My Hangouts' },
                                { id: 'past', label: 'Past Archives' }
                            ].map(tab => `
                                <button onclick="setHangoutFilter('${tab.id}')" class="px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition ${state.activeFilter === tab.id ? 'bg-brand-600 text-white shadow-md' : 'bg-slate-800/60 text-slate-400 hover:text-white'}">
                                    ${tab.label}
                                </button>
                            `).join('')}
                        </div>
                    </div>

                    <!-- Hangout Cards Grid -->
                    <div class="mt-6">
                        ${state.hangouts.length === 0 ? `
                            <div class="text-center py-16 glass-card rounded-3xl p-8 max-w-md mx-auto">
                                <i data-lucide="calendar-x" class="w-12 h-12 text-slate-500 mx-auto mb-3"></i>
                                <h3 class="text-base font-bold text-white">No hangouts found</h3>
                                <p class="text-xs text-slate-400 mt-1">Plan your first hangout and add friends with assigned tasks!</p>
                                <button onclick="openPlanHangoutModal()" class="mt-4 glow-button px-4 py-2 rounded-xl text-white text-xs font-bold">+ Plan A Hangout Now</button>
                            </div>
                        ` : `
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                ${state.hangouts.map(h => renderHangoutCard(h)).join('')}
                            </div>
                        `}
                    </div>
                </div>
            `;
        }

        function renderHangoutCard(h) {
            const isVoting = h.status === 'voting';
            const isPast = h.status === 'past';
            const taskPct = h.total_tasks > 0 ? Math.round((h.completed_tasks / h.total_tasks) * 100) : 0;

            return `
                <div class="glass-card rounded-3xl overflow-hidden flex flex-col group border border-white/10 hover:border-brand-500/40">
                    <div class="relative h-44 w-full overflow-hidden cursor-pointer" onclick="openHangoutDetail(${h.id})">
                        <img src="${h.cover_image || 'https://images.unsplash.com/photo-1529156069898-49953e39b3ac?auto=format&fit=crop&w=800&q=80'}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" />
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/40 to-transparent"></div>
                        
                        <div class="absolute top-3 left-3">
                            ${isVoting ? `
                                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-amber-500 text-amber-950 font-bold text-[10px] shadow">
                                    <span class="w-2 h-2 rounded-full bg-amber-950 animate-ping"></span>
                                    🗳️ Date Poll
                                </span>
                            ` : isPast ? `
                                <span class="px-3 py-1 rounded-full bg-slate-800 text-slate-300 font-semibold text-[10px]">Past</span>
                            ` : `
                                <span class="px-3 py-1 rounded-full bg-emerald-500 text-emerald-950 font-bold text-[10px] shadow">✓ Confirmed</span>
                            `}
                        </div>

                        <div class="absolute top-3 right-3">
                            <span class="px-2.5 py-0.5 rounded-full bg-black/60 text-white text-[10px] font-medium border border-white/10">${escapeHtml(h.category)}</span>
                        </div>

                        <div class="absolute bottom-3 left-4 right-4">
                            <h3 class="font-display font-bold text-base text-white group-hover:text-brand-300 transition line-clamp-1">${escapeHtml(h.title)}</h3>
                            <div class="flex items-center gap-1.5 text-[11px] text-slate-300 mt-0.5">
                                <i data-lucide="map-pin" class="w-3.5 h-3.5 text-brand-400"></i>
                                <span class="truncate">${escapeHtml(h.location_name)}</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 flex-1 flex flex-col justify-between space-y-3">
                        <div>
                            <div class="flex items-center gap-2 text-xs font-semibold text-brand-300">
                                <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                                <span>${formatHangoutDate(h.event_date, h.event_time)}</span>
                            </div>

                            ${h.purpose ? `
                                <div class="mt-2 text-xs text-slate-300 line-clamp-2 bg-slate-800/40 p-2 rounded-xl border border-white/5">
                                    <strong class="text-amber-400 font-semibold">Kio (Why):</strong> ${escapeHtml(h.purpose)}
                                </div>
                            ` : ''}

                            ${h.total_tasks > 0 ? `
                                <div class="mt-3">
                                    <div class="flex items-center justify-between text-[11px] text-slate-400 mb-1">
                                        <span class="flex items-center gap-1">
                                            <i data-lucide="check-square" class="w-3 h-3 text-emerald-400"></i>
                                            Assigned Tasks:
                                        </span>
                                        <span class="font-semibold text-slate-200">${h.completed_tasks}/${h.total_tasks} completed</span>
                                    </div>
                                    <div class="w-full h-1.5 rounded-full bg-slate-800 overflow-hidden">
                                        <div class="h-full bg-emerald-500 rounded-full transition-all" style="width: ${taskPct}%"></div>
                                    </div>
                                </div>
                            ` : ''}
                        </div>

                        <div class="pt-3 border-t border-white/5 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-1.5">
                                <div class="flex -space-x-2 overflow-hidden">
                                    ${(h.friends_preview || []).map(f => `
                                        <img src="${f.avatar_url}" title="${escapeHtml(f.name)}" class="w-6 h-6 rounded-full object-cover ring-2 ring-slate-900" />
                                    `).join('')}
                                </div>
                                <span class="text-[11px] text-slate-400">${h.invited_count || h.attending_count} friends</span>
                            </div>

                            <button onclick="openHangoutDetail(${h.id})" class="px-3 py-1.5 rounded-xl bg-brand-600/20 hover:bg-brand-600/30 text-brand-300 hover:text-white border border-brand-500/30 text-xs font-semibold transition flex items-center gap-1.5">
                                <span>Details & Tasks</span>
                                <i data-lucide="arrow-right" class="w-3 h-3"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }

        // ==============================================================================
        // VIEW: FRIENDS DIRECTORY (CONTACTS, QUALITIES, BEHAVIOR)
        // ==============================================================================
        function renderFriendsView() {
            return `
                <div class="max-w-7xl mx-auto px-4 sm:px-8 py-6 w-full">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-white/10">
                        <div>
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold mb-2">
                                <i data-lucide="book-open" class="w-3.5 h-3.5"></i>
                                Dosto Ki Directory & Contacts
                            </div>
                            <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-white">
                                Friends Contact Directory
                            </h2>
                            <p class="text-xs sm:text-sm text-slate-400 mt-1">
                                Manage your friends list, their location (kaha rehta hai), profession (kia karta hai), special qualities, and behavior notes.
                            </p>
                        </div>

                        <button onclick="openFriendModal()" class="glow-button flex items-center gap-2 px-4 py-2.5 rounded-2xl text-white font-bold text-xs sm:text-sm self-start sm:self-auto shadow-lg">
                            <i data-lucide="user-plus" class="w-4 h-4"></i>
                            <span>+ Add New Friend</span>
                        </button>
                    </div>

                    <!-- Search and Circle Filters -->
                    <div class="mt-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="relative flex-1 max-w-md">
                            <i data-lucide="search" class="absolute left-3.5 top-3 w-4 h-4 text-slate-400"></i>
                            <input 
                                type="text"
                                value="${escapeHtml(state.friendSearch)}"
                                oninput="handleFriendSearch(this.value)"
                                placeholder="Search by name, phone, area, profession, or qualities..."
                                class="w-full pl-10 pr-4 py-2.5 rounded-2xl bg-slate-800/80 border border-white/10 text-white placeholder-slate-500 text-xs focus:outline-none focus:border-brand-500"
                            />
                        </div>

                        <div class="flex items-center gap-1.5 overflow-x-auto pb-1">
                            ${(() => {
                                const defaultCircles = ['all', 'Best Friend', 'Close Friend', 'College Friend', 'Work Friend', 'Gym Buddy', 'Family & Cousin'];
                                const customCircles = Array.from(new Set(state.friends.map(f => f.circle).filter(c => c && !defaultCircles.includes(c))));
                                const allTabs = [...defaultCircles, ...customCircles];
                                return allTabs.map(circle => `
                                    <button onclick="setFriendCircleFilter('${circle}')" class="px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition ${state.friendCircle === circle ? 'bg-emerald-600 text-white shadow-md' : 'bg-slate-800/60 text-slate-400 hover:text-white'}">
                                        ${circle === 'all' ? 'All Friends' : escapeHtml(circle)}
                                    </button>
                                `).join('');
                            })()}
                        </div>
                    </div>

                    <!-- Friends Cards Grid -->
                    <div class="mt-6">
                        ${state.friends.length === 0 ? `
                            <div class="text-center py-16 glass-card rounded-3xl p-8 max-w-md mx-auto">
                                <i data-lucide="user-x" class="w-12 h-12 text-slate-500 mx-auto mb-3"></i>
                                <h3 class="text-base font-bold text-white">No friends found</h3>
                                <p class="text-xs text-slate-400 mt-1">Add your friends and note down their contact, behavior, and qualities.</p>
                                <button onclick="openFriendModal()" class="mt-4 glow-button px-4 py-2 rounded-xl text-white text-xs font-bold">+ Add A Friend</button>
                            </div>
                        ` : `
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                ${state.friends.map(f => renderFriendCard(f)).join('')}
                            </div>
                        `}
                    </div>
                </div>
            `;
        }

        function renderFriendCard(f) {
            const qualitiesList = f.qualities ? f.qualities.split(',').map(q => q.trim()).filter(Boolean) : [];
            const cleanPhone = f.phone ? f.phone.replace(/[^0-9]/g, '') : '';

            return `
                <div class="glass-card rounded-3xl p-5 border border-white/10 flex flex-col justify-between hover:border-emerald-500/40 relative group">
                    <div>
                        <!-- Friend Header -->
                        <div class="flex items-start gap-3.5">
                            <img src="${f.avatar_url || 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=250&q=80'}" class="w-13 h-13 rounded-2xl object-cover ring-2 ring-emerald-500/30" style="width: 52px; height: 52px;" />
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between">
                                    <h3 class="font-display font-bold text-base text-white truncate">${escapeHtml(f.name)}</h3>
                                    <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-[10px] font-bold whitespace-nowrap">
                                        ${escapeHtml(f.circle || 'Friend')}
                                    </span>
                                </div>
                                
                                <div class="text-xs font-medium text-slate-300 mt-0.5 flex items-center gap-1.5">
                                    <i data-lucide="briefcase" class="w-3.5 h-3.5 text-emerald-400 flex-shrink-0"></i>
                                    <span class="truncate">${escapeHtml(f.occupation || 'Profession not added')}</span>
                                </div>

                                <div class="text-[11px] text-slate-400 mt-1 flex items-center gap-1.5">
                                    <i data-lucide="map-pin" class="w-3.5 h-3.5 text-brand-400 flex-shrink-0"></i>
                                    <span class="truncate">${escapeHtml(f.location || 'Location not added')}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Phone / WhatsApp -->
                        ${f.phone ? `
                            <div class="mt-3.5 p-2 rounded-xl bg-slate-800/60 border border-white/5 flex items-center justify-between text-xs">
                                <span class="text-slate-400 font-mono flex items-center gap-1.5">
                                    <i data-lucide="phone" class="w-3.5 h-3.5 text-emerald-400"></i>
                                    ${escapeHtml(f.phone)}
                                </span>
                                <div class="flex items-center gap-2">
                                    <a href="https://wa.me/${cleanPhone}" target="_blank" rel="noopener noreferrer" class="text-[10px] px-2 py-0.5 rounded bg-emerald-600 hover:bg-emerald-500 text-white font-bold">
                                        WhatsApp
                                    </a>
                                    <a href="tel:${escapeHtml(f.phone)}" class="text-[10px] px-2 py-0.5 rounded bg-slate-700 hover:bg-slate-600 text-slate-200">
                                        Call
                                    </a>
                                </div>
                            </div>
                        ` : ''}

                        <!-- Qualities -->
                        ${qualitiesList.length > 0 ? `
                            <div class="mt-3.5">
                                <div class="text-[10px] uppercase tracking-wider font-bold text-slate-400 mb-1.5 flex items-center gap-1">
                                    <i data-lucide="award" class="w-3 h-3 text-amber-400"></i>
                                    Qualities & Skills:
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    ${qualitiesList.map(q => `
                                        <span class="px-2 py-0.5 rounded-lg bg-indigo-950/60 border border-indigo-500/30 text-indigo-300 text-[11px] font-medium">
                                            ${escapeHtml(q)}
                                        </span>
                                    `).join('')}
                                </div>
                            </div>
                        ` : ''}

                        <!-- Behavior Notes -->
                        ${f.behavior_notes ? `
                            <div class="mt-3.5 p-3 rounded-2xl bg-amber-950/20 border border-amber-500/20 text-xs text-amber-200/90 leading-relaxed">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-amber-400 mb-1 flex items-center gap-1">
                                    <i data-lucide="smile" class="w-3 h-3"></i>
                                    Behavior & Habits:
                                </div>
                                <p class="italic">"${escapeHtml(f.behavior_notes)}"</p>
                            </div>
                        ` : ''}
                    </div>

                    <!-- Card Actions -->
                    <div class="mt-5 pt-3 border-t border-white/5 flex items-center justify-between">
                        <div class="text-[10px] text-slate-500">
                            Invited in ${f.hangout_count || 0} meetups
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button onclick="openFriendModal(${JSON.stringify(f).replace(/"/g, '&quot;')})" class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition" title="Edit Friend">
                                <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                            </button>
                            <button onclick="deleteFriend(${f.id}, '${escapeHtml(f.name)}')" class="p-1.5 rounded-lg bg-rose-950/40 hover:bg-rose-900/60 text-rose-400 hover:text-rose-200 border border-rose-500/20 transition" title="Remove Friend">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }

        // ==============================================================================
        // VIEW: VENUE MAP EXPLORER
        // ==============================================================================
        function renderMapView() {
            return `
                <div class="max-w-7xl mx-auto px-4 sm:px-8 py-6 w-full">
                    <div class="mb-4">
                        <h2 class="font-display text-2xl font-extrabold text-white">Interactive Venue Map</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Explore hangout spots and venues pinned on the map.</p>
                    </div>
                    <div class="relative w-full h-[580px] rounded-3xl overflow-hidden border border-white/10 glass-panel shadow-2xl">
                        <div id="city-map-container" class="w-full h-full"></div>
                    </div>
                </div>
            `;
        }

        function initLeafletCityMap() {
            setTimeout(() => {
                const el = document.getElementById('city-map-container');
                if (!el) return;

                const centerLat = state.hangouts.length > 0 && state.hangouts[0].latitude ? parseFloat(state.hangouts[0].latitude) : 30.1798;
                const centerLng = state.hangouts.length > 0 && state.hangouts[0].longitude ? parseFloat(state.hangouts[0].longitude) : 66.9750;

                const map = L.map(el).setView([centerLat, centerLng], 12);
                L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
                    attribution: '&copy; CartoDB',
                    maxZoom: 19
                }).addTo(map);

                const bounds = [];
                state.hangouts.forEach(h => {
                    if (h.latitude && h.longitude) {
                        const lat = parseFloat(h.latitude);
                        const lng = parseFloat(h.longitude);
                        bounds.push([lat, lng]);

                        const customIcon = L.divIcon({
                            className: 'custom-map-pin',
                            html: `
                                <div style="background: linear-gradient(135deg, #6366f1, #ec4899); width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 15px rgba(99,102,241,0.8); border: 2px solid #ffffff;">
                                    <span style="font-size: 16px;">📍</span>
                                </div>
                            `,
                            iconSize: [34, 34],
                            iconAnchor: [17, 34]
                        });

                        const marker = L.marker([lat, lng], { icon: customIcon }).addTo(map);
                        marker.bindPopup(`
                            <div style="padding: 6px; font-family: 'Plus Jakarta Sans', sans-serif;">
                                <div style="font-size: 14px; font-weight: 700; color: #ffffff; margin-bottom: 2px;">${escapeHtml(h.title)}</div>
                                <div style="font-size: 11px; color: #a5b4fc; margin-bottom: 4px;">📍 ${escapeHtml(h.location_name)}</div>
                                <button onclick="openHangoutDetail(${h.id})" style="width: 100%; background: #4f46e5; color: #ffffff; border: none; border-radius: 8px; padding: 6px 12px; font-size: 11px; font-weight: 600; cursor: pointer; margin-top: 6px;">
                                    View Hangout & Tasks
                                </button>
                            </div>
                        `);
                    }
                });

                if (bounds.length > 0) map.fitBounds(bounds, { padding: [50, 50], maxZoom: 14 });
            }, 100);
        }

        // ==============================================================================
        // ACTIONS & HANDLERS
        // ==============================================================================
        function switchView(viewName) {
            state.currentView = viewName;
            try { localStorage.setItem('friendApp_currentView', viewName); } catch (e) {}
            renderApp();
        }

        let searchDebounce;
        function handleHangoutSearch(val) {
            state.searchQuery = val;
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(async () => {
                const res = await api.get('get_hangouts', { filter: state.activeFilter, search: state.searchQuery });
                if (res.success) {
                    state.hangouts = res.hangouts;
                    renderApp();
                }
            }, 200);
        }

        async function setHangoutFilter(filterName) {
            state.activeFilter = filterName;
            try { localStorage.setItem('friendApp_activeFilter', filterName); } catch (e) {}
            const res = await api.get('get_hangouts', { filter: state.activeFilter, search: state.searchQuery });
            if (res.success) {
                state.hangouts = res.hangouts;
                renderApp();
            }
        }

        let friendSearchDebounce;
        function handleFriendSearch(val) {
            state.friendSearch = val;
            clearTimeout(friendSearchDebounce);
            friendSearchDebounce = setTimeout(async () => {
                const res = await api.get('get_friends', { circle: state.friendCircle, search: state.friendSearch });
                if (res.success) {
                    state.friends = res.friends;
                    renderApp();
                }
            }, 200);
        }

        async function setFriendCircleFilter(circleName) {
            state.friendCircle = circleName;
            try { localStorage.setItem('friendApp_friendCircle', circleName); } catch (e) {}
            const res = await api.get('get_friends', { circle: state.friendCircle, search: state.friendSearch });
            if (res.success) {
                state.friends = res.friends;
                renderApp();
            }
        }

        async function switchUserPersona(targetId) {
            const res = await api.post('switch_user', { user_id: targetId });
            if (res.success) {
                showToast(`Switched persona to ${res.user.name}`);
                loadAppData();
            }
        }

        async function deleteFriend(id, name) {
            if (!confirm(`Are you sure you want to remove "${name}" from your friends contact list?`)) return;
            const res = await api.post('delete_friend', { id });
            if (res.success) {
                showToast(`${name} removed from contacts`);
                loadAppData();
            }
        }

        async function resetSampleData() {
            if (!confirm("Reset demo data with sample Pakistani friends, hangouts & tasks?")) return;
            const res = await api.post('reset_sample_data');
            if (res.success) {
                showToast("Sample data reset successfully!");
                loadAppData();
            }
        }

        // ==============================================================================
        // HANGOUT DETAIL MODAL (KAHA, KIO, KESE, TASKS & REAL-TIME CHAT)
        // ==============================================================================
        let activeDetailTab = 'plan';
        let chatInterval = null;

        async function openHangoutDetail(id) {
            const modalRoot = document.getElementById('modal-container');
            modalRoot.innerHTML = `
                <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-md p-4">
                    <div class="glass-panel p-8 rounded-3xl flex flex-col items-center">
                        <div class="w-10 h-10 border-4 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
                        <span class="text-xs text-slate-300 mt-3">Loading Hangout Details...</span>
                    </div>
                </div>
            `;

            const res = await api.get('get_hangout_detail', { id });
            if (!res.success) {
                showToast("Failed to load hangout details", "error");
                closeModal();
                return;
            }

            state.selectedHangout = res;
            activeDetailTab = 'plan';
            try { localStorage.setItem('friendApp_lastHangoutId', id); } catch (e) {}
            renderHangoutDetailModal();
        }

        function renderHangoutDetailModal() {
            const data = state.selectedHangout;
            if (!data) return;

            const h = data.hangout;
            const modalRoot = document.getElementById('modal-container');

            modalRoot.innerHTML = `
                <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-md p-2 sm:p-6 overflow-y-auto">
                    <div class="relative w-full max-w-4xl bg-slate-900 border border-white/10 rounded-3xl overflow-hidden shadow-2xl flex flex-col max-h-[92vh]">
                        
                        <!-- Header Banner -->
                        <div class="relative h-48 sm:h-56 w-full flex-shrink-0">
                            <img src="${h.cover_image || 'https://images.unsplash.com/photo-1529156069898-49953e39b3ac?auto=format&fit=crop&w=1200&q=80'}" class="w-full h-full object-cover" />
                            <div class="absolute inset-0 bg-gradient-to-t from-slate-900 via-slate-900/60 to-black/30"></div>

                            <button onclick="closeModal()" class="absolute top-4 right-4 p-2 rounded-full bg-black/60 hover:bg-black/90 text-white backdrop-blur-md transition border border-white/10">
                                <i data-lucide="x" class="w-5 h-5"></i>
                            </button>

                            <div class="absolute bottom-4 left-6 right-6">
                                <div class="flex items-center gap-2 mb-1.5">
                                    <span class="px-2.5 py-0.5 rounded-full bg-brand-500 text-white text-[10px] font-bold uppercase tracking-wider">${escapeHtml(h.category)}</span>
                                    ${h.status === 'voting' ? '<span class="px-2.5 py-0.5 rounded-full bg-amber-500 text-amber-950 text-[10px] font-bold">🗳️ Voting Open</span>' : ''}
                                </div>
                                <h2 class="text-2xl sm:text-3xl font-display font-extrabold text-white">${escapeHtml(h.title)}</h2>
                                <div class="flex flex-wrap items-center gap-4 text-xs text-slate-300 mt-2">
                                    <div class="flex items-center gap-1.5">
                                        <i data-lucide="map-pin" class="w-3.5 h-3.5 text-brand-400"></i>
                                        <span>${escapeHtml(h.location_name)}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <i data-lucide="calendar" class="w-3.5 h-3.5 text-brand-400"></i>
                                        <span>${formatHangoutDate(h.event_date, h.event_time)}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab Switcher -->
                        <div class="flex items-center justify-between border-b border-white/10 px-6 py-2.5 bg-slate-950/60 overflow-x-auto gap-2">
                            <div class="flex items-center gap-2">
                                ${[
                                    { id: 'plan', label: '📍 Kaha, Kio & Kese', icon: 'compass' },
                                    { id: 'tasks', label: `📋 Assigned Tasks (${data.tasks.filter(t => t.is_completed).length}/${data.tasks.length})`, icon: 'check-square' },
                                    { id: 'friends', label: `👥 Squad (${data.invited_friends.length})`, icon: 'users' },
                                    { id: 'poll', label: `🗳️ Date Poll ${h.poll ? `(${h.poll.total_votes})` : ''}`, icon: 'vote' },
                                    { id: 'chat', label: `💬 Live Chat (${data.messages.length})`, icon: 'message-square' }
                                ].map(tab => `
                                    <button onclick="switchDetailTab('${tab.id}')" class="flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition ${activeDetailTab === tab.id ? 'bg-brand-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800'}">
                                        <i data-lucide="${tab.icon}" class="w-3.5 h-3.5"></i>
                                        ${tab.label}
                                    </button>
                                `).join('')}
                            </div>
                        </div>

                        <!-- Detail Body -->
                        <div class="p-6 overflow-y-auto flex-1">
                            ${activeDetailTab === 'plan' ? renderDetailPlanTab(data) : ''}
                            ${activeDetailTab === 'tasks' ? renderDetailTasksTab(data) : ''}
                            ${activeDetailTab === 'friends' ? renderDetailFriendsTab(data) : ''}
                            ${activeDetailTab === 'poll' ? renderDetailPollTab(data) : ''}
                            ${activeDetailTab === 'chat' ? renderDetailChatTab(data) : ''}
                        </div>

                    </div>
                </div>
            `;

            if (window.lucide) window.lucide.createIcons();

            if (activeDetailTab === 'plan') {
                initDetailVenueMap(h);
            }

            if (activeDetailTab === 'chat') {
                startChatSync(h.id);
            } else {
                clearInterval(chatInterval);
            }
        }

        function switchDetailTab(tabId) {
            activeDetailTab = tabId;
            renderHangoutDetailModal();
        }

        function renderDetailPlanTab(data) {
            const h = data.hangout;
            return `
                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="p-4 rounded-2xl bg-slate-800/60 border border-white/5 space-y-2">
                            <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-amber-400">
                                <i data-lucide="target" class="w-4 h-4"></i>
                                <span>Kio (Why / Purpose)</span>
                            </div>
                            <p class="text-xs text-slate-200 leading-relaxed">${escapeHtml(h.purpose || h.description || "Get-together with friends.")}</p>
                        </div>

                        <div class="p-4 rounded-2xl bg-slate-800/60 border border-white/5 space-y-2">
                            <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-400">
                                <i data-lucide="truck" class="w-4 h-4"></i>
                                <span>Kese (How, Transport & Budget)</span>
                            </div>
                            <p class="text-xs text-slate-200 leading-relaxed whitespace-pre-line">${escapeHtml(h.plan_logistics || "Direct meet at venue. Costs split equally.")}</p>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-xs font-bold uppercase tracking-wider text-brand-400 flex items-center gap-1.5">
                                <i data-lucide="map-pin" class="w-4 h-4"></i>
                                <span>Kaha (Venue & Location)</span>
                            </div>
                            ${h.latitude && h.longitude ? `
                                <a href="https://www.google.com/maps/dir/?api=1&destination=${h.latitude},${h.longitude}" target="_blank" rel="noopener noreferrer" class="text-xs text-brand-400 hover:text-brand-300 font-bold flex items-center gap-1">
                                    <span>Open in Google Maps</span>
                                    <i data-lucide="external-link" class="w-3 h-3"></i>
                                </a>
                            ` : ''}
                        </div>
                        <div class="p-3 bg-slate-800/40 rounded-2xl border border-white/5 mb-3">
                            <div class="text-sm font-bold text-white">${escapeHtml(h.location_name)}</div>
                            <div class="text-xs text-slate-400">${escapeHtml(h.address || 'Address pinned on map below')}</div>
                        </div>
                        <div id="detail-map-box" class="w-full h-52 rounded-2xl overflow-hidden border border-white/10 z-0"></div>
                    </div>
                </div>
            `;
        }

        function initDetailVenueMap(h) {
            setTimeout(() => {
                const el = document.getElementById('detail-map-box');
                if (!el || !h.latitude) return;

                const lat = parseFloat(h.latitude);
                const lng = parseFloat(h.longitude);
                const map = L.map(el).setView([lat, lng], 14);
                L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
                    attribution: '&copy; CartoDB',
                    maxZoom: 19
                }).addTo(map);

                const marker = L.marker([lat, lng]).addTo(map);
                marker.bindPopup(`<b>${escapeHtml(h.location_name)}</b><br>${escapeHtml(h.address || '')}`).openPopup();
            }, 100);
        }

        function renderDetailTasksTab(data) {
            const h = data.hangout;
            return `
                <div class="space-y-6">
                    <form onsubmit="handleInlineAddTask(event, ${h.id})" class="p-4 rounded-2xl bg-slate-800/70 border border-brand-500/30 flex flex-col sm:flex-row items-center gap-3">
                        <input type="text" id="inline-task-title" required placeholder="Assign a task: e.g. Bring Bluetooth Speaker, Buy charcoal..." class="flex-1 bg-slate-900 border border-white/10 rounded-xl px-3.5 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-brand-500 w-full" />
                        <select id="inline-task-friend" class="bg-slate-900 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-brand-500 w-full sm:w-48">
                            <option value="">Assign To: Anyone</option>
                            ${state.friends.map(f => `<option value="${f.id}">${escapeHtml(f.name)}</option>`).join('')}
                        </select>
                        <button type="submit" class="glow-button px-4 py-2 rounded-xl text-white font-bold text-xs whitespace-nowrap w-full sm:w-auto">+ Assign Task</button>
                    </form>

                    <div class="space-y-3">
                        <div class="text-xs uppercase font-bold text-slate-400">Task Checklist</div>
                        ${data.tasks.length === 0 ? `
                            <div class="text-center py-10 bg-slate-800/30 rounded-2xl p-4 text-xs text-slate-400">
                                No tasks assigned yet. Use the bar above to assign jobs (e.g. BBQ gear, carpool, games) to your friends!
                            </div>
                        ` : data.tasks.map(t => `
                            <div class="p-3.5 rounded-2xl border transition flex items-center justify-between gap-3 ${t.is_completed ? 'bg-emerald-950/20 border-emerald-500/30' : 'bg-slate-800/50 border-white/5'}">
                                <div class="flex items-center gap-3 flex-1 min-w-0">
                                    <input type="checkbox" ${t.is_completed ? 'checked' : ''} onchange="toggleTaskStatus(${t.id}, ${h.id})" class="w-4 h-4 rounded text-brand-600 focus:ring-0 cursor-pointer accent-brand-500" />
                                    <div class="min-w-0">
                                        <div class="text-xs font-semibold ${t.is_completed ? 'line-through text-slate-500' : 'text-white'}">${escapeHtml(t.title)}</div>
                                        ${t.description ? `<div class="text-[10px] text-slate-400">${escapeHtml(t.description)}</div>` : ''}
                                    </div>
                                </div>

                                ${t.friend_name ? `
                                    <div class="flex items-center gap-2 bg-slate-900/80 px-2.5 py-1 rounded-xl border border-white/5">
                                        <img src="${t.friend_avatar}" class="w-5 h-5 rounded-full object-cover" />
                                        <div class="text-right">
                                            <div class="text-[11px] font-bold text-brand-300">${escapeHtml(t.friend_name)}</div>
                                            ${t.friend_phone ? `<div class="text-[9px] text-slate-500 font-mono">${escapeHtml(t.friend_phone)}</div>` : ''}
                                        </div>
                                    </div>
                                ` : '<span class="text-[10px] text-slate-500 bg-slate-800 px-2 py-0.5 rounded">Unassigned</span>'}
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        async function handleInlineAddTask(e, hangoutId) {
            e.preventDefault();
            const title = document.getElementById('inline-task-title').value.trim();
            const friendId = document.getElementById('inline-task-friend').value;
            if (!title) return;

            const res = await api.post('assign_task', {
                hangout_id: hangoutId,
                friend_id: friendId || null,
                title: title
            });

            if (res.success) {
                showToast("Task assigned!");
                openHangoutDetail(hangoutId);
                loadAppData();
            }
        }

        async function toggleTaskStatus(taskId, hangoutId) {
            const res = await api.post('toggle_task', { task_id: taskId });
            if (res.success) {
                openHangoutDetail(hangoutId);
                loadAppData();
            }
        }

        function renderDetailFriendsTab(data) {
            return `
                <div class="space-y-4">
                    <div class="text-xs uppercase font-bold text-slate-400">Friends In This Hangout</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        ${data.invited_friends.map(f => {
                            const clean = f.phone ? f.phone.replace(/[^0-9]/g, '') : '';
                            return `
                                <div class="p-3 rounded-2xl bg-slate-800/50 border border-white/5 flex items-center gap-3">
                                    <img src="${f.avatar_url}" class="w-10 h-10 rounded-xl object-cover" />
                                    <div class="flex-1 min-w-0">
                                        <div class="text-xs font-bold text-white truncate">${escapeHtml(f.name)}</div>
                                        <div class="text-[10px] text-emerald-400 font-medium">${escapeHtml(f.occupation || '')}</div>
                                        <div class="text-[10px] text-slate-400 truncate mt-0.5">📍 ${escapeHtml(f.location || '')}</div>
                                    </div>
                                    ${f.phone ? `
                                        <a href="https://wa.me/${clean}" target="_blank" rel="noopener noreferrer" class="p-2 rounded-xl bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-300 border border-emerald-500/30 text-xs">
                                            <i data-lucide="message-circle" class="w-3.5 h-3.5"></i>
                                        </a>
                                    ` : ''}
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
        }

        function renderDetailPollTab(data) {
            const p = data.hangout.poll;
            if (!p) {
                return '<div class="text-center py-8 text-xs text-slate-400">This event has a confirmed date and no active scheduling poll.</div>';
            }

            return `
                <div class="bg-slate-800/50 p-5 rounded-2xl border border-white/10 space-y-4">
                    <h3 class="text-sm font-bold text-white">${escapeHtml(p.question)}</h3>
                    <div class="space-y-3">
                        ${p.options.map(opt => {
                            const pct = p.total_votes > 0 ? Math.round((opt.vote_count / p.total_votes) * 100) : 0;
                            return `
                                <div class="relative rounded-xl p-3 bg-slate-900 border border-white/10 overflow-hidden">
                                    <div class="absolute inset-0 bg-brand-600/20" style="width: ${pct}%"></div>
                                    <div class="relative z-10 flex items-center justify-between">
                                        <span class="text-xs font-semibold text-white">${escapeHtml(opt.option_text)}</span>
                                        <button onclick="handleVotePoll(${opt.id}, ${data.hangout.id})" class="px-3 py-1 rounded-lg text-xs font-bold transition ${opt.user_voted ? 'bg-rose-500 text-white' : 'bg-brand-600 text-white'}">
                                            ${opt.user_voted ? 'Voted ✓' : 'Vote'} (${pct}%)
                                        </button>
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
        }

        async function handleVotePoll(optionId, hangoutId) {
            const res = await api.post('vote_poll', { option_id: optionId, hangout_id: hangoutId });
            if (res.success) {
                showToast(res.voted ? "Vote recorded!" : "Vote removed!");
                openHangoutDetail(hangoutId);
                loadAppData();
            }
        }

        function renderDetailChatTab(data) {
            return `
                <div class="flex flex-col h-[380px]">
                    <div id="chat-messages-container" class="flex-1 overflow-y-auto space-y-2.5 pr-2">
                        ${data.messages.map(m => {
                            const isMine = m.user_id === state.currentUser?.id;
                            return `
                                <div class="flex items-start gap-2 ${isMine ? 'flex-row-reverse' : ''}">
                                    <img src="${m.user_avatar}" class="w-6 h-6 rounded-full object-cover mt-1" />
                                    <div class="max-w-[75%] rounded-2xl px-3.5 py-2 text-xs ${isMine ? 'bg-brand-600 text-white' : 'bg-slate-800 text-slate-200'}">
                                        <div class="text-[10px] font-bold text-indigo-300">${escapeHtml(m.user_name)}</div>
                                        <div class="mt-0.5">${escapeHtml(m.message)}</div>
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>

                    <form onsubmit="handleSendChat(event, ${data.hangout.id})" class="mt-3 pt-3 border-t border-white/10 flex items-center gap-2">
                        <input type="text" id="chat-input" placeholder="Write message to the squad..." class="flex-1 bg-slate-800 border border-white/10 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-brand-500" />
                        <button type="submit" class="glow-button px-4 py-2 rounded-xl text-white font-bold text-xs">Send</button>
                    </form>
                </div>
            `;
        }

        function startChatSync(hangoutId) {
            clearInterval(chatInterval);
            chatInterval = setInterval(async () => {
                const res = await api.get('get_messages', { hangout_id: hangoutId });
                if (res.success && state.selectedHangout) {
                    state.selectedHangout.messages = res.messages;
                    const container = document.getElementById('chat-messages-container');
                    if (container) {
                        container.innerHTML = res.messages.map(m => {
                            const isMine = m.user_id === state.currentUser?.id;
                            return `
                                <div class="flex items-start gap-2 ${isMine ? 'flex-row-reverse' : ''}">
                                    <img src="${m.user_avatar}" class="w-6 h-6 rounded-full object-cover mt-1" />
                                    <div class="max-w-[75%] rounded-2xl px-3.5 py-2 text-xs ${isMine ? 'bg-brand-600 text-white' : 'bg-slate-800 text-slate-200'}">
                                        <div class="text-[10px] font-bold text-indigo-300">${escapeHtml(m.user_name)}</div>
                                        <div class="mt-0.5">${escapeHtml(m.message)}</div>
                                    </div>
                                </div>
                            `;
                        }).join('');
                    }
                }
            }, 3000);
        }

        async function handleSendChat(e, hangoutId) {
            e.preventDefault();
            const input = document.getElementById('chat-input');
            const text = input.value.trim();
            if (!text) return;
            input.value = '';

            await api.post('send_message', { hangout_id: hangoutId, message: text });
            const res = await api.get('get_messages', { hangout_id: hangoutId });
            if (res.success) {
                state.selectedHangout.messages = res.messages;
                renderHangoutDetailModal();
            }
        }

        // ==============================================================================
        // PLAN HANGOUT MODAL (KAHA, KIO, KESE, DOST SELECTION & TASKS)
        // ==============================================================================
        let createTasksList = [
            { title: 'Bring Bluetooth Speaker & Playlist', friend_id: '' },
            { title: 'Pick up snacks and ice', friend_id: '' }
        ];

        function openPlanHangoutModal() {
            createTasksList = [
                { title: 'Bring Bluetooth Speaker & Playlist', friend_id: state.friends[0]?.id || '' },
                { title: 'Pick up snacks and ice', friend_id: state.friends[1]?.id || '' }
            ];

            const modalRoot = document.getElementById('modal-container');
            modalRoot.innerHTML = `
                <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-md p-3 sm:p-6 overflow-y-auto">
                    <div class="relative w-full max-w-2xl bg-slate-900 border border-white/10 rounded-3xl overflow-hidden shadow-2xl p-6 sm:p-8 max-h-[90vh] overflow-y-auto">
                        <div class="flex items-center justify-between pb-4 border-b border-white/10">
                            <div>
                                <h3 class="font-display text-xl font-bold text-white">Plan Hangout & Assign Tasks</h3>
                                <p class="text-xs text-slate-400 mt-0.5">Specify Kaha, Kio, Kese and select which friends to invite.</p>
                            </div>
                            <button onclick="closeModal()" class="p-2 rounded-xl bg-slate-800 text-slate-400 hover:text-white">
                                <i data-lucide="x" class="w-5 h-5"></i>
                            </button>
                        </div>

                        <form onsubmit="handleCreateHangoutSubmit(event)" class="mt-5 space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-bold text-slate-300 mb-1">Hangout Title *</label>
                                    <input type="text" id="create-title" required placeholder="e.g. Weekend Rooftop BBQ & Acoustic Jam" class="w-full bg-slate-800 border border-white/10 rounded-xl px-3.5 py-2 text-xs text-white" />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-300 mb-1">Vibe Category</label>
                                    <select id="create-category" onchange="toggleCustomHangoutCategory(this.value)" class="w-full bg-slate-800 border border-white/10 rounded-xl px-3 py-2 text-xs text-white">
                                        <option value="Parties & Social">Parties & Social</option>
                                        <option value="Food & Dining">Food & Dining</option>
                                        <option value="Gaming & Chill">Gaming & Chill</option>
                                        <option value="Outdoor & Nature">Outdoor & Nature</option>
                                        <option value="Coffee & Coworking">Coffee & Coworking</option>
                                        <option value="Sports & Fitness">Sports & Fitness</option>
                                        <option value="Road Trip & Travel">Road Trip & Travel</option>
                                        <option value="Qawwali & Music">Qawwali & Music</option>
                                        <option value="__CUSTOM__">➕ Custom Category...</option>
                                    </select>
                                    <div id="custom-hangout-category-box" class="hidden mt-2">
                                        <input type="text" id="create-custom-category" placeholder="Enter custom vibe category..." class="w-full bg-slate-950 border border-brand-500 rounded-xl px-3 py-1.5 text-xs text-brand-200 placeholder-slate-500" />
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-amber-400 mb-1 flex items-center gap-1.5">
                                    <i data-lucide="target" class="w-3.5 h-3.5"></i>
                                    Kio (Why / Purpose of this hangout)
                                </label>
                                <textarea id="create-purpose" rows="2" placeholder="e.g. Celebrating Bilal's promotion, chilling out after exam stress..." class="w-full bg-slate-800 border border-white/10 rounded-xl px-3.5 py-2 text-xs text-white"></textarea>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-brand-400 mb-1 flex items-center justify-between">
                                    <span class="flex items-center gap-1.5">
                                        <i data-lucide="map-pin" class="w-3.5 h-3.5"></i>
                                        Kaha (Venue Spot in Quetta / Balochistan) *
                                    </span>
                                </label>
                                <input type="text" id="create-location" required placeholder="e.g. Hanna Lake & Urrak Valley, Quetta" class="w-full bg-slate-800 border border-white/10 rounded-xl px-3.5 py-2 text-xs text-white mb-2" />
                                
                                <!-- Quetta Popular Presets -->
                                <div class="flex items-center gap-1.5 overflow-x-auto text-[11px] pb-1">
                                    <span class="text-slate-400 text-[10px] uppercase font-bold whitespace-nowrap">Quetta Spots:</span>
                                    <button type="button" onclick="setPresetLocation('Hanna Lake & Urrak Valley, Quetta', 30.2520, 67.0980)" class="px-2 py-0.5 rounded-lg bg-slate-800 hover:bg-brand-600 text-slate-300 hover:text-white border border-white/5 whitespace-nowrap">Hanna Lake</button>
                                    <button type="button" onclick="setPresetLocation('Prince Road Food Street (Namkeen Rosh), Quetta', 30.1880, 67.0110)" class="px-2 py-0.5 rounded-lg bg-slate-800 hover:bg-brand-600 text-slate-300 hover:text-white border border-white/5 whitespace-nowrap">Prince Road</button>
                                    <button type="button" onclick="setPresetLocation('Ziarat Juniper Valley & Quaid Residency', 30.3810, 67.7270)" class="px-2 py-0.5 rounded-lg bg-slate-800 hover:bg-brand-600 text-slate-300 hover:text-white border border-white/5 whitespace-nowrap">Ziarat Valley</button>
                                    <button type="button" onclick="setPresetLocation('Serena Hotel Quetta (Zarghoon Lounge)', 30.1985, 67.0145)" class="px-2 py-0.5 rounded-lg bg-slate-800 hover:bg-brand-600 text-slate-300 hover:text-white border border-white/5 whitespace-nowrap">Serena Hotel</button>
                                    <button type="button" onclick="setPresetLocation('Millennium Mall & Cafe Boulevard, Cantt Quetta', 30.1920, 67.0090)" class="px-2 py-0.5 rounded-lg bg-slate-800 hover:bg-brand-600 text-slate-300 hover:text-white border border-white/5 whitespace-nowrap">Millennium Mall</button>
                                </div>
                                <input type="hidden" id="create-lat" value="30.1798" />
                                <input type="hidden" id="create-lng" value="66.9750" />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-emerald-400 mb-1 flex items-center gap-1.5">
                                    <i data-lucide="truck" class="w-3.5 h-3.5"></i>
                                    Kese (How: Logistics, Carpool, Budget & What to bring)
                                </label>
                                <textarea id="create-logistics" rows="2" placeholder="e.g. Meet at Model Town at 4:30 PM. Bilal driving 4x4 Cruiser. Budget: Rs. 1,500/person. Bring warm jacket." class="w-full bg-slate-800 border border-white/10 rounded-xl px-3.5 py-2 text-xs text-white"></textarea>
                            </div>

                            <!-- Kon Kon Se Dost -->
                            <div class="p-3.5 rounded-2xl bg-slate-800/60 border border-white/5 space-y-2">
                                <label class="block text-xs font-bold text-white">Kon Kon Se Dost (Select Friends to Invite):</label>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-36 overflow-y-auto pr-1">
                                    ${state.friends.map(f => `
                                        <label class="p-2 rounded-xl border bg-slate-900 border-white/10 flex items-center gap-2 cursor-pointer text-xs">
                                            <input type="checkbox" name="invited_friends" value="${f.id}" checked class="w-3.5 h-3.5 rounded accent-brand-500" />
                                            <img src="${f.avatar_url}" class="w-6 h-6 rounded-full object-cover" />
                                            <span class="truncate">${escapeHtml(f.name)}</span>
                                        </label>
                                    `).join('')}
                                </div>
                            </div>

                            <!-- Assigned Tasks -->
                            <div class="p-3.5 rounded-2xl bg-slate-800/60 border border-white/5 space-y-2">
                                <label class="block text-xs font-bold text-white flex items-center justify-between">
                                    <span>Tasks Assigned To Friends:</span>
                                    <button type="button" onclick="addCreateTaskRow()" class="text-[11px] text-emerald-400 hover:text-emerald-300 font-bold">+ Add Task</button>
                                </label>
                                <div id="create-tasks-container" class="space-y-2 max-h-36 overflow-y-auto pr-1">
                                    ${createTasksList.map((t, idx) => `
                                        <div class="flex items-center gap-2" id="task-row-${idx}">
                                            <input type="text" value="${escapeHtml(t.title)}" placeholder="Task title..." class="task-title-input flex-1 bg-slate-900 border border-white/10 rounded-xl px-3 py-1.5 text-xs text-white" />
                                            <select class="task-friend-select bg-slate-900 border border-white/10 rounded-xl px-2.5 py-1.5 text-xs text-white w-36">
                                                <option value="">Assign To...</option>
                                                ${state.friends.map(f => `<option value="${f.id}" ${f.id == t.friend_id ? 'selected' : ''}>${escapeHtml(f.name)}</option>`).join('')}
                                            </select>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>

                            <!-- Date / Time -->
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] text-slate-400 mb-1">Date</label>
                                    <input type="date" id="create-date" class="w-full bg-slate-800 border border-white/10 rounded-xl px-3 py-1.5 text-xs text-white" />
                                </div>
                                <div>
                                    <label class="block text-[11px] text-slate-400 mb-1">Time</label>
                                    <input type="time" id="create-time" value="19:00" class="w-full bg-slate-800 border border-white/10 rounded-xl px-3 py-1.5 text-xs text-white" />
                                </div>
                            </div>

                            <div class="pt-3 border-t border-white/10 flex items-center justify-end gap-3">
                                <button type="button" onclick="closeModal()" class="px-4 py-2 rounded-xl bg-slate-800 text-xs font-semibold text-slate-300">Cancel</button>
                                <button type="submit" class="glow-button px-6 py-2.5 rounded-xl text-white font-bold text-xs">Create Hangout & Assign Tasks 🎉</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;

            if (window.lucide) window.lucide.createIcons();
        }

        function addCreateTaskRow() {
            const container = document.getElementById('create-tasks-container');
            const div = document.createElement('div');
            div.className = 'flex items-center gap-2';
            div.innerHTML = `
                <input type="text" placeholder="Task title..." class="task-title-input flex-1 bg-slate-900 border border-white/10 rounded-xl px-3 py-1.5 text-xs text-white" />
                <select class="task-friend-select bg-slate-900 border border-white/10 rounded-xl px-2.5 py-1.5 text-xs text-white w-36">
                    <option value="">Assign To...</option>
                    ${state.friends.map(f => `<option value="${f.id}">${escapeHtml(f.name)}</option>`).join('')}
                </select>
            `;
            container.appendChild(div);
        }

        function setPresetLocation(name, lat, lng) {
            const locInput = document.getElementById('create-location');
            const latInput = document.getElementById('create-lat');
            const lngInput = document.getElementById('create-lng');
            if (locInput) locInput.value = name;
            if (latInput) latInput.value = lat;
            if (lngInput) lngInput.value = lng;
        }

        async function handleCreateHangoutSubmit(e) {
            e.preventDefault();
            const title = document.getElementById('create-title').value.trim();
            let category = document.getElementById('create-category').value;
            if (category === '__CUSTOM__') {
                const customVal = document.getElementById('create-custom-category')?.value.trim();
                category = customVal || 'Custom Meetup';
            }
            const purpose = document.getElementById('create-purpose').value.trim();
            const location = document.getElementById('create-location').value.trim();
            const lat = parseFloat(document.getElementById('create-lat')?.value || '30.1798');
            const lng = parseFloat(document.getElementById('create-lng')?.value || '66.9750');
            const logistics = document.getElementById('create-logistics').value.trim();
            const date = document.getElementById('create-date').value;
            const time = document.getElementById('create-time').value;

            const friendCheckboxes = document.querySelectorAll('input[name="invited_friends"]:checked');
            const invitedFriendIds = Array.from(friendCheckboxes).map(cb => parseInt(cb.value));

            const taskTitles = document.querySelectorAll('.task-title-input');
            const taskFriends = document.querySelectorAll('.task-friend-select');
            const tasks = [];
            taskTitles.forEach((el, i) => {
                const t = el.value.trim();
                if (t) {
                    tasks.push({ title: t, friend_id: taskFriends[i]?.value || null });
                }
            });

            const res = await api.post('create_hangout', {
                title,
                category,
                purpose,
                location_name: location,
                latitude: lat,
                longitude: lng,
                plan_logistics: logistics,
                event_date: date || null,
                event_time: time || null,
                invited_friend_ids: invitedFriendIds,
                initial_tasks: tasks
            });

            if (res.success) {
                showToast("Hangout created successfully!");
                closeModal();
                loadAppData();
                openHangoutDetail(res.hangout_id);
            } else {
                showToast(res.error || "Failed to create hangout", "error");
            }
        }

        // ==============================================================================
        // FRIEND FORM MODAL (ADD & EDIT)
        // ==============================================================================
        // ==============================================================================
        // FRIEND FORM MODAL (ADD & EDIT) & AUTO-AVATAR GENDER SELECTION
        // ==============================================================================
        const PRESET_CIRCLES = ['Best Friend', 'Close Friend', 'College Friend', 'Work Friend', 'Gym Buddy', 'Neighborhood', 'Family & Cousin'];

        function openFriendModal(existingFriend = null) {
            const isEditing = Boolean(existingFriend);
            const modalRoot = document.getElementById('modal-container');

            const existingGender = existingFriend ? detectGenderFromName(existingFriend.name) : 'male';
            currentModalGender = existingGender;

            const initialAvatar = existingFriend?.avatar_url || getRandomAvatar(existingGender);
            const isCustomCircle = existingFriend && existingFriend.circle && !PRESET_CIRCLES.includes(existingFriend.circle);

            modalRoot.innerHTML = `
                <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-md p-3 sm:p-6 overflow-y-auto">
                    <div class="relative w-full max-w-lg bg-slate-900 border border-white/10 rounded-3xl overflow-hidden shadow-2xl p-6 sm:p-8 max-h-[92vh] overflow-y-auto">
                        <div class="flex items-center justify-between pb-4 border-b border-white/10">
                            <div>
                                <h3 class="font-display text-xl font-bold text-white">
                                    ${isEditing ? `Edit ${escapeHtml(existingFriend.name)}'s Details` : 'Add Friend to Directory'}
                                </h3>
                                <p class="text-xs text-slate-400 mt-0.5">Contact info, auto-avatar selection, qualities, and where they live.</p>
                            </div>
                            <button onclick="closeModal()" class="p-2 rounded-xl bg-slate-800 text-slate-400 hover:text-white">
                                <i data-lucide="x" class="w-5 h-5"></i>
                            </button>
                        </div>

                        <form onsubmit="handleFriendFormSubmit(event, ${existingFriend ? existingFriend.id : 'null'})" class="mt-5 space-y-4">
                            <!-- Name & Circle -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-slate-300 mb-1">Friend Name *</label>
                                    <input 
                                        type="text" 
                                        id="friend-name" 
                                        required 
                                        value="${existingFriend ? escapeHtml(existingFriend.name) : ''}" 
                                        oninput="handleFriendNameInput(this.value)" 
                                        placeholder="e.g. Fatima Noor or Bilal Ahmed" 
                                        class="w-full bg-slate-800 border border-white/10 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-brand-500" 
                                    />
                                    <span class="text-[10px] text-slate-400 mt-0.5 block">Auto selects female/male portrait as you type!</span>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-300 mb-1">Circle / Category</label>
                                    <select id="friend-circle" onchange="toggleCustomFriendCircle(this.value)" class="w-full bg-slate-800 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-brand-500">
                                        ${PRESET_CIRCLES.map(c => `
                                            <option value="${c}" ${existingFriend && existingFriend.circle === c ? 'selected' : ''}>${c}</option>
                                        `).join('')}
                                        <option value="__CUSTOM__" ${isCustomCircle ? 'selected' : ''}>➕ Custom Circle...</option>
                                    </select>
                                    <div id="custom-circle-box" class="${isCustomCircle ? '' : 'hidden'} mt-2">
                                        <input 
                                            type="text" 
                                            id="friend-custom-circle" 
                                            value="${isCustomCircle ? escapeHtml(existingFriend.circle) : ''}" 
                                            placeholder="Enter custom circle (e.g. Travel Crew, Cricket Team)..." 
                                            class="w-full bg-slate-950 border border-emerald-500 rounded-xl px-3 py-1.5 text-xs text-emerald-200 placeholder-slate-500" 
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Avatar & Smart Gender Selector -->
                            <div class="p-3.5 rounded-2xl bg-slate-800/60 border border-white/10 space-y-3">
                                <div class="flex items-center justify-between">
                                    <label class="text-xs font-bold text-white flex items-center gap-1.5">
                                        <i data-lucide="camera" class="w-3.5 h-3.5 text-brand-400"></i>
                                        Avatar Picture & Smart Gender Detection
                                    </label>
                                    <span id="detected-gender-badge" class="px-2.5 py-0.5 rounded-full text-[10px] font-bold ${existingGender === 'female' ? 'bg-pink-500/20 text-pink-300 border border-pink-500/30' : 'bg-blue-500/20 text-blue-300 border border-blue-500/30'}">
                                        ${existingGender === 'female' ? '👩 Female Profile' : '👨 Male Profile'}
                                    </span>
                                </div>

                                <div class="flex items-center gap-4">
                                    <div class="relative flex-shrink-0">
                                        <img id="friend-avatar-preview" src="${initialAvatar}" class="w-16 h-16 rounded-2xl object-cover ring-2 ring-brand-500 shadow-lg" />
                                        <span id="gender-icon-badge" class="absolute -bottom-1 -right-1 w-6 h-6 rounded-full bg-slate-900 border border-white/20 flex items-center justify-center text-xs">
                                            ${existingGender === 'female' ? '👩' : '👨'}
                                        </span>
                                    </div>

                                    <div class="flex-1 space-y-2">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <button type="button" onclick="setFriendGenderManual('male')" class="px-2.5 py-1 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-white/10 text-xs font-medium flex items-center gap-1 transition">
                                                <span>👨 Male Avatar</span>
                                            </button>
                                            <button type="button" onclick="setFriendGenderManual('female')" class="px-2.5 py-1 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-white/10 text-xs font-medium flex items-center gap-1 transition">
                                                <span>👩 Female Avatar</span>
                                            </button>
                                            <button type="button" onclick="randomizeCurrentAvatar()" class="px-2.5 py-1 rounded-xl bg-brand-600/20 hover:bg-brand-600/30 text-brand-300 border border-brand-500/30 text-xs font-bold flex items-center gap-1 transition">
                                                <i data-lucide="refresh-cw" class="w-3 h-3"></i>
                                                <span>🎲 New Pic</span>
                                            </button>
                                        </div>
                                        
                                        <div>
                                            <input 
                                                type="url" 
                                                id="friend-avatar" 
                                                oninput="handleManualAvatarUrl(this.value)" 
                                                value="${initialAvatar}" 
                                                placeholder="Avatar image URL (auto-generated or paste custom)..." 
                                                class="w-full bg-slate-900 border border-white/10 rounded-xl px-3 py-1.5 text-[11px] text-slate-300 font-mono focus:outline-none focus:border-brand-500" 
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-slate-300 mb-1">Phone / WhatsApp</label>
                                    <input type="text" id="friend-phone" value="${existingFriend ? escapeHtml(existingFriend.phone || '') : ''}" placeholder="+92 300 1234567" class="w-full bg-slate-800 border border-white/10 rounded-xl px-3.5 py-2 text-xs text-white font-mono" />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-300 mb-1">Email (Optional)</label>
                                    <input type="email" id="friend-email" value="${existingFriend ? escapeHtml(existingFriend.email || '') : ''}" placeholder="friend@example.com" class="w-full bg-slate-800 border border-white/10 rounded-xl px-3.5 py-2 text-xs text-white" />
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-brand-400 mb-1 flex items-center gap-1.5">
                                    <i data-lucide="map-pin" class="w-3.5 h-3.5"></i>
                                    Kaha Rehta Hai (Location / Area / City)
                                </label>
                                <input type="text" id="friend-location" value="${existingFriend ? escapeHtml(existingFriend.location || '') : ''}" placeholder="e.g. Model Town / Zarghoon Road, Quetta" class="w-full bg-slate-800 border border-white/10 rounded-xl px-3.5 py-2 text-xs text-white" />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-emerald-400 mb-1 flex items-center gap-1.5">
                                    <i data-lucide="briefcase" class="w-3.5 h-3.5"></i>
                                    Kia Karta Hai (Profession / Job / Studies)
                                </label>
                                <input type="text" id="friend-occupation" value="${existingFriend ? escapeHtml(existingFriend.occupation || '') : ''}" placeholder="e.g. Software Engineer, Doctor, Student, Banker" class="w-full bg-slate-800 border border-white/10 rounded-xl px-3.5 py-2 text-xs text-white" />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-indigo-400 mb-1 flex items-center gap-1.5">
                                    <i data-lucide="award" class="w-3.5 h-3.5"></i>
                                    Qualities / Skills (Comma-separated)
                                </label>
                                <input type="text" id="friend-qualities" value="${existingFriend ? escapeHtml(existingFriend.qualities || '') : ''}" placeholder="e.g. BBQ Master, 4x4 Driver, Guitarist, Punctual" class="w-full bg-slate-800 border border-white/10 rounded-xl px-3.5 py-2 text-xs text-white" />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-amber-400 mb-1 flex items-center gap-1.5">
                                    <i data-lucide="smile" class="w-3.5 h-3.5"></i>
                                    Behavior & Habits (Kaisa behavior hai)
                                </label>
                                <textarea id="friend-behavior" rows="2" placeholder="e.g. Hamesha time pe ata hai, driving bohot achi karta hai, calm aur chill nature hai." class="w-full bg-slate-800 border border-white/10 rounded-xl px-3.5 py-2 text-xs text-white">${existingFriend ? escapeHtml(existingFriend.behavior_notes || '') : ''}</textarea>
                            </div>

                            <div class="pt-3 border-t border-white/10 flex items-center justify-end gap-3">
                                <button type="button" onclick="closeModal()" class="px-4 py-2 rounded-xl bg-slate-800 text-xs font-semibold text-slate-300">Cancel</button>
                                <button type="submit" class="glow-button px-6 py-2.5 rounded-xl text-white font-bold text-xs">${isEditing ? 'Save Changes' : '+ Add Friend'}</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;

            if (window.lucide) window.lucide.createIcons();
        }

        function handleFriendNameInput(name) {
            const gender = detectGenderFromName(name);
            const badge = document.getElementById('detected-gender-badge');
            const iconBadge = document.getElementById('gender-icon-badge');
            const avatarImg = document.getElementById('friend-avatar-preview');
            const avatarInput = document.getElementById('friend-avatar');

            if (currentModalGender !== gender) {
                currentModalGender = gender;
                const newPic = getRandomAvatar(gender);
                if (avatarImg) avatarImg.src = newPic;
                if (avatarInput) avatarInput.value = newPic;
            }

            if (badge) {
                if (gender === 'female') {
                    badge.innerHTML = '👩 Auto: Female Profile';
                    badge.className = 'px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-pink-500/20 text-pink-300 border border-pink-500/30';
                } else {
                    badge.innerHTML = '👨 Auto: Male Profile';
                    badge.className = 'px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-500/20 text-blue-300 border border-blue-500/30';
                }
            }
            if (iconBadge) {
                iconBadge.innerHTML = gender === 'female' ? '👩' : '👨';
            }
        }

        function setFriendGenderManual(gender) {
            currentModalGender = gender;
            const newPic = getRandomAvatar(gender);
            const badge = document.getElementById('detected-gender-badge');
            const iconBadge = document.getElementById('gender-icon-badge');
            const avatarImg = document.getElementById('friend-avatar-preview');
            const avatarInput = document.getElementById('friend-avatar');

            if (avatarImg) avatarImg.src = newPic;
            if (avatarInput) avatarInput.value = newPic;
            if (badge) {
                badge.innerHTML = gender === 'female' ? '👩 Female Selected' : '👨 Male Selected';
                badge.className = gender === 'female' 
                    ? 'px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-pink-500/20 text-pink-300 border border-pink-500/30'
                    : 'px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-500/20 text-blue-300 border border-blue-500/30';
            }
            if (iconBadge) {
                iconBadge.innerHTML = gender === 'female' ? '👩' : '👨';
            }
        }

        function randomizeCurrentAvatar() {
            const newPic = getRandomAvatar(currentModalGender);
            const avatarImg = document.getElementById('friend-avatar-preview');
            const avatarInput = document.getElementById('friend-avatar');
            if (avatarImg) avatarImg.src = newPic;
            if (avatarInput) avatarInput.value = newPic;
        }

        function handleManualAvatarUrl(url) {
            const avatarImg = document.getElementById('friend-avatar-preview');
            if (avatarImg && url.trim().length > 10) {
                avatarImg.src = url.trim();
            }
        }

        function toggleCustomFriendCircle(val) {
            const box = document.getElementById('custom-circle-box');
            if (box) {
                if (val === '__CUSTOM__') {
                    box.classList.remove('hidden');
                    document.getElementById('friend-custom-circle')?.focus();
                } else {
                    box.classList.add('hidden');
                }
            }
        }

        function toggleCustomHangoutCategory(val) {
            const box = document.getElementById('custom-hangout-category-box');
            if (box) {
                if (val === '__CUSTOM__') {
                    box.classList.remove('hidden');
                    document.getElementById('create-custom-category')?.focus();
                } else {
                    box.classList.add('hidden');
                }
            }
        }

        async function handleFriendFormSubmit(e, existingId) {
            e.preventDefault();
            const name = document.getElementById('friend-name').value.trim();
            let circle = document.getElementById('friend-circle').value;
            if (circle === '__CUSTOM__') {
                const customVal = document.getElementById('friend-custom-circle')?.value.trim();
                circle = customVal || 'Custom Circle';
            }
            const phone = document.getElementById('friend-phone').value.trim();
            const email = document.getElementById('friend-email').value.trim();
            const location = document.getElementById('friend-location').value.trim();
            const occupation = document.getElementById('friend-occupation').value.trim();
            const qualities = document.getElementById('friend-qualities').value.trim();
            const behavior_notes = document.getElementById('friend-behavior').value.trim();
            const avatar_url = document.getElementById('friend-avatar').value.trim();

            const payload = { name, circle, phone, email, location, occupation, qualities, behavior_notes, avatar_url };

            let res;
            if (existingId) {
                res = await api.post('update_friend', { id: existingId, ...payload });
            } else {
                res = await api.post('add_friend', payload);
            }

            if (res.success) {
                showToast(existingId ? "Friend details updated!" : "Friend added to directory!");
                closeModal();
                loadAppData();
            } else {
                showToast(res.error || "Failed to save friend", "error");
            }
        }

        function closeModal() {
            clearInterval(chatInterval);
            state.selectedHangout = null;
            try { localStorage.removeItem('friendApp_lastHangoutId'); } catch (e) {}
            document.getElementById('modal-container').innerHTML = '';
        }

        // INITIAL BOOTSTRAP
        window.addEventListener('DOMContentLoaded', () => {
            loadAppData();
        });
    </script>
</body>
</html>
