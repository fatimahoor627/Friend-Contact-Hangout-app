<?php
// ==============================================================================
// FRIENDS HANGOUT - DATABASE CONNECTION & INITIALIZATION
// QUETTA & BALOCHISTAN REGION EDITION
// ==============================================================================

session_start();

function getDbConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $mysqlHost = '127.0.0.1';
    $mysqlPort = '3306';
    $mysqlUser = 'root';
    $mysqlPass = '';
    $mysqlDb = 'friend-app';

    try {
        $pdo = new PDO(
            "mysql:host={$mysqlHost};port={$mysqlPort};charset=utf8mb4",
            $mysqlUser,
            $mysqlPass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$mysqlDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$mysqlDb}`");

        return $pdo;
    } catch (PDOException $e) {
        $sqlitePath = __DIR__ . '/hangout_store.sqlite';
        try {
            $pdo = new PDO("sqlite:{$sqlitePath}", null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            return $pdo;
        } catch (Exception $sqle) {
            die(json_encode([
                'success' => false,
                'error' => 'Database connection failed: ' . $e->getMessage() . ' | SQLite: ' . $sqle->getMessage()
            ]));
        }
    }
}

function initDatabase() {
    $pdo = getDbConnection();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'mysql') {
        $queries = [
            "CREATE TABLE IF NOT EXISTS `users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL,
                `email` VARCHAR(150) NOT NULL UNIQUE,
                `password_hash` VARCHAR(255) NOT NULL,
                `avatar_url` VARCHAR(500) NULL,
                `bio` VARCHAR(255) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            "CREATE TABLE IF NOT EXISTS `friends` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL DEFAULT 1,
                `name` VARCHAR(100) NOT NULL,
                `phone` VARCHAR(40) NULL,
                `email` VARCHAR(150) NULL,
                `location` VARCHAR(255) NULL,
                `occupation` VARCHAR(150) NULL,
                `qualities` VARCHAR(255) NULL,
                `behavior_notes` TEXT NULL,
                `circle` VARCHAR(50) DEFAULT 'Close Friend',
                `avatar_url` VARCHAR(500) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_friend_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            "CREATE TABLE IF NOT EXISTS `hangouts` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `title` VARCHAR(200) NOT NULL,
                `description` TEXT NOT NULL,
                `purpose` TEXT NULL,
                `plan_logistics` TEXT NULL,
                `category` VARCHAR(50) NOT NULL DEFAULT 'Social',
                `location_name` VARCHAR(255) NOT NULL,
                `address` VARCHAR(255) NULL,
                `latitude` DECIMAL(10, 7) NULL,
                `longitude` DECIMAL(10, 7) NULL,
                `event_date` DATE NULL,
                `event_time` TIME NULL,
                `has_poll` TINYINT(1) DEFAULT 0,
                `status` ENUM('upcoming', 'voting', 'past', 'cancelled') DEFAULT 'upcoming',
                `creator_id` INT NOT NULL,
                `cover_image` VARCHAR(500) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_creator` (`creator_id`),
                INDEX `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            "CREATE TABLE IF NOT EXISTS `hangout_tasks` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `hangout_id` INT NOT NULL,
                `friend_id` INT NULL,
                `title` VARCHAR(255) NOT NULL,
                `description` VARCHAR(255) NULL,
                `is_completed` TINYINT(1) DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_task_hangout` (`hangout_id`),
                INDEX `idx_task_friend` (`friend_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            "CREATE TABLE IF NOT EXISTS `hangout_invited_friends` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `hangout_id` INT NOT NULL,
                `friend_id` INT NOT NULL,
                `status` ENUM('invited', 'going', 'maybe', 'busy') DEFAULT 'invited',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_hangout_invited` (`hangout_id`, `friend_id`),
                INDEX `idx_invited_hangout` (`hangout_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            "CREATE TABLE IF NOT EXISTS `hangout_attendees` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `hangout_id` INT NOT NULL,
                `user_id` INT NOT NULL,
                `status` ENUM('attending', 'maybe', 'declined') DEFAULT 'attending',
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_hangout_user` (`hangout_id`, `user_id`),
                INDEX `idx_hangout` (`hangout_id`),
                INDEX `idx_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            "CREATE TABLE IF NOT EXISTS `hangout_polls` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `hangout_id` INT NOT NULL,
                `question` VARCHAR(255) NOT NULL,
                `is_closed` TINYINT(1) DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_poll_hangout` (`hangout_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            "CREATE TABLE IF NOT EXISTS `hangout_poll_options` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `poll_id` INT NOT NULL,
                `option_text` VARCHAR(255) NOT NULL,
                `option_datetime` DATETIME NULL,
                INDEX `idx_option_poll` (`poll_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            "CREATE TABLE IF NOT EXISTS `hangout_poll_votes` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `option_id` INT NOT NULL,
                `user_id` INT NOT NULL,
                `voted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_poll_vote` (`option_id`, `user_id`),
                INDEX `idx_vote_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            "CREATE TABLE IF NOT EXISTS `hangout_messages` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `hangout_id` INT NOT NULL,
                `user_id` INT NOT NULL,
                `message` TEXT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_message_hangout` (`hangout_id`),
                INDEX `idx_message_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            "CREATE TABLE IF NOT EXISTS `hangout_activities` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `hangout_id` INT NOT NULL,
                `user_id` INT NOT NULL,
                `action_type` VARCHAR(50) NOT NULL,
                `description` VARCHAR(255) NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_activity_hangout` (`hangout_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            "CREATE TABLE IF NOT EXISTS `notifications` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `title` VARCHAR(150) NOT NULL,
                `message` VARCHAR(255) NOT NULL,
                `link_hangout_id` INT NULL,
                `is_read` TINYINT(1) DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_notification_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
        ];
    } else {
        $queries = [
            "CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, avatar_url TEXT NULL, bio TEXT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);",
            "CREATE TABLE IF NOT EXISTS friends (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL DEFAULT 1, name TEXT NOT NULL, phone TEXT NULL, email TEXT NULL, location TEXT NULL, occupation TEXT NULL, qualities TEXT NULL, behavior_notes TEXT NULL, circle TEXT DEFAULT 'Close Friend', avatar_url TEXT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);",
            "CREATE TABLE IF NOT EXISTS hangouts (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, description TEXT NOT NULL, purpose TEXT NULL, plan_logistics TEXT NULL, category TEXT NOT NULL DEFAULT 'Social', location_name TEXT NOT NULL, address TEXT NULL, latitude REAL NULL, longitude REAL NULL, event_date TEXT NULL, event_time TEXT NULL, has_poll INTEGER DEFAULT 0, status TEXT DEFAULT 'upcoming', creator_id INTEGER NOT NULL, cover_image TEXT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);",
            "CREATE TABLE IF NOT EXISTS hangout_tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, hangout_id INTEGER NOT NULL, friend_id INTEGER NULL, title TEXT NOT NULL, description TEXT NULL, is_completed INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);",
            "CREATE TABLE IF NOT EXISTS hangout_invited_friends (id INTEGER PRIMARY KEY AUTOINCREMENT, hangout_id INTEGER NOT NULL, friend_id INTEGER NOT NULL, status TEXT DEFAULT 'invited', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE (hangout_id, friend_id));",
            "CREATE TABLE IF NOT EXISTS hangout_attendees (id INTEGER PRIMARY KEY AUTOINCREMENT, hangout_id INTEGER NOT NULL, user_id INTEGER NOT NULL, status TEXT DEFAULT 'attending', updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE (hangout_id, user_id));",
            "CREATE TABLE IF NOT EXISTS hangout_polls (id INTEGER PRIMARY KEY AUTOINCREMENT, hangout_id INTEGER NOT NULL, question TEXT NOT NULL, is_closed INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);",
            "CREATE TABLE IF NOT EXISTS hangout_poll_options (id INTEGER PRIMARY KEY AUTOINCREMENT, poll_id INTEGER NOT NULL, option_text TEXT NOT NULL, option_datetime TEXT NULL);",
            "CREATE TABLE IF NOT EXISTS hangout_poll_votes (id INTEGER PRIMARY KEY AUTOINCREMENT, option_id INTEGER NOT NULL, user_id INTEGER NOT NULL, voted_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE (option_id, user_id));",
            "CREATE TABLE IF NOT EXISTS hangout_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, hangout_id INTEGER NOT NULL, user_id INTEGER NOT NULL, message TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);",
            "CREATE TABLE IF NOT EXISTS hangout_activities (id INTEGER PRIMARY KEY AUTOINCREMENT, hangout_id INTEGER NOT NULL, user_id INTEGER NOT NULL, action_type TEXT NOT NULL, description TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);",
            "CREATE TABLE IF NOT EXISTS notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, title TEXT NOT NULL, message TEXT NOT NULL, link_hangout_id INTEGER NULL, is_read INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);"
        ];
    }

    foreach ($queries as $sql) {
        $pdo->exec($sql);
    }

    // Seed friends if empty or check if needs Quetta seed
    $stmt = $pdo->query("SELECT COUNT(*) FROM `friends`");
    $friendCount = $stmt->fetchColumn();
    if ($friendCount == 0) {
        seedQuettaData($pdo);
    }
}

