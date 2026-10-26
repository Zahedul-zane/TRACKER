<?php
/**
 * Amar Desh Tracker - Bangladesh 64 District & Spots Travel Explorer
 * Built with PHP, HTML5, CSS3, Vanilla JS & MongoDB
 */
require_once __DIR__ . '/config.php';
$db = TrackerDB::getInstance();
$config = $db->getConfig();
$userProfile = $db->getUserProfile();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Amar Desh Tracker — 64 District & All Spots Explorer 🇧🇩</title>
  <meta name="description" content="Explore and track all 64 districts and famous tourist spots across Bangladesh with interactive Leaflet map, User Profiles, and MongoDB persistence.">
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%23059669'/><circle cx='45' cy='50' r='24' fill='%23ef4444'/></svg>">

  <!-- Google Fonts: Outfit & Hind Siliguri -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Font Awesome 6 Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- Leaflet Map CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>

  <!-- Custom Stylesheet -->
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

  <!-- ===================================================================
       HEADER & NAVIGATION
       =================================================================== -->
  <header class="header">
    <div class="container header-content">
      <!-- Brand -->
      <a href="#" class="brand">
        <div class="brand-flag-badge">
          <div class="flag-circle"></div>
        </div>
        <div class="brand-titles">
          <div class="brand-title">
            Amar Desh <span class="highlight">Tracker</span>
          </div>
          <div class="brand-subtitle">আমার সোনার বাংলা • ৬৪ জেলা ও দর্শনীয় স্থান</div>
        </div>
      </a>

      <!-- View Navigation Pills -->
      <nav class="header-nav">
        <button type="button" class="nav-pill active" data-view="map">
          <i class="fas fa-map-marked-alt"></i> Map Explorer
        </button>
        <button type="button" class="nav-pill" data-view="spots">
          <i class="fas fa-umbrella-beach"></i> All Spots (50+)
        </button>
        <button type="button" class="nav-pill" data-view="grid">
          <i class="fas fa-th-large"></i> District Directory
        </button>
        <button type="button" class="nav-pill" data-view="analytics">
          <i class="fas fa-award"></i> Badges & Stats
        </button>
        <button type="button" class="nav-pill" data-view="passport">
          <i class="fas fa-id-card"></i> Traveler Passport
        </button>
        <button type="button" class="nav-pill" data-view="profile">
          <i class="fas fa-user-circle"></i> My Profile
        </button>
        <button type="button" class="nav-pill highlight-pill" data-view="dashboard">
          <i class="fas fa-users-cog"></i> Account & Dashboard
        </button>
      </nav>

      <!-- Action Items & DB Status -->
      <div class="header-actions">
        <!-- Dynamic Auth Widget: Logged-in badge or Login/Register button -->
        <div id="headerAuthContainer" class="header-auth-container">
          <!-- Logged In State Badge -->
          <div id="headerUserBadge" class="header-user-badge" title="Active Explorer Profile. Click to open Account Dashboard.">
            <span id="headerUserAvatar" class="header-user-avatar"><?= htmlspecialchars($userProfile['avatar'] ?? '🧭') ?></span>
            <div class="header-user-text">
              <span id="headerUserName" class="header-user-name"><?= htmlspecialchars($userProfile['name'] ?? 'Zi Zahed') ?></span>
              <span id="activeGmailDisplay" class="header-user-email"><?= htmlspecialchars($userProfile['gmail'] ?? 'zizahed202@gmail.com') ?></span>
            </div>
            <i class="fas fa-chevron-down header-chevron"></i>
          </div>

          <!-- Guest State: Login / Register Button -->
          <button type="button" class="btn btn-primary btn-sm btn-header-login" id="btnHeaderLogin" style="display:none;" title="Login or create an account to save travels in MongoDB">
            <i class="fas fa-sign-in-alt"></i> Login / Register
          </button>
        </div>

        <!-- Quick Logout Button -->
        <button type="button" class="btn btn-secondary btn-sm" id="btnHeaderLogout" title="Log out from this device">
          <i class="fas fa-sign-out-alt"></i>
        </button>

        <!-- MongoDB Live Status Pill -->
        <div id="mongoStatusBadge" class="mongo-badge <?= $config['mongo_connected'] ? 'connected' : 'fallback' ?>" title="Click to view MongoDB Atlas settings">
          <span class="status-dot"></span>
          <span id="mongoStatusLabel"><?= $config['mongo_connected'] ? 'Atlas: Connected' : 'Storage: Local Fallback' ?></span>
        </div>

        <!-- Quick Demo Button -->
        <button type="button" class="btn btn-secondary btn-sm" id="btnLoadDemoTrip" title="Populate sample travel trip across Bangladesh">
          <i class="fas fa-wand-magic-sparkles"></i> Demo Trip
        </button>
      </div>
    </div>
  </header>

  <!-- ===================================================================
       HERO / TOP STATS OVERVIEW
       =================================================================== -->
  <section class="hero-stats">
    <div class="container">
      <div class="stats-grid">
        <!-- Progress Bar Card -->
        <div class="stat-card progress-main-card">
          <div class="progress-header">
            <div class="progress-title-wrap">
              <i class="fas fa-route"></i>
              <div class="progress-title">Nationwide Exploration Journey</div>
            </div>
            <div class="progress-percentage" id="statProgressPercent">0%</div>
          </div>
          <div class="progress-bar-container">
            <div class="progress-bar-fill" id="statProgressBarFill" style="width: 0%;"></div>
          </div>
          <div class="progress-footer">
            <span>Bangladesh 64 Districts Goal</span>
            <span id="statTotalVisited">0 / 64</span>
          </div>
        </div>

        <!-- Stat 1: Total Spots Visited -->
        <div class="stat-card stat-visited">
          <div class="stat-icon-wrap">
            <i class="fas fa-map-pin"></i>
          </div>
          <div class="stat-number" id="statVisitedSpots">0 / 50</div>
          <div class="stat-label">Spots Visited</div>
        </div>

        <!-- Stat 2: Wishlist -->
        <div class="stat-card stat-wishlist">
          <div class="stat-icon-wrap">
            <i class="fas fa-heart"></i>
          </div>
          <div class="stat-number" id="statWishlistCount">0</div>
          <div class="stat-label">Wishlist Targets</div>
        </div>

        <!-- Stat 3: Divisions Touched -->
        <div class="stat-card stat-divisions">
          <div class="stat-icon-wrap">
            <i class="fas fa-layer-group"></i>
          </div>
          <div class="stat-number" id="statDivisionsCount">0 / 8</div>
          <div class="stat-label">Divisions Explored</div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================================================================
       MAIN VIEWS
       =================================================================== -->
  <main class="main-view-container container">

    <!-- VIEW 1: INTERACTIVE MAP EXPLORER -->
    <section id="view-map" class="view-section active">
      <div class="map-explorer-layout">
        
        <!-- Interactive Map Container -->
        <div class="map-card">
          <div class="map-card-header">
            <div class="map-title-wrap">
              <i class="fas fa-map" style="color:var(--emerald-primary);"></i>
              <strong style="font-size:0.95rem;">Interactive Bangladesh Map</strong>
            </div>
            <div class="map-legend">
              <div class="legend-item"><span class="legend-dot visited"></span> Visited</div>
              <div class="legend-item"><span class="legend-dot wishlist"></span> Wishlist</div>
              <div class="legend-item"><span class="legend-dot unvisited"></span> Unvisited</div>
            </div>
          </div>

          <div id="bdMap"></div>

          <!-- Floating Map Tool Buttons -->
          <div class="map-floating-bar">
            <button type="button" class="map-tool-btn" id="btnResetMap" title="Reset View to full Bangladesh">
              <i class="fas fa-compress-arrows-alt"></i> Reset
            </button>
            <button type="button" class="map-tool-btn" id="btnToggleSpotsMarkers" title="Toggle Famous Spot Markers on Map">
              <i class="fas fa-map-pin"></i> <span id="spotsToggleText">Show Spots</span>
            </button>
            <button type="button" class="map-tool-btn" id="btnToggleSatellite" title="Toggle satellite imagery">
              <i class="fas fa-satellite"></i> Satellite
            </button>
          </div>
        </div>

        <!-- District Quick Finder & Action Drawer -->
        <div class="map-sidebar-card">
          <div class="sidebar-header">
            <!-- Search Input -->
            <div class="search-box">
              <i class="fas fa-search"></i>
              <input type="text" id="sidebarSearchInput" placeholder="Search by English or বাংলা name (e.g. Cox, ঢাকা)..." autocomplete="off">
            </div>

            <!-- Division Chips Filter Strip -->
            <div class="division-filter-strip">
              <button type="button" class="div-chip active" data-div="all">All Divisions</button>
              <button type="button" class="div-chip" data-div="dhaka">Dhaka</button>
              <button type="button" class="div-chip" data-div="chattogram">Chattogram</button>
              <button type="button" class="div-chip" data-div="sylhet">Sylhet</button>
              <button type="button" class="div-chip" data-div="khulna">Khulna</button>
              <button type="button" class="div-chip" data-div="rajshahi">Rajshahi</button>
              <button type="button" class="div-chip" data-div="rangpur">Rangpur</button>
              <button type="button" class="div-chip" data-div="barishal">Barishal</button>
              <button type="button" class="div-chip" data-div="mymensingh">Mymensingh</button>
            </div>
          </div>

          <!-- District List Inside Sidebar -->
          <div class="districts-scroll-list" id="districtsSidebarList">
            <!-- Rendered dynamically by JavaScript -->
          </div>
        </div>

      </div>
    </section>

    <!-- VIEW 2: ALL SPOTS IN BANGLADESH (NEW) -->
    <section id="view-spots" class="view-section">
      <!-- Spots Filter Bar -->
      <div class="grid-filter-bar">
        <div class="filter-group-left">
          <div class="search-box" style="margin-bottom:0; width:280px;">
            <i class="fas fa-search"></i>
            <input type="text" id="spotsSearchInput" placeholder="Search spots (e.g. Sajek, ইনানী)..." autocomplete="off">
          </div>

          <select id="spotsDivisionSelect" class="filter-select">
            <option value="all">All Divisions</option>
            <option value="dhaka">Dhaka (ঢাকা)</option>
            <option value="chattogram">Chattogram (চট্টগ্রাম)</option>
            <option value="sylhet">Sylhet (সিলেট)</option>
            <option value="khulna">Khulna (খুলনা)</option>
            <option value="rajshahi">Rajshahi (রাজশাহী)</option>
            <option value="rangpur">Rangpur (রংপুর)</option>
            <option value="barishal">Barishal (বরিশাল)</option>
            <option value="mymensingh">Mymensingh (ময়মনসিংহ)</option>
          </select>

          <select id="spotsStatusSelect" class="filter-select">
            <option value="all">All Statuses</option>
            <option value="visited">Visited Only</option>
            <option value="wishlist">Wishlist Only</option>
            <option value="unvisited">Unvisited Only</option>
          </select>
        </div>

        <div class="filter-group-right">
          <span style="font-size:0.85rem; color:var(--text-muted); font-weight:600;" id="spotsCountBadge">Showing 50 spots</span>
        </div>
      </div>

      <!-- Categories Filter Strip -->
      <div class="category-filter-strip" id="categoryFilterStrip">
        <button type="button" class="category-chip active" data-cat="all">🌟 All Categories</button>
        <button type="button" class="category-chip" data-cat="Beach & Island">🏖️ Beach & Island</button>
        <button type="button" class="category-chip" data-cat="Hills & Trekking">⛰️ Hills & Trekking</button>
        <button type="button" class="category-chip" data-cat="Heritage & History">🏛️ Heritage & History</button>
        <button type="button" class="category-chip" data-cat="Nature & Wildlife">🌿 Nature & Wildlife</button>
        <button type="button" class="category-chip" data-cat="Rivers & Lakes">⛵ Rivers & Lakes</button>
        <button type="button" class="category-chip" data-cat="Spiritual & Religious">🕌 Spiritual & Religious</button>
      </div>

      <!-- Spots Cards Grid -->
      <div class="spots-cards-grid" id="spotsCardsGrid">
        <!-- Rendered dynamically by JavaScript -->
      </div>
    </section>

    <!-- VIEW 3: DISTRICT CARDS DIRECTORY -->
    <section id="view-grid" class="view-section">
      <!-- Grid Filter Bar -->
      <div class="grid-filter-bar">
        <div class="filter-group-left">
          <div class="search-box" style="margin-bottom:0; width:260px;">
            <i class="fas fa-search"></i>
            <input type="text" id="gridSearchInput" placeholder="Search district or spot..." autocomplete="off">
          </div>

          <select id="gridDivisionSelect" class="filter-select">
            <option value="all">All Divisions</option>
            <option value="dhaka">Dhaka (ঢাকা)</option>
            <option value="chattogram">Chattogram (চট্টগ্রাম)</option>
            <option value="sylhet">Sylhet (সিলেট)</option>
            <option value="khulna">Khulna (খুলনা)</option>
            <option value="rajshahi">Rajshahi (রাজশাহী)</option>
            <option value="rangpur">Rangpur (রংপুর)</option>
            <option value="barishal">Barishal (বরিশাল)</option>
            <option value="mymensingh">Mymensingh (ময়মনসিংহ)</option>
          </select>

          <select id="gridStatusSelect" class="filter-select">
            <option value="all">All Statuses</option>
            <option value="visited">Visited Only</option>
            <option value="wishlist">Wishlist Only</option>
            <option value="unvisited">Unvisited Only</option>
          </select>
        </div>

        <div class="filter-group-right">
          <select id="gridSortSelect" class="filter-select">
            <option value="name_asc">Name (A-Z)</option>
            <option value="name_desc">Name (Z-A)</option>
            <option value="rating_desc">Highest Rated</option>
            <option value="date_desc">Latest Visit</option>
          </select>
        </div>
      </div>

      <!-- District Cards Grid -->
      <div class="districts-cards-grid" id="districtsCardsGrid">
        <!-- Rendered dynamically by JavaScript -->
      </div>
    </section>

    <!-- VIEW 4: ANALYTICS & BADGES -->
    <section id="view-analytics" class="view-section">
      <div class="analytics-container">
        
        <!-- Division Breakdown -->
        <div class="division-breakdown-card">
          <div class="section-heading">
            <i class="fas fa-chart-pie" style="color:var(--emerald-primary);"></i>
            Division Progress Breakdown
          </div>
          <div class="section-desc">Track how many districts you've completed across all 8 administrative divisions.</div>
          <div class="division-bars-grid" id="divisionBarsGrid">
            <!-- Rendered by JS -->
          </div>
        </div>

        <!-- Badges Showcase -->
        <div class="division-breakdown-card">
          <div class="section-heading">
            <i class="fas fa-trophy" style="color:var(--amber-gold);"></i>
            Travel Achievements & Badges
          </div>
          <div class="section-desc">Unlock prestigious explorer titles as your journey expands across Bangladesh.</div>
          <div class="badges-grid" id="badgesGrid">
            <!-- Rendered by JS -->
          </div>
        </div>

      </div>
    </section>

    <!-- VIEW 5: TRAVELER PASSPORT & CERTIFICATE -->
    <section id="view-passport" class="view-section">
      <div class="passport-container">
        
        <!-- Digital Passport Certificate Card -->
        <div class="passport-card" id="passportCardElement">
          <div class="passport-header">
            <div class="passport-emblem">
              <span class="emblem-icon">🇧🇩</span>
              <div>
                <div class="passport-title-main">Bangladesh Traveler Passport</div>
                <div class="passport-sub-bn">গণপ্রজাতন্ত্রী বাংলাদেশ • ভ্রমণ সনদ</div>
              </div>
            </div>
            <div class="passport-stamp">
              <span>EXPLORER</span>
              <strong id="passportDateStamp">2026</strong>
              <span>VERIFIED</span>
            </div>
          </div>

          <div class="passport-user-row">
            <div class="user-avatar-circle" id="passportAvatar">
              🧭
            </div>
            <div class="user-details">
              <h3 id="passportUserName"><?= htmlspecialchars($userProfile['name'] ?? 'Tanvir Ahmed') ?></h3>
              <p id="passportHomeDist">Home: <?= htmlspecialchars($userProfile['home_district'] ?? 'Dhaka') ?>, Bangladesh</p>
            </div>
          </div>

          <div class="passport-score-grid">
            <div>
              <div class="score-box-num" id="passportVisitedVal">0/64</div>
              <div class="score-box-lbl">Districts Visited</div>
            </div>
            <div>
              <div class="score-box-num" id="passportSpotsVal">0/50</div>
              <div class="score-box-lbl">Spots Visited</div>
            </div>
            <div>
              <div class="score-box-num" id="passportPercentVal">0%</div>
              <div class="score-box-lbl">Completion</div>
            </div>
          </div>

          <div class="passport-footer">
            <span>Official 64 District & Spots Tracker</span>
            <span>MongoDB Data Store</span>
          </div>
        </div>

        <!-- Passport Actions -->
        <div class="passport-action-bar">
          <button type="button" class="btn btn-primary" id="btnDownloadPassport">
            <i class="fas fa-download"></i> Download Passport Card (PNG)
          </button>
          <button type="button" class="btn btn-secondary" id="btnCopyShareText">
            <i class="fas fa-share-alt"></i> Copy Share Text
          </button>
        </div>

      </div>
    </section>

    <!-- VIEW 6: USER PROFILE & MONGODB DATA STORE (NEW) -->
    <section id="view-profile" class="view-section">
      <div class="profile-layout-grid">
        
        <!-- Left: Profile Info Card -->
        <div class="profile-card">
          <div class="profile-hero">
            <div class="profile-avatar-large" id="profileAvatarDisplay">
              <?= htmlspecialchars($userProfile['avatar'] ?? '🧭') ?>
            </div>
            <div class="profile-name" id="profileNameDisplay">
              <?= htmlspecialchars($userProfile['name'] ?? 'Tanvir Ahmed') ?>
            </div>
            <div class="profile-username" id="profileUsernameDisplay">
              @<?= htmlspecialchars($userProfile['username'] ?? 'voyager_bd') ?>
            </div>
            <div class="profile-bio" id="profileBioDisplay">
              "<?= htmlspecialchars($userProfile['bio'] ?? 'Passionate explorer chasing sunsets, hill peaks, and coastal horizons across all 64 districts of Bangladesh.') ?>"
            </div>
          </div>

          <div class="profile-meta-rows">
            <div class="profile-meta-item">
              <span><i class="fas fa-map-marker-alt"></i> Home District</span>
              <span id="profileHomeDistDisplay"><?= htmlspecialchars($userProfile['home_district'] ?? 'Dhaka') ?></span>
            </div>
            <div class="profile-meta-item">
              <span><i class="fas fa-hiking"></i> Travel Style</span>
              <span id="profileStyleDisplay"><?= htmlspecialchars($userProfile['travel_style'] ?? 'Adventure & Photography') ?></span>
            </div>
            <div class="profile-meta-item">
              <span><i class="fas fa-quote-left"></i> Travel Motto</span>
              <span id="profileMottoDisplay"><?= htmlspecialchars($userProfile['travel_motto'] ?? 'জীবন একটাই, বাংলাদেশটা ঘুরে দেখো!') ?></span>
            </div>
            <div class="profile-meta-item">
              <span><i class="far fa-envelope"></i> Email</span>
              <span id="profileEmailDisplay"><?= htmlspecialchars($userProfile['email'] ?? 'tanvir@voyager.bd') ?></span>
            </div>
            <div class="profile-meta-item">
              <span><i class="far fa-calendar-alt"></i> Explorer Since</span>
              <span id="profileJoinedDisplay"><?= htmlspecialchars($userProfile['joined_date'] ?? 'Jan 2024') ?></span>
            </div>
          </div>

          <button type="button" class="btn btn-primary" id="btnEditProfileOpen" style="width:100%;">
            <i class="fas fa-user-edit"></i> Edit Profile Information
          </button>
        </div>

        <!-- Right: MongoDB Data Store & Travel Statistics -->
        <div class="profile-stats-container">
          
          <!-- MongoDB Status & Sync Card -->
          <div class="stats-summary-card">
            <div class="section-heading">
              <i class="fas fa-database" style="color:var(--emerald-primary);"></i>
              MongoDB Data Store
            </div>
            <div class="section-desc">User profile and all travel records are stored in MongoDB collections.</div>

            <div style="background:rgba(255,255,255,0.03); border:1px solid var(--border-color); border-radius:var(--radius-md); padding:16px; margin-bottom:16px;">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <span style="font-size:0.85rem; color:var(--text-muted);"><i class="fas fa-server"></i> Database:</span>
                <strong style="color:#34d399; font-size:0.9rem;"><?= htmlspecialchars($config['db_name']) ?></strong>
              </div>
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <span style="font-size:0.85rem; color:var(--text-muted);"><i class="fas fa-link"></i> Active URI:</span>
                <span style="font-size:0.82rem; font-family:monospace; color:var(--text-dim);"><?= htmlspecialchars($config['mongo_uri']) ?></span>
              </div>
              <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size:0.85rem; color:var(--text-muted);"><i class="fas fa-folder-open"></i> Collections:</span>
                <span style="font-size:0.82rem; color:#818cf8;">users, spots, user_spot_visits, district_visits</span>
              </div>
            </div>

            <div style="display:flex; gap:12px; flex-wrap:wrap;">
              <button type="button" class="btn btn-secondary btn-sm" id="btnOpenMongoSettingsFromProfile">
                <i class="fas fa-cog"></i> Database Settings
              </button>
              <button type="button" class="btn btn-secondary btn-sm" id="btnSyncMongoFromProfile">
                <i class="fas fa-sync-alt"></i> Force Sync to MongoDB
              </button>
              <button type="button" class="btn btn-secondary btn-sm" id="btnExportData">
                <i class="fas fa-file-export"></i> Backup (JSON)
              </button>
            </div>
          </div>

          <!-- Quick Numbers Summary -->
          <div class="stats-summary-card">
            <div class="section-heading">
              <i class="fas fa-tachometer-alt" style="color:#38bdf8;"></i>
              Travel Summary Statistics
            </div>
            <div class="section-desc">Your verified exploration milestones across the nation.</div>

            <div class="profile-numbers-row">
              <div class="profile-number-box">
                <div class="profile-number-val" id="profileNumDistricts">0</div>
                <div class="profile-number-label">Districts Visited</div>
              </div>
              <div class="profile-number-box">
                <div class="profile-number-val" id="profileNumSpots">0</div>
                <div class="profile-number-label">Spots Visited</div>
              </div>
              <div class="profile-number-box">
                <div class="profile-number-val" id="profileNumBadges">0</div>
                <div class="profile-number-label">Badges Unlocked</div>
              </div>
              <div class="profile-number-box">
                <div class="profile-number-val" id="profileNumWishlist">0</div>
                <div class="profile-number-label">Wishlist Targets</div>
              </div>
            </div>
          </div>

        </div>

      </div>
    </section>

    <!-- VIEW 7: TRAVELER ACCOUNT & COMMUNITY DASHBOARD (NEW) -->
    <section id="view-dashboard" class="view-section">
      <div class="dashboard-hero-banner">
        <div class="dashboard-hero-content">
          <div class="dashboard-hero-titles">
            <div class="dashboard-badge-pill">
              <span class="status-dot"></span>
              <span>MongoDB Atlas Cloud Connected</span>
            </div>
            <h2 class="dashboard-title">Traveler Account & Community Dashboard</h2>
            <p class="dashboard-subtitle">আমার সোনার বাংলা ভ্রমণকারী একাউন্ট, সিকিউরিটি এবং কমিউনিটি লিডারবোর্ড</p>
          </div>
          <div class="dashboard-hero-actions">
            <button type="button" class="btn btn-secondary btn-sm" id="btnDashboardOpenLogin">
              <i class="fas fa-sign-in-alt"></i> Log In
            </button>
            <button type="button" class="btn btn-primary btn-sm" id="btnDashboardOpenRegister">
              <i class="fas fa-user-plus"></i> Register Free
            </button>
            <button type="button" class="btn btn-secondary btn-sm" id="btnDashboardLogout" style="display:none;">
              <i class="fas fa-sign-out-alt"></i> Log Out
            </button>
          </div>
        </div>
      </div>

      <!-- Guest Mode Banner (visible when not logged in) -->
      <div id="dashboardGuestBanner" class="dashboard-guest-card" style="display:none;">
        <div class="guest-card-icon">🧭</div>
        <div class="guest-card-text">
          <h3>You are currently browsing as a Guest</h3>
          <p>Create a free traveler account or log in with your Gmail to sync all your 64-district travels, spots, and certificates directly into MongoDB Atlas cloud storage.</p>
        </div>
        <div class="guest-card-actions">
          <button type="button" class="btn btn-primary" onclick="openAuthModal('login')">
            <i class="fas fa-sign-in-alt"></i> Log In Now
          </button>
          <button type="button" class="btn btn-secondary" onclick="openAuthModal('register')">
            <i class="fas fa-user-plus"></i> Create Account
          </button>
        </div>
      </div>

      <!-- Logged-in Dashboard Content -->
      <div id="dashboardAuthContainer" class="dashboard-content-grid">
        
        <!-- Card 1: Active Account & Profile Overview -->
        <div class="dashboard-card">
          <div class="dashboard-card-header">
            <div class="dashboard-card-title">
              <i class="fas fa-id-badge" style="color:var(--emerald-primary);"></i>
              Active Traveler Account
            </div>
            <span class="active-tag"><i class="fas fa-check-circle"></i> Authenticated</span>
          </div>
          
          <div class="dashboard-profile-box">
            <div class="dashboard-avatar-wrap" id="dashAvatarDisplay">🧭</div>
            <div class="dashboard-profile-info">
              <div class="dashboard-profile-name" id="dashNameDisplay">Zi Zahed</div>
              <div class="dashboard-profile-email" id="dashEmailDisplay">zizahed202@gmail.com</div>
              <div class="dashboard-profile-chips">
                <span class="dash-chip" id="dashHomeDistDisplay"><i class="fas fa-map-marker-alt"></i> Dhaka</span>
                <span class="dash-chip" id="dashStyleDisplay"><i class="fas fa-hiking"></i> Adventure</span>
              </div>
            </div>
          </div>

          <div class="dashboard-stats-mini-grid">
            <div class="mini-stat-item">
              <div class="mini-stat-val" id="dashVisitedDistricts">0 / 64</div>
              <div class="mini-stat-lbl">Districts Visited</div>
            </div>
            <div class="mini-stat-item">
              <div class="mini-stat-val" id="dashVisitedSpots">0 / 50</div>
              <div class="mini-stat-lbl">Spots Visited</div>
            </div>
            <div class="mini-stat-item">
              <div class="mini-stat-val" id="dashCompletionRate">0%</div>
              <div class="mini-stat-lbl">Completion</div>
            </div>
          </div>

          <div class="dashboard-action-links">
            <button type="button" class="btn btn-secondary btn-sm" id="dashBtnEditProfile">
              <i class="fas fa-user-edit"></i> Edit Bio & Style
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="switchView('passport')">
              <i class="fas fa-id-card"></i> View Passport
            </button>
          </div>
        </div>

        <!-- Card 2: Security & Password Management -->
        <div class="dashboard-card">
          <div class="dashboard-card-header">
            <div class="dashboard-card-title">
              <i class="fas fa-shield-alt" style="color:#38bdf8;"></i>
              Security & Password
            </div>
            <span class="meta-chip"><i class="fas fa-lock"></i> BCRYPT</span>
          </div>

          <form id="dashPasswordForm" class="dashboard-password-form">
            <div class="form-group">
              <label class="form-label" for="dashOldPass">Current Password</label>
              <input type="password" id="dashOldPass" class="form-control" placeholder="Enter current password">
            </div>
            <div class="form-row-2">
              <div class="form-group">
                <label class="form-label" for="dashNewPass">New Password</label>
                <input type="password" id="dashNewPass" class="form-control" placeholder="Min. 6 chars">
              </div>
              <div class="form-group">
                <label class="form-label" for="dashConfirmNewPass">Confirm New Password</label>
                <input type="password" id="dashConfirmNewPass" class="form-control" placeholder="Re-enter password">
              </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm" id="btnUpdatePasswordSubmit" style="width:100%; margin-top:8px;">
              <i class="fas fa-key"></i> Update Password
            </button>
          </form>

          <div class="security-info-box">
            <i class="fas fa-info-circle"></i>
            <span>Passwords are hashed with BCRYPT before storing in MongoDB Atlas. Never share your password.</span>
          </div>
        </div>

        <!-- Card 3: Cloud Database & Registered Accounts Switcher -->
        <div class="dashboard-card">
          <div class="dashboard-card-header">
            <div class="dashboard-card-title">
              <i class="fas fa-database" style="color:#a855f7;"></i>
              MongoDB Accounts & Sync
            </div>
            <button type="button" class="btn btn-secondary btn-sm" id="dashBtnNewAccount" onclick="openAuthModal('register')">
              <i class="fas fa-plus"></i> New Account
            </button>
          </div>

          <p style="font-size:0.83rem; color:var(--text-muted); line-height:1.5;">
            Switch instantly between registered traveler profiles stored in MongoDB database:
            <strong style="color:#34d399; font-family:monospace;">bangladesh_tracker</strong>.
          </p>

          <div id="dashAccountsList" class="dash-accounts-scrollbox">
            <!-- Populated by JS -->
            <div style="color:var(--text-dim); font-size:0.82rem; text-align:center; padding:16px;">
              <i class="fas fa-spinner fa-spin"></i> Loading registered accounts...
            </div>
          </div>
        </div>

      </div>

      <!-- Registered Travelers Directory & Leaderboard Section -->
      <div class="leaderboard-section">
        <div class="leaderboard-header">
          <div>
            <h3 class="leaderboard-title">
              <i class="fas fa-trophy" style="color:#f59e0b;"></i>
              Registered Travelers Leaderboard
            </h3>
            <p class="leaderboard-subtitle">Community of explorers traversing the 64 districts & famous tourist spots of Bangladesh.</p>
          </div>
          <div class="leaderboard-search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="leaderboardSearchInput" placeholder="Search traveler by name, district, or email...">
          </div>
        </div>

        <div id="leaderboardGrid" class="leaderboard-grid">
          <!-- Populated by JS -->
          <div style="text-align:center; padding:30px; color:var(--text-muted); grid-column:1/-1;">
            <i class="fas fa-spinner fa-spin fa-2x" style="color:var(--emerald-primary); margin-bottom:12px;"></i>
            <div>Loading travelers from MongoDB Atlas...</div>
          </div>
        </div>
      </div>
    </section>

  </main>

  <!-- ===================================================================
       MODAL 1: DISTRICT VISIT DETAILS MODAL
       =================================================================== -->
  <div class="modal-backdrop" id="districtModal">
    <div class="modal-box">
      <div class="modal-header">
        <div class="modal-title">
          <i class="fas fa-map-pin" style="color:var(--emerald-primary);"></i>
          <span id="modalDistrictName">District</span>
          <span id="modalDistrictBn" style="font-family:var(--font-bangla); font-weight:400; color:var(--text-muted); font-size:1rem;"></span>
        </div>
        <button type="button" class="modal-close-btn">&times;</button>
      </div>

      <div class="modal-body">
        <div id="modalDistrictDiv" style="font-size:0.85rem; color:var(--text-dim); margin-top:-8px; margin-bottom:8px;"></div>

        <!-- Status Toggle -->
        <div class="form-group">
          <label class="form-label">Travel Status</label>
          <div class="segmented-control">
            <button type="button" class="segment-btn active visited" data-status="visited">
              <i class="fas fa-check-circle"></i> Visited
            </button>
            <button type="button" class="segment-btn wishlist" data-status="wishlist">
              <i class="fas fa-bookmark"></i> Wishlist
            </button>
            <button type="button" class="segment-btn unvisited" data-status="unvisited">
              <i class="far fa-circle"></i> Unvisited
            </button>
          </div>
        </div>

        <!-- Date & Rating -->
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label" for="visitDateInput">Visit Date</label>
            <input type="date" id="visitDateInput" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Experience Rating</label>
            <div class="star-rating-box">
              <i class="fas fa-star active" data-val="1"></i>
              <i class="fas fa-star active" data-val="2"></i>
              <i class="fas fa-star active" data-val="3"></i>
              <i class="fas fa-star active" data-val="4"></i>
              <i class="fas fa-star active" data-val="5"></i>
            </div>
            <input type="hidden" id="ratingValueInput" value="5">
          </div>
        </div>

        <!-- Companions & Transport -->
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label" for="companionSelect">Traveled With</label>
            <select id="companionSelect" class="form-control">
              <option value="Solo">Solo Traveler</option>
              <option value="Family">Family</option>
              <option value="Friends">Friends</option>
              <option value="Partner">Partner / Spouse</option>
              <option value="Group Tour">Group Tour / Club</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="transportSelect">Transport Mode</label>
            <select id="transportSelect" class="form-control">
              <option value="Bus">Bus</option>
              <option value="Train">Train</option>
              <option value="Air">Flight (Air)</option>
              <option value="Car / Bike">Personal Car / Bike</option>
              <option value="Launch / Boat">Launch / Boat</option>
            </select>
          </div>
        </div>

        <!-- Highlights & Notes -->
        <div class="form-group">
          <label class="form-label" for="favoriteSpotsInput">Highlights & Favorite Places</label>
          <input type="text" id="favoriteSpotsInput" class="form-control" placeholder="e.g. Sajek Valley, Inani Beach, Lalbagh Fort...">
        </div>

        <div class="form-group">
          <label class="form-label" for="travelNotesInput">Travel Memories & Notes</label>
          <textarea id="travelNotesInput" class="form-control" rows="3" placeholder="What did you experience? Best food you had, cultural memories, local attractions..."></textarea>
        </div>

        <!-- Photo Upload Box -->
        <div class="form-group">
          <label class="form-label">Trip Photo Memories</label>
          <div class="photo-upload-area" id="photoUploadArea">
            <i class="fas fa-cloud-upload-alt" style="font-size:1.8rem; color:var(--emerald-primary); margin-bottom:6px;"></i>
            <div style="font-size:0.85rem; font-weight:600;">Click to upload a trip photo</div>
            <div style="font-size:0.75rem; color:var(--text-dim);">JPG, PNG, WEBP up to 6MB</div>
          </div>
          <input type="file" id="photoFileInput" accept="image/*" style="display:none;">
          <div class="photo-preview-grid" id="photoPreviewGrid"></div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" id="btnDeleteVisit" style="color:#ef4444;">
          <i class="fas fa-trash-alt"></i> Reset
        </button>
        <div style="display:flex; gap:10px;">
          <button type="button" class="btn btn-secondary modal-cancel-btn">Cancel</button>
          <button type="button" class="btn btn-primary" id="btnSaveVisit">
            <i class="fas fa-save"></i> Save Visit
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ===================================================================
       MODAL 2: SPOT DETAILS & VISIT MODAL (NEW)
       =================================================================== -->
  <div class="modal-backdrop" id="spotModal">
    <div class="modal-box">
      <div class="modal-header">
        <div class="modal-title">
          <span id="modalSpotIcon" style="font-size:1.4rem;">📍</span>
          <div>
            <div id="modalSpotName" style="font-size:1.15rem; font-weight:700;">Spot Name</div>
            <div id="modalSpotBn" style="font-size:0.85rem; color:var(--text-muted); font-family:var(--font-bangla);"></div>
          </div>
        </div>
        <button type="button" class="modal-close-btn">&times;</button>
      </div>

      <div class="modal-body">
        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px;">
          <span class="spot-category-badge" id="modalSpotCategory">Category</span>
          <span class="meta-chip" id="modalSpotLocation"><i class="fas fa-map-marker-alt"></i> Location</span>
          <span class="spot-best-time" id="modalSpotBestTime"><i class="far fa-calendar-alt"></i> Best Time</span>
        </div>

        <p id="modalSpotDesc" style="font-size:0.88rem; color:var(--text-muted); line-height:1.5; margin-bottom:16px; background:rgba(255,255,255,0.02); padding:10px 14px; border-radius:var(--radius-md); border:1px solid var(--border-color);"></p>

        <!-- Status Toggle -->
        <div class="form-group">
          <label class="form-label">Spot Status</label>
          <div class="segmented-control" id="spotSegmentedControl">
            <button type="button" class="segment-btn active visited" data-status="visited">
              <i class="fas fa-check-circle"></i> Visited
            </button>
            <button type="button" class="segment-btn wishlist" data-status="wishlist">
              <i class="fas fa-bookmark"></i> Wishlist
            </button>
            <button type="button" class="segment-btn unvisited" data-status="unvisited">
              <i class="far fa-circle"></i> Unvisited
            </button>
          </div>
        </div>

        <!-- Date & Rating -->
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label" for="spotVisitDateInput">Visit Date</label>
            <input type="date" id="spotVisitDateInput" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Spot Experience Rating</label>
            <div class="star-rating-box" id="spotStarRatingBox">
              <i class="fas fa-star active" data-val="1"></i>
              <i class="fas fa-star active" data-val="2"></i>
              <i class="fas fa-star active" data-val="3"></i>
              <i class="fas fa-star active" data-val="4"></i>
              <i class="fas fa-star active" data-val="5"></i>
            </div>
            <input type="hidden" id="spotRatingValueInput" value="5">
          </div>
        </div>

        <!-- Notes -->
        <div class="form-group">
          <label class="form-label" for="spotNotesInput">Notes & Memories</label>
          <textarea id="spotNotesInput" class="form-control" rows="3" placeholder="How was your visit to this spot? Best spots, photos taken, travel tip..."></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" id="btnDeleteSpotVisit" style="color:#ef4444;">
          <i class="fas fa-trash-alt"></i> Reset
        </button>
        <div style="display:flex; gap:10px;">
          <button type="button" class="btn btn-secondary modal-cancel-btn">Cancel</button>
          <button type="button" class="btn btn-primary" id="btnSaveSpotVisit">
            <i class="fas fa-save"></i> Save Spot
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ===================================================================
       MODAL 3: EDIT USER PROFILE (NEW)
       =================================================================== -->
  <div class="modal-backdrop" id="editProfileModal">
    <div class="modal-box">
      <div class="modal-header">
        <div class="modal-title">
          <i class="fas fa-user-edit" style="color:var(--emerald-primary);"></i>
          Edit Profile Information (Stored in MongoDB)
        </div>
        <button type="button" class="modal-close-btn">&times;</button>
      </div>

      <div class="modal-body">
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label" for="editProfName">Full Name</label>
            <input type="text" id="editProfName" class="form-control" value="<?= htmlspecialchars($userProfile['name'] ?? 'Tanvir Ahmed') ?>">
          </div>
          <div class="form-group">
            <label class="form-label" for="editProfUsername">Username (Unique ID)</label>
            <input type="text" id="editProfUsername" class="form-control" value="<?= htmlspecialchars($userProfile['username'] ?? 'voyager_bd') ?>">
          </div>
        </div>

        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label" for="editProfEmail">Email Address</label>
            <input type="email" id="editProfEmail" class="form-control" value="<?= htmlspecialchars($userProfile['email'] ?? 'tanvir@voyager.bd') ?>">
          </div>
          <div class="form-group">
            <label class="form-label" for="editProfPhone">Phone / Mobile</label>
            <input type="text" id="editProfPhone" class="form-control" value="<?= htmlspecialchars($userProfile['phone'] ?? '+880 1712-345678') ?>">
          </div>
        </div>

        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label" for="editProfHomeDist">Home District (Origin)</label>
            <select id="editProfHomeDist" class="form-control">
              <option value="Dhaka">Dhaka (ঢাকা)</option>
              <option value="Chattogram">Chattogram (চট্টগ্রাম)</option>
              <option value="Sylhet">Sylhet (সিলেট)</option>
              <option value="Khulna">Khulna (খুলনা)</option>
              <option value="Rajshahi">Rajshahi (রাজশাহী)</option>
              <option value="Barishal">Barishal (বরিশাল)</option>
              <option value="Rangpur">Rangpur (রংপুর)</option>
              <option value="Mymensingh">Mymensingh (ময়মনসিংহ)</option>
              <option value="Cox's Bazar">Cox's Bazar (কক্সবাজার)</option>
              <option value="Cumilla">Cumilla (কুমিল্লা)</option>
              <option value="Bogura">Bogura (বগুড়া)</option>
              <option value="Gazipur">Gazipur (গাজীপুর)</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="editProfAvatar">Avatar Icon</label>
            <select id="editProfAvatar" class="form-control">
              <option value="🧭">🧭 Compass</option>
              <option value="🧗">🧗 Climber</option>
              <option value="🎒">🎒 Backpacker</option>
              <option value="⛺">⛺ Camper</option>
              <option value="⛵">⛵ Sailor</option>
              <option value="📸">📸 Photographer</option>
              <option value="🐅">🐅 Bengal Tiger</option>
              <option value="🦅">🦅 Falcon</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="editProfStyle">Travel Style</label>
          <input type="text" id="editProfStyle" class="form-control" value="<?= htmlspecialchars($userProfile['travel_style'] ?? 'Adventure & Photography') ?>">
        </div>

        <div class="form-group">
          <label class="form-label" for="editProfMotto">Travel Motto (বাংলা/English)</label>
          <input type="text" id="editProfMotto" class="form-control" value="<?= htmlspecialchars($userProfile['travel_motto'] ?? 'জীবন একটাই, বাংলাদেশটা ঘুরে দেখো!') ?>">
        </div>

        <div class="form-group">
          <label class="form-label" for="editProfBio">Short Bio / Story</label>
          <textarea id="editProfBio" class="form-control" rows="2"><?= htmlspecialchars($userProfile['bio'] ?? 'Passionate explorer chasing sunsets, hill peaks, and coastal horizons across all 64 districts of Bangladesh.') ?></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary modal-cancel-btn">Cancel</button>
        <button type="button" class="btn btn-primary" id="btnSaveFullProfile">
          <i class="fas fa-save"></i> Save Profile to MongoDB
        </button>
      </div>
    </div>
  </div>

  <!-- ===================================================================
       MODAL 4: MONGODB CONFIGURATION & SYNC
       =================================================================== -->
  <div class="modal-backdrop" id="mongoModal">
    <div class="modal-box">
      <div class="modal-header">
        <div class="modal-title">
          <i class="fas fa-database" style="color:var(--emerald-primary);"></i>
          MongoDB Connection & Settings
        </div>
        <button type="button" class="modal-close-btn">&times;</button>
      </div>

      <div class="modal-body">
        <p style="font-size:0.85rem; color:var(--text-muted); line-height:1.5;">
          The tracker natively uses PHP's official MongoDB extension (<code>MongoDB\Driver\Manager</code>). You can connect to a local MongoDB daemon or MongoDB Atlas cloud URI.
        </p>

        <div id="mongoTestResultBox" style="padding:10px 14px; background:rgba(255,255,255,0.03); border:1px solid var(--border-color); border-radius:var(--radius-md); font-size:0.85rem;">
          <!-- Result injected here -->
        </div>

        <div class="form-group">
          <label class="form-label" for="mongoUriInput">MongoDB Connection URI</label>
          <input type="text" id="mongoUriInput" class="form-control" value="<?= htmlspecialchars($config['mongo_uri']) ?>" placeholder="mongodb://127.0.0.1:27017 or mongodb+srv://...">
        </div>

        <div class="form-group">
          <label class="form-label" for="mongoDbNameInput">Database Name</label>
          <input type="text" id="mongoDbNameInput" class="form-control" value="<?= htmlspecialchars($config['db_name']) ?>" placeholder="bangladesh_tracker">
        </div>

        <div style="display:flex; gap:10px; margin-top:6px;">
          <button type="button" class="btn btn-secondary btn-sm" id="btnTestMongo">
            <i class="fas fa-plug"></i> Test Connection Live
          </button>
          <button type="button" class="btn btn-secondary btn-sm" id="btnSyncMongo" title="Push offline records to MongoDB">
            <i class="fas fa-sync-alt"></i> Sync Local to Mongo
          </button>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary modal-cancel-btn">Close</button>
        <button type="button" class="btn btn-primary" id="btnSaveMongoConfig">
          <i class="fas fa-check"></i> Save & Connect
        </button>
      </div>
    </div>
  </div>

  <!-- ===================================================================
       MODAL 5: LOGIN & REGISTRATION DASHBOARD MODAL (NEW)
       =================================================================== -->
  <div class="modal-backdrop" id="authModal">
    <div class="modal-box" style="max-width:580px;">
      <!-- Tab Header -->
      <div class="modal-header auth-modal-header">
        <div class="auth-tabs-nav">
          <button type="button" class="auth-tab-btn active" data-tab="login" id="tabBtnLogin">
            <i class="fas fa-sign-in-alt"></i> Log In
          </button>
          <button type="button" class="auth-tab-btn" data-tab="register" id="tabBtnRegister">
            <i class="fas fa-user-plus"></i> Create Account
          </button>
        </div>
        <button type="button" class="modal-close-btn">&times;</button>
      </div>

      <div class="modal-body auth-modal-body">
        
        <!-- ================= TAB 1: LOG IN ================= -->
        <div class="auth-tab-panel active" id="authTabLogin">
          <div class="auth-banner-cloud">
            <span class="status-dot"></span>
            <span>Connected to <strong>MongoDB Atlas</strong> database: <code>bangladesh_tracker</code></span>
          </div>

          <form id="authLoginForm" class="auth-form" onsubmit="return false;">
            <div class="form-group">
              <label class="form-label" for="loginGmailInput">Gmail / Email Address</label>
              <div class="input-with-icon">
                <i class="fab fa-google icon-field"></i>
                <input type="email" id="loginGmailInput" class="form-control" placeholder="your.name@gmail.com" required value="<?= htmlspecialchars($userProfile['gmail'] ?? 'zizahed202@gmail.com') ?>">
              </div>
            </div>

            <div class="form-group">
              <div style="display:flex; justify-content:space-between; align-items:center;">
                <label class="form-label" for="loginPasswordInput">Password</label>
                <span class="password-toggle-btn" onclick="togglePasswordVisibility('loginPasswordInput', this)">
                  <i class="far fa-eye"></i> Show
                </span>
              </div>
              <div class="input-with-icon">
                <i class="fas fa-lock icon-field"></i>
                <input type="password" id="loginPasswordInput" class="form-control" placeholder="Enter your password" required value="tracker123">
              </div>
            </div>

            <!-- Instant Demo Login Box -->
            <div class="demo-login-card" id="demoLoginCard">
              <div class="demo-card-info">
                <div class="demo-card-title"><i class="fas fa-bolt" style="color:#fbbf24;"></i> Quick 1-Click Demo Login</div>
                <div class="demo-card-sub">Pre-fills developer account: <code>zizahed202@gmail.com</code> / <code>tracker123</code></div>
              </div>
              <button type="button" class="btn btn-secondary btn-sm" id="btnFillDemoLogin">
                Use Demo
              </button>
            </div>

            <button type="submit" class="btn btn-primary" id="btnLoginSubmit" style="width:100%; margin-top:8px;">
              <i class="fas fa-sign-in-alt"></i> Log In to Traveler Account
            </button>
          </form>

          <div class="auth-footer-switch">
            <span>Don't have an account yet?</span>
            <button type="button" class="btn-link" onclick="switchAuthTab('register')">Register a Free Account</button>
          </div>
        </div>

        <!-- ================= TAB 2: REGISTER ================= -->
        <div class="auth-tab-panel" id="authTabRegister">
          <div class="auth-banner-cloud">
            <span class="status-dot"></span>
            <span>Create your traveler passport & sync 64 districts in <strong>MongoDB Atlas</strong></span>
          </div>

          <form id="authRegisterForm" class="auth-form" onsubmit="return false;">
            
            <div class="form-row-2">
              <div class="form-group">
                <label class="form-label" for="regGmailInput">Gmail Address <span style="color:#ef4444;">*</span></label>
                <div class="input-with-icon">
                  <i class="fab fa-google icon-field"></i>
                  <input type="email" id="regGmailInput" class="form-control" placeholder="name@gmail.com" required>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label" for="regNameInput">Full Name (English) <span style="color:#ef4444;">*</span></label>
                <div class="input-with-icon">
                  <i class="fas fa-user icon-field"></i>
                  <input type="text" id="regNameInput" class="form-control" placeholder="e.g. Tanvir Ahmed" required>
                </div>
              </div>
            </div>

            <div class="form-row-2">
              <div class="form-group">
                <label class="form-label" for="regNameBnInput">Name in Bengali (বাংলা নাম)</label>
                <input type="text" id="regNameBnInput" class="form-control" placeholder="যেমন: তানভীর আহমেদ" style="font-family:var(--font-bangla);">
              </div>

              <div class="form-group">
                <label class="form-label" for="regPhoneInput">Phone / Mobile</label>
                <input type="text" id="regPhoneInput" class="form-control" placeholder="+880 1712-345678">
              </div>
            </div>

            <div class="form-row-2">
              <div class="form-group">
                <label class="form-label" for="regHomeDistrictInput">Home District (Origin) <span style="color:#ef4444;">*</span></label>
                <select id="regHomeDistrictInput" class="form-control" required>
                  <optgroup label="Dhaka Division (১৩ জেলা)">
                    <option value="Dhaka" selected>Dhaka (ঢাকা)</option>
                    <option value="Gazipur">Gazipur (গাজীপুর)</option>
                    <option value="Kishoreganj">Kishoreganj (কিশোরগঞ্জ)</option>
                    <option value="Manikganj">Manikganj (মানিকগঞ্জ)</option>
                    <option value="Munshiganj">Munshiganj (মুন্সীগঞ্জ)</option>
                    <option value="Narayanganj">Narayanganj (নারায়ণগঞ্জ)</option>
                    <option value="Narsingdi">Narsingdi (নরসিংদী)</option>
                    <option value="Tangail">Tangail (টাঙ্গাইল)</option>
                    <option value="Faridpur">Faridpur (ফরিদপুর)</option>
                    <option value="Gopalganj">Gopalganj (গোপালগঞ্জ)</option>
                    <option value="Madaripur">Madaripur (মাদারীপুর)</option>
                    <option value="Rajbari">Rajbari (রাজবাড়ী)</option>
                    <option value="Shariatpur">Shariatpur (শরীয়তপুর)</option>
                  </optgroup>
                  <optgroup label="Chattogram Division (১১ জেলা)">
                    <option value="Chattogram">Chattogram (চট্টগ্রাম)</option>
                    <option value="Cox's Bazar">Cox's Bazar (কক্সবাজার)</option>
                    <option value="Bandarban">Bandarban (বান্দরবান)</option>
                    <option value="Rangamati">Rangamati (রাঙ্গামাটি)</option>
                    <option value="Khagrachhari">Khagrachhari (খাগড়াছড়ি)</option>
                    <option value="Cumilla">Cumilla (কুমিল্লা)</option>
                    <option value="Feni">Feni (ফেনী)</option>
                    <option value="Brahmanbaria">Brahmanbaria (ব্রাহ্মণবাড়িয়া)</option>
                    <option value="Chandpur">Chandpur (চাঁদপুর)</option>
                    <option value="Lakshmipur">Lakshmipur (লক্ষ্মীপুর)</option>
                    <option value="Noakhali">Noakhali (নোয়াখালী)</option>
                  </optgroup>
                  <optgroup label="Sylhet Division (৪ জেলা)">
                    <option value="Sylhet">Sylhet (সিলেট)</option>
                    <option value="Moulvibazar">Moulvibazar (মৌলভীবাজার)</option>
                    <option value="Habiganj">Habiganj (হবিগঞ্জ)</option>
                    <option value="Sunamganj">Sunamganj (সুনামগঞ্জ)</option>
                  </optgroup>
                  <optgroup label="Khulna Division (১০ জেলা)">
                    <option value="Khulna">Khulna (খুলনা)</option>
                    <option value="Bagerhat">Bagerhat (বাগেরহাট)</option>
                    <option value="Satkhira">Satkhira (সাতক্ষীরা)</option>
                    <option value="Jashore">Jashore (যশোর)</option>
                    <option value="Jhenaidah">Jhenaidah (ঝিনাইদহ)</option>
                    <option value="Kushtia">Kushtia (কুষ্টিয়া)</option>
                    <option value="Magura">Magura (মাগুরা)</option>
                    <option value="Meherpur">Meherpur (মেহেরপুর)</option>
                    <option value="Narail">Narail (নড়াইল)</option>
                    <option value="Chuadanga">Chuadanga (চুয়াডাঙ্গা)</option>
                  </optgroup>
                  <optgroup label="Rajshahi Division (৮ জেলা)">
                    <option value="Rajshahi">Rajshahi (রাজশাহী)</option>
                    <option value="Bogura">Bogura (বগুড়া)</option>
                    <option value="Joypurhat">Joypurhat (জয়পুরহাট)</option>
                    <option value="Naogaon">Naogaon (নওগাঁ)</option>
                    <option value="Natore">Natore (নাটোর)</option>
                    <option value="Chapai Nawabganj">Chapai Nawabganj (চাঁপাইনবাবগঞ্জ)</option>
                    <option value="Pabna">Pabna (পাবনা)</option>
                    <option value="Sirajganj">Sirajganj (সিরাজগঞ্জ)</option>
                  </optgroup>
                  <optgroup label="Barishal Division (৬ জেলা)">
                    <option value="Barishal">Barishal (বরিশাল)</option>
                    <option value="Patuakhali">Patuakhali (পটুয়াখালী)</option>
                    <option value="Bhola">Bhola (ভোলা)</option>
                    <option value="Barguna">Barguna (বরগুনা)</option>
                    <option value="Jhalokati">Jhalokati (ঝালকাঠি)</option>
                    <option value="Pirojpur">Pirojpur (পিরোজপুর)</option>
                  </optgroup>
                  <optgroup label="Rangpur Division (৮ জেলা)">
                    <option value="Rangpur">Rangpur (রংপুর)</option>
                    <option value="Dinajpur">Dinajpur (দিনাজপুর)</option>
                    <option value="Gaibandha">Gaibandha (গাইবান্ধা)</option>
                    <option value="Kurigram">Kurigram (কুড়িগ্রাম)</option>
                    <option value="Lalmonirhat">Lalmonirhat (লালমনিরহাট)</option>
                    <option value="Nilphamari">Nilphamari (নীলফামারী)</option>
                    <option value="Panchagarh">Panchagarh (পঞ্চগড়)</option>
                    <option value="Thakurgaon">Thakurgaon (ঠাকুরগাঁও)</option>
                  </optgroup>
                  <optgroup label="Mymensingh Division (৪ জেলা)">
                    <option value="Mymensingh">Mymensingh (ময়মনসিংহ)</option>
                    <option value="Jamalpur">Jamalpur (জামালপুর)</option>
                    <option value="Netrokona">Netrokona (নেত্রকোণা)</option>
                    <option value="Sherpur">Sherpur (শেরপুর)</option>
                  </optgroup>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label" for="regAvatarInput">Choose Avatar Icon</label>
                <select id="regAvatarInput" class="form-control">
                  <option value="🧭">🧭 Compass Explorer</option>
                  <option value="🧗">🧗 Hill Climber</option>
                  <option value="🎒">🎒 Backpacker Nomad</option>
                  <option value="⛺">⛺ Nature Camper</option>
                  <option value="⛵">⛵ River Sailor</option>
                  <option value="📸">📸 Travel Photographer</option>
                  <option value="🐅">🐅 Royal Bengal Tiger</option>
                  <option value="🦅">🦅 Sky Falcon</option>
                  <option value="🌊">🌊 Coastal Surfer</option>
                  <option value="🌴">🌴 Tropical Voyager</option>
                </select>
              </div>
            </div>

            <div class="form-row-2">
              <div class="form-group">
                <label class="form-label" for="regTravelStyleInput">Travel Style</label>
                <select id="regTravelStyleInput" class="form-control">
                  <option value="Adventure & Trekking">Adventure & Trekking</option>
                  <option value="Nature & Wildlife Photography">Nature & Wildlife Photography</option>
                  <option value="Heritage, Archaeology & History">Heritage, Archaeology & History</option>
                  <option value="Rivers, Haors & Waterfalls">Rivers, Haors & Waterfalls</option>
                  <option value="Solo Backpacker & Roadtrips">Solo Backpacker & Roadtrips</option>
                  <option value="Family Holidays & Relaxed Tours">Family Holidays & Relaxed Tours</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label" for="regMottoInput">Travel Motto</label>
                <input type="text" id="regMottoInput" class="form-control" value="জীবন একটাই, বাংলাদেশটা ঘুরে দেখো!" placeholder="Your travel philosophy">
              </div>
            </div>

            <div class="form-row-2">
              <div class="form-group">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                  <label class="form-label" for="regPasswordInput">Password <span style="color:#ef4444;">*</span></label>
                  <span class="password-toggle-btn" onclick="togglePasswordVisibility('regPasswordInput', this)">
                    <i class="far fa-eye"></i> Show
                  </span>
                </div>
                <div class="input-with-icon">
                  <i class="fas fa-key icon-field"></i>
                  <input type="password" id="regPasswordInput" class="form-control" placeholder="Min. 6 characters" required>
                </div>
              </div>

              <div class="form-group">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                  <label class="form-label" for="regConfirmPasswordInput">Confirm Password <span style="color:#ef4444;">*</span></label>
                  <span class="password-toggle-btn" onclick="togglePasswordVisibility('regConfirmPasswordInput', this)">
                    <i class="far fa-eye"></i> Show
                  </span>
                </div>
                <div class="input-with-icon">
                  <i class="fas fa-check-double icon-field"></i>
                  <input type="password" id="regConfirmPasswordInput" class="form-control" placeholder="Re-enter password" required>
                </div>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="regBioInput">Short Bio</label>
              <textarea id="regBioInput" class="form-control" rows="2" placeholder="Tell other travelers about your passions and dreams in exploring Bangladesh..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary" id="btnRegisterSubmit" style="width:100%; margin-top:8px;">
              <i class="fas fa-user-plus"></i> Create Explorer Account (MongoDB Cloud)
            </button>
          </form>

          <div class="auth-footer-switch">
            <span>Already have an account?</span>
            <button type="button" class="btn-link" onclick="switchAuthTab('login')">Log In to Your Profile</button>
          </div>
        </div>

      </div>

      <div class="modal-footer" style="justify-content:flex-end;">
        <button type="button" class="btn btn-secondary modal-cancel-btn">Close</button>
      </div>
    </div>
  </div>

  <!-- Alias/Fallback modal for Quick Switch -->
  <div class="modal-backdrop" id="gmailModal">
    <div class="modal-box" style="max-width:500px;">
      <div class="modal-header">
        <div class="modal-title">
          <div class="gmail-g-icon" style="width:28px; height:28px; font-size:0.9rem;"><i class="fab fa-google"></i></div>
          <span>Switch Explorer Profile</span>
        </div>
        <button type="button" class="modal-close-btn">&times;</button>
      </div>

      <div class="modal-body">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
          <span style="font-size:0.85rem; color:var(--text-muted);">Registered Profiles in MongoDB Atlas</span>
          <button type="button" class="btn btn-primary btn-sm" onclick="closeModal('gmailModal'); openAuthModal('register');">
            <i class="fas fa-plus"></i> Register New
          </button>
        </div>
        <div id="gmailAccountsListContainer">
          <!-- Injected by JS -->
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary modal-cancel-btn">Close</button>
      </div>
    </div>
  </div>

  <!-- Toast Notification Container -->
  <div class="toast-container" id="toastContainer"></div>

  <!-- Leaflet JS -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

  <!-- Application JS -->
  <script src="assets/js/app.js"></script>
</body>
</html>
