<?php
// ==============================================================================
// FRIENDS HANGOUT - REST API BACKEND
// ==============================================================================

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/db.php';

// Initialize DB and ensure tables exist
initDatabase();
$pdo = getDbConnection();

// Set default logged in user if session empty
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

function getCurrentUser($pdo) {
    if (!isset($_SESSION['user_id'])) return null;
    $stmt = $pdo->prepare("SELECT id, name, email, avatar_url, bio, created_at FROM `users` WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function respond($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function respondError($message, $statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

try {
    switch ($action) {

        // ================= AUTH & USER =================
        case 'me':
            $user = getCurrentUser($pdo);
            $stmt = $pdo->query("SELECT id, name, email, avatar_url, bio FROM `users` ORDER BY id ASC");
            $allUsers = $stmt->fetchAll();
            respond([
                'success' => true,
                'user' => $user,
                'all_users' => $allUsers
            ]);
            break;

        case 'switch_user':
            $targetId = (int)($input['user_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT id, name FROM `users` WHERE id = ?");
            $stmt->execute([$targetId]);
            $found = $stmt->fetch();
            if (!$found) respondError("User not found", 404);
            $_SESSION['user_id'] = $targetId;
            respond([
                'success' => true,
                'user' => getCurrentUser($pdo),
                'message' => "Switched active persona to {$found['name']}"
            ]);
            break;

        // ================= FRIENDS CONTACT DIRECTORY (CRUD) =================
        case 'get_friends':
            $search = trim($input['search'] ?? $_GET['search'] ?? '');
            $circle = trim($input['circle'] ?? $_GET['circle'] ?? 'all');

            $sql = "SELECT f.*,
                    (SELECT COUNT(*) FROM `hangout_invited_friends` WHERE friend_id = f.id) AS hangout_count,
                    (SELECT COUNT(*) FROM `hangout_tasks` WHERE friend_id = f.id AND is_completed = 0) AS pending_tasks_count
                    FROM `friends` f
                    WHERE 1=1";
            $params = [];

            if ($circle !== 'all') {
                $sql .= " AND f.circle = ?";
                $params[] = $circle;
            }

            if ($search !== '') {
                $sql .= " AND (f.name LIKE ? OR f.phone LIKE ? OR f.location LIKE ? OR f.occupation LIKE ? OR f.qualities LIKE ? OR f.behavior_notes LIKE ?)";
                $wildcard = "%{$search}%";
                $params = array_merge($params, [$wildcard, $wildcard, $wildcard, $wildcard, $wildcard, $wildcard]);
            }

            $sql .= " ORDER BY f.name ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $friends = $stmt->fetchAll();

            respond(['success' => true, 'friends' => $friends]);
            break;

        case 'get_friend_detail':
            $friendId = (int)($input['id'] ?? $_GET['id'] ?? 0);
            if (!$friendId) respondError("Friend ID missing");

            $stmt = $pdo->prepare("SELECT * FROM `friends` WHERE id = ?");
            $stmt->execute([$friendId]);
            $friend = $stmt->fetch();
            if (!$friend) respondError("Friend not found", 404);

            // Fetch hangouts invited to
            $hStmt = $pdo->prepare("SELECT h.id, h.title, h.event_date, h.location_name, h.category, h.status, hif.status AS invite_status
                FROM `hangout_invited_friends` hif
                JOIN `hangouts` h ON hif.hangout_id = h.id
                WHERE hif.friend_id = ?
                ORDER BY h.event_date DESC");
            $hStmt->execute([$friendId]);
            $friend['hangouts'] = $hStmt->fetchAll();

            // Fetch assigned tasks
            $tStmt = $pdo->prepare("SELECT ht.*, h.title AS hangout_title 
                FROM `hangout_tasks` ht
                JOIN `hangouts` h ON ht.hangout_id = h.id
                WHERE ht.friend_id = ?
                ORDER BY ht.is_completed ASC, ht.created_at DESC");
            $tStmt->execute([$friendId]);
            $friend['tasks'] = $tStmt->fetchAll();

            respond(['success' => true, 'friend' => $friend]);
            break;

        case 'add_friend':
            $currentUser = getCurrentUser($pdo);
            $userId = $currentUser ? $currentUser['id'] : 1;

            $name = trim($input['name'] ?? '');
            $phone = trim($input['phone'] ?? '');
            $email = trim($input['email'] ?? '');
            $location = trim($input['location'] ?? '');
            $occupation = trim($input['occupation'] ?? '');
            $qualities = trim($input['qualities'] ?? '');
            $behavior_notes = trim($input['behavior_notes'] ?? '');
            $circle = trim($input['circle'] ?? 'Close Friend');
            $avatar_url = trim($input['avatar_url'] ?? '');

            if (!$name) respondError("Friend's name is required");

            if (!$avatar_url) {
                $avatar_url = 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=250&q=80';
            }

            $stmt = $pdo->prepare("INSERT INTO `friends` 
                (user_id, name, phone, email, location, occupation, qualities, behavior_notes, circle, avatar_url) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $name, $phone, $email, $location, $occupation, $qualities, $behavior_notes, $circle, $avatar_url]);
            $newId = $pdo->lastInsertId();

            respond([
                'success' => true,
                'friend_id' => $newId,
                'message' => "Friend added to contact directory!"
            ]);
            break;

        case 'update_friend':
            $friendId = (int)($input['id'] ?? 0);
            if (!$friendId) respondError("Friend ID missing");

            $name = trim($input['name'] ?? '');
            $phone = trim($input['phone'] ?? '');
            $email = trim($input['email'] ?? '');
            $location = trim($input['location'] ?? '');
            $occupation = trim($input['occupation'] ?? '');
            $qualities = trim($input['qualities'] ?? '');
            $behavior_notes = trim($input['behavior_notes'] ?? '');
            $circle = trim($input['circle'] ?? 'Close Friend');
            $avatar_url = trim($input['avatar_url'] ?? '');

            if (!$name) respondError("Friend's name cannot be empty");

            $stmt = $pdo->prepare("UPDATE `friends` SET 
                name = ?, phone = ?, email = ?, location = ?, occupation = ?, qualities = ?, behavior_notes = ?, circle = ?, avatar_url = ? 
                WHERE id = ?");
            $stmt->execute([$name, $phone, $email, $location, $occupation, $qualities, $behavior_notes, $circle, $avatar_url, $friendId]);

            respond(['success' => true, 'message' => "Friend details updated successfully!"]);
            break;

        case 'delete_friend':
            $friendId = (int)($input['id'] ?? 0);
            if (!$friendId) respondError("Friend ID missing");

            // Remove invited entries & unassign tasks
            $pdo->prepare("DELETE FROM `hangout_invited_friends` WHERE friend_id = ?")->execute([$friendId]);
            $pdo->prepare("UPDATE `hangout_tasks` SET friend_id = NULL WHERE friend_id = ?")->execute([$friendId]);
            $pdo->prepare("DELETE FROM `friends` WHERE id = ?")->execute([$friendId]);

            respond(['success' => true, 'message' => "Friend deleted from contact directory"]);
            break;

        // ================= HANGOUTS LIST & DASHBOARD =================
        case 'get_hangouts':
            $currentUser = getCurrentUser($pdo);
            $userId = $currentUser ? $currentUser['id'] : 0;
            $filter = $input['filter'] ?? $_GET['filter'] ?? 'all';
            $category = $input['category'] ?? $_GET['category'] ?? 'all';
            $search = trim($input['search'] ?? $_GET['search'] ?? '');

            $sql = "SELECT h.*, 
                    u.name AS creator_name, 
                    u.avatar_url AS creator_avatar,
                    (SELECT COUNT(*) FROM `hangout_attendees` WHERE hangout_id = h.id AND status = 'attending') AS attending_count,
                    (SELECT status FROM `hangout_attendees` WHERE hangout_id = h.id AND user_id = ?) AS my_rsvp,
                    (SELECT COUNT(*) FROM `hangout_messages` WHERE hangout_id = h.id) AS message_count,
                    (SELECT COUNT(*) FROM `hangout_tasks` WHERE hangout_id = h.id) AS total_tasks,
                    (SELECT COUNT(*) FROM `hangout_tasks` WHERE hangout_id = h.id AND is_completed = 1) AS completed_tasks,
                    (SELECT COUNT(*) FROM `hangout_invited_friends` WHERE hangout_id = h.id) AS invited_count
                    FROM `hangouts` h
                    JOIN `users` u ON h.creator_id = u.id
                    WHERE 1=1";
            $params = [$userId];

            if ($filter === 'upcoming') {
                $sql .= " AND h.status = 'upcoming'";
            } elseif ($filter === 'voting') {
                $sql .= " AND h.status = 'voting'";
            } elseif ($filter === 'past') {
                $sql .= " AND h.status = 'past'";
            } elseif ($filter === 'mine') {
                $sql .= " AND (h.creator_id = ? OR EXISTS (SELECT 1 FROM `hangout_attendees` WHERE hangout_id = h.id AND user_id = ?))";
                $params[] = $userId;
                $params[] = $userId;
            }

            if ($category !== 'all') {
                $sql .= " AND h.category = ?";
                $params[] = $category;
            }

            if ($search !== '') {
                $sql .= " AND (h.title LIKE ? OR h.description LIKE ? OR h.location_name LIKE ? OR h.purpose LIKE ?)";
                $wildcard = "%{$search}%";
                $params = array_merge($params, [$wildcard, $wildcard, $wildcard, $wildcard]);
            }

            $sql .= " ORDER BY 
                CASE 
                    WHEN h.status = 'voting' THEN 1 
                    WHEN h.status = 'upcoming' THEN 2 
                    ELSE 3 
                END, 
                h.event_date ASC, 
                h.created_at DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $hangouts = $stmt->fetchAll();

            foreach ($hangouts as &$h) {
                // Fetch preview of invited friends
                $invStmt = $pdo->prepare("SELECT f.id, f.name, f.avatar_url, f.phone, f.qualities, hif.status 
                    FROM `hangout_invited_friends` hif
                    JOIN `friends` f ON hif.friend_id = f.id
                    WHERE hif.hangout_id = ?
                    LIMIT 6");
                $invStmt->execute([$h['id']]);
                $h['friends_preview'] = $invStmt->fetchAll();
            }

            respond([
                'success' => true,
                'hangouts' => $hangouts
            ]);
            break;

        // ================= HANGOUT DETAIL =================
        case 'get_hangout_detail':
            $currentUser = getCurrentUser($pdo);
            $userId = $currentUser ? $currentUser['id'] : 0;
            $hangoutId = (int)($input['id'] ?? $_GET['id'] ?? 0);

            if (!$hangoutId) respondError("Hangout ID missing");

            $stmt = $pdo->prepare("SELECT h.*, 
                u.name AS creator_name, 
                u.email AS creator_email, 
                u.avatar_url AS creator_avatar,
                u.bio AS creator_bio,
                (SELECT status FROM `hangout_attendees` WHERE hangout_id = h.id AND user_id = ?) AS my_rsvp
                FROM `hangouts` h
                JOIN `users` u ON h.creator_id = u.id
                WHERE h.id = ?");
            $stmt->execute([$userId, $hangoutId]);
            $hangout = $stmt->fetch();
            if (!$hangout) respondError("Hangout not found", 404);

            // Fetch invited friends with contact and qualities
            $invStmt = $pdo->prepare("SELECT f.*, hif.status AS invite_status, hif.id AS invite_id
                FROM `hangout_invited_friends` hif
                JOIN `friends` f ON hif.friend_id = f.id
                WHERE hif.hangout_id = ?
                ORDER BY f.name ASC");
            $invStmt->execute([$hangoutId]);
            $invitedFriends = $invStmt->fetchAll();

            // Fetch assigned tasks
            $taskStmt = $pdo->prepare("SELECT ht.*, f.name AS friend_name, f.avatar_url AS friend_avatar, f.phone AS friend_phone, f.qualities AS friend_qualities
                FROM `hangout_tasks` ht
                LEFT JOIN `friends` f ON ht.friend_id = f.id
                WHERE ht.hangout_id = ?
                ORDER BY ht.is_completed ASC, ht.created_at ASC");
            $taskStmt->execute([$hangoutId]);
            $tasks = $taskStmt->fetchAll();

            // Fetch attendees
            $attStmt = $pdo->prepare("SELECT ha.id AS rsvp_id, ha.status, ha.updated_at, 
                u.id AS user_id, u.name, u.email, u.avatar_url 
                FROM `hangout_attendees` ha
                JOIN `users` u ON ha.user_id = u.id
                WHERE ha.hangout_id = ?
                ORDER BY FIELD(ha.status, 'attending', 'maybe', 'declined'), u.name ASC");
            $attStmt->execute([$hangoutId]);
            $attendees = $attStmt->fetchAll();

            // Fetch poll if any
            $pollStmt = $pdo->prepare("SELECT * FROM `hangout_polls` WHERE hangout_id = ? ORDER BY id DESC LIMIT 1");
            $pollStmt->execute([$hangoutId]);
            $poll = $pollStmt->fetch();

            if ($poll) {
                $optStmt = $pdo->prepare("SELECT o.*, 
                    COUNT(v.id) AS vote_count,
                    EXISTS(SELECT 1 FROM `hangout_poll_votes` WHERE option_id = o.id AND user_id = ?) AS user_voted
                    FROM `hangout_poll_options` o
                    LEFT JOIN `hangout_poll_votes` v ON o.id = v.option_id
                    WHERE o.poll_id = ?
                    GROUP BY o.id");
                $optStmt->execute([$userId, $poll['id']]);
                $options = $optStmt->fetchAll();

                foreach ($options as &$opt) {
                    $votersStmt = $pdo->prepare("SELECT u.id, u.name, u.avatar_url 
                        FROM `hangout_poll_votes` v 
                        JOIN `users` u ON v.user_id = u.id 
                        WHERE v.option_id = ?");
                    $votersStmt->execute([$opt['id']]);
                    $opt['voters'] = $votersStmt->fetchAll();
                }

                $totalVotes = 0;
                foreach ($options as $opt) {
                    $totalVotes += (int)$opt['vote_count'];
                }
                $poll['total_votes'] = $totalVotes;
                $poll['options'] = $options;
                $hangout['poll'] = $poll;
            } else {
                $hangout['poll'] = null;
            }

            // Fetch messages
            $msgStmt = $pdo->prepare("SELECT m.id, m.hangout_id, m.user_id, m.message, m.created_at,
                u.name AS user_name, u.avatar_url AS user_avatar
                FROM `hangout_messages` m
                JOIN `users` u ON m.user_id = u.id
                WHERE m.hangout_id = ?
                ORDER BY m.created_at ASC");
            $msgStmt->execute([$hangoutId]);
            $messages = $msgStmt->fetchAll();

            // Fetch activities
            $actStmt = $pdo->prepare("SELECT a.*, u.name AS user_name, u.avatar_url AS user_avatar
                FROM `hangout_activities` a
                JOIN `users` u ON a.user_id = u.id
                WHERE a.hangout_id = ?
                ORDER BY a.created_at DESC LIMIT 25");
            $actStmt->execute([$hangoutId]);
            $activities = $actStmt->fetchAll();

            respond([
                'success' => true,
                'hangout' => $hangout,
                'invited_friends' => $invitedFriends,
                'tasks' => $tasks,
                'attendees' => $attendees,
                'messages' => $messages,
                'activities' => $activities
            ]);
            break;

        // ================= CREATE HANGOUT =================
        case 'create_hangout':
            $currentUser = getCurrentUser($pdo);
            if (!$currentUser) respondError("Please log in first", 401);

            $title = trim($input['title'] ?? '');
            $description = trim($input['description'] ?? '');
            $purpose = trim($input['purpose'] ?? ''); // Kio
            $plan_logistics = trim($input['plan_logistics'] ?? ''); // Kese
            $category = trim($input['category'] ?? 'Social');
            $location_name = trim($input['location_name'] ?? ''); // Kaha
            $address = trim($input['address'] ?? '');
            $latitude = !empty($input['latitude']) ? (float)$input['latitude'] : 37.7749;
            $longitude = !empty($input['longitude']) ? (float)$input['longitude'] : -122.4194;
            $cover_image = trim($input['cover_image'] ?? '');
            $has_poll = !empty($input['has_poll']) ? 1 : 0;
            $event_date = !empty($input['event_date']) ? $input['event_date'] : null;
            $event_time = !empty($input['event_time']) ? $input['event_time'] : null;
            $poll_question = trim($input['poll_question'] ?? 'When are you free for this hangout?');
            $poll_options = $input['poll_options'] ?? [];
            $invited_friend_ids = $input['invited_friend_ids'] ?? [];
            $initial_tasks = $input['initial_tasks'] ?? [];

            if (!$title || !$location_name) {
                respondError("Title and location (Kaha) are required");
            }

            if (!$cover_image) {
                $defaultCovers = [
                    'Food & Dining' => 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?auto=format&fit=crop&w=1200&q=80',
                    'Outdoor & Nature' => 'https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=1200&q=80',
                    'Gaming & Chill' => 'https://images.unsplash.com/photo-1610890716171-6b1bb98ffd09?auto=format&fit=crop&w=1200&q=80',
                    'Parties & Social' => 'https://images.unsplash.com/photo-1533105079780-92b9be482077?auto=format&fit=crop&w=1200&q=80',
                    'Coffee & Coworking' => 'https://images.unsplash.com/photo-1501339847302-ac426a4a7cbb?auto=format&fit=crop&w=1200&q=80',
                ];
                $cover_image = $defaultCovers[$category] ?? 'https://images.unsplash.com/photo-1529156069898-49953e39b3ac?auto=format&fit=crop&w=1200&q=80';
            }

            $status = $has_poll ? 'voting' : 'upcoming';

            $insert = $pdo->prepare("INSERT INTO `hangouts` 
                (title, description, purpose, plan_logistics, category, location_name, address, latitude, longitude, event_date, event_time, has_poll, status, creator_id, cover_image) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert->execute([$title, $description, $purpose, $plan_logistics, $category, $location_name, $address, $latitude, $longitude, $event_date, $event_time, $has_poll, $status, $currentUser['id'], $cover_image]);
            $hangoutId = $pdo->lastInsertId();

            // Auto-RSVP creator as 'attending'
            $pdo->prepare("INSERT INTO `hangout_attendees` (hangout_id, user_id, status) VALUES (?, ?, 'attending')")
                ->execute([$hangoutId, $currentUser['id']]);

            // Add invited friends
            if (!empty($invited_friend_ids) && is_array($invited_friend_ids)) {
                $invStmt = $pdo->prepare("INSERT IGNORE INTO `hangout_invited_friends` (hangout_id, friend_id, status) VALUES (?, ?, 'invited')");
                foreach ($invited_friend_ids as $fid) {
                    $invStmt->execute([$hangoutId, (int)$fid]);
                }
            }

            // Add initial assigned tasks
            if (!empty($initial_tasks) && is_array($initial_tasks)) {
                $taskStmt = $pdo->prepare("INSERT INTO `hangout_tasks` (hangout_id, friend_id, title, description, is_completed) VALUES (?, ?, ?, ?, 0)");
                foreach ($initial_tasks as $t) {
                    if (!empty($t['title'])) {
                        $taskStmt->execute([
                            $hangoutId,
                            !empty($t['friend_id']) ? (int)$t['friend_id'] : null,
                            trim($t['title']),
                            trim($t['description'] ?? '')
                        ]);
                    }
                }
            }

            // Create Poll if requested
            if ($has_poll && !empty($poll_options)) {
                $pStmt = $pdo->prepare("INSERT INTO `hangout_polls` (hangout_id, question, is_closed) VALUES (?, ?, 0)");
                $pStmt->execute([$hangoutId, $poll_question]);
                $pollId = $pdo->lastInsertId();

                $optStmt = $pdo->prepare("INSERT INTO `hangout_poll_options` (poll_id, option_text) VALUES (?, ?)");
                foreach ($poll_options as $optText) {
                    $optText = trim($optText);
                    if ($optText !== '') {
                        $optStmt->execute([$pollId, $optText]);
                    }
                }
            }

            // Log activity
            $act = $pdo->prepare("INSERT INTO `hangout_activities` (hangout_id, user_id, action_type, description) VALUES (?, ?, 'created', ?)");
            $act->execute([$hangoutId, $currentUser['id'], "planned new hangout: \"{$title}\""]);

            respond([
                'success' => true,
                'hangout_id' => $hangoutId,
                'message' => 'Hangout created with selected friends and assigned tasks!'
            ]);
            break;

        // ================= TASK MANAGEMENT (ASSIGN, TOGGLE, DELETE) =================
        case 'assign_task':
            $hangoutId = (int)($input['hangout_id'] ?? 0);
            $friendId = !empty($input['friend_id']) ? (int)$input['friend_id'] : null;
            $title = trim($input['title'] ?? '');
            $description = trim($input['description'] ?? '');

            if (!$hangoutId || !$title) respondError("Hangout ID and Task Title required");

            $stmt = $pdo->prepare("INSERT INTO `hangout_tasks` (hangout_id, friend_id, title, description, is_completed) VALUES (?, ?, ?, ?, 0)");
            $stmt->execute([$hangoutId, $friendId, $title, $description]);
            $taskId = $pdo->lastInsertId();

            respond([
                'success' => true,
                'task_id' => $taskId,
                'message' => "Task assigned successfully!"
            ]);
            break;

        case 'toggle_task':
            $taskId = (int)($input['task_id'] ?? 0);
            if (!$taskId) respondError("Task ID missing");

            $stmt = $pdo->prepare("SELECT is_completed FROM `hangout_tasks` WHERE id = ?");
            $stmt->execute([$taskId]);
            $current = $stmt->fetchColumn();

            $newStatus = $current ? 0 : 1;
            $pdo->prepare("UPDATE `hangout_tasks` SET is_completed = ? WHERE id = ?")->execute([$newStatus, $taskId]);

            respond(['success' => true, 'is_completed' => $newStatus]);
            break;

        case 'delete_task':
            $taskId = (int)($input['task_id'] ?? 0);
            if (!$taskId) respondError("Task ID missing");

            $pdo->prepare("DELETE FROM `hangout_tasks` WHERE id = ?")->execute([$taskId]);
            respond(['success' => true, 'message' => "Task removed"]);
            break;

        // ================= INVITE FRIENDS TO EXISTING HANGOUT =================
        case 'invite_friend':
            $hangoutId = (int)($input['hangout_id'] ?? 0);
            $friendId = (int)($input['friend_id'] ?? 0);

            if (!$hangoutId || !$friendId) respondError("Hangout ID and Friend ID required");

            $pdo->prepare("INSERT IGNORE INTO `hangout_invited_friends` (hangout_id, friend_id, status) VALUES (?, ?, 'invited')")
                ->execute([$hangoutId, $friendId]);

            respond(['success' => true, 'message' => "Friend added to hangout!"]);
            break;

        case 'remove_invited_friend':
            $hangoutId = (int)($input['hangout_id'] ?? 0);
            $friendId = (int)($input['friend_id'] ?? 0);

            $pdo->prepare("DELETE FROM `hangout_invited_friends` WHERE hangout_id = ? AND friend_id = ?")
                ->execute([$hangoutId, $friendId]);

            respond(['success' => true, 'message' => "Friend removed from hangout"]);
            break;

        // ================= UPDATE RSVP =================
        case 'update_rsvp':
            $currentUser = getCurrentUser($pdo);
            if (!$currentUser) respondError("Please log in first", 401);

            $hangoutId = (int)($input['hangout_id'] ?? 0);
            $status = $input['status'] ?? 'attending';

            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') {
                $sql = "INSERT INTO `hangout_attendees` (hangout_id, user_id, status) VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = CURRENT_TIMESTAMP";
                $pdo->prepare($sql)->execute([$hangoutId, $currentUser['id'], $status]);
            } else {
                $check = $pdo->prepare("SELECT id FROM `hangout_attendees` WHERE hangout_id = ? AND user_id = ?");
                $check->execute([$hangoutId, $currentUser['id']]);
                if ($check->fetch()) {
                    $pdo->prepare("UPDATE `hangout_attendees` SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE hangout_id = ? AND user_id = ?")
                        ->execute([$status, $hangoutId, $currentUser['id']]);
                } else {
                    $pdo->prepare("INSERT INTO `hangout_attendees` (hangout_id, user_id, status) VALUES (?, ?, ?)")
                        ->execute([$hangoutId, $currentUser['id'], $status]);
                }
            }

            $act = $pdo->prepare("INSERT INTO `hangout_activities` (hangout_id, user_id, action_type, description) VALUES (?, ?, 'rsvp', ?)");
            $act->execute([$hangoutId, $currentUser['id'], "marked RSVP as: {$status}"]);

            respond(['success' => true, 'status' => $status]);
            break;

        // ================= POLL VOTING & SCHEDULING =================
        case 'vote_poll':
            $currentUser = getCurrentUser($pdo);
            if (!$currentUser) respondError("Please log in first", 401);

            $optionId = (int)($input['option_id'] ?? 0);
            if (!$optionId) respondError("Option ID missing");

            $optStmt = $pdo->prepare("SELECT o.*, p.id AS poll_id, p.hangout_id, p.is_closed 
                FROM `hangout_poll_options` o 
                JOIN `hangout_polls` p ON o.poll_id = p.id 
                WHERE o.id = ?");
            $optStmt->execute([$optionId]);
            $option = $optStmt->fetch();

            if (!$option) respondError("Poll option not found", 404);
            if ($option['is_closed']) respondError("This poll is closed");

            $existingVote = $pdo->prepare("SELECT v.id, v.option_id FROM `hangout_poll_votes` v 
                JOIN `hangout_poll_options` o ON v.option_id = o.id 
                WHERE o.poll_id = ? AND v.user_id = ?");
            $existingVote->execute([$option['poll_id'], $currentUser['id']]);
            $prev = $existingVote->fetch();

            if ($prev) {
                if ($prev['option_id'] == $optionId) {
                    $pdo->prepare("DELETE FROM `hangout_poll_votes` WHERE id = ?")->execute([$prev['id']]);
                    $voted = false;
                } else {
                    $pdo->prepare("UPDATE `hangout_poll_votes` SET option_id = ?, voted_at = CURRENT_TIMESTAMP WHERE id = ?")
                        ->execute([$optionId, $prev['id']]);
                    $voted = true;
                }
            } else {
                $pdo->prepare("INSERT INTO `hangout_poll_votes` (option_id, user_id) VALUES (?, ?)")
                    ->execute([$optionId, $currentUser['id']]);
                $voted = true;
            }

            if ($voted) {
                $act = $pdo->prepare("INSERT INTO `hangout_activities` (hangout_id, user_id, action_type, description) VALUES (?, ?, 'voted', ?)");
                $act->execute([$option['hangout_id'], $currentUser['id'], "voted for: {$option['option_text']}"]);
            }

            respond(['success' => true, 'voted' => $voted]);
            break;

        case 'finalize_poll':
            $currentUser = getCurrentUser($pdo);
            $hangoutId = (int)($input['hangout_id'] ?? 0);
            $chosenDate = trim($input['event_date'] ?? '');
            $chosenTime = trim($input['event_time'] ?? '');

            if (!$hangoutId || !$chosenDate) respondError("Hangout ID and finalized date required");

            $pdo->prepare("UPDATE `hangout_polls` SET is_closed = 1 WHERE hangout_id = ?")->execute([$hangoutId]);
            $pdo->prepare("UPDATE `hangouts` SET event_date = ?, event_time = ?, status = 'upcoming' WHERE id = ?")
                ->execute([$chosenDate, $chosenTime ?: null, $hangoutId]);

            respond(['success' => true, 'message' => "Schedule finalized!"]);
            break;

        // ================= LIVE GROUP CHAT =================
        case 'send_message':
            $currentUser = getCurrentUser($pdo);
            if (!$currentUser) respondError("Please log in first", 401);

            $hangoutId = (int)($input['hangout_id'] ?? 0);
            $message = trim($input['message'] ?? '');

            if (!$hangoutId || !$message) respondError("Message and Hangout ID are required");

            $ins = $pdo->prepare("INSERT INTO `hangout_messages` (hangout_id, user_id, message) VALUES (?, ?, ?)");
            $ins->execute([$hangoutId, $currentUser['id'], $message]);
            $msgId = $pdo->lastInsertId();

            respond([
                'success' => true,
                'message_id' => $msgId,
                'message' => [
                    'id' => $msgId,
                    'hangout_id' => $hangoutId,
                    'user_id' => $currentUser['id'],
                    'user_name' => $currentUser['name'],
                    'user_avatar' => $currentUser['avatar_url'],
                    'message' => $message,
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ]);
            break;

        case 'get_messages':
            $hangoutId = (int)($input['hangout_id'] ?? $_GET['hangout_id'] ?? 0);
            if (!$hangoutId) respondError("Hangout ID missing");

            $stmt = $pdo->prepare("SELECT m.id, m.hangout_id, m.user_id, m.message, m.created_at,
                u.name AS user_name, u.avatar_url AS user_avatar
                FROM `hangout_messages` m
                JOIN `users` u ON m.user_id = u.id
                WHERE m.hangout_id = ?
                ORDER BY m.created_at ASC");
            $stmt->execute([$hangoutId]);
            $messages = $stmt->fetchAll();

            respond(['success' => true, 'messages' => $messages]);
            break;

        // ================= NOTIFICATIONS =================
        case 'get_notifications':
            $currentUser = getCurrentUser($pdo);
            if (!$currentUser) respond(['success' => true, 'notifications' => [], 'unread_count' => 0]);

            $stmt = $pdo->prepare("SELECT * FROM `notifications` WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
            $stmt->execute([$currentUser['id']]);
            $notifs = $stmt->fetchAll();

            $unreadCount = 0;
            foreach ($notifs as $n) {
                if (!$n['is_read']) $unreadCount++;
            }

            respond([
                'success' => true,
                'notifications' => $notifs,
                'unread_count' => $unreadCount
            ]);
            break;

        case 'mark_notifications_read':
            $currentUser = getCurrentUser($pdo);
            if ($currentUser) {
                $pdo->prepare("UPDATE `notifications` SET is_read = 1 WHERE user_id = ?")->execute([$currentUser['id']]);
            }
            respond(['success' => true]);
            break;

        // ================= RESET SAMPLE DATA =================
        case 'reset_sample_data':
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
            $tables = ['notifications', 'hangout_activities', 'hangout_messages', 'hangout_poll_votes', 'hangout_poll_options', 'hangout_polls', 'hangout_attendees', 'hangout_tasks', 'hangout_invited_friends', 'hangouts', 'friends', 'users'];
            foreach ($tables as $t) {
                try {
                    $pdo->exec("TRUNCATE TABLE `{$t}`;");
                } catch (Exception $e) {}
            }
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            seedQuettaData($pdo);
            respond(['success' => true, 'message' => "Quetta & Balochistan demo data reseeded successfully!"]);
            break;

        default:
            respondError("Unknown action: " . htmlspecialchars($action), 404);
            break;
    }
} catch (Exception $e) {
    respondError($e->getMessage(), 500);
}