// Function to populate complete Quetta & Balochistan dataset
function seedQuettaData($pdo) {
    // 1. Users
    $users = [
        [
            'id' => 1,
            'name' => 'Muhammad Faisal',
            'email' => 'faisal@quetta.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'avatar_url' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=250&q=80',
            'bio' => 'Quetta local, outdoor explorer, Sajji & Sulaimani Chai lover.'
        ],
        [
            'id' => 2,
            'name' => 'Bilal Khan Kakar',
            'email' => 'bilal.kakar@quetta.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'avatar_url' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=250&q=80',
            'bio' => 'Software Engineer in Quetta. 4x4 Cruiser roadtrip driver.'
        ],
        [
            'id' => 3,
            'name' => 'Dr. Zainab Achakzai',
            'email' => 'zainab.achakzai@quetta.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'avatar_url' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=250&q=80',
            'bio' => 'Medical doctor at BMC Quetta & event enthusiast.'
        ],
        [
            'id' => 4,
            'name' => 'Tariq Mengal',
            'email' => 'tariq.mengal@quetta.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'avatar_url' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=250&q=80',
            'bio' => 'Photographer & Rubab player. Always ready for Hanna Lake sunsets.'
        ]
    ];

    $uStmt = $pdo->prepare("INSERT INTO `users` (id, name, email, password_hash, avatar_url, bio) VALUES (:id, :name, :email, :password_hash, :avatar_url, :bio)");
    foreach ($users as $u) {
        $uStmt->execute($u);
    }

    // 2. Friends Contact Directory in Quetta
    $friends = [
        [
            'id' => 1,
            'user_id' => 1,
            'name' => 'Bilal Khan Kakar',
            'phone' => '+92 333 7891234',
            'email' => 'bilal.kakar@example.com',
            'location' => 'Model Town, Quetta',
            'occupation' => 'Software Engineer & Tech Lead',
            'qualities' => 'Car Owner (Land Cruiser 4x4), Ziarat Roadtrip Guide, Mountain Driving, Chill Vibe',
            'behavior_notes' => 'Hamesha time pe ata hai. Driving bohot zabardast karta hai paharo par. Har meetup ka main pillar hai.',
            'circle' => 'Best Friend',
            'avatar_url' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=250&q=80'
        ],
        [
            'id' => 2,
            'user_id' => 1,
            'name' => 'Hamza Mengal',
            'phone' => '+92 321 8234567',
            'email' => 'hamza.mengal@example.com',
            'location' => 'Nawan Killi, Brewery Road, Quetta',
            'occupation' => 'Landscape Photographer & Filmmaker',
            'qualities' => 'DSLR Camera, Drone Pilot, Hanna Lake Sunset Shots, Bluetooth Sound System',
            'behavior_notes' => 'Thora late ata hai (15-20 min) lekin gathering ka mood maker hai! Balochistan ke epic shots banata hai.',
            'circle' => 'College Friend',
            'avatar_url' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=250&q=80'
        ],
        [
            'id' => 3,
            'user_id' => 1,
            'name' => 'Mir Sherbaz Jamali',
            'phone' => '+92 345 8901234',
            'email' => 'sherbaz.jamali@example.com',
            'location' => 'Shahrah-e-Zarghoon, Quetta Cantt',
            'occupation' => 'Food Connoisseur & Meat Specialist',
            'qualities' => 'Balochi Namkeen Rosh Master, Prince Road Connections, BBQ Grill Expert',
            'behavior_notes' => 'Khaane peene ka sab se bara shaukeen hai. Best lamb meat aur authentic Balochi Sajji spots ka ustaad hai.',
            'circle' => 'Close Friend',
            'avatar_url' => 'https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?auto=format&fit=crop&w=250&q=80'
        ],
        [
            'id' => 4,
            'user_id' => 1,
            'name' => 'Dr. Zainab Achakzai',
            'phone' => '+92 312 9012345',
            'email' => 'zainab.achakzai@example.com',
            'location' => 'Satellite Town, Block 3, Quetta',
            'occupation' => 'Medical Officer at BMC Hospital',
            'qualities' => 'Event Planner, First Aid Box, Kashmiri Chai Specialist, Board Games',
            'behavior_notes' => 'Super organized! Har hangout ki timing aur planning manage karti hai. Very sweet aur polite nature.',
            'circle' => 'Close Friend',
            'avatar_url' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=250&q=80'
        ],
        [
            'id' => 5,
            'user_id' => 1,
            'name' => 'Tariq Baloch',
            'phone' => '+92 334 8123456',
            'email' => 'tariq.baloch@example.com',
            'location' => 'Jinnah Town, Quetta',
            'occupation' => 'Civil Engineer & Musician',
            'qualities' => 'Acoustic Rubab Player, Camping Tents, Bonfire Expert, High Energy',
            'behavior_notes' => 'Campfire ke ird gird jadu jaisi Rubab bajata hai. Ziarat aur Hanna Lake camping mein sab se active hota hai.',
            'circle' => 'Gym Buddy',
            'avatar_url' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=250&q=80'
        ],
        [
            'id' => 6,
            'user_id' => 1,
            'name' => 'Tehmina',
            'phone' => '+92 331 4567890',
            'email' => 'tehmina@example.com',
            'location' => 'Cantt Zarghoon Road, Quetta',
            'occupation' => 'Fashion Designer & Content Creator',
            'qualities' => 'Aesthetic Planner, Photography, Desserts & Baking, Positive Vibe',
            'behavior_notes' => 'Bohot sweet aur cooperative nature hai, meetups ki photography aur aesthetic decor bohot achi karti hai.',
            'circle' => 'Close Friend',
            'avatar_url' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=300&q=80'
        ],
        [
            'id' => 7,
            'user_id' => 1,
            'name' => 'Bilal Ahmed',
            'phone' => '+92 300 9876543',
            'email' => 'bilal.ahmed@example.com',
            'location' => 'Samungli Road, Quetta',
            'occupation' => 'Senior Software Engineer',
            'qualities' => 'Roadtrip Driver, Tech Gadgets, BBQ Master, Gaming Champion',
            'behavior_notes' => 'Hamesha loyal aur ready to help. Har hangout mein time pe pohochta hai aur music arrange karta hai.',
            'circle' => 'Best Friend',
            'avatar_url' => 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&w=300&q=80'
        ],
        [
            'id' => 8,
            'user_id' => 1,
            'name' => 'Kainat Rafiq',
            'phone' => '+92 322 6543210',
            'email' => 'kainat.rafiq@example.com',
            'location' => 'Jinnah Town, Quetta',
            'occupation' => 'Clinical Psychologist',
            'qualities' => 'Great Listener, Motivational, Fun Conversations, Coffee Lover',
            'behavior_notes' => 'Sab dosto ka khayal rakhti hai, conversation kabhi boring nahi hone deti. Super friendly personality.',
            'circle' => 'College Friend',
            'avatar_url' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=300&q=80'
        ],
        [
            'id' => 9,
            'user_id' => 1,
            'name' => 'Alina Shahzad',
            'phone' => '+92 344 1122334',
            'email' => 'alina.shahzad@example.com',
            'location' => 'Shahbaz Town, Quetta',
            'occupation' => 'Architect & Interior Designer',
            'qualities' => 'Venue Scouting, Playlist Curator, Board Games Master, Punctual',
            'behavior_notes' => 'Creative mindset, gathering ko plan aur organize karne mein expert hai. Always brings energy!',
            'circle' => 'Close Friend',
            'avatar_url' => 'https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&w=300&q=80'
        ]
    ];

    $fStmt = $pdo->prepare("INSERT INTO `friends` (id, user_id, name, phone, email, location, occupation, qualities, behavior_notes, circle, avatar_url) 
        VALUES (:id, :user_id, :name, :phone, :email, :location, :occupation, :qualities, :behavior_notes, :circle, :avatar_url)");
    foreach ($friends as $f) {
        $fStmt->execute($f);
    }

    // 3. Hangouts in Quetta & Balochistan
    $futureDate1 = date('Y-m-d', strtotime('+3 days'));
    $futureDate2 = date('Y-m-d', strtotime('+6 days'));
    $pastDate = date('Y-m-d', strtotime('-4 days'));

    $hangouts = [
        [
            'id' => 1,
            'title' => 'Hanna Lake Lakeside Picnic & Tikka BBQ',
            'description' => 'Cool mountain breeze near the Hanna Lake reservoir, fresh mutton seekh tikka, green kehwa, and photography at sunset.',
            'purpose' => 'Hanna Lake ki thandi hawa mein dosto ke sath BBQ grill karna, weekend enjoy karna aur sunset photography.',
            'plan_logistics' => "Meeting Point: Model Town Chowk at 4:30 PM.\nTransport: Bilal carpooling in Land Cruiser 4x4.\nBudget: Rs. 1,500 per head (fresh lamb & kehwa).\nWhat to bring: Light jacket (shaam ko sardi hoti hai)!",
            'category' => 'Outdoor & Nature',
            'location_name' => 'Hanna Lake & Urrak Valley',
            'address' => 'Hanna Road, 10 km from Quetta Cantt',
            'latitude' => 30.252000,
            'longitude' => 67.098000,
            'event_date' => $futureDate1,
            'event_time' => '17:00:00',
            'has_poll' => 0,
            'status' => 'upcoming',
            'creator_id' => 1,
            'cover_image' => 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1200&q=80'
        ],
        [
            'id' => 2,
            'title' => 'Prince Road Balochi Rosh & Kehwa Night',
            'description' => 'Authentic slow-cooked Balochi Namkeen Rosh, freshly baked Kandahari naan, and cardamon green tea on Prince Road.',
            'purpose' => 'Quetta ka mashhoor namkeen rosh khana aur voting karke sab se best shaam decide karna.',
            'plan_logistics' => "Meeting Point: Prince Road Food Street entrance at 8:15 PM.\nTiming: Voting in progress to pick the best evening slot.\nBudget: Approx Rs. 1,200 per head.",
            'category' => 'Food & Dining',
            'location_name' => 'Prince Road Food Street',
            'address' => 'Near Liaquat Bazaar & Prince Road, Quetta',
            'latitude' => 30.188000,
            'longitude' => 67.011000,
            'event_date' => null,
            'event_time' => null,
            'has_poll' => 1,
            'status' => 'voting',
            'creator_id' => 1,
            'cover_image' => 'https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=1200&q=80'
        ],
        [
            'id' => 3,
            'title' => 'Ziarat Valley Juniper Forest Bonfire & Camping',
            'description' => 'Weekend mountain roadtrip through Ziarat ancient juniper forests, Quaid Residency viewpoint, camping tents, and acoustic Rubab melodies around campfire.',
            'purpose' => 'Shehar ke shor se door Ziarat ki wadi mein camping, bonfire aur live Rubab session.',
            'plan_logistics' => "Meeting Point: Serena Hotel Quetta at 7:00 AM Saturday.\nTransport: 2 SUVs convoy. Tariq bringing camping tents.\nBudget: Rs. 4,000 per person (fuel, food & camping).\nWhat to bring: Heavy jackets, sleeping bags, torch!",
            'category' => 'Outdoor & Nature',
            'location_name' => 'Ziarat Juniper Valley',
            'address' => 'Ziarat District, 120 km from Quetta, Balochistan',
            'latitude' => 30.381000,
            'longitude' => 67.727000,
            'event_date' => $futureDate2,
            'event_time' => '07:30:00',
            'has_poll' => 0,
            'status' => 'upcoming',
            'creator_id' => 2,
            'cover_image' => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=1200&q=80'
        ],
        [
            'id' => 4,
            'title' => 'Serena Hotel Quetta Evening High-Tea & Chai',
            'description' => 'Casual high-tea at Zarghoon Lounge, Kashmiri tea, pastries, and discussing plans for the northern Balochistan trek.',
            'purpose' => 'Chill indoor meeting over signature green tea and planning our next meetup.',
            'plan_logistics' => 'Direct meet at Serena Hotel Zarghoon Lounge terrace at 6:00 PM.',
            'category' => 'Coffee & Coworking',
            'location_name' => 'Serena Hotel Quetta (Zarghoon Lounge)',
            'address' => 'Shahrah-e-Zarghoon, Quetta Cantt',
            'latitude' => 30.198500,
            'longitude' => 67.014500,
            'event_date' => $pastDate,
            'event_time' => '18:00:00',
            'has_poll' => 0,
            'status' => 'past',
            'creator_id' => 1,
            'cover_image' => 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=1200&q=80'
        ]
    ];

    $hStmt = $pdo->prepare("INSERT INTO `hangouts` 
        (id, title, description, purpose, plan_logistics, category, location_name, address, latitude, longitude, event_date, event_time, has_poll, status, creator_id, cover_image) 
        VALUES (:id, :title, :description, :purpose, :plan_logistics, :category, :location_name, :address, :latitude, :longitude, :event_date, :event_time, :has_poll, :status, :creator_id, :cover_image)");
    foreach ($hangouts as $h) {
        $hStmt->execute($h);
    }

    // 4. Tasks Assigned to Quetta Friends
    $tasks = [
        ['hangout_id' => 1, 'friend_id' => 3, 'title' => 'Pick up 3kg fresh mutton ribs & skewers from Prince Road', 'description' => 'Mir Sherbaz knows the best butcher in Quetta', 'is_completed' => 1],
        ['hangout_id' => 1, 'friend_id' => 1, 'title' => 'Drive 4x4 Cruiser for carpool from Model Town', 'description' => 'Bilal taking squad up Hanna Road', 'is_completed' => 1],
        ['hangout_id' => 1, 'friend_id' => 2, 'title' => 'Bring DSLR camera & drone for lake aerial video', 'description' => 'Hamza capturing 4K sunset video', 'is_completed' => 0],
        ['hangout_id' => 1, 'friend_id' => 4, 'title' => 'Bring Cardamom green tea (Kehwa) & thermos', 'description' => 'Dr. Zainab bringing hot kehwa & dry fruits', 'is_completed' => 0],
        // Hangout 2 tasks
        ['hangout_id' => 2, 'friend_id' => 3, 'title' => 'Call ahead to reserve authentic Rosh seating at Prince Road', 'description' => 'Table for 6-8 people', 'is_completed' => 0],
        // Hangout 3 tasks (Ziarat)
        ['hangout_id' => 3, 'friend_id' => 5, 'title' => 'Bring acoustic Rubab & 2 camping tents', 'description' => 'Tariq Baloch leading campfire music session', 'is_completed' => 0],
        ['hangout_id' => 3, 'friend_id' => 1, 'title' => 'Check 4x4 tire pressure & emergency tow rope', 'description' => 'Ready for mountain road to Ziarat', 'is_completed' => 0]
    ];

    $tStmt = $pdo->prepare("INSERT INTO `hangout_tasks` (hangout_id, friend_id, title, description, is_completed) VALUES (:hangout_id, :friend_id, :title, :description, :is_completed)");
    foreach ($tasks as $t) {
        $tStmt->execute($t);
    }

    // 5. Invited Friends
    $invites = [
        ['hangout_id' => 1, 'friend_id' => 1, 'status' => 'going'],
        ['hangout_id' => 1, 'friend_id' => 2, 'status' => 'going'],
        ['hangout_id' => 1, 'friend_id' => 3, 'status' => 'going'],
        ['hangout_id' => 1, 'friend_id' => 4, 'status' => 'going'],
        ['hangout_id' => 2, 'friend_id' => 1, 'status' => 'going'],
        ['hangout_id' => 2, 'friend_id' => 3, 'status' => 'going'],
        ['hangout_id' => 2, 'friend_id' => 4, 'status' => 'maybe'],
        ['hangout_id' => 3, 'friend_id' => 1, 'status' => 'going'],
        ['hangout_id' => 3, 'friend_id' => 5, 'status' => 'going']
    ];
    $invStmt = $pdo->prepare("INSERT INTO `hangout_invited_friends` (hangout_id, friend_id, status) VALUES (:hangout_id, :friend_id, :status)");
    foreach ($invites as $inv) {
        $invStmt->execute($inv);
    }

    // 6. Scheduling Poll for Prince Road Hangout
    $pdo->exec("INSERT INTO `hangout_polls` (id, hangout_id, question, is_closed) VALUES (1, 2, 'Prince Road par Rosh & Kehwa kis shaam sab se best rahega?', 0)");
    $pollOptions = [
        ['id' => 1, 'poll_id' => 1, 'option_text' => 'Thursday Evening (8:00 PM)'],
        ['id' => 2, 'poll_id' => 1, 'option_text' => 'Friday Night Rosh Feast (8:30 PM)'],
        ['id' => 3, 'poll_id' => 1, 'option_text' => 'Saturday Post-Hike Dinner (7:30 PM)']
    ];
    $optStmt = $pdo->prepare("INSERT INTO `hangout_poll_options` (id, poll_id, option_text) VALUES (:id, :poll_id, :option_text)");
    foreach ($pollOptions as $opt) {
        $optStmt->execute($opt);
    }

    $pdo->exec("INSERT INTO `hangout_poll_votes` (option_id, user_id) VALUES (2, 1), (2, 2), (1, 3)");

    // 7. Chat Messages in Quetta Hangouts
    $messages = [
        ['hangout_id' => 1, 'user_id' => 1, 'message' => "Salam dosto! Hanna Lake ke liye 4x4 Cruiser ready hai. Shaam ka sunset zabardast hoga!"],
        ['hangout_id' => 1, 'user_id' => 2, 'message' => "Walaikum Assalam! Main drone aur camera pack kar chuka hoon, epic video banegi InshaAllah."],
        ['hangout_id' => 1, 'user_id' => 3, 'message' => "Zabardast! Main fresh lamb seekh tikka Prince Road ke mashhoor butcher se la raha hoon 🍖"],
        ['hangout_id' => 2, 'user_id' => 1, 'message' => "Sab log poll mein vote kardein taake Prince Road par seating reserve kar sakein."]
    ];
    $mStmt = $pdo->prepare("INSERT INTO `hangout_messages` (hangout_id, user_id, message) VALUES (:hangout_id, :user_id, :message)");
    foreach ($messages as $m) {
        $mStmt->execute($m);
    }

    // 8. Attendees
    $pdo->exec("INSERT INTO `hangout_attendees` (hangout_id, user_id, status) VALUES 
        (1, 1, 'attending'), (1, 2, 'attending'), (1, 3, 'attending'), (1, 4, 'attending'),
        (2, 1, 'attending'), (2, 2, 'attending'), (3, 1, 'attending'), (3, 2, 'attending')");
}
