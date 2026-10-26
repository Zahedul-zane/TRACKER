<?php
/**
 * Bangladesh District & Spots Tracker - Config & Database Handler
 * Connected to MongoDB Atlas Cluster with Gmail-based User Profiles.
 */

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Configuration constants
define('DATA_DIR', __DIR__ . '/data');
define('UPLOADS_DIR', __DIR__ . '/assets/uploads');
define('CONFIG_FILE', DATA_DIR . '/config.json');
define('FALLBACK_STORAGE_FILE', DATA_DIR . '/visits_fallback.json');
define('SPOTS_FILE', DATA_DIR . '/spots.json');
define('SPOT_VISITS_FILE', DATA_DIR . '/spot_visits_fallback.json');
define('USERS_FILE', DATA_DIR . '/users.json');

// Ensure directories exist
if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
if (!is_dir(UPLOADS_DIR)) mkdir(UPLOADS_DIR, 0755, true);

class TrackerDB {
    private static $instance = null;
    private $mongoManager = null;
    private $mongoConnected = false;
    
    // User MongoDB Atlas Credentials
    private $mongoUri = 'mongodb+srv://zizahed202_db_user:xwlR2Yfxh1HdqM1r@cluster0.k69terq.mongodb.net/?retryWrites=true&w=majority';
    private $dbName = 'bangladesh_tracker';
    private $defaultGmail = 'zizahed202@gmail.com';

    // MongoDB Collections
    private $collectionVisits = 'district_visits';
    private $collectionSettings = 'tracker_settings';
    private $collectionUsers = 'users';
    private $collectionSpots = 'spots';
    private $collectionSpotVisits = 'user_spot_visits';
    private $lastError = null;

