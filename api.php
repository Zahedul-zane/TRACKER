<?php
/**
 * Bangladesh District & Spots Tracker - REST API Backend
 * Connected to MongoDB Atlas with Gmail-based user authentication & tracking.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

$db = TrackerDB::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Helper for sending JSON responses
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 8 Divisions and their total district counts in Bangladesh
$divisionsInfo = [
    'Dhaka' => ['name_bn' => 'ঢাকা', 'total' => 13, 'districts' => ['Dhaka', 'Gazipur', 'Kishoreganj', 'Manikganj', 'Munshiganj', 'Narayanganj', 'Narsingdi', 'Tangail', 'Faridpur', 'Gopalganj', 'Madaripur', 'Rajbari', 'Shariatpur']],
    'Chattogram' => ['name_bn' => 'চট্টগ্রাম', 'total' => 11, 'districts' => ['Chattogram', 'Cox\'s Bazar', 'Cumilla', 'Feni', 'Brahmanbaria', 'Chandpur', 'Lakshmipur', 'Noakhali', 'Khagrachhari', 'Rangamati', 'Bandarban']],
    'Rajshahi' => ['name_bn' => 'রাজশাহী', 'total' => 8, 'districts' => ['Rajshahi', 'Bogura', 'Joypurhat', 'Naogaon', 'Natore', 'Chapai Nawabganj', 'Pabna', 'Sirajganj']],
    'Khulna' => ['name_bn' => 'খুলনা', 'total' => 10, 'districts' => ['Khulna', 'Bagerhat', 'Chuadanga', 'Jashore', 'Jhenaidah', 'Kushtia', 'Magura', 'Meherpur', 'Narail', 'Satkhira']],
    'Barishal' => ['name_bn' => 'বরিশাল', 'total' => 6, 'districts' => ['Barishal', 'Barguna', 'Bhola', 'Jhalokati', 'Patuakhali', 'Pirojpur']],
    'Sylhet' => ['name_bn' => 'সিলেট', 'total' => 4, 'districts' => ['Sylhet', 'Habiganj', 'Moulvibazar', 'Sunamganj']],
    'Rangpur' => ['name_bn' => 'রংপুর', 'total' => 8, 'districts' => ['Rangpur', 'Dinajpur', 'Gaibandha', 'Kurigram', 'Lalmonirhat', 'Nilphamari', 'Panchagarh', 'Thakurgaon']],
    'Mymensingh' => ['name_bn' => 'ময়মনসিংহ', 'total' => 4, 'districts' => ['Mymensingh', 'Jamalpur', 'Netrokona', 'Sherpur']]
];

function calculateBadges($visits, $spotVisits = []) {
    $visitedDistricts = [];
    foreach ($visits as $name => $v) {
        if (($v['status'] ?? '') === 'visited') {
            $visitedDistricts[strtolower(trim($name))] = true;
        }
    }
    $visitedCount = count($visitedDistricts);

    $visitedSpotsCount = 0;
    foreach ($spotVisits as $sv) {
        if (($sv['status'] ?? '') === 'visited') $visitedSpotsCount++;
    }

    $badges = [
        [
            'id' => 'first_step',
            'title' => 'First Footstep',
            'title_bn' => 'প্রথম পদক্ষেপ',
            'icon' => '👣',
            'desc' => 'Visited your first district in Bangladesh.',
            'unlocked' => $visitedCount >= 1,
            'progress' => min(1, $visitedCount / 1)
        ],
        [
            'id' => 'spots_pioneer',
            'title' => 'Spot Explorer',
            'title_bn' => 'দর্শনীয় স্থান অনুসন্ধানী',
            'icon' => '📍',
            'desc' => 'Visited at least 5 famous tourist spots.',
            'unlocked' => $visitedSpotsCount >= 5,
            'progress' => min(1, $visitedSpotsCount / 5)
        ],
        [
            'id' => 'sylhet_wanderer',
            'title' => 'Tea Garden Wanderer',
            'title_bn' => 'চায়ের দেশের পথিক',
            'icon' => '🍵',
            'desc' => 'Visited all 4 districts of Sylhet Division.',
            'unlocked' => isset($visitedDistricts['sylhet']) && isset($visitedDistricts['moulvibazar']) && isset($visitedDistricts['habiganj']) && isset($visitedDistricts['sunamganj']),
            'progress' => ((int)isset($visitedDistricts['sylhet']) + (int)isset($visitedDistricts['moulvibazar']) + (int)isset($visitedDistricts['habiganj']) + (int)isset($visitedDistricts['sunamganj'])) / 4
        ],
        [
            'id' => 'cht_explorer',
            'title' => 'Highland Conqueror',
            'title_bn' => 'পাহাড় বিজয়ী',
            'icon' => '⛰️',
            'desc' => 'Conquered Bandarban, Rangamati & Khagrachhari.',
            'unlocked' => isset($visitedDistricts['bandarban']) && isset($visitedDistricts['rangamati']) && isset($visitedDistricts['khagrachhari']),
            'progress' => ((int)isset($visitedDistricts['bandarban']) + (int)isset($visitedDistricts['rangamati']) + (int)isset($visitedDistricts['khagrachhari'])) / 3
        ],
        [
            'id' => 'coastal_nomad',
            'title' => 'Coastal Nomad',
            'title_bn' => 'উপকূল যাযাবর',
            'icon' => '🌊',
            'desc' => 'Explored the southern coast (Cox\'s Bazar, Chattogram, Patuakhali, Bhola).',
            'unlocked' => isset($visitedDistricts['cox\'s bazar']) && isset($visitedDistricts['chattogram']) && isset($visitedDistricts['patuakhali']) && isset($visitedDistricts['bhola']),
            'progress' => ((int)isset($visitedDistricts['cox\'s bazar']) + (int)isset($visitedDistricts['chattogram']) + (int)isset($visitedDistricts['patuakhali']) + (int)isset($visitedDistricts['bhola'])) / 4
        ],
        [
            'id' => 'riverine_wanderer',
            'title' => 'Delta Voyager',
            'title_bn' => 'নদীমাতৃক পরিব্রাজক',
            'icon' => '⛵',
            'desc' => 'Traveled to 10+ districts across Bangladesh.',
            'unlocked' => $visitedCount >= 10,
            'progress' => min(1, $visitedCount / 10)
        ],
        [
            'id' => 'spots_master',
            'title' => 'Heritage & Nature Master',
            'title_bn' => 'প্রকৃতি ও ঐতিহ্য বিজয়ী',
            'icon' => '🌟',
            'desc' => 'Explored 15+ tourist attractions and heritage spots.',
            'unlocked' => $visitedSpotsCount >= 15,
            'progress' => min(1, $visitedSpotsCount / 15)
        ],
        [
            'id' => 'half_century',
            'title' => 'Half Bengal Explorer',
            'title_bn' => 'অর্ধ-বাংলা জয়ী',
            'icon' => '⭐',
            'desc' => 'Visited 32+ districts (50% of the nation).',
            'unlocked' => $visitedCount >= 32,
            'progress' => min(1, $visitedCount / 32)
        ],
        [
            'id' => 'sonar_bangla',
            'title' => 'Sonar Bangla Master',
            'title_bn' => 'সোনার বাংলা বিজয়ী',
            'icon' => '👑',
            'desc' => 'Ultimate achievement: Explored all 64 districts!',
            'unlocked' => $visitedCount >= 64,
            'progress' => min(1, $visitedCount / 64)
        ]
    ];

    return $badges;
}

function calculateDivisionStats($visits, $divisionsInfo) {
    $divStats = [];
    foreach ($divisionsInfo as $divName => $info) {
        $visitedInDiv = 0;
        $wishlistInDiv = 0;
        foreach ($info['districts'] as $distName) {
            $status = $visits[$distName]['status'] ?? '';
            if ($status === 'visited') $visitedInDiv++;
            elseif ($status === 'wishlist') $wishlistInDiv++;
        }
        $divStats[$divName] = [
            'name' => $divName,
            'name_bn' => $info['name_bn'],
            'total' => $info['total'],
            'visited' => $visitedInDiv,
            'wishlist' => $wishlistInDiv,
            'percentage' => round(($visitedInDiv / $info['total']) * 100, 1)
        ];
    }
    return $divStats;
}

switch ($action) {
    case 'get_data':
        $reqGmail = trim($_GET['gmail'] ?? '');
        if ($reqGmail) {
            $db->setActiveGmail($reqGmail);
        }

        $activeGmail = $db->getActiveGmail();
        $visits = $db->getAllVisits($activeGmail);
        $userProfile = $db->getUserProfile($activeGmail);
        $config = $db->getConfig();
        $spots = $db->getAllSpots();
        $spotVisits = $db->getUserSpotVisits($activeGmail);
        $badges = calculateBadges($visits, $spotVisits);
        $divisionStats = calculateDivisionStats($visits, $divisionsInfo);

        $visitedCount = 0;
        $wishlistCount = 0;
        foreach ($visits as $v) {
            if (($v['status'] ?? '') === 'visited') $visitedCount++;
            elseif (($v['status'] ?? '') === 'wishlist') $wishlistCount++;
        }

        $visitedSpotsCount = 0;
        $wishlistSpotsCount = 0;
        foreach ($spotVisits as $sv) {
            if (($sv['status'] ?? '') === 'visited') $visitedSpotsCount++;
            elseif (($sv['status'] ?? '') === 'wishlist') $wishlistSpotsCount++;
        }

        sendResponse([
            'success' => true,
            'active_gmail' => $activeGmail,
            'auth' => $db->checkAuth(),
            'visits' => $visits,
            'user' => $userProfile,
            'config' => $config,
            'stats' => [
                'total_districts' => 64,
                'visited_count' => $visitedCount,
                'wishlist_count' => $wishlistCount,
                'unvisited_count' => 64 - ($visitedCount + $wishlistCount),
                'percentage' => round(($visitedCount / 64) * 100, 1),
                'total_spots' => count($spots),
                'visited_spots_count' => $visitedSpotsCount,
                'wishlist_spots_count' => $wishlistSpotsCount,
                'spots_percentage' => count($spots) > 0 ? round(($visitedSpotsCount / count($spots)) * 100, 1) : 0,
                'divisions' => $divisionStats,
                'badges' => $badges
            ]
        ]);
        break;

    /* ========================================================
     * GMAIL-BASED USER AUTHENTICATION & LOGIN/REGISTRATION DASHBOARD
     * ======================================================== */
    case 'auth_check':
        sendResponse([
            'success' => true,
            'auth' => $db->checkAuth()
        ]);
        break;

    case 'auth_register':
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true) ?: $_POST;

        $gmail = strtolower(trim($input['gmail'] ?? ''));
        $password = $input['password'] ?? '';
        $confirmPassword = $input['confirm_password'] ?? '';
        $name = trim($input['name'] ?? '');
        $homeDistrict = trim($input['home_district'] ?? 'Dhaka');

        if (empty($gmail) || !filter_var($gmail, FILTER_VALIDATE_EMAIL)) {
            sendResponse(['success' => false, 'message' => 'Please provide a valid Gmail address.'], 400);
        }

        if (strlen($password) < 6) {
            sendResponse(['success' => false, 'message' => 'Password must be at least 6 characters long.'], 400);
        }

        if (!empty($confirmPassword) && $password !== $confirmPassword) {
            sendResponse(['success' => false, 'message' => 'Passwords do not match.'], 400);
        }

        $extra = [
            'name_bn' => trim($input['name_bn'] ?? ''),
            'phone' => trim($input['phone'] ?? ''),
            'bio' => trim($input['bio'] ?? ''),
            'travel_motto' => trim($input['travel_motto'] ?? ''),
            'avatar' => trim($input['avatar'] ?? '🧭'),
            'travel_style' => trim($input['travel_style'] ?? 'Adventure & Photography')
        ];

        $res = $db->registerUser($gmail, $password, $name, $homeDistrict, $extra);
        sendResponse($res, $res['success'] ? 200 : 400);
        break;

    case 'auth_login':
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true) ?: $_POST;

        $gmail = strtolower(trim($input['gmail'] ?? ''));
        $password = $input['password'] ?? '';

        if (empty($gmail) || empty($password)) {
            sendResponse(['success' => false, 'message' => 'Please provide both your Gmail and password.'], 400);
        }

        $res = $db->loginUser($gmail, $password);
        sendResponse($res, $res['success'] ? 200 : 400);
        break;

    case 'auth_logout':
        $res = $db->logoutUser();
        sendResponse($res);
        break;

    case 'auth_change_password':
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true) ?: $_POST;

        $oldPassword = $input['old_password'] ?? '';
        $newPassword = $input['new_password'] ?? '';
        $confirmPassword = $input['confirm_password'] ?? '';

        if (empty($newPassword) || strlen($newPassword) < 6) {
            sendResponse(['success' => false, 'message' => 'New password must be at least 6 characters.'], 400);
        }

        if (!empty($confirmPassword) && $newPassword !== $confirmPassword) {
            sendResponse(['success' => false, 'message' => 'New passwords do not match.'], 400);
        }

        $res = $db->changePassword($oldPassword, $newPassword);
        sendResponse($res, $res['success'] ? 200 : 400);
        break;

    case 'get_travelers_leaderboard':
        $leaderboard = $db->getTravelersDashboard();
        sendResponse([
            'success' => true,
            'leaderboard' => $leaderboard,
            'active_gmail' => $db->getActiveGmail()
        ]);
        break;

    case 'switch_gmail':
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true) ?: $_POST;

        $gmail = strtolower(trim($input['gmail'] ?? ''));
        if (empty($gmail) || !filter_var($gmail, FILTER_VALIDATE_EMAIL)) {
            sendResponse(['success' => false, 'message' => 'Please provide a valid Gmail address.'], 400);
        }

        $db->setActiveGmail($gmail);
        $profile = $db->getUserProfile($gmail);

        sendResponse([
            'success' => true,
            'message' => "Logged in with Gmail: {$gmail}",
            'active_gmail' => $gmail,
            'user' => $profile
        ]);
        break;

    case 'get_profile':
        $gmail = trim($_GET['gmail'] ?? '');
        $profile = $db->getUserProfile($gmail ?: null);
        sendResponse([
            'success' => true,
            'user' => $profile
        ]);
        break;

    case 'save_profile':
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true) ?: $_POST;

        $gmail = strtolower(trim($input['gmail'] ?? $db->getActiveGmail()));
        $saved = $db->saveUserProfile($input, $gmail);

        sendResponse([
            'success' => true,
            'message' => 'User profile updated in MongoDB Atlas successfully.',
            'user' => $saved
        ]);
        break;

    case 'get_users':
        $users = $db->getAllUsers();
        sendResponse([
            'success' => true,
            'users' => $users,
            'active_gmail' => $db->getActiveGmail()
        ]);
        break;

    /* ========================================================
     * SPOTS API (PER GMAIL)
     * ======================================================== */
    case 'get_spots':
        $activeGmail = $db->getActiveGmail();
        $spots = $db->getAllSpots();
        $spotVisits = $db->getUserSpotVisits($activeGmail);

        $enrichedSpots = array_map(function($s) use ($spotVisits) {
            $id = $s['id'];
            $visit = $spotVisits[$id] ?? null;
            $s['status'] = $visit['status'] ?? 'unvisited';
            $s['rating'] = $visit['rating'] ?? 5;
            $s['visit_date'] = $visit['visit_date'] ?? null;
            $s['notes'] = $visit['notes'] ?? '';
            $s['photos'] = $visit['photos'] ?? [];
            return $s;
        }, $spots);

        $categories = array_values(array_unique(array_column($spots, 'category')));
        $divisions = array_values(array_unique(array_column($spots, 'division')));

        sendResponse([
            'success' => true,
            'active_gmail' => $activeGmail,
            'spots' => $enrichedSpots,
            'categories' => $categories,
            'divisions' => $divisions,
            'total_spots' => count($spots),
            'spot_visits' => $spotVisits
        ]);
        break;

    case 'toggle_spot':
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true) ?: $_POST;

        $spotId = trim($input['spot_id'] ?? '');
        $status = trim($input['status'] ?? 'visited');

        if (empty($spotId)) {
            sendResponse(['success' => false, 'message' => 'Spot ID is required.'], 400);
        }

        $res = $db->toggleSpotStatus($spotId, $status);
        sendResponse([
            'success' => true,
            'message' => "Updated spot status to {$status}.",
            'record' => $res
        ]);
        break;

    case 'save_spot_visit':
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true) ?: $_POST;

        $spotId = trim($input['spot_id'] ?? '');
        if (empty($spotId)) {
            sendResponse(['success' => false, 'message' => 'Spot ID is required.'], 400);
        }

        $saved = $db->saveSpotVisit($spotId, $input);
        sendResponse([
            'success' => true,
            'message' => 'Saved spot travel memories to MongoDB Atlas.',
            'record' => $saved
        ]);
        break;

    case 'delete_spot_visit':
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true) ?: $_POST;

        $spotId = trim($input['spot_id'] ?? '');
        if (empty($spotId)) {
            sendResponse(['success' => false, 'message' => 'Spot ID is required.'], 400);
        }

        $db->deleteSpotVisit($spotId);
        sendResponse([
            'success' => true,
            'message' => 'Reset spot visit.'
        ]);
        break;

    /* ========================================================
     * DISTRICT VISITS (PER GMAIL)
     * ======================================================== */
    case 'save_visit':
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true) ?: $_POST;

        $districtName = trim($input['district_name'] ?? '');
        if (empty($districtName)) {
            sendResponse(['success' => false, 'message' => 'District name is required.'], 400);
        }

        $saved = $db->saveVisit($districtName, $input);
        sendResponse([
            'success' => true,
            'message' => "Saved travel details for {$districtName} to MongoDB Atlas.",
            'record' => $saved
        ]);
        break;

    case 'toggle_status':
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true) ?: $_POST;

        $districtName = trim($input['district_name'] ?? '');
        $status = trim($input['status'] ?? 'visited');

        if (empty($districtName)) {
            sendResponse(['success' => false, 'message' => 'District name is required.'], 400);
        }

        $res = $db->toggleDistrictStatus($districtName, $status, $input);
        sendResponse([
            'success' => true,
            'message' => "Updated status of {$districtName} to {$status}.",
            'record' => $res
        ]);
        break;

    case 'delete_visit':
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true) ?: $_POST;

        $districtName = trim($input['district_name'] ?? '');
        if (empty($districtName)) {
            sendResponse(['success' => false, 'message' => 'District name is required.'], 400);
        }

        $db->deleteVisit($districtName);
        sendResponse([
            'success' => true,
            'message' => "Reset details for {$districtName}."
        ]);
        break;

    case 'save_config':
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true) ?: $_POST;

        $uri = trim($input['mongo_uri'] ?? '');
        $dbName = trim($input['db_name'] ?? 'bangladesh_tracker');

        $test = $db->saveConfig($uri, $dbName);
        sendResponse([
            'success' => $test['success'],
            'message' => $test['message'],
            'config' => $db->getConfig()
        ]);
        break;

    case 'test_mongo':
        $test = $db->testMongoConnection();
        sendResponse($test);
        break;

    case 'sync_mongo':
        $res = $db->syncLocalToMongo();
        sendResponse($res);
        break;

    case 'upload_photo':
        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            sendResponse(['success' => false, 'message' => 'No file uploaded or upload error.'], 400);
        }

        $file = $_FILES['photo'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (!in_array($ext, $allowed)) {
            sendResponse(['success' => false, 'message' => 'Invalid image format. Allowed: JPG, PNG, WEBP, GIF.'], 400);
        }

        if ($file['size'] > 6 * 1024 * 1024) {
            sendResponse(['success' => false, 'message' => 'File size exceeds 6MB limit.'], 400);
        }

        $filename = 'trip_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $dest = UPLOADS_DIR . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            sendResponse([
                'success' => true,
                'message' => 'Photo uploaded successfully.',
                'file_path' => 'assets/uploads/' . $filename
            ]);
        } else {
            sendResponse(['success' => false, 'message' => 'Failed to save uploaded photo.'], 500);
        }
        break;

    case 'export':
        $data = $db->exportData();
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        header('Content-Disposition: attachment; filename="bangladesh_travel_' . $db->getActiveGmail() . '_' . date('Y-m-d') . '.json"');
        echo $json;
        exit;

    case 'import':
        if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
            $content = file_get_contents($_FILES['import_file']['tmp_name']);
        } else {
            $raw = file_get_contents('php://input');
            $input = json_decode($raw, true);
            $content = isset($input['data']) ? json_encode($input['data']) : $raw;
        }

        $parsed = json_decode($content, true);
        if (!$parsed) {
            sendResponse(['success' => false, 'message' => 'Invalid JSON file.'], 400);
        }

        $res = $db->importData($parsed);
        sendResponse($res);
        break;

    case 'load_demo':
        $gmail = $db->getActiveGmail();
        $demoVisits = [
            'Dhaka' => [
                'district_name' => 'Dhaka',
                'district_bn' => 'ঢাকা',
                'division' => 'Dhaka',
                'division_bn' => 'ঢাকা',
                'status' => 'visited',
                'visit_date' => '2024-01-10',
                'rating' => 5,
                'notes' => 'Explored Lalbagh Fort, Ahsan Manzil, and had Biryani in Old Dhaka!',
                'favorite_spots' => 'Lalbagh Fort, Ahsan Manzil, Hatirjheel',
                'companions' => 'Friends',
                'transport_mode' => 'Metro & Rickshaw',
                'photos' => []
            ],
            'Cox\'s Bazar' => [
                'district_name' => 'Cox\'s Bazar',
                'district_bn' => 'কক্সবাজার',
                'division' => 'Chattogram',
                'division_bn' => 'চট্টগ্রাম',
                'status' => 'visited',
                'visit_date' => '2024-02-14',
                'rating' => 5,
                'notes' => 'Witnessed majestic sunset at Inani beach and took a ride through Marine Drive.',
                'favorite_spots' => 'Inani Beach, Marine Drive, Saint Martin Island',
                'companions' => 'Family',
                'transport_mode' => 'Air & Car',
                'photos' => []
            ],
            'Bandarban' => [
                'district_name' => 'Bandarban',
                'district_bn' => 'বান্দরবান',
                'division' => 'Chattogram',
                'division_bn' => 'চট্টগ্রাম',
                'status' => 'visited',
                'visit_date' => '2024-04-18',
                'rating' => 5,
                'notes' => 'Cloud touching experience at Nilgiri, trek through Boga Lake & Nafakhum waterfall.',
                'favorite_spots' => 'Nilgiri, Nilachal, Boga Lake, Nafakhum',
                'companions' => 'Friends',
                'transport_mode' => 'Chander Gari (4x4)',
                'photos' => []
            ],
            'Sylhet' => [
                'district_name' => 'Sylhet',
                'district_bn' => 'সিলেট',
                'division' => 'Sylhet',
                'division_bn' => 'সিলেট',
                'status' => 'visited',
                'visit_date' => '2024-05-20',
                'rating' => 5,
                'notes' => 'Lush green tea gardens, Ratargul swamp forest and the turquoise waters of Jaflong.',
                'favorite_spots' => 'Ratargul Swamp Forest, Jaflong, Malnicherra Tea Estate',
                'companions' => 'Group Tour',
                'transport_mode' => 'Train',
                'photos' => []
            ],
            'Moulvibazar' => [
                'district_name' => 'Moulvibazar',
                'district_bn' => 'মৌলভীবাজার',
                'division' => 'Sylhet',
                'division_bn' => 'সিলেট',
                'status' => 'visited',
                'visit_date' => '2024-05-22',
                'rating' => 5,
                'notes' => 'Visited Lawachara National Park and tasted famous 7-layer tea at Sreemangal.',
                'favorite_spots' => 'Lawachara Rain Forest, Sreemangal Tea Estates, Madhabkunda',
                'companions' => 'Group Tour',
                'transport_mode' => 'Train',
                'photos' => []
            ],
            'Bagerhat' => [
                'district_name' => 'Bagerhat',
                'district_bn' => 'বাগেরহাট',
                'division' => 'Khulna',
                'division_bn' => 'খুলনা',
                'status' => 'visited',
                'visit_date' => '2024-08-12',
                'rating' => 5,
                'notes' => 'UNESCO World Heritage Sixty Dome Mosque (Shat Gombuj Masjid) and Sundarbans gateway.',
                'favorite_spots' => 'Sixty Dome Mosque, Khan Jahan Ali Mazar, Sundarbans',
                'companions' => 'Solo',
                'transport_mode' => 'Bus',
                'photos' => []
            ],
            'Rangamati' => [
                'district_name' => 'Rangamati',
                'district_bn' => 'রাঙ্গামাটি',
                'division' => 'Chattogram',
                'division_bn' => 'চট্টগ্রাম',
                'status' => 'wishlist',
                'visit_date' => '',
                'rating' => 5,
                'notes' => 'Planning to cruise on Kaptai Lake and visit Hanging Bridge next winter!',
                'favorite_spots' => 'Kaptai Lake, Hanging Bridge, Shuvolong Waterfall',
                'companions' => 'Friends',
                'transport_mode' => 'Bus & Boat',
                'photos' => []
            ],
            'Panchagarh' => [
                'district_name' => 'Panchagarh',
                'district_bn' => 'পঞ্চগড়',
                'division' => 'Rangpur',
                'division_bn' => 'রংপুর',
                'status' => 'wishlist',
                'visit_date' => '',
                'rating' => 5,
                'notes' => 'Want to see the peaks of Kanchenjunga from Tetulia in late autumn!',
                'favorite_spots' => 'Tetulia Zero Point, Kanchenjunga Viewpoint, Tea Gardens',
                'companions' => 'Solo',
                'transport_mode' => 'Train',
                'photos' => []
            ]
        ];

        foreach ($demoVisits as $dist => $item) {
            $db->saveVisit($dist, $item, $gmail);
        }

        $demoSpots = [
            'spot_1' => ['status' => 'visited', 'rating' => 5, 'visit_date' => '2024-02-16', 'notes' => 'Walked along Chera Dwip and saw real coral reefs!'],
            'spot_2' => ['status' => 'visited', 'rating' => 5, 'visit_date' => '2024-02-14', 'notes' => 'Unbelievable sunset by the marine drive road.'],
            'spot_4' => ['status' => 'visited', 'rating' => 5, 'visit_date' => '2024-04-18', 'notes' => 'Woke up at 5 AM surrounded by white fluffy clouds.'],
            'spot_9' => ['status' => 'visited', 'rating' => 5, 'visit_date' => '2024-04-20', 'notes' => 'Sajek helipad night sky is packed with shining stars.'],
            'spot_16' => ['status' => 'visited', 'rating' => 5, 'visit_date' => '2024-01-10', 'notes' => 'Historical Mughal architecture in Old Dhaka.'],
            'spot_24' => ['status' => 'visited', 'rating' => 5, 'visit_date' => '2024-05-20', 'notes' => 'Mystical canoe ride under submerged trees.'],
            'spot_26' => ['status' => 'visited', 'rating' => 5, 'visit_date' => '2024-05-21', 'notes' => 'Crystal cold mountain water rushing over white stones.'],
            'spot_31' => ['status' => 'visited', 'rating' => 5, 'visit_date' => '2024-05-22', 'notes' => 'Sreemangal 7-color tea and endless green tea gardens.'],
            'spot_34' => ['status' => 'visited', 'rating' => 5, 'visit_date' => '2024-08-12', 'notes' => 'Historic 77 domes and ancient architectural grandeur.'],
            'spot_38' => ['status' => 'wishlist', 'rating' => 5, 'visit_date' => '', 'notes' => 'Must watch both sunrise & sunset from Kuakata beach.'],
            'spot_41' => ['status' => 'wishlist', 'rating' => 5, 'visit_date' => '', 'notes' => 'Paharpur Somapura Mahavihara UNESCO site tour.'],
            'spot_47' => ['status' => 'wishlist', 'rating' => 5, 'visit_date' => '', 'notes' => 'Spotting Mount Kanchenjunga from Tetulia.']
        ];

        foreach ($demoSpots as $sId => $sData) {
            $db->saveSpotVisit($sId, $sData, $gmail);
        }

        sendResponse([
            'success' => true,
            'message' => "Loaded demo travel data for {$gmail} in MongoDB Atlas!"
        ]);
        break;

    case 'reset_all':
        $gmail = $db->getActiveGmail();
        $all = $db->getAllVisits($gmail);
        foreach ($all as $distName => $v) {
            $db->deleteVisit($distName, $gmail);
        }
        $spotVisits = $db->getUserSpotVisits($gmail);
        foreach ($spotVisits as $sId => $v) {
            $db->deleteSpotVisit($sId, $gmail);
        }
        sendResponse([
            'success' => true,
            'message' => "All district visits and spots have been reset for {$gmail}."
        ]);
        break;

    default:
        sendResponse(['success' => false, 'message' => 'Invalid action.'], 404);
}