    private function __construct() {
        $this->loadConfig();
        $this->initMongo();
        if ($this->mongoConnected) {
            $this->seedSpotsToMongo();
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadConfig() {
        if (file_exists(CONFIG_FILE)) {
            $conf = json_decode(file_get_contents(CONFIG_FILE), true);
            if (is_array($conf)) {
                if (!empty($conf['mongo_uri'])) {
                    $this->mongoUri = trim($conf['mongo_uri']);
                }
                if (!empty($conf['db_name'])) {
                    $this->dbName = trim($conf['db_name']);
                }
                if (!empty($conf['active_gmail'])) {
                    $this->defaultGmail = trim($conf['active_gmail']);
                }
            }
        }

        // Initialize active session Gmail
        if (empty($_SESSION['active_gmail'])) {
            $_SESSION['active_gmail'] = $this->defaultGmail;
        }
    }

    public function saveConfig($uri, $dbName = 'bangladesh_tracker') {
        $this->mongoUri = trim($uri);
        $this->dbName = trim($dbName) ?: 'bangladesh_tracker';
        
        $conf = [
            'mongo_uri' => $this->mongoUri,
            'db_name' => $this->dbName,
            'active_gmail' => $this->getActiveGmail(),
            'updated_at' => date('c')
        ];
        file_put_contents(CONFIG_FILE, json_encode($conf, JSON_PRETTY_PRINT));
        
        $this->initMongo();
        if ($this->mongoConnected) {
            $this->seedSpotsToMongo();
        }
        return $this->testMongoConnection();
    }

    public function getConfig() {
        return [
            'mongo_uri' => $this->mongoUri,
            'db_name' => $this->dbName,
            'mongo_connected' => $this->mongoConnected,
            'active_gmail' => $this->getActiveGmail(),
            'last_error' => $this->lastError
        ];
    }

    private function initMongo() {
        $this->mongoConnected = false;
        $this->lastError = null;

        if (!class_exists('MongoDB\Driver\Manager')) {
            $this->lastError = 'MongoDB PHP driver extension (ext-mongodb) is not available.';
            return;
        }

        try {
            $options = [
                'serverSelectionTimeoutMS' => 5000,
                'connectTimeoutMS' => 5000
            ];
            $this->mongoManager = new MongoDB\Driver\Manager($this->mongoUri, $options);
            
            $command = new MongoDB\Driver\Command(['ping' => 1]);
            $this->mongoManager->executeCommand('admin', $command);
            $this->mongoConnected = true;
        } catch (\Exception $e) {
            $this->mongoConnected = false;
            $this->lastError = $e->getMessage();
        }
    }

    public function testMongoConnection() {
        if (!class_exists('MongoDB\Driver\Manager')) {
            return [
                'success' => false,
                'message' => 'MongoDB PHP extension is not installed.'
            ];
        }

        try {
            $options = [
                'serverSelectionTimeoutMS' => 5000,
                'connectTimeoutMS' => 5000
            ];
            $manager = new MongoDB\Driver\Manager($this->mongoUri, $options);
            $start = microtime(true);
            $command = new MongoDB\Driver\Command(['ping' => 1]);
            $cursor = $manager->executeCommand('admin', $command);
            $response = current($cursor->toArray());
            $latency = round((microtime(true) - $start) * 1000, 2);

            $this->mongoConnected = true;
            $this->mongoManager = $manager;
            $this->lastError = null;

            return [
                'success' => true,
                'message' => 'Connected successfully to MongoDB Atlas (' . $latency . 'ms latency)',
                'latency_ms' => $latency,
                'db_name' => $this->dbName,
                'uri' => $this->maskUri($this->mongoUri)
            ];
        } catch (\Exception $e) {
            $this->mongoConnected = false;
            $this->lastError = $e->getMessage();
            return [
                'success' => false,
                'message' => 'MongoDB Connection Failed: ' . $e->getMessage()
            ];
        }
    }

    private function maskUri($uri) {
        return preg_replace('/:\/\/([^:]+):([^@]+)@/', '://$1:****@', $uri);
    }

    public function isConnected() {
        return $this->mongoConnected;
    }

    /* ========================================================
     * GMAIL-BASED USER AUTHENTICATION & PROFILES (MONGODB)
     * ======================================================== */

    public function getActiveGmail() {
        if (!empty($_SESSION['auth_user'])) {
            return strtolower(trim($_SESSION['auth_user']));
        }
        if (!empty($_SESSION['active_gmail'])) {
            return strtolower(trim($_SESSION['active_gmail']));
        }
        return strtolower(trim($this->defaultGmail));
    }

    public function setActiveGmail($gmail) {
        $clean = strtolower(trim($gmail));
        if (filter_var($clean, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['active_gmail'] = $clean;
            $this->defaultGmail = $clean;
            $this->saveConfig($this->mongoUri, $this->dbName);
            // Ensure profile exists
            $this->getUserProfile($clean);
            return true;
        }
        return false;
    }

    public function checkAuth() {
        $isLoggedIn = !empty($_SESSION['auth_user']);
        $gmail = $this->getActiveGmail();
        $user = $this->getUserProfile($gmail);
        return [
            'authenticated' => $isLoggedIn,
            'user' => $user,
            'active_gmail' => $gmail
        ];
    }

    public function registerUser($gmail, $password, $name, $homeDistrict = 'Dhaka', $extra = []) {
        $cleanGmail = strtolower(trim($gmail));

        if (!filter_var($cleanGmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Please provide a valid email address (e.g. name@gmail.com).'];
        }

        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters long.'];
        }

        // Check if user already exists
        $existing = $this->getUserRaw($cleanGmail);
        if ($existing && !empty($existing['password_hash'])) {
            return ['success' => false, 'message' => 'An account with this email is already registered. Please log in.'];
        }

        $now = date('c');
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $nameFromEmail = ucwords(str_replace(['.', '_', '-'], ' ', explode('@', $cleanGmail)[0]));
        $userName = trim($name) ?: $nameFromEmail;

        $userDoc = [
            'gmail' => $cleanGmail,
            'password_hash' => $hash,
            'name' => $userName,
            'name_bn' => trim($extra['name_bn'] ?? ''),
            'phone' => trim($extra['phone'] ?? '+880 1712-345678'),
            'home_district' => trim($homeDistrict) ?: 'Dhaka',
            'bio' => trim($extra['bio'] ?? 'Exploring the beauty of Bangladesh across all 64 districts.'),
            'travel_motto' => trim($extra['travel_motto'] ?? 'জীবন একটাই, বাংলাদেশটা ঘুরে দেখো!'),
            'avatar' => trim($extra['avatar'] ?? '🧭'),
            'travel_style' => trim($extra['travel_style'] ?? 'Adventure & Photography'),
            'joined_date' => date('M Y'),
            'created_at' => $existing['created_at'] ?? $now,
            'updated_at' => $now,
            'last_login' => $now
        ];

        // Save fallback
        $allUsers = $this->readFallbackUsers();
        $allUsers[$cleanGmail] = $userDoc;
        $this->writeFallbackUsers($allUsers);

        // Save to MongoDB Atlas
        if ($this->mongoConnected) {
            try {
                $bulk = new MongoDB\Driver\BulkWrite();
                $bulk->update(['gmail' => $cleanGmail], ['$set' => $userDoc], ['upsert' => true]);
                $this->mongoManager->executeBulkWrite("{$this->dbName}.{$this->collectionUsers}", $bulk);
            } catch (\Exception $e) {
                $this->lastError = $e->getMessage();
            }
        }

        // Authenticate session
        $_SESSION['auth_user'] = $cleanGmail;
        $_SESSION['active_gmail'] = $cleanGmail;
        $_SESSION['user_name'] = $userName;

        unset($userDoc['password_hash']);
        return [
            'success' => true,
            'message' => 'Registration successful! Welcome to Amar Desh Tracker.',
            'user' => $userDoc
        ];
    }

    public function loginUser($gmail, $password) {
        $cleanGmail = strtolower(trim($gmail));

        if (empty($cleanGmail) || empty($password)) {
            return ['success' => false, 'message' => 'Please enter both your Gmail and password.'];
        }

        $user = $this->getUserRaw($cleanGmail);

        // If user doesn't exist at all, offer prompt
        if (!$user) {
            // Check if user is logging into default account zizahed202@gmail.com
            if ($cleanGmail === 'zizahed202@gmail.com' || strpos($cleanGmail, '@') !== false) {
                // Auto-register demo/initial profile
                return $this->registerUser($cleanGmail, $password, 'Zi Zahed', 'Dhaka');
            }
            return ['success' => false, 'message' => 'Account not found. Please register first.'];
        }

        // If existing profile has no password_hash yet (legacy profile), initialize password
        if (empty($user['password_hash'])) {
            $user['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
            $user['last_login'] = date('c');
            $this->saveUserRaw($user);
        } else {
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                // Special developer/demo fallback for zizahed202@gmail.com with password 'tracker123'
                if ($cleanGmail === 'zizahed202@gmail.com' && $password === 'tracker123') {
                    $user['password_hash'] = password_hash('tracker123', PASSWORD_BCRYPT);
                    $this->saveUserRaw($user);
                } else {
                    return ['success' => false, 'message' => 'Incorrect password. Please try again.'];
                }
            }
        }

        // Update last login
        $now = date('c');
        $user['last_login'] = $now;
        $user['updated_at'] = $now;
        $this->saveUserRaw($user);

        // Set session
        $_SESSION['auth_user'] = $cleanGmail;
        $_SESSION['active_gmail'] = $cleanGmail;
        $_SESSION['user_name'] = $user['name'] ?? $cleanGmail;

        unset($user['password_hash']);
        return [
            'success' => true,
            'message' => 'Welcome back, ' . ($user['name'] ?? $cleanGmail) . '!',
            'user' => $user
        ];
    }

    public function logoutUser() {
        unset($_SESSION['auth_user']);
        unset($_SESSION['user_name']);
        // Keep active_gmail as default or reset
        $_SESSION['active_gmail'] = $this->defaultGmail;
        return [
            'success' => true,
            'message' => 'You have logged out successfully.'
        ];
    }

    public function changePassword($oldPassword, $newPassword, $gmail = null) {
        $userGmail = $gmail ? strtolower(trim($gmail)) : $this->getActiveGmail();
        $user = $this->getUserRaw($userGmail);

        if (!$user) {
            return ['success' => false, 'message' => 'User not found.'];
        }

        if (strlen($newPassword) < 6) {
            return ['success' => false, 'message' => 'New password must be at least 6 characters long.'];
        }

        if (!empty($user['password_hash']) && !password_verify($oldPassword, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Current password does not match.'];
        }

        $user['password_hash'] = password_hash($newPassword, PASSWORD_BCRYPT);
        $user['updated_at'] = date('c');
        $this->saveUserRaw($user);

        return ['success' => true, 'message' => 'Password updated successfully!'];
    }

    private function getUserRaw($gmail) {
        $clean = strtolower(trim($gmail));

        if ($this->mongoConnected) {
            try {
                $query = new MongoDB\Driver\Query(['gmail' => $clean]);
                $cursor = $this->mongoManager->executeQuery("{$this->dbName}.{$this->collectionUsers}", $query);
                $res = current($cursor->toArray());
                if ($res) {
                    $item = (array)$res;
                    if (isset($item['_id']) && is_object($item['_id'])) {
                        $item['id'] = (string)$item['_id'];
                    }
                    return $item;
                }
            } catch (\Exception $e) {
                $this->lastError = $e->getMessage();
            }
        }

        $allUsers = $this->readFallbackUsers();
        return $allUsers[$clean] ?? null;
    }

    private function saveUserRaw($userDoc) {
        $clean = strtolower(trim($userDoc['gmail']));

        $allUsers = $this->readFallbackUsers();
        $allUsers[$clean] = $userDoc;
        $this->writeFallbackUsers($allUsers);

        if ($this->mongoConnected) {
            try {
                $bulk = new MongoDB\Driver\BulkWrite();
                $bulk->update(['gmail' => $clean], ['$set' => $userDoc], ['upsert' => true]);
                $this->mongoManager->executeBulkWrite("{$this->dbName}.{$this->collectionUsers}", $bulk);
            } catch (\Exception $e) {
                $this->lastError = $e->getMessage();
            }
        }
    }

    public function getUserProfile($gmail = null) {
        $userGmail = $gmail ? strtolower(trim($gmail)) : $this->getActiveGmail();
        $nameFromEmail = ucwords(str_replace(['.', '_', '-'], ' ', explode('@', $userGmail)[0]));

        $defaultProfile = [
            'gmail' => $userGmail,
            'name' => $nameFromEmail ?: 'Zi Zahed',
            'name_bn' => 'জি জাহেদ',
            'phone' => '+880 1712-345678',
            'home_district' => 'Dhaka',
            'bio' => 'Exploring all 64 districts and famous spots across Bangladesh.',
            'travel_motto' => 'জীবন একটাই, বাংলাদেশটা ঘুরে দেখো!',
            'avatar' => '🧭',
            'travel_style' => 'Adventure & Nature Photography',
            'joined_date' => date('M Y'),
            'created_at' => date('c'),
            'updated_at' => date('c'),
            'last_login' => date('c')
        ];

        // 1. Fetch from MongoDB
        if ($this->mongoConnected) {
            try {
                $query = new MongoDB\Driver\Query(['gmail' => $userGmail]);
                $cursor = $this->mongoManager->executeQuery("{$this->dbName}.{$this->collectionUsers}", $query);
                $res = current($cursor->toArray());
                if ($res) {
                    $profile = (array)$res;
                    if (isset($profile['_id']) && is_object($profile['_id'])) {
                        $profile['id'] = (string)$profile['_id'];
                    }
                    unset($profile['password_hash']);
                    return array_merge($defaultProfile, $profile);
                } else {
                    // Create in MongoDB
                    $bulk = new MongoDB\Driver\BulkWrite();
                    $bulk->insert($defaultProfile);
                    $this->mongoManager->executeBulkWrite("{$this->dbName}.{$this->collectionUsers}", $bulk);
                    return $defaultProfile;
                }
            } catch (\Exception $e) {
                $this->lastError = $e->getMessage();
            }
        }

        // 2. Fallback to file
        $allUsers = $this->readFallbackUsers();
        if (isset($allUsers[$userGmail])) {
            $profile = $allUsers[$userGmail];
            unset($profile['password_hash']);
            return array_merge($defaultProfile, $profile);
        }

        $allUsers[$userGmail] = $defaultProfile;
        $this->writeFallbackUsers($allUsers);
        return $defaultProfile;
    }

    public function saveUserProfile($data, $gmail = null) {
        $userGmail = $gmail ? strtolower(trim($gmail)) : ($data['gmail'] ?? $this->getActiveGmail());
        $userGmail = strtolower(trim($userGmail));

        $current = $this->getUserRaw($userGmail) ?: $this->getUserProfile($userGmail);
        
        // Prevent accidental overwrite of password_hash if not provided
        if (isset($data['password_hash']) && empty($data['password_hash'])) {
            unset($data['password_hash']);
        }

        $updated = array_merge($current, $data);
        $updated['gmail'] = $userGmail;
        $updated['updated_at'] = date('c');

        $this->saveUserRaw($updated);

        unset($updated['password_hash']);
        return $updated;
    }

    public function getAllUsers() {
        if ($this->mongoConnected) {
            try {
                $query = new MongoDB\Driver\Query([], ['sort' => ['updated_at' => -1]]);
                $cursor = $this->mongoManager->executeQuery("{$this->dbName}.{$this->collectionUsers}", $query);
                $users = [];
                foreach ($cursor as $doc) {
                    $item = (array)$doc;
                    if (isset($item['_id']) && is_object($item['_id'])) {
                        $item['id'] = (string)$item['_id'];
                    }
                    unset($item['password_hash']);
                    if (!empty($item['gmail'])) {
                        $users[$item['gmail']] = $item;
                    }
                }
                if (!empty($users)) return array_values($users);
            } catch (\Exception $e) {
                // fall through
            }
        }

        $local = $this->readFallbackUsers();
        foreach ($local as &$u) {
            unset($u['password_hash']);
        }
        return array_values($local);
    }

    public function getTravelersDashboard() {
        $users = $this->getAllUsers();
        $leaderboard = [];

        foreach ($users as $u) {
            $gmail = $u['gmail'];
            $visits = $this->getAllVisits($gmail);
            $spotVisits = $this->getUserSpotVisits($gmail);

            $visitedDistricts = 0;
            foreach ($visits as $v) {
                if (($v['status'] ?? '') === 'visited') $visitedDistricts++;
            }

            $visitedSpots = 0;
            foreach ($spotVisits as $sv) {
                if (($sv['status'] ?? '') === 'visited') $visitedSpots++;
            }

            $percentage = round(($visitedDistricts / 64) * 100, 1);

            $badgeTitle = 'Explorer';
            if ($visitedDistricts >= 64) $badgeTitle = 'Sonar Bangla Master 👑';
            elseif ($visitedDistricts >= 32) $badgeTitle = 'Half Bengal Hero ⭐';
            elseif ($visitedDistricts >= 15) $badgeTitle = 'Delta Voyager ⛵';
            elseif ($visitedDistricts >= 5) $badgeTitle = 'Pathfinder 🧭';

            $leaderboard[] = [
                'gmail' => $gmail,
                'name' => $u['name'] ?? $gmail,
                'avatar' => $u['avatar'] ?? '🧭',
                'home_district' => $u['home_district'] ?? 'Dhaka',
                'travel_style' => $u['travel_style'] ?? 'Adventurer',
                'joined_date' => $u['joined_date'] ?? date('M Y'),
                'last_login' => $u['last_login'] ?? $u['updated_at'] ?? 'Recently',
                'visited_districts' => $visitedDistricts,
                'visited_spots' => $visitedSpots,
                'percentage' => $percentage,
                'badge' => $badgeTitle
            ];
        }

        // Sort descending by visited districts, then visited spots
        usort($leaderboard, function($a, $b) {
            if ($a['visited_districts'] === $b['visited_districts']) {
                return $b['visited_spots'] <=> $a['visited_spots'];
            }
            return $b['visited_districts'] <=> $a['visited_districts'];
        });

        return $leaderboard;
    }

    private function readFallbackUsers() {
        if (file_exists(USERS_FILE)) {
            $data = json_decode(file_get_contents(USERS_FILE), true);
            return is_array($data) ? $data : [];
        }
        return [];
    }

    private function writeFallbackUsers($data) {
        file_put_contents(USERS_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /* ========================================================
     * ALL SPOTS IN BANGLADESH (MONGODB + SEEDING)
     * ======================================================== */

    public function getAllSpots() {
        $spots = [];

        if ($this->mongoConnected) {
            try {
                $query = new MongoDB\Driver\Query([], ['sort' => ['division' => 1, 'name' => 1]]);
                $cursor = $this->mongoManager->executeQuery("{$this->dbName}.{$this->collectionSpots}", $query);
                foreach ($cursor as $doc) {
                    $item = (array)$doc;
                    if (isset($item['_id']) && is_object($item['_id'])) {
                        $item['_id'] = (string)$item['_id'];
                    }
                    $spots[] = $item;
                }
            } catch (\Exception $e) {
                $this->lastError = $e->getMessage();
            }
        }

        if (empty($spots) && file_exists(SPOTS_FILE)) {
            $spots = json_decode(file_get_contents(SPOTS_FILE), true);
        }

        return is_array($spots) ? $spots : [];
    }

    public function seedSpotsToMongo() {
        if (!$this->mongoConnected || !file_exists(SPOTS_FILE)) return;

        try {
            $query = new MongoDB\Driver\Query([], ['limit' => 1]);
            $cursor = $this->mongoManager->executeQuery("{$this->dbName}.{$this->collectionSpots}", $query);
            $hasSpots = count($cursor->toArray()) > 0;

            if (!$hasSpots) {
                $spots = json_decode(file_get_contents(SPOTS_FILE), true);
                if (is_array($spots)) {
                    $bulk = new MongoDB\Driver\BulkWrite();
                    foreach ($spots as $s) {
                        $bulk->update(['id' => $s['id']], ['$set' => $s], ['upsert' => true]);
                    }
                    $this->mongoManager->executeBulkWrite("{$this->dbName}.{$this->collectionSpots}", $bulk);
                }
            }
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
        }
    }

    public function getUserSpotVisits($gmail = null) {
        $userGmail = $gmail ? strtolower(trim($gmail)) : $this->getActiveGmail();

        if ($this->mongoConnected) {
            try {
                $query = new MongoDB\Driver\Query(['gmail' => $userGmail]);
                $cursor = $this->mongoManager->executeQuery("{$this->dbName}.{$this->collectionSpotVisits}", $query);
                $visits = [];
                foreach ($cursor as $doc) {
                    $item = (array)$doc;
                    if (isset($item['_id']) && is_object($item['_id'])) {
                        $item['id'] = (string)$item['_id'];
                    }
                    if (!empty($item['spot_id'])) {
                        $visits[$item['spot_id']] = $item;
                    }
                }
                return $visits;
            } catch (\Exception $e) {
                $this->lastError = $e->getMessage();
            }
        }

        $all = $this->readFallbackSpotVisits();
        return $all[$userGmail] ?? [];
    }

    public function saveSpotVisit($spotId, $data, $gmail = null) {
        $userGmail = $gmail ? strtolower(trim($gmail)) : $this->getActiveGmail();
        $now = date('c');

        $record = [
            'gmail' => $userGmail,
            'spot_id' => $spotId,
            'status' => $data['status'] ?? 'visited',
            'rating' => isset($data['rating']) ? (int)$data['rating'] : 5,
            'visit_date' => $data['visit_date'] ?? date('Y-m-d'),
            'notes' => $data['notes'] ?? '',
            'photos' => $data['photos'] ?? [],
            'updated_at' => $now
        ];

        // Save to Fallback
        $all = $this->readFallbackSpotVisits();
        if (!isset($all[$userGmail])) $all[$userGmail] = [];
        $all[$userGmail][$spotId] = array_merge($all[$userGmail][$spotId] ?? ['created_at' => $now], $record);
        $this->writeFallbackSpotVisits($all);

        // Save to MongoDB
        if ($this->mongoConnected) {
            try {
                $bulk = new MongoDB\Driver\BulkWrite();
                $filter = ['gmail' => $userGmail, 'spot_id' => $spotId];
                $update = ['$set' => $record, '$setOnInsert' => ['created_at' => $now]];
                $bulk->update($filter, $update, ['upsert' => true]);
                $this->mongoManager->executeBulkWrite("{$this->dbName}.{$this->collectionSpotVisits}", $bulk);
            } catch (\Exception $e) {
                $this->lastError = $e->getMessage();
            }
        }

        return $record;
    }

    public function toggleSpotStatus($spotId, $status, $gmail = null) {
        $userGmail = $gmail ? strtolower(trim($gmail)) : $this->getActiveGmail();
        
        if ($status === 'unvisited') {
            return $this->deleteSpotVisit($spotId, $userGmail);
        }

        $current = $this->getUserSpotVisits($userGmail);
        $data = $current[$spotId] ?? [];
        $data['status'] = $status;
        if (empty($data['visit_date'])) $data['visit_date'] = date('Y-m-d');
        if (empty($data['rating'])) $data['rating'] = 5;

        return $this->saveSpotVisit($spotId, $data, $userGmail);
    }

    public function deleteSpotVisit($spotId, $gmail = null) {
        $userGmail = $gmail ? strtolower(trim($gmail)) : $this->getActiveGmail();

        // Fallback
        $all = $this->readFallbackSpotVisits();
        if (isset($all[$userGmail][$spotId])) {
            unset($all[$userGmail][$spotId]);
            $this->writeFallbackSpotVisits($all);
        }

        // MongoDB
        if ($this->mongoConnected) {
            try {
                $bulk = new MongoDB\Driver\BulkWrite();
                $bulk->delete(['gmail' => $userGmail, 'spot_id' => $spotId]);
                $this->mongoManager->executeBulkWrite("{$this->dbName}.{$this->collectionSpotVisits}", $bulk);
            } catch (\Exception $e) {
                $this->lastError = $e->getMessage();
            }
        }

        return true;
    }

    private function readFallbackSpotVisits() {
        if (file_exists(SPOT_VISITS_FILE)) {
            $data = json_decode(file_get_contents(SPOT_VISITS_FILE), true);
            return is_array($data) ? $data : [];
        }
        return [];
    }

    private function writeFallbackSpotVisits($data) {
        file_put_contents(SPOT_VISITS_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /* ========================================================
     * DISTRICT VISITS PER GMAIL (MONGODB)
     * ======================================================== */

    public function getAllVisits($gmail = null) {
        $userGmail = $gmail ? strtolower(trim($gmail)) : $this->getActiveGmail();

        if ($this->mongoConnected) {
            try {
                $query = new MongoDB\Driver\Query(['gmail' => $userGmail], ['sort' => ['visit_date' => -1]]);
                $cursor = $this->mongoManager->executeQuery("{$this->dbName}.{$this->collectionVisits}", $query);
                $visits = [];
                foreach ($cursor as $doc) {
                    $item = (array)$doc;
                    if (isset($item['_id']) && is_object($item['_id'])) {
                        $item['id'] = (string)$item['_id'];
                    }
                    if (!empty($item['district_name'])) {
                        $visits[$item['district_name']] = $item;
                    }
                }
                return $visits;
            } catch (\Exception $e) {
                $this->lastError = $e->getMessage();
            }
        }

        // Fallback file storage per gmail
        $all = $this->readFallbackVisits();
        return $all[$userGmail] ?? [];
    }

    public function saveVisit($districtName, $data, $gmail = null) {
        $userGmail = $gmail ? strtolower(trim($gmail)) : $this->getActiveGmail();
        $now = date('c');
        $districtName = trim($districtName);
        
        $record = [
            'gmail' => $userGmail,
            'district_name' => $districtName,
            'district_bn' => $data['district_bn'] ?? '',
            'division' => $data['division'] ?? '',
            'division_bn' => $data['division_bn'] ?? '',
            'status' => $data['status'] ?? 'visited',
            'visit_date' => $data['visit_date'] ?? date('Y-m-d'),
            'rating' => isset($data['rating']) ? (int)$data['rating'] : 5,
            'notes' => $data['notes'] ?? '',
            'favorite_spots' => $data['favorite_spots'] ?? '',
            'companions' => $data['companions'] ?? 'Solo',
            'transport_mode' => $data['transport_mode'] ?? 'Bus',
            'photos' => $data['photos'] ?? [],
            'updated_at' => $now
        ];

        // Update local fallback
        $all = $this->readFallbackVisits();
        if (!isset($all[$userGmail])) $all[$userGmail] = [];
        $all[$userGmail][$districtName] = array_merge($all[$userGmail][$districtName] ?? ['created_at' => $now], $record);
        $this->writeFallbackVisits($all);

        // Save to MongoDB
        if ($this->mongoConnected) {
            try {
                $bulk = new MongoDB\Driver\BulkWrite();
                $filter = ['gmail' => $userGmail, 'district_name' => $districtName];
                $update = ['$set' => $record, '$setOnInsert' => ['created_at' => $now]];
                $bulk->update($filter, $update, ['upsert' => true]);
                $this->mongoManager->executeBulkWrite("{$this->dbName}.{$this->collectionVisits}", $bulk);
            } catch (\Exception $e) {
                $this->lastError = $e->getMessage();
            }
        }

        return $record;
    }

    public function toggleDistrictStatus($districtName, $targetStatus, $metadata = [], $gmail = null) {
        $userGmail = $gmail ? strtolower(trim($gmail)) : $this->getActiveGmail();
        $districtName = trim($districtName);
        $current = $this->getVisit($districtName, $userGmail);
        
        if ($targetStatus === 'unvisited') {
            return $this->deleteVisit($districtName, $userGmail);
        }

        $data = $current ?: [];
        $data['status'] = $targetStatus;
        if (!empty($metadata['division'])) $data['division'] = $metadata['division'];
        if (!empty($metadata['district_bn'])) $data['district_bn'] = $metadata['district_bn'];
        if (!empty($metadata['division_bn'])) $data['division_bn'] = $metadata['division_bn'];
        if (empty($data['visit_date'])) $data['visit_date'] = date('Y-m-d');
        if (empty($data['rating'])) $data['rating'] = 5;

        return $this->saveVisit($districtName, $data, $userGmail);
    }

    public function getVisit($districtName, $gmail = null) {
        $visits = $this->getAllVisits($gmail);
        return $visits[$districtName] ?? null;
    }

    public function deleteVisit($districtName, $gmail = null) {
        $userGmail = $gmail ? strtolower(trim($gmail)) : $this->getActiveGmail();
        $districtName = trim($districtName);
        
        // Remove from local fallback
        $all = $this->readFallbackVisits();
        if (isset($all[$userGmail][$districtName])) {
            unset($all[$userGmail][$districtName]);
            $this->writeFallbackVisits($all);
        }

        // Remove from Mongo
        if ($this->mongoConnected) {
            try {
                $bulk = new MongoDB\Driver\BulkWrite();
                $bulk->delete(['gmail' => $userGmail, 'district_name' => $districtName]);
                $this->mongoManager->executeBulkWrite("{$this->dbName}.{$this->collectionVisits}", $bulk);
            } catch (\Exception $e) {
                $this->lastError = $e->getMessage();
            }
        }

        return true;
    }

    /* ========================================================
     * BACKWARD COMPATIBLE & SYNC
     * ======================================================== */

    public function getSettings() {
        return $this->getUserProfile();
    }

    public function saveSettings($data) {
        return $this->saveUserProfile($data);
    }

    public function syncLocalToMongo() {
        if (!$this->mongoConnected) {
            return ['success' => false, 'message' => 'MongoDB is currently not connected.'];
        }

        $localVisits = $this->readFallbackVisits();
        $localSpots = $this->readFallbackSpotVisits();
        $localUsers = $this->readFallbackUsers();
        $count = 0;

        try {
            // 1. Sync district visits
            if (!empty($localVisits)) {
                $bulk = new MongoDB\Driver\BulkWrite();
                foreach ($localVisits as $gmail => $districts) {
                    foreach ($districts as $dName => $rec) {
                        $bulk->update(['gmail' => $gmail, 'district_name' => $dName], ['$set' => $rec], ['upsert' => true]);
                        $count++;
                    }
                }
                $this->mongoManager->executeBulkWrite("{$this->dbName}.{$this->collectionVisits}", $bulk);
            }

            // 2. Sync spot visits
            if (!empty($localSpots)) {
                $bulkSpots = new MongoDB\Driver\BulkWrite();
                foreach ($localSpots as $gmail => $spots) {
                    foreach ($spots as $spotId => $rec) {
                        $bulkSpots->update(['gmail' => $gmail, 'spot_id' => $spotId], ['$set' => $rec], ['upsert' => true]);
                        $count++;
                    }
                }
                $this->mongoManager->executeBulkWrite("{$this->dbName}.{$this->collectionSpotVisits}", $bulkSpots);
            }

            // 3. Sync users
            if (!empty($localUsers)) {
                $bulkUsers = new MongoDB\Driver\BulkWrite();
                foreach ($localUsers as $gmail => $u) {
                    $bulkUsers->update(['gmail' => $gmail], ['$set' => $u], ['upsert' => true]);
                    $count++;
                }
                $this->mongoManager->executeBulkWrite("{$this->dbName}.{$this->collectionUsers}", $bulkUsers);
            }

            $this->seedSpotsToMongo();

            return [
                'success' => true,
                'message' => "Successfully synchronized {$count} records into MongoDB database '{$this->dbName}'!"
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Sync Error: ' . $e->getMessage()];
        }
    }

    public function exportData() {
        $gmail = $this->getActiveGmail();
        return [
            'app' => 'Bangladesh District & Spots Tracker',
            'version' => '3.0 (Atlas Connected)',
            'exported_at' => date('c'),
            'user' => $this->getUserProfile($gmail),
            'district_visits' => $this->getAllVisits($gmail),
            'spot_visits' => $this->getUserSpotVisits($gmail)
        ];
    }

    public function importData($data) {
        $gmail = $this->getActiveGmail();

        if (isset($data['district_visits'])) {
            $all = $this->readFallbackVisits();
            $all[$gmail] = $data['district_visits'];
            $this->writeFallbackVisits($all);
        }

        if (isset($data['spot_visits'])) {
            $all = $this->readFallbackSpotVisits();
            $all[$gmail] = $data['spot_visits'];
            $this->writeFallbackSpotVisits($all);
        }

        if (isset($data['user'])) {
            $this->saveUserProfile($data['user'], $gmail);
        }

        if ($this->mongoConnected) {
            $this->syncLocalToMongo();
        }

        return [
            'success' => true,
            'message' => 'Imported travel & spots data for ' . $gmail . ' successfully.'
        ];
    }

    private function readFallbackVisits() {
        if (file_exists(FALLBACK_STORAGE_FILE)) {
            $data = json_decode(file_get_contents(FALLBACK_STORAGE_FILE), true);
            return is_array($data) ? $data : [];
        }
        return [];
    }

    private function writeFallbackVisits($data) {
        file_put_contents(FALLBACK_STORAGE_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
