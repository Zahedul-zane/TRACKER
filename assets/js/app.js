/**
 * Bangladesh District & All Spots Travel Explorer - Frontend Application
 * Handles Leaflet Map, GeoJSON styling, Spots Database, User Profiles,
 * MongoDB state synchronization, badges, and scorecard generator.
 */

const AppState = {
  visits: {},
  spots: [],
  user: {},
  config: {},
  stats: {},
  auth: { authenticated: false, user: null, active_gmail: '' },
  leaderboard: [],
  districtsGeo: null,
  map: null,
  geoJsonLayer: null,
  spotsLayerGroup: null,
  showSpotsOnMap: false,
  activeView: 'map',
  filterDivision: 'all',
  filterStatus: 'all',
  filterCategory: 'all',
  searchQuery: '',
  spotsSearchQuery: '',
  spotsFilterDivision: 'all',
  spotsFilterStatus: 'all',
  sortBy: 'name_asc',
  selectedDistrict: null,
  selectedSpot: null,
  uploadedPhotos: [],
  tileLayer: null,
  isSatellite: false
};

const DISTRICT_CENTERS = {};

// Initialize application on DOM loaded
document.addEventListener('DOMContentLoaded', async () => {
  initNavigation();
  initMap();
  await loadAppData();
  await loadGeoJSON();
  await loadSpotsData();
  initFilters();
  initModals();
  initPassport();
});

/* ===================================================================
   NAVIGATION & VIEW SWITCHING
   =================================================================== */

function initNavigation() {
  const navPills = document.querySelectorAll('.nav-pill');
  navPills.forEach(pill => {
    pill.addEventListener('click', () => {
      const targetView = pill.dataset.view;
      switchView(targetView);
    });
  });
}

function switchView(viewName) {
  AppState.activeView = viewName;
  document.querySelectorAll('.nav-pill').forEach(p => {
    p.classList.toggle('active', p.dataset.view === viewName);
  });

  document.querySelectorAll('.view-section').forEach(sec => {
    sec.classList.toggle('active', sec.id === `view-${viewName}`);
  });

  if (viewName === 'map' && AppState.map) {
    setTimeout(() => AppState.map.invalidateSize(), 200);
  } else if (viewName === 'spots') {
    renderSpotsGrid();
  } else if (viewName === 'grid') {
    renderDistrictsGrid();
  } else if (viewName === 'analytics') {
    renderAnalytics();
  } else if (viewName === 'passport') {
    renderPassport();
  } else if (viewName === 'profile') {
    renderProfileView();
  } else if (viewName === 'dashboard') {
    renderDashboardView();
  }
}

/* ===================================================================
   DATA LOADING & REST API INTERFACE
   =================================================================== */

async function loadAppData() {
  try {
    const res = await fetch('api.php?action=get_data');
    const data = await res.json();
    if (data.success) {
      AppState.visits = data.visits || {};
      AppState.user = data.user || {};
      AppState.config = data.config || {};
      AppState.stats = data.stats || {};
      AppState.auth = data.auth || { authenticated: false, user: AppState.user, active_gmail: data.active_gmail };

      updateHeaderStats();
      updateMongoStatus();
      updateAuthUI();
      if (AppState.geoJsonLayer) {
        updateMapDistrictStyles();
      }
      renderSidebarList();
    }
  } catch (err) {
    console.error('Failed to load application data:', err);
    showToast('Failed to load travel data from server.', 'error');
  }
}

async function loadGeoJSON() {
  try {
    const res = await fetch('assets/data/bd_districts.geojson');
    const geoData = await res.json();
    AppState.districtsGeo = geoData;

    geoData.features.forEach(f => {
      const p = f.properties;
      if (p.name && p.lat && p.long) {
        DISTRICT_CENTERS[p.name.toLowerCase()] = [p.lat, p.long];
      }
    });

    renderGeoJsonOnMap();
    renderSidebarList();
  } catch (err) {
    console.error('Failed to load Bangladesh GeoJSON:', err);
    showToast('Could not load district boundary map.', 'error');
  }
}

async function loadSpotsData() {
  try {
    const res = await fetch('api.php?action=get_spots');
    const data = await res.json();
    if (data.success) {
      AppState.spots = data.spots || [];
      renderSpotsOnMap();
      renderSpotsGrid();
    }
  } catch (err) {
    console.error('Failed to load spots data:', err);
  }
}

/* ===================================================================
   LEAFLET MAP INTEGRATION (DISTRICT POLYGONS + SPOTS MARKERS)
   =================================================================== */

function initMap() {
  AppState.map = L.map('bdMap', {
    center: [23.8103, 90.3563],
    zoom: 7.2,
    minZoom: 6,
    maxZoom: 14,
    zoomControl: false
  });

  L.control.zoom({ position: 'topright' }).addTo(AppState.map);

  AppState.tileLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; <a href="https://carto.com/">CARTO</a>, OpenStreetMap',
    subdomains: 'abcd',
    maxZoom: 19
  }).addTo(AppState.map);

  AppState.spotsLayerGroup = L.layerGroup().addTo(AppState.map);

  // Floating controls
  document.getElementById('btnResetMap')?.addEventListener('click', resetMapView);
  document.getElementById('btnToggleSatellite')?.addEventListener('click', toggleMapTileLayer);
  document.getElementById('btnToggleSpotsMarkers')?.addEventListener('click', toggleSpotsOnMap);
}

function resetMapView() {
  if (AppState.geoJsonLayer) {
    AppState.map.fitBounds(AppState.geoJsonLayer.getBounds(), { padding: [25, 25] });
  } else {
    AppState.map.setView([23.8103, 90.3563], 7.2);
  }
}

function toggleMapTileLayer() {
  if (AppState.isSatellite) {
    AppState.map.removeLayer(AppState.tileLayer);
    AppState.tileLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
      maxZoom: 19
    }).addTo(AppState.map);
    AppState.isSatellite = false;
    document.getElementById('btnToggleSatellite').innerHTML = '<i class="fas fa-satellite"></i> Satellite';
  } else {
    AppState.map.removeLayer(AppState.tileLayer);
    AppState.tileLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
      maxZoom: 18,
      attribution: 'Tiles &copy; Esri'
    }).addTo(AppState.map);
    AppState.isSatellite = true;
    document.getElementById('btnToggleSatellite').innerHTML = '<i class="fas fa-map"></i> Dark Map';
  }
}

function toggleSpotsOnMap() {
  AppState.showSpotsOnMap = !AppState.showSpotsOnMap;
  const btn = document.getElementById('btnToggleSpotsMarkers');
  const txt = document.getElementById('spotsToggleText');
  
  if (AppState.showSpotsOnMap) {
    renderSpotsOnMap();
    if (txt) txt.textContent = 'Hide Spots';
    btn?.classList.add('active');
    showToast('Showing famous spots on map.', 'success');
  } else {
    if (AppState.spotsLayerGroup) AppState.spotsLayerGroup.clearLayers();
    if (txt) txt.textContent = 'Show Spots';
    btn?.classList.remove('active');
  }
}

function renderSpotsOnMap() {
  if (!AppState.spotsLayerGroup || !AppState.spots) return;
  AppState.spotsLayerGroup.clearLayers();

  if (!AppState.showSpotsOnMap) return;

  AppState.spots.forEach(spot => {
    if (!spot.lat || !spot.long) return;

    const iconHtml = `<div class="spot-custom-marker" title="${spot.name}">${spot.icon || '📍'}</div>`;
    const customIcon = L.divIcon({
      className: 'spot-leaflet-icon',
      html: iconHtml,
      iconSize: [32, 32],
      iconAnchor: [16, 16]
    });

    const marker = L.marker([spot.lat, spot.long], { icon: customIcon });

    const statusBadge = spot.status === 'visited' ? '🟢 Visited' : (spot.status === 'wishlist' ? '🟡 Wishlist' : '⚪ Unvisited');
    const popupHtml = `
      <div style="font-size:0.85rem; line-height:1.4;">
        <strong style="font-size:0.95rem;">${spot.name}</strong><br/>
        <span style="font-family:var(--font-bangla); color:#34d399;">${spot.name_bn || ''}</span><br/>
        <span style="color:#94a3b8; font-size:0.75rem;">${spot.district}, ${spot.division}</span><br/>
        <span style="display:inline-block; margin-top:4px; font-weight:700;">${statusBadge}</span><br/>
        <button style="margin-top:6px; padding:4px 8px; background:#10b981; color:#fff; border:none; border-radius:4px; font-size:0.75rem; cursor:pointer;" onclick="openSpotModal('${spot.id}')">
          View & Log Spot
        </button>
      </div>
    `;

    marker.bindPopup(popupHtml);
    AppState.spotsLayerGroup.addLayer(marker);
  });
}

function getDistrictStatus(districtName) {
  const visit = AppState.visits[districtName];
  if (visit && visit.status) {
    return visit.status;
  }
  return 'unvisited';
}

function getDistrictFeatureStyle(feature) {
  const name = feature.properties.name;
  const status = getDistrictStatus(name);

  if (status === 'visited') {
    return {
      fillColor: '#10b981',
      fillOpacity: 0.65,
      weight: 1.6,
      opacity: 0.9,
      color: '#34d399'
    };
  } else if (status === 'wishlist') {
    return {
      fillColor: '#f59e0b',
      fillOpacity: 0.55,
      weight: 1.5,
      opacity: 0.85,
      color: '#fbbf24'
    };
  } else {
    return {
      fillColor: '#1e293b',
      fillOpacity: 0.45,
      weight: 1.0,
      opacity: 0.6,
      color: '#334155'
    };
  }
}

function renderGeoJsonOnMap() {
  if (!AppState.districtsGeo || !AppState.map) return;

  if (AppState.geoJsonLayer) {
    AppState.map.removeLayer(AppState.geoJsonLayer);
  }

  AppState.geoJsonLayer = L.geoJSON(AppState.districtsGeo, {
    style: getDistrictFeatureStyle,
    onEachFeature: (feature, layer) => {
      const p = feature.properties;
      const status = getDistrictStatus(p.name);
      
      const statusLabel = status === 'visited' ? '🟢 Visited' : (status === 'wishlist' ? '🟡 Wishlist' : '⚪ Not Visited');
      const tooltipContent = `
        <div style="font-size:0.85rem; line-height:1.4;">
          <strong>${p.name}</strong> (${p.bn_name || ''})<br/>
          <span style="color:#94a3b8; font-size:0.75rem;">Division: ${p.division || ''}</span><br/>
          <span style="font-weight:600; font-size:0.78rem;">${statusLabel}</span>
        </div>
      `;
      layer.bindTooltip(tooltipContent, { sticky: true, opacity: 0.95 });

      layer.on('mouseover', (e) => {
        const l = e.target;
        l.setStyle({ weight: 2.5, color: '#ffffff', fillOpacity: 0.85 });
        l.bringToFront();
      });

      layer.on('mouseout', (e) => {
        AppState.geoJsonLayer.resetStyle(e.target);
      });

      layer.on('click', () => {
        openDistrictModal(p.name, p);
      });
    }
  }).addTo(AppState.map);

  AppState.map.fitBounds(AppState.geoJsonLayer.getBounds(), { padding: [15, 15] });
}

function updateMapDistrictStyles() {
  if (!AppState.geoJsonLayer) return;
  AppState.geoJsonLayer.eachLayer(layer => {
    if (layer.feature) {
      layer.setStyle(getDistrictFeatureStyle(layer.feature));
    }
  });
}

function focusDistrictOnMap(districtName) {
  if (!AppState.geoJsonLayer) return;
  AppState.geoJsonLayer.eachLayer(layer => {
    if (layer.feature && layer.feature.properties.name.toLowerCase() === districtName.toLowerCase()) {
      AppState.map.fitBounds(layer.getBounds(), { maxZoom: 10, padding: [40, 40] });
      layer.setStyle({ weight: 3, color: '#38bdf8', fillOpacity: 0.9 });
      setTimeout(() => AppState.geoJsonLayer.resetStyle(layer), 2500);
    }
  });
}

/* ===================================================================
   ALL SPOTS IN BANGLADESH VIEW
   =================================================================== */

function getFilteredSpots() {
  let list = [...AppState.spots];

  // Category Filter
  if (AppState.filterCategory !== 'all') {
    list = list.filter(s => s.category === AppState.filterCategory);
  }

  // Division Filter
  if (AppState.spotsFilterDivision !== 'all') {
    list = list.filter(s => (s.division || '').toLowerCase() === AppState.spotsFilterDivision.toLowerCase());
  }

  // Status Filter
  if (AppState.spotsFilterStatus !== 'all') {
    list = list.filter(s => (s.status || 'unvisited') === AppState.spotsFilterStatus);
  }

  // Search
  if (AppState.spotsSearchQuery.trim() !== '') {
    const q = AppState.spotsSearchQuery.toLowerCase().trim();
    list = list.filter(s => {
      const en = (s.name || '').toLowerCase();
      const bn = (s.name_bn || '').toLowerCase();
      const dist = (s.district || '').toLowerCase();
      const div = (s.division || '').toLowerCase();
      const desc = (s.description || '').toLowerCase();
      return en.includes(q) || bn.includes(q) || dist.includes(q) || div.includes(q) || desc.includes(q);
    });
  }

  return list;
}

function renderSpotsGrid() {
  const container = document.getElementById('spotsCardsGrid');
  const countBadge = document.getElementById('spotsCountBadge');
  if (!container) return;

  const spots = getFilteredSpots();
  if (countBadge) countBadge.textContent = `Showing ${spots.length} spots`;

  if (spots.length === 0) {
    container.innerHTML = `
      <div style="grid-column: 1 / -1; text-align:center; padding: 60px 20px; color:var(--text-dim);">
        <i class="fas fa-search-location" style="font-size: 2.5rem; margin-bottom: 12px; display:block;"></i>
        <h3>No spots found</h3>
        <p>Try searching for a different keyword or selecting 'All Categories'.</p>
      </div>
    `;
    return;
  }

  let html = '';
  spots.forEach(s => {
    const status = s.status || 'unvisited';
    const rating = s.rating || 5;

    let starsHtml = '';
    for (let i = 1; i <= 5; i++) {
      starsHtml += `<i class="${i <= rating ? 'fas' : 'far'} fa-star"></i>`;
    }

    html += `
      <div class="spot-card ${status}" onclick="openSpotModal('${s.id}')">
        <div>
          <div class="spot-card-header">
            <div class="spot-title-wrap">
              <div class="spot-title">
                <span>${s.icon || '📍'}</span>
                <span>${s.name}</span>
              </div>
              <div class="spot-title-bn">${s.name_bn || ''}</div>
            </div>
            <span class="spot-category-badge">${s.category || 'Attraction'}</span>
          </div>

          <div class="spot-loc-tags">
            <span class="meta-chip"><i class="fas fa-map-marker-alt"></i> ${s.district}, ${s.division}</span>
            ${status === 'visited' ? `<span class="meta-chip rating-stars">${starsHtml}</span>` : ''}
            ${s.visit_date ? `<span class="meta-chip"><i class="far fa-calendar-alt"></i> ${s.visit_date}</span>` : ''}
          </div>

          ${s.best_time ? `<div class="spot-best-time"><i class="far fa-clock"></i> Best: ${s.best_time}</div>` : ''}

          <div class="spot-description">
            ${s.notes ? '<em>Your Note:</em> ' + s.notes : s.description}
          </div>
        </div>

        <div class="spot-card-footer" onclick="event.stopPropagation()">
          <span class="district-badge-status ${status}">${status}</span>
          <div style="display:flex; gap:6px;">
            <button class="btn btn-sm ${status === 'visited' ? 'btn-primary' : 'btn-secondary'}" onclick="quickSetSpotStatus('${s.id}', 'visited')">
              <i class="fas fa-check"></i> Visited
            </button>
            <button class="btn btn-sm ${status === 'wishlist' ? 'btn-primary' : 'btn-secondary'}" onclick="quickSetSpotStatus('${s.id}', 'wishlist')">
              <i class="fas fa-bookmark"></i> Wishlist
            </button>
            <button class="btn btn-sm btn-secondary" onclick="openSpotModal('${s.id}')" title="Log visit details">
              <i class="fas fa-pencil-alt"></i>
            </button>
          </div>
        </div>
      </div>
    `;
  });

  container.innerHTML = html;
}

function openSpotModal(spotId) {
  const spot = AppState.spots.find(s => s.id === spotId);
  if (!spot) return;

  AppState.selectedSpot = spot;

  document.getElementById('modalSpotIcon').textContent = spot.icon || '📍';
  document.getElementById('modalSpotName').textContent = spot.name;
  document.getElementById('modalSpotBn').textContent = spot.name_bn || '';
  document.getElementById('modalSpotCategory').textContent = spot.category || 'Attraction';
  document.getElementById('modalSpotLocation').innerHTML = `<i class="fas fa-map-marker-alt"></i> ${spot.district}, ${spot.division}`;
  document.getElementById('modalSpotBestTime').innerHTML = `<i class="far fa-calendar-alt"></i> ${spot.best_time || 'Year round'}`;
  document.getElementById('modalSpotDesc').textContent = spot.description || '';

  // Status buttons
  const status = spot.status || 'visited';
  setModalSpotStatus(status);

  document.getElementById('spotVisitDateInput').value = spot.visit_date || new Date().toISOString().split('T')[0];
  setModalSpotStarRating(spot.rating || 5);
  document.getElementById('spotNotesInput').value = spot.notes || '';

  // Show/Hide delete
  const btnDel = document.getElementById('btnDeleteSpotVisit');
  if (btnDel) {
    btnDel.style.display = (spot.status && spot.status !== 'unvisited') ? 'inline-flex' : 'none';
  }

  openModal('spotModal');
}

function setModalSpotStatus(status) {
  document.querySelectorAll('#spotSegmentedControl .segment-btn').forEach(btn => {
    btn.classList.remove('active', 'visited', 'wishlist', 'unvisited');
    if (btn.dataset.status === status) {
      btn.classList.add('active', status);
    }
  });
}

function getSelectedModalSpotStatus() {
  const activeBtn = document.querySelector('#spotSegmentedControl .segment-btn.active');
  return activeBtn ? activeBtn.dataset.status : 'visited';
}

function setModalSpotStarRating(rating) {
  document.querySelectorAll('#spotStarRatingBox i').forEach(star => {
    const val = parseInt(star.dataset.val, 10);
    star.className = val <= rating ? 'fas fa-star active' : 'far fa-star';
  });
  document.getElementById('spotRatingValueInput').value = rating;
}

async function quickSetSpotStatus(spotId, status) {
  try {
    const res = await fetch('api.php?action=toggle_spot', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ spot_id: spotId, status: status })
    });
    const data = await res.json();
    if (data.success) {
      const sp = AppState.spots.find(s => s.id === spotId);
      if (sp) {
        sp.status = status;
      }
      await loadAppData();
      renderSpotsGrid();
      if (AppState.showSpotsOnMap) renderSpotsOnMap();
      showToast(`Updated ${sp ? sp.name : 'spot'} status to ${status}.`, 'success');
    }
  } catch (err) {
    showToast('Failed to update spot.', 'error');
  }
}

async function saveSpotVisitData() {
  if (!AppState.selectedSpot) return;

  const spotId = AppState.selectedSpot.id;
  const status = getSelectedModalSpotStatus();
  const date = document.getElementById('spotVisitDateInput').value;
  const rating = parseInt(document.getElementById('spotRatingValueInput').value, 10) || 5;
  const notes = document.getElementById('spotNotesInput').value;

  try {
    const res = await fetch('api.php?action=save_spot_visit', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        spot_id: spotId,
        status: status,
        visit_date: date,
        rating: rating,
        notes: notes
      })
    });
    const data = await res.json();
    if (data.success) {
      const sp = AppState.spots.find(s => s.id === spotId);
      if (sp) {
        sp.status = status;
        sp.visit_date = date;
        sp.rating = rating;
        sp.notes = notes;
      }
      closeModal('spotModal');
      await loadAppData();
      renderSpotsGrid();
      if (AppState.showSpotsOnMap) renderSpotsOnMap();
      showToast(`Saved spot memories for ${sp ? sp.name : 'spot'}!`, 'success');
    }
  } catch (err) {
    showToast('Error saving spot visit.', 'error');
  }
}

async function deleteSpotVisitData() {
  if (!AppState.selectedSpot) return;
  const spotId = AppState.selectedSpot.id;

  try {
    const res = await fetch('api.php?action=delete_spot_visit', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ spot_id: spotId })
    });
    const data = await res.json();
    if (data.success) {
      const sp = AppState.spots.find(s => s.id === spotId);
      if (sp) {
        sp.status = 'unvisited';
        sp.notes = '';
      }
      closeModal('spotModal');
      await loadAppData();
      renderSpotsGrid();
      if (AppState.showSpotsOnMap) renderSpotsOnMap();
      showToast('Spot visit reset.', 'success');
    }
  } catch (err) {
    showToast('Error resetting spot.', 'error');
  }
}

/* ===================================================================
   USER PROFILE & MONGODB VIEW
   =================================================================== */

function renderProfileView() {
  const u = AppState.user || {};
  const stats = AppState.stats || {};

  document.getElementById('profileAvatarDisplay').textContent = u.avatar || '🧭';
  document.getElementById('profileNameDisplay').textContent = u.name || 'Tanvir Ahmed';
  document.getElementById('profileUsernameDisplay').textContent = `@${u.username || 'voyager_bd'}`;
  document.getElementById('profileBioDisplay').textContent = `"${u.bio || 'Exploring Bangladesh.'}"`;
  document.getElementById('profileHomeDistDisplay').textContent = u.home_district || 'Dhaka';
  document.getElementById('profileStyleDisplay').textContent = u.travel_style || 'Adventure & Photography';
  document.getElementById('profileMottoDisplay').textContent = u.travel_motto || 'জীবন একটাই, বাংলাদেশটা ঘুরে দেখো!';
  document.getElementById('profileEmailDisplay').textContent = u.email || 'tanvir@voyager.bd';
  document.getElementById('profileJoinedDisplay').textContent = u.joined_date || 'Jan 2024';

  // Stats boxes
  document.getElementById('profileNumDistricts').textContent = `${stats.visited_count || 0} / 64`;
  document.getElementById('profileNumSpots').textContent = `${stats.visited_spots_count || 0} / ${stats.total_spots || 50}`;
  
  let unlockedBadgesCount = 0;
  if (stats.badges) {
    stats.badges.forEach(b => { if (b.unlocked) unlockedBadgesCount++; });
  }
  document.getElementById('profileNumBadges').textContent = `${unlockedBadgesCount} / 8`;
  document.getElementById('profileNumWishlist').textContent = (stats.wishlist_count || 0) + (stats.wishlist_spots_count || 0);
}

function openEditProfileModal() {
  const u = AppState.user || {};
  document.getElementById('editProfName').value = u.name || '';
  document.getElementById('editProfUsername').value = u.username || '';
  document.getElementById('editProfEmail').value = u.email || '';
  document.getElementById('editProfPhone').value = u.phone || '';
  document.getElementById('editProfHomeDist').value = u.home_district || 'Dhaka';
  document.getElementById('editProfAvatar').value = u.avatar || '🧭';
  document.getElementById('editProfStyle').value = u.travel_style || '';
  document.getElementById('editProfMotto').value = u.travel_motto || '';
  document.getElementById('editProfBio').value = u.bio || '';

  openModal('editProfileModal');
}

async function saveFullUserProfile() {
  const payload = {
    name: document.getElementById('editProfName').value.trim(),
    username: document.getElementById('editProfUsername').value.trim(),
    email: document.getElementById('editProfEmail').value.trim(),
    phone: document.getElementById('editProfPhone').value.trim(),
    home_district: document.getElementById('editProfHomeDist').value,
    avatar: document.getElementById('editProfAvatar').value,
    travel_style: document.getElementById('editProfStyle').value.trim(),
    travel_motto: document.getElementById('editProfMotto').value.trim(),
    bio: document.getElementById('editProfBio').value.trim()
  };

  try {
    const res = await fetch('api.php?action=save_profile', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
      AppState.user = data.user;
      closeModal('editProfileModal');
      renderProfileView();
      renderPassport();
      showToast('Profile stored in MongoDB successfully!', 'success');
    }
  } catch (err) {
    showToast('Failed to update user profile.', 'error');
  }
}

/* ===================================================================
   DISTRICT DIRECTORY & SIDEBAR LIST
   =================================================================== */

function getFilteredDistricts() {
  if (!AppState.districtsGeo) return [];

  let list = AppState.districtsGeo.features.map(f => f.properties);

  if (AppState.filterDivision !== 'all') {
    list = list.filter(d => (d.division || '').toLowerCase() === AppState.filterDivision.toLowerCase());
  }

  if (AppState.filterStatus !== 'all') {
    list = list.filter(d => getDistrictStatus(d.name) === AppState.filterStatus);
  }

  if (AppState.searchQuery.trim() !== '') {
    const q = AppState.searchQuery.toLowerCase().trim();
    list = list.filter(d => {
      const en = (d.name || '').toLowerCase();
      const bn = (d.bn_name || '').toLowerCase();
      const div = (d.division || '').toLowerCase();
      return en.includes(q) || bn.includes(q) || div.includes(q);
    });
  }

  list.sort((a, b) => {
    if (AppState.sortBy === 'name_asc') {
      return a.name.localeCompare(b.name);
    } else if (AppState.sortBy === 'name_desc') {
      return b.name.localeCompare(a.name);
    } else if (AppState.sortBy === 'rating_desc') {
      const rA = AppState.visits[a.name]?.rating || 0;
      const rB = AppState.visits[b.name]?.rating || 0;
      return rB - rA;
    } else if (AppState.sortBy === 'date_desc') {
      const dA = AppState.visits[a.name]?.visit_date || '';
      const dB = AppState.visits[b.name]?.visit_date || '';
      return dB.localeCompare(dA);
    }
    return 0;
  });

  return list;
}

function renderSidebarList() {
  const container = document.getElementById('districtsSidebarList');
  if (!container) return;

  const districts = getFilteredDistricts();
  if (districts.length === 0) {
    container.innerHTML = `
      <div style="text-align:center; padding:30px 10px; color:var(--text-dim); font-size:0.85rem;">
        <i class="fas fa-search" style="font-size:1.5rem; margin-bottom:8px; display:block;"></i>
        No districts match your filter.
      </div>
    `;
    return;
  }

  let html = '';
  districts.forEach(d => {
    const status = getDistrictStatus(d.name);
    const visit = AppState.visits[d.name] || {};
    const rowClass = status === 'visited' ? 'is-visited' : (status === 'wishlist' ? 'is-wishlist' : '');

    html += `
      <div class="district-item-row ${rowClass}" onclick="handleSidebarDistrictClick('${d.name}')">
        <div class="district-item-info">
          <div class="district-item-name">
            ${d.name} <span class="district-bn-tag">${d.bn_name || ''}</span>
          </div>
          <div class="district-item-div">${d.division || 'Bangladesh'} ${visit.visit_date ? '• ' + visit.visit_date : ''}</div>
        </div>
        <div class="district-quick-actions" onclick="event.stopPropagation()">
          ${status === 'visited' 
            ? `<button class="quick-badge-btn visited" title="Visited (Click to cycle)" onclick="cycleDistrictStatus('${d.name}')"><i class="fas fa-check-circle"></i> Visited</button>` 
            : (status === 'wishlist' 
                ? `<button class="quick-badge-btn wishlist" title="Wishlist (Click to cycle)" onclick="cycleDistrictStatus('${d.name}')"><i class="fas fa-bookmark"></i> Wishlist</button>`
                : `<button class="quick-badge-btn unvisited" title="Mark as visited" onclick="cycleDistrictStatus('${d.name}')"><i class="far fa-circle"></i> Mark</button>`
              )
          }
        </div>
      </div>
    `;
  });

  container.innerHTML = html;
}

function handleSidebarDistrictClick(districtName) {
  focusDistrictOnMap(districtName);
  const feature = AppState.districtsGeo?.features.find(f => f.properties.name.toLowerCase() === districtName.toLowerCase());
  openDistrictModal(districtName, feature ? feature.properties : { name: districtName });
}

/* ===================================================================
   DISTRICT GRID VIEW
   =================================================================== */

function renderDistrictsGrid() {
  const container = document.getElementById('districtsCardsGrid');
  if (!container) return;

  const districts = getFilteredDistricts();
  if (districts.length === 0) {
    container.innerHTML = `
      <div style="grid-column: 1 / -1; text-align:center; padding: 60px 20px; color:var(--text-dim);">
        <i class="fas fa-map-marker-alt" style="font-size: 2.5rem; margin-bottom: 12px; display:block;"></i>
        <h3>No districts found</h3>
        <p>Try resetting the search keyword or division filter.</p>
      </div>
    `;
    return;
  }

  let html = '';
  districts.forEach(d => {
    const status = getDistrictStatus(d.name);
    const visit = AppState.visits[d.name] || {};
    const rating = visit.rating || 5;

    let starsHtml = '';
    for (let i = 1; i <= 5; i++) {
      starsHtml += `<i class="${i <= rating ? 'fas' : 'far'} fa-star"></i>`;
    }

    const photosCount = (visit.photos && Array.isArray(visit.photos)) ? visit.photos.length : 0;

    html += `
      <div class="district-card ${status}" onclick="openDistrictModal('${d.name}')">
        <div class="district-card-top">
          <div class="district-card-title">
            <span>${d.name}</span>
            <span class="district-card-bn">${d.bn_name || ''}</span>
          </div>
          <span class="district-badge-status ${status}">${status}</span>
        </div>

        <div class="district-meta-tags">
          <span class="meta-chip"><i class="fas fa-compass"></i> ${d.division || 'Bangladesh'}</span>
          ${visit.visit_date ? `<span class="meta-chip"><i class="far fa-calendar-alt"></i> ${visit.visit_date}</span>` : ''}
          ${status === 'visited' ? `<span class="meta-chip rating-stars">${starsHtml}</span>` : ''}
          ${photosCount > 0 ? `<span class="meta-chip"><i class="fas fa-camera"></i> ${photosCount}</span>` : ''}
        </div>

        <div class="district-notes-preview">
          ${visit.notes ? visit.notes : (visit.favorite_spots ? 'Highlights: ' + visit.favorite_spots : 'No notes added yet. Click to record your travel memories.')}
        </div>

        <div class="district-card-actions" onclick="event.stopPropagation()">
          <button class="btn btn-sm ${status === 'visited' ? 'btn-primary' : 'btn-secondary'}" onclick="quickSetStatus('${d.name}', 'visited')">
            <i class="fas fa-check"></i> Visited
          </button>
          <button class="btn btn-sm ${status === 'wishlist' ? 'btn-primary' : 'btn-secondary'}" onclick="quickSetStatus('${d.name}', 'wishlist')">
            <i class="fas fa-bookmark"></i> Wishlist
          </button>
          <button class="btn btn-sm btn-secondary" onclick="openDistrictModal('${d.name}')" title="Edit travel details">
            <i class="fas fa-pencil-alt"></i> Details
          </button>
        </div>
      </div>
    `;
  });

  container.innerHTML = html;
}

async function cycleDistrictStatus(districtName) {
  const current = getDistrictStatus(districtName);
  let nextStatus = 'visited';
  if (current === 'visited') nextStatus = 'wishlist';
  else if (current === 'wishlist') nextStatus = 'unvisited';
  else nextStatus = 'visited';

  await quickSetStatus(districtName, nextStatus);
}

async function quickSetStatus(districtName, status) {
  try {
    const meta = AppState.districtsGeo?.features.find(f => f.properties.name.toLowerCase() === districtName.toLowerCase())?.properties || {};

    const res = await fetch('api.php?action=toggle_status', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        district_name: districtName,
        status: status,
        district_bn: meta.bn_name || '',
        division: meta.division || '',
        division_bn: meta.division_bn || ''
      })
    });

    const data = await res.json();
    if (data.success) {
      if (status === 'unvisited') {
        delete AppState.visits[districtName];
      } else {
        AppState.visits[districtName] = data.record;
      }

      await loadAppData();
      showToast(`${districtName} marked as ${status}.`, 'success');
    }
  } catch (err) {
    showToast('Failed to update district status.', 'error');
  }
}

/* ===================================================================
   FILTERS & EVENT LISTENERS
   =================================================================== */

function initFilters() {
  // Sidebar Search
  document.getElementById('sidebarSearchInput')?.addEventListener('input', (e) => {
    AppState.searchQuery = e.target.value;
    renderSidebarList();
  });

  // Division chips in sidebar
  document.querySelectorAll('.division-filter-strip .div-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      document.querySelectorAll('.division-filter-strip .div-chip').forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      AppState.filterDivision = chip.dataset.div;
      renderSidebarList();
    });
  });

  // Spots Category Chips
  document.querySelectorAll('#categoryFilterStrip .category-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      document.querySelectorAll('#categoryFilterStrip .category-chip').forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      AppState.filterCategory = chip.dataset.cat;
      renderSpotsGrid();
    });
  });

  // Spots Search & Filters
  document.getElementById('spotsSearchInput')?.addEventListener('input', (e) => {
    AppState.spotsSearchQuery = e.target.value;
    renderSpotsGrid();
  });

  document.getElementById('spotsDivisionSelect')?.addEventListener('change', (e) => {
    AppState.spotsFilterDivision = e.target.value;
    renderSpotsGrid();
  });

  document.getElementById('spotsStatusSelect')?.addEventListener('change', (e) => {
    AppState.spotsFilterStatus = e.target.value;
    renderSpotsGrid();
  });

  // Districts Grid Search & Selects
  document.getElementById('gridSearchInput')?.addEventListener('input', (e) => {
    AppState.searchQuery = e.target.value;
    renderDistrictsGrid();
  });

  document.getElementById('gridDivisionSelect')?.addEventListener('change', (e) => {
    AppState.filterDivision = e.target.value;
    renderDistrictsGrid();
  });

  document.getElementById('gridStatusSelect')?.addEventListener('change', (e) => {
    AppState.filterStatus = e.target.value;
    renderDistrictsGrid();
  });

  document.getElementById('gridSortSelect')?.addEventListener('change', (e) => {
    AppState.sortBy = e.target.value;
    renderDistrictsGrid();
  });
}

/* ===================================================================
   STATS & HEADER UPDATES
   =================================================================== */

function updateHeaderStats() {
  const visited = AppState.stats.visited_count || 0;
  const wishlist = AppState.stats.wishlist_count || 0;
  const percentage = AppState.stats.percentage || 0;
  const visitedSpots = AppState.stats.visited_spots_count || 0;
  const totalSpots = AppState.stats.total_spots || 50;

  let divVisitedCount = 0;
  if (AppState.stats.divisions) {
    Object.values(AppState.stats.divisions).forEach(d => {
      if (d.visited > 0) divVisitedCount++;
    });
  }

  const elTotalVisited = document.getElementById('statTotalVisited');
  if (elTotalVisited) elTotalVisited.textContent = `${visited} / 64`;

  const elPercentage = document.getElementById('statProgressPercent');
  if (elPercentage) elPercentage.textContent = `${percentage}%`;

  const elProgressFill = document.getElementById('statProgressBarFill');
  if (elProgressFill) elProgressFill.style.width = `${percentage}%`;

  const elSpots = document.getElementById('statVisitedSpots');
  if (elSpots) elSpots.textContent = `${visitedSpots} / ${totalSpots}`;

  const elWishlist = document.getElementById('statWishlistCount');
  if (elWishlist) elWishlist.textContent = wishlist;

  const elDivisions = document.getElementById('statDivisionsCount');
  if (elDivisions) elDivisions.textContent = `${divVisitedCount} / 8`;
}

function updateMongoStatus() {
  const badge = document.getElementById('mongoStatusBadge');
  const label = document.getElementById('mongoStatusLabel');
  if (!badge || !label) return;

  if (AppState.config && AppState.config.mongo_connected) {
    badge.className = 'mongo-badge connected';
    label.innerHTML = `MongoDB: Connected`;
    badge.title = `Connected to ${AppState.config.mongo_uri} (${AppState.config.db_name}). Click to manage.`;
  } else {
    badge.className = 'mongo-badge fallback';
    label.innerHTML = `Storage: Local Fallback`;
    badge.title = `MongoDB offline. Saving to local storage. Click to configure MongoDB.`;
  }
}

/* ===================================================================
   ANALYTICS & BADGES VIEW
   =================================================================== */

function renderAnalytics() {
  renderDivisionProgressBars();
  renderBadgesList();
}

function renderDivisionProgressBars() {
  const container = document.getElementById('divisionBarsGrid');
  if (!container || !AppState.stats.divisions) return;

  let html = '';
  Object.values(AppState.stats.divisions).forEach(div => {
    html += `
      <div class="division-bar-item">
        <div class="div-bar-top">
          <div class="div-bar-name">
            ${div.name} <small>${div.name_bn || ''}</small>
          </div>
          <div class="div-bar-count">
            ${div.visited} / ${div.total} (${div.percentage}%)
          </div>
        </div>
        <div class="div-bar-track">
          <div class="div-bar-fill" style="width: ${div.percentage}%;"></div>
        </div>
      </div>
    `;
  });

  container.innerHTML = html;
}

function renderBadgesList() {
  const container = document.getElementById('badgesGrid');
  if (!container || !AppState.stats.badges) return;

  let html = '';
  AppState.stats.badges.forEach(b => {
    html += `
      <div class="badge-card ${b.unlocked ? 'unlocked' : 'locked'}">
        <div class="badge-icon-box">
          ${b.icon}
        </div>
        <h4 class="badge-title">${b.title}</h4>
        <div class="badge-title-bn">${b.title_bn || ''}</div>
        <p class="badge-desc">${b.desc}</p>
        <div class="badge-status-pill">
          ${b.unlocked ? '<i class="fas fa-check"></i> Unlocked' : `${Math.round(b.progress * 100)}% Complete`}
        </div>
      </div>
    `;
  });

  container.innerHTML = html;
}

/* ===================================================================
   DISTRICT MODAL & PHOTO UPLOAD
   =================================================================== */

function openDistrictModal(districtName, properties = null) {
  if (!properties && AppState.districtsGeo) {
    const f = AppState.districtsGeo.features.find(x => x.properties.name.toLowerCase() === districtName.toLowerCase());
    if (f) properties = f.properties;
  }

  properties = properties || { name: districtName, bn_name: '', division: '' };
  AppState.selectedDistrict = properties;

  const visit = AppState.visits[districtName] || {};

  document.getElementById('modalDistrictName').textContent = properties.name;
  document.getElementById('modalDistrictBn').textContent = properties.bn_name ? `(${properties.bn_name})` : '';
  document.getElementById('modalDistrictDiv').textContent = properties.division ? `Division: ${properties.division}` : '';

  setModalStatus(visit.status || 'visited');
  document.getElementById('visitDateInput').value = visit.visit_date || new Date().toISOString().split('T')[0];
  setModalStarRating(visit.rating || 5);
  document.getElementById('companionSelect').value = visit.companions || 'Solo';
  document.getElementById('transportSelect').value = visit.transport_mode || 'Bus';
  document.getElementById('favoriteSpotsInput').value = visit.favorite_spots || '';
  document.getElementById('travelNotesInput').value = visit.notes || '';

  AppState.uploadedPhotos = (visit.photos && Array.isArray(visit.photos)) ? [...visit.photos] : [];
  renderPhotoThumbnails();

  const btnDelete = document.getElementById('btnDeleteVisit');
  if (btnDelete) {
    btnDelete.style.display = AppState.visits[districtName] ? 'inline-flex' : 'none';
  }

  openModal('districtModal');
}

function setModalStatus(status) {
  document.querySelectorAll('#districtModal .segmented-control .segment-btn').forEach(btn => {
    btn.classList.remove('active', 'visited', 'wishlist', 'unvisited');
    if (btn.dataset.status === status) {
      btn.classList.add('active', status);
    }
  });
}

function getSelectedModalStatus() {
  const activeBtn = document.querySelector('#districtModal .segmented-control .segment-btn.active');
  return activeBtn ? activeBtn.dataset.status : 'visited';
}

function setModalStarRating(rating) {
  document.querySelectorAll('#districtModal .star-rating-box i').forEach(star => {
    const val = parseInt(star.dataset.val, 10);
    star.className = val <= rating ? 'fas fa-star active' : 'far fa-star';
  });
  document.getElementById('ratingValueInput').value = rating;
}

function renderPhotoThumbnails() {
  const container = document.getElementById('photoPreviewGrid');
  if (!container) return;

  if (AppState.uploadedPhotos.length === 0) {
    container.innerHTML = '';
    return;
  }

  let html = '';
  AppState.uploadedPhotos.forEach((src, idx) => {
    html += `
      <div style="position:relative; display:inline-block;">
        <img src="${src}" class="photo-thumb" alt="Trip Photo">
        <button type="button" onclick="removePhoto(${idx})" style="position:absolute; top:-6px; right:-6px; background:#ef4444; color:#fff; border:none; border-radius:50%; width:20px; height:20px; font-size:10px; cursor:pointer; display:flex; align-items:center; justify-content:center;">&times;</button>
      </div>
    `;
  });

  container.innerHTML = html;
}

function removePhoto(index) {
  AppState.uploadedPhotos.splice(index, 1);
  renderPhotoThumbnails();
}

async function handlePhotoUpload(input) {
  if (!input.files || input.files.length === 0) return;

  const file = input.files[0];
  const formData = new FormData();
  formData.append('photo', file);

  try {
    showToast('Uploading photo...', 'warning');
    const res = await fetch('api.php?action=upload_photo', {
      method: 'POST',
      body: formData
    });
    const data = await res.json();
    if (data.success) {
      AppState.uploadedPhotos.push(data.file_path);
      renderPhotoThumbnails();
      showToast('Photo uploaded successfully!', 'success');
    } else {
      showToast(data.message || 'Photo upload failed.', 'error');
    }
  } catch (err) {
    showToast('Error uploading photo.', 'error');
  }
}

async function saveDistrictVisit() {
  if (!AppState.selectedDistrict) return;

  const districtName = AppState.selectedDistrict.name;
  const status = getSelectedModalStatus();
  const visitDate = document.getElementById('visitDateInput').value;
  const rating = parseInt(document.getElementById('ratingValueInput').value, 10) || 5;
  const companions = document.getElementById('companionSelect').value;
  const transport = document.getElementById('transportSelect').value;
  const favoriteSpots = document.getElementById('favoriteSpotsInput').value;
  const notes = document.getElementById('travelNotesInput').value;

  const payload = {
    district_name: districtName,
    district_bn: AppState.selectedDistrict.bn_name || '',
    division: AppState.selectedDistrict.division || '',
    division_bn: AppState.selectedDistrict.division_bn || '',
    status: status,
    visit_date: visitDate,
    rating: rating,
    companions: companions,
    transport_mode: transport,
    favorite_spots: favoriteSpots,
    notes: notes,
    photos: AppState.uploadedPhotos
  };

  try {
    const res = await fetch('api.php?action=save_visit', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();

    if (data.success) {
      AppState.visits[districtName] = data.record;
      closeModal('districtModal');
      await loadAppData();
      showToast(`Saved travel memories for ${districtName}!`, 'success');
    }
  } catch (err) {
    showToast('Server error while saving visit.', 'error');
  }
}

async function deleteDistrictVisit() {
  if (!AppState.selectedDistrict) return;
  const districtName = AppState.selectedDistrict.name;

  if (!confirm(`Are you sure you want to reset all visit details for ${districtName}?`)) {
    return;
  }

  try {
    const res = await fetch('api.php?action=delete_visit', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ district_name: districtName })
    });
    const data = await res.json();
    if (data.success) {
      delete AppState.visits[districtName];
      closeModal('districtModal');
      await loadAppData();
      showToast(`Reset details for ${districtName}.`, 'success');
    }
  } catch (err) {
    showToast('Error removing visit.', 'error');
  }
}

/* ===================================================================
   PASSPORT SCORECARD
   =================================================================== */

function initPassport() {
  document.getElementById('btnDownloadPassport')?.addEventListener('click', downloadPassportCard);
  document.getElementById('btnCopyShareText')?.addEventListener('click', copyShareText);
}

function renderPassport() {
  const u = AppState.user || {};
  const visited = AppState.stats.visited_count || 0;
  const percentage = AppState.stats.percentage || 0;
  const visitedSpots = AppState.stats.visited_spots_count || 0;

  document.getElementById('passportUserName').textContent = u.name || 'Tanvir Ahmed';
  document.getElementById('passportHomeDist').textContent = `Home: ${u.home_district || 'Dhaka'}, Bangladesh`;
  document.getElementById('passportVisitedVal').textContent = `${visited}/64`;
  document.getElementById('passportSpotsVal').textContent = `${visitedSpots}/50`;
  document.getElementById('passportPercentVal').textContent = `${percentage}%`;
  document.getElementById('passportDateStamp').textContent = new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
  document.getElementById('passportAvatar').textContent = u.avatar || '🧭';
}

function copyShareText() {
  const visited = AppState.stats.visited_count || 0;
  const spots = AppState.stats.visited_spots_count || 0;
  const percentage = AppState.stats.percentage || 0;
  const name = AppState.user?.name || 'A Bangladeshi Voyager';

  const text = `🇧🇩 My Bangladesh Travel Score!\n\nI have explored ${visited} out of 64 districts (${percentage}%) and ${spots} famous tourist spots across Bangladesh!\nTrack your travels across Bangladesh with Amar Desh Tracker.`;
  navigator.clipboard.writeText(text).then(() => {
    showToast('Shareable travel summary copied to clipboard!', 'success');
  });
}

function downloadPassportCard() {
  const canvas = document.createElement('canvas');
  canvas.width = 1200;
  canvas.height = 700;
  const ctx = canvas.getContext('2d');

  const bgGrad = ctx.createLinearGradient(0, 0, 1200, 700);
  bgGrad.addColorStop(0, '#0a1914');
  bgGrad.addColorStop(0.5, '#07101c');
  bgGrad.addColorStop(1, '#05070f');
  ctx.fillStyle = bgGrad;
  ctx.fillRect(0, 0, 1200, 700);

  ctx.strokeStyle = '#10b981';
  ctx.lineWidth = 6;
  ctx.strokeRect(30, 30, 1140, 640);

  ctx.fillStyle = '#ffffff';
  ctx.font = 'bold 44px Outfit, sans-serif';
  ctx.fillText('BANGLADESH TRAVELER PASSPORT', 80, 110);

  ctx.fillStyle = '#10b981';
  ctx.font = '500 24px "Hind Siliguri", Outfit, sans-serif';
  ctx.fillText('আমার সোনার বাংলা • ৬৪ জেলা ও দর্শনীয় স্থান ভ্রমণ ট্র্যাকার', 80, 150);

  // User
  ctx.fillStyle = '#ffffff';
  ctx.font = 'bold 36px Outfit, sans-serif';
  ctx.fillText(AppState.user.name || 'Tanvir Ahmed', 220, 260);

  ctx.fillStyle = '#94a3b8';
  ctx.font = '22px Outfit, sans-serif';
  ctx.fillText(`Home: ${AppState.user.home_district || 'Dhaka'} • Issue: ${new Date().toLocaleDateString('en-GB')}`, 220, 295);

  const stats = [
    { label: 'DISTRICTS VISITED', value: `${AppState.stats.visited_count || 0} / 64` },
    { label: 'SPOTS VISITED', value: `${AppState.stats.visited_spots_count || 0} / ${AppState.stats.total_spots || 50}` },
    { label: 'COMPLETION', value: `${AppState.stats.percentage || 0}%` }
  ];

  stats.forEach((s, idx) => {
    const x = 80 + (idx * 350);
    const y = 370;
    ctx.fillStyle = 'rgba(255, 255, 255, 0.05)';
    ctx.fillRect(x, y, 320, 160);
    ctx.strokeStyle = 'rgba(16, 185, 129, 0.3)';
    ctx.lineWidth = 2;
    ctx.strokeRect(x, y, 320, 160);

    ctx.fillStyle = '#34d399';
    ctx.font = 'bold 48px Outfit, sans-serif';
    ctx.fillText(s.value, x + 30, y + 75);

    ctx.fillStyle = '#94a3b8';
    ctx.font = 'bold 16px Outfit, sans-serif';
    ctx.fillText(s.label, x + 30, y + 120);
  });

  ctx.fillStyle = '#64748b';
  ctx.font = '18px Outfit, sans-serif';
  ctx.fillText('Amar Desh Tracker • Powered by PHP & MongoDB', 80, 610);

  const link = document.createElement('a');
  link.download = `Bangladesh_Traveler_Passport_${AppState.user.username || 'Explorer'}.png`;
  link.href = canvas.toDataURL('image/png');
  link.click();
  showToast('Passport certificate downloaded!', 'success');
}

/* ===================================================================
   MODALS & EVENT INITIALIZATION
   =================================================================== */

function initModals() {
  // District status buttons
  document.querySelectorAll('#districtModal .segmented-control .segment-btn').forEach(btn => {
    btn.addEventListener('click', () => setModalStatus(btn.dataset.status));
  });

  // Spot status buttons
  document.querySelectorAll('#spotSegmentedControl .segment-btn').forEach(btn => {
    btn.addEventListener('click', () => setModalSpotStatus(btn.dataset.status));
  });

  // District star rating
  document.querySelectorAll('#districtModal .star-rating-box i').forEach(star => {
    star.addEventListener('click', () => setModalStarRating(parseInt(star.dataset.val, 10)));
  });

  // Spot star rating
  document.querySelectorAll('#spotStarRatingBox i').forEach(star => {
    star.addEventListener('click', () => setModalSpotStarRating(parseInt(star.dataset.val, 10)));
  });

  // District action buttons
  document.getElementById('btnSaveVisit')?.addEventListener('click', saveDistrictVisit);
  document.getElementById('btnDeleteVisit')?.addEventListener('click', deleteDistrictVisit);

  // Spot action buttons
  document.getElementById('btnSaveSpotVisit')?.addEventListener('click', saveSpotVisitData);
  document.getElementById('btnDeleteSpotVisit')?.addEventListener('click', deleteSpotVisitData);

  // Photo file upload
  const photoInput = document.getElementById('photoFileInput');
  photoInput?.addEventListener('change', (e) => handlePhotoUpload(e.target));
  document.getElementById('photoUploadArea')?.addEventListener('click', () => photoInput.click());

  // Profile Edit modal
  document.getElementById('btnOpenProfileModal')?.addEventListener('click', openEditProfileModal);
  document.getElementById('btnEditProfileOpen')?.addEventListener('click', openEditProfileModal);
  document.getElementById('btnSaveFullProfile')?.addEventListener('click', saveFullUserProfile);

  // MongoDB Modal & Actions
  document.getElementById('mongoStatusBadge')?.addEventListener('click', openMongoModal);
  document.getElementById('btnOpenMongoSettingsFromProfile')?.addEventListener('click', openMongoModal);
  document.getElementById('btnTestMongo')?.addEventListener('click', testMongoConnection);
  document.getElementById('btnSaveMongoConfig')?.addEventListener('click', saveMongoConfig);
  document.getElementById('btnSyncMongo')?.addEventListener('click', syncLocalToMongo);
  document.getElementById('btnSyncMongoFromProfile')?.addEventListener('click', syncLocalToMongo);

  // Gmail Badge & Account Switcher / Auth Dashboard
  document.getElementById('gmailUserBadge')?.addEventListener('click', openGmailModal);
  document.getElementById('headerUserBadge')?.addEventListener('click', () => switchView('dashboard'));
  document.getElementById('btnHeaderLogin')?.addEventListener('click', () => openAuthModal('login'));
  document.getElementById('btnHeaderLogout')?.addEventListener('click', handleLogout);
  document.getElementById('btnDashboardLogout')?.addEventListener('click', handleLogout);
  document.getElementById('btnDashboardOpenLogin')?.addEventListener('click', () => openAuthModal('login'));
  document.getElementById('btnDashboardOpenRegister')?.addEventListener('click', () => openAuthModal('register'));
  document.getElementById('dashBtnEditProfile')?.addEventListener('click', openEditProfileModal);

  // Auth Modal Tab Buttons
  document.getElementById('tabBtnLogin')?.addEventListener('click', () => switchAuthTab('login'));
  document.getElementById('tabBtnRegister')?.addEventListener('click', () => switchAuthTab('register'));

  // Quick Demo Login Fill
  document.getElementById('btnFillDemoLogin')?.addEventListener('click', () => {
    document.getElementById('loginGmailInput').value = 'zizahed202@gmail.com';
    document.getElementById('loginPasswordInput').value = 'tracker123';
    showToast('Demo credentials pre-filled! Click Log In.', 'warning');
  });

  // Auth Form Submissions
  document.getElementById('authLoginForm')?.addEventListener('submit', handleLoginSubmit);
  document.getElementById('btnLoginSubmit')?.addEventListener('click', handleLoginSubmit);

  document.getElementById('authRegisterForm')?.addEventListener('submit', handleRegisterSubmit);
  document.getElementById('btnRegisterSubmit')?.addEventListener('click', handleRegisterSubmit);

  // Password Update Form
  document.getElementById('dashPasswordForm')?.addEventListener('submit', handlePasswordChange);

  // Leaderboard search filter
  document.getElementById('leaderboardSearchInput')?.addEventListener('input', (e) => {
    const q = e.target.value.toLowerCase().trim();
    if (!AppState.leaderboard) return;
    const filtered = AppState.leaderboard.filter(t => 
      (t.name && t.name.toLowerCase().includes(q)) ||
      (t.gmail && t.gmail.toLowerCase().includes(q)) ||
      (t.home_district && t.home_district.toLowerCase().includes(q)) ||
      (t.badge && t.badge.toLowerCase().includes(q))
    );
    renderLeaderboardCards(filtered);
  });

  document.getElementById('btnSwitchGmailSubmit')?.addEventListener('click', () => {
    const gmail = document.getElementById('gmailAccountInput').value.trim();
    if (gmail) switchGmailAccount(gmail);
  });

  // Demo trip
  document.getElementById('btnLoadDemoTrip')?.addEventListener('click', loadDemoTrip);

  // Export Data
  document.getElementById('btnExportData')?.addEventListener('click', () => {
    window.location.href = 'api.php?action=export';
  });

  // Close modals
  document.querySelectorAll('.modal-close-btn, .modal-cancel-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const modal = e.target.closest('.modal-backdrop');
      if (modal) modal.classList.remove('open');
    });
  });

  document.querySelectorAll('.modal-backdrop').forEach(modal => {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) modal.classList.remove('open');
    });
  });
}

function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.add('open');
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove('open');
}

function openMongoModal() {
  document.getElementById('mongoUriInput').value = AppState.config.mongo_uri || 'mongodb://127.0.0.1:27017';
  document.getElementById('mongoDbNameInput').value = AppState.config.db_name || 'bangladesh_tracker';
  
  const statusBox = document.getElementById('mongoTestResultBox');
  if (statusBox) {
    if (AppState.config.mongo_connected) {
      statusBox.innerHTML = `<span style="color:#10b981;"><i class="fas fa-check-circle"></i> Connected to MongoDB database.</span>`;
    } else {
      statusBox.innerHTML = `<span style="color:#f59e0b;"><i class="fas fa-exclamation-triangle"></i> MongoDB offline. Saving to local storage.</span>`;
    }
  }

  openModal('mongoModal');
}

async function testMongoConnection() {
  const resultBox = document.getElementById('mongoTestResultBox');
  resultBox.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Testing MongoDB connection...`;

  try {
    const res = await fetch('api.php?action=test_mongo');
    const data = await res.json();
    if (data.success) {
      resultBox.innerHTML = `<span style="color:#10b981;"><i class="fas fa-check-circle"></i> ${data.message}</span>`;
      AppState.config.mongo_connected = true;
      updateMongoStatus();
    } else {
      resultBox.innerHTML = `<span style="color:#ef4444;"><i class="fas fa-times-circle"></i> ${data.message}</span>`;
      AppState.config.mongo_connected = false;
      updateMongoStatus();
    }
  } catch (err) {
    resultBox.innerHTML = `<span style="color:#ef4444;">Error testing connection.</span>`;
  }
}

async function saveMongoConfig() {
  const uri = document.getElementById('mongoUriInput').value.trim();
  const dbName = document.getElementById('mongoDbNameInput').value.trim();

  try {
    const res = await fetch('api.php?action=save_config', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ mongo_uri: uri, db_name: dbName })
    });
    const data = await res.json();

    if (data.config) AppState.config = data.config;
    updateMongoStatus();

    if (data.success) {
      showToast('MongoDB settings saved and connected!', 'success');
      closeModal('mongoModal');
    } else {
      showToast(data.message, 'warning');
    }
  } catch (err) {
    showToast('Failed to save configuration.', 'error');
  }
}

async function syncLocalToMongo() {
  try {
    showToast('Syncing user profile, spots, and visits to MongoDB...', 'warning');
    const res = await fetch('api.php?action=sync_mongo');
    const data = await res.json();
    if (data.success) {
      showToast(data.message, 'success');
    } else {
      showToast(data.message, 'error');
    }
  } catch (err) {
    showToast('Error syncing to MongoDB.', 'error');
  }
}

async function loadDemoTrip() {
  if (!confirm('Load sample travel journey across Bangladesh? (Cox\'s Bazar, Saint Martin, Bandarban, Nilgiri, Sajek, Sylhet, Ratargul, Bagerhat, Dhaka, etc.)')) {
    return;
  }

  try {
    const res = await fetch('api.php?action=load_demo');
    const data = await res.json();
    if (data.success) {
      await loadAppData();
      await loadSpotsData();
      showToast(data.message, 'success');
    }
  } catch (err) {
    showToast('Failed to load demo data.', 'error');
  }
}

async function openGmailModal() {
  const container = document.getElementById('gmailAccountsListContainer');
  if (container) {
    container.innerHTML = `<div style="color:var(--text-dim); font-size:0.85rem;"><i class="fas fa-spinner fa-spin"></i> Loading registered accounts from MongoDB Atlas...</div>`;
  }

  openModal('gmailModal');

  try {
    const res = await fetch('api.php?action=get_users');
    const data = await res.json();
    if (data.success && container) {
      if (!data.users || data.users.length === 0) {
        container.innerHTML = `<div style="color:var(--text-dim); font-size:0.85rem;">No other accounts yet.</div>`;
        return;
      }

      let html = '';
      data.users.forEach(u => {
        const isActive = u.gmail === data.active_gmail;
        html += `
          <div class="gmail-account-item ${isActive ? 'active' : ''}" onclick="switchGmailAccount('${u.gmail}')">
            <div style="display:flex; align-items:center; gap:10px;">
              <span class="gmail-g-icon" style="width:24px; height:24px;"><i class="fab fa-google"></i></span>
              <div>
                <strong style="font-size:0.88rem; color:#fff;">${u.name || u.gmail}</strong>
                <div style="font-size:0.75rem; color:var(--text-muted);">${u.gmail}</div>
              </div>
            </div>
            ${isActive ? '<span style="font-size:0.75rem; color:#10b981; font-weight:700;"><i class="fas fa-check-circle"></i> Active</span>' : '<span style="font-size:0.75rem; color:var(--text-dim);">Switch</span>'}
          </div>
        `;
      });
      container.innerHTML = html;
    }
  } catch (err) {
    if (container) container.innerHTML = `<div style="color:var(--text-dim); font-size:0.85rem;">Could not load accounts list.</div>`;
  }
}

async function switchGmailAccount(gmail) {
  try {
    showToast(`Signing in as ${gmail}...`, 'warning');
    const res = await fetch('api.php?action=switch_gmail', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ gmail: gmail })
    });
    const data = await res.json();
    if (data.success) {
      AppState.user = data.user;
      AppState.auth = { authenticated: true, user: data.user, active_gmail: data.active_gmail };
      updateAuthUI();
      closeModal('gmailModal');
      await loadAppData();
      await loadSpotsData();
      renderProfileView();
      renderPassport();
      showToast(`Welcome back, ${data.user.name}! Connected with MongoDB Atlas.`, 'success');
    } else {
      showToast(data.message || 'Failed to switch Gmail account.', 'error');
    }
  } catch (err) {
    showToast('Error switching account.', 'error');
  }
}

/* ===================================================================
   AUTHENTICATION & REGISTRATION DASHBOARD LOGIC (NEW)
   =================================================================== */

function updateAuthUI() {
  const isAuth = !!AppState.auth?.authenticated;
  const user = AppState.user || {};

  // Header Elements
  const headerUserBadge = document.getElementById('headerUserBadge');
  const btnHeaderLogin = document.getElementById('btnHeaderLogin');
  const btnHeaderLogout = document.getElementById('btnHeaderLogout');
  const headerUserAvatar = document.getElementById('headerUserAvatar');
  const headerUserName = document.getElementById('headerUserName');
  const activeGmailDisplay = document.getElementById('activeGmailDisplay');

  if (isAuth) {
    if (headerUserBadge) headerUserBadge.style.display = 'inline-flex';
    if (btnHeaderLogin) btnHeaderLogin.style.display = 'none';
    if (btnHeaderLogout) btnHeaderLogout.style.display = 'inline-flex';
    if (headerUserAvatar) headerUserAvatar.textContent = user.avatar || '🧭';
    if (headerUserName) headerUserName.textContent = user.name || 'Explorer';
    if (activeGmailDisplay) activeGmailDisplay.textContent = user.gmail || '';
  } else {
    if (headerUserBadge) headerUserBadge.style.display = 'none';
    if (btnHeaderLogin) btnHeaderLogin.style.display = 'inline-flex';
    if (btnHeaderLogout) btnHeaderLogout.style.display = 'none';
  }

  // Dashboard View Elements
  const guestBanner = document.getElementById('dashboardGuestBanner');
  const authContainer = document.getElementById('dashboardAuthContainer');
  const btnDashLogout = document.getElementById('btnDashboardLogout');
  const btnDashOpenLogin = document.getElementById('btnDashboardOpenLogin');

  if (isAuth) {
    if (guestBanner) guestBanner.style.display = 'none';
    if (authContainer) authContainer.style.display = 'grid';
    if (btnDashLogout) btnDashLogout.style.display = 'inline-flex';
    if (btnDashOpenLogin) btnDashOpenLogin.innerHTML = '<i class="fas fa-sync-alt"></i> Switch Account';

    // Populate Active Account Card
    const dashAvatar = document.getElementById('dashAvatarDisplay');
    const dashName = document.getElementById('dashNameDisplay');
    const dashEmail = document.getElementById('dashEmailDisplay');
    const dashHome = document.getElementById('dashHomeDistDisplay');
    const dashStyle = document.getElementById('dashStyleDisplay');
    const dashDistricts = document.getElementById('dashVisitedDistricts');
    const dashSpots = document.getElementById('dashVisitedSpots');
    const dashRate = document.getElementById('dashCompletionRate');

    if (dashAvatar) dashAvatar.textContent = user.avatar || '🧭';
    if (dashName) dashName.textContent = user.name || 'Explorer';
    if (dashEmail) dashEmail.textContent = user.gmail || '';
    if (dashHome) dashHome.innerHTML = `<i class="fas fa-map-marker-alt"></i> ${user.home_district || 'Dhaka'}`;
    if (dashStyle) dashStyle.innerHTML = `<i class="fas fa-hiking"></i> ${user.travel_style || 'Adventure'}`;
    if (dashDistricts) dashDistricts.textContent = `${AppState.stats.visited_count || 0} / 64`;
    if (dashSpots) dashSpots.textContent = `${AppState.stats.visited_spots_count || 0} / 50`;
    if (dashRate) dashRate.textContent = `${AppState.stats.percentage || 0}%`;
  } else {
    if (guestBanner) guestBanner.style.display = 'flex';
    if (authContainer) authContainer.style.display = 'none';
    if (btnDashLogout) btnDashLogout.style.display = 'none';
    if (btnDashOpenLogin) btnDashOpenLogin.innerHTML = '<i class="fas fa-sign-in-alt"></i> Log In';
  }
}

function openAuthModal(tab = 'login') {
  switchAuthTab(tab);
  openModal('authModal');
}

function switchAuthTab(tab) {
  const isLogin = tab === 'login';
  
  const tabBtnLogin = document.getElementById('tabBtnLogin');
  const tabBtnRegister = document.getElementById('tabBtnRegister');
  const panelLogin = document.getElementById('authTabLogin');
  const panelRegister = document.getElementById('authTabRegister');

  if (tabBtnLogin) tabBtnLogin.classList.toggle('active', isLogin);
  if (tabBtnRegister) tabBtnRegister.classList.toggle('active', !isLogin);
  if (panelLogin) panelLogin.classList.toggle('active', isLogin);
  if (panelRegister) panelRegister.classList.toggle('active', !isLogin);
}

function togglePasswordVisibility(inputId, btnEl) {
  const input = document.getElementById(inputId);
  if (!input) return;

  const isPassword = input.type === 'password';
  input.type = isPassword ? 'text' : 'password';
  btnEl.innerHTML = isPassword ? '<i class="far fa-eye-slash"></i> Hide' : '<i class="far fa-eye"></i> Show';
}

async function handleLoginSubmit(e) {
  if (e && e.preventDefault) e.preventDefault();

  const gmail = document.getElementById('loginGmailInput')?.value.trim();
  const password = document.getElementById('loginPasswordInput')?.value;

  if (!gmail || !password) {
    showToast('Please enter both Gmail and password.', 'error');
    return;
  }

  const btn = document.getElementById('btnLoginSubmit');
  const originalHtml = btn ? btn.innerHTML : '';
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Authenticating with MongoDB...';
  }

  try {
    const res = await fetch('api.php?action=auth_login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ gmail, password })
    });
    const data = await res.json();

    if (data.success) {
      AppState.auth = { authenticated: true, user: data.user, active_gmail: data.user.gmail };
      AppState.user = data.user;
      closeModal('authModal');
      await loadAppData();
      await loadSpotsData();
      renderProfileView();
      renderPassport();
      if (AppState.activeView === 'dashboard') {
        renderDashboardView();
      }
      showToast(data.message || 'Logged in successfully!', 'success');
    } else {
      showToast(data.message || 'Login failed. Check your password.', 'error');
    }
  } catch (err) {
    showToast('Network error during login.', 'error');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    }
  }
}

async function handleRegisterSubmit(e) {
  if (e && e.preventDefault) e.preventDefault();

  const gmail = document.getElementById('regGmailInput')?.value.trim();
  const name = document.getElementById('regNameInput')?.value.trim();
  const name_bn = document.getElementById('regNameBnInput')?.value.trim();
  const phone = document.getElementById('regPhoneInput')?.value.trim();
  const home_district = document.getElementById('regHomeDistrictInput')?.value;
  const avatar = document.getElementById('regAvatarInput')?.value;
  const travel_style = document.getElementById('regTravelStyleInput')?.value;
  const travel_motto = document.getElementById('regMottoInput')?.value.trim();
  const password = document.getElementById('regPasswordInput')?.value;
  const confirm_password = document.getElementById('regConfirmPasswordInput')?.value;
  const bio = document.getElementById('regBioInput')?.value.trim();

  if (!gmail || !name || !password) {
    showToast('Please fill in required fields (Gmail, Name, Password).', 'error');
    return;
  }

  if (password.length < 6) {
    showToast('Password must be at least 6 characters long.', 'error');
    return;
  }

  if (password !== confirm_password) {
    showToast('Passwords do not match.', 'error');
    return;
  }

  const btn = document.getElementById('btnRegisterSubmit');
  const originalHtml = btn ? btn.innerHTML : '';
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account in MongoDB Atlas...';
  }

  try {
    const res = await fetch('api.php?action=auth_register', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        gmail,
        name,
        name_bn,
        phone,
        home_district,
        avatar,
        travel_style,
        travel_motto,
        password,
        confirm_password,
        bio
      })
    });
    const data = await res.json();

    if (data.success) {
      AppState.auth = { authenticated: true, user: data.user, active_gmail: data.user.gmail };
      AppState.user = data.user;
      closeModal('authModal');
      await loadAppData();
      await loadSpotsData();
      renderProfileView();
      renderPassport();
      if (AppState.activeView === 'dashboard') {
        renderDashboardView();
      }
      showToast('Registration successful! Welcome to Amar Desh Tracker.', 'success');
    } else {
      showToast(data.message || 'Registration failed.', 'error');
    }
  } catch (err) {
    showToast('Error during registration.', 'error');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    }
  }
}

async function handleLogout() {
  if (!confirm('Are you sure you want to log out from this account?')) return;

  try {
    const res = await fetch('api.php?action=auth_logout');
    const data = await res.json();

    AppState.auth = { authenticated: false, user: null, active_gmail: '' };
    updateAuthUI();
    showToast('Logged out successfully.', 'warning');
    await loadAppData();
    await loadSpotsData();
    renderProfileView();
    if (AppState.activeView === 'dashboard') {
      renderDashboardView();
    }
  } catch (err) {
    showToast('Logout error.', 'error');
  }
}

async function handlePasswordChange(e) {
  if (e && e.preventDefault) e.preventDefault();

  const old_password = document.getElementById('dashOldPass')?.value;
  const new_password = document.getElementById('dashNewPass')?.value;
  const confirm_password = document.getElementById('dashConfirmNewPass')?.value;

  if (!new_password || new_password.length < 6) {
    showToast('New password must be at least 6 characters.', 'error');
    return;
  }

  if (new_password !== confirm_password) {
    showToast('New passwords do not match.', 'error');
    return;
  }

  const btn = document.getElementById('btnUpdatePasswordSubmit');
  const originalHtml = btn ? btn.innerHTML : '';
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating in MongoDB...';
  }

  try {
    const res = await fetch('api.php?action=auth_change_password', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ old_password, new_password, confirm_password })
    });
    const data = await res.json();

    if (data.success) {
      document.getElementById('dashOldPass').value = '';
      document.getElementById('dashNewPass').value = '';
      document.getElementById('dashConfirmNewPass').value = '';
      showToast('Password updated securely with BCRYPT hash!', 'success');
    } else {
      showToast(data.message || 'Could not update password.', 'error');
    }
  } catch (err) {
    showToast('Password update error.', 'error');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    }
  }
}

function renderDashboardView() {
  updateAuthUI();
  loadLeaderboard();
  loadRegisteredAccounts();
}

async function loadLeaderboard() {
  const container = document.getElementById('leaderboardGrid');
  if (!container) return;

  container.innerHTML = `
    <div style="text-align:center; padding:30px; color:var(--text-muted); grid-column:1/-1;">
      <i class="fas fa-spinner fa-spin fa-2x" style="color:var(--emerald-primary); margin-bottom:12px;"></i>
      <div>Loading travelers from MongoDB Atlas...</div>
    </div>
  `;

  try {
    const res = await fetch('api.php?action=get_travelers_leaderboard');
    const data = await res.json();
    if (data.success && Array.isArray(data.leaderboard)) {
      AppState.leaderboard = data.leaderboard;
      renderLeaderboardCards(data.leaderboard);
    } else {
      container.innerHTML = `<div style="text-align:center; padding:20px; color:var(--text-dim); grid-column:1/-1;">No community travelers registered yet.</div>`;
    }
  } catch (err) {
    container.innerHTML = `<div style="text-align:center; padding:20px; color:#ef4444; grid-column:1/-1;">Could not load travelers leaderboard.</div>`;
  }
}

function renderLeaderboardCards(list) {
  const container = document.getElementById('leaderboardGrid');
  if (!container) return;

  if (!list || list.length === 0) {
    container.innerHTML = `<div style="text-align:center; padding:20px; color:var(--text-dim); grid-column:1/-1;">No matching travelers found.</div>`;
    return;
  }

  let html = '';
  list.forEach((t, idx) => {
    const rank = idx + 1;
    let rankBadgeClass = 'rank-other';
    let rankIcon = `#${rank}`;
    if (rank === 1) { rankBadgeClass = 'rank-1'; rankIcon = '🥇 1st'; }
    else if (rank === 2) { rankBadgeClass = 'rank-2'; rankIcon = '🥈 2nd'; }
    else if (rank === 3) { rankBadgeClass = 'rank-3'; rankIcon = '🥉 3rd'; }

    const isCurrent = t.gmail === AppState.user?.gmail;

    html += `
      <div class="leaderboard-card ${isCurrent ? 'current-user' : ''}">
        <div class="leaderboard-card-top">
          <div class="leaderboard-user-details">
            <div class="leaderboard-avatar">${t.avatar || '🧭'}</div>
            <div class="leaderboard-meta">
              <h4>${t.name || t.gmail}</h4>
              <span><i class="fas fa-map-marker-alt"></i> ${t.home_district || 'Bangladesh'} • Joined ${t.joined_date || 'Recently'}</span>
            </div>
          </div>
          <span class="leaderboard-rank-badge ${rankBadgeClass}">${rankIcon}</span>
        </div>

        <div class="leaderboard-progress-wrap">
          <div class="leaderboard-progress-labels">
            <span><strong>${t.visited_districts}</strong> / 64 Districts Visited</span>
            <strong style="color:#34d399;">${t.percentage}%</strong>
          </div>
          <div class="leaderboard-progress-bar">
            <div class="leaderboard-progress-fill" style="width: ${t.percentage}%;"></div>
          </div>
        </div>

        <div class="leaderboard-footer-chips">
          <span><i class="fas fa-map-pin" style="color:#f59e0b;"></i> ${t.visited_spots} Spots Visited</span>
          <span style="color:#38bdf8; font-weight:600;">${t.badge || 'Explorer'}</span>
          ${isCurrent ? 
            '<span style="color:#10b981; font-weight:700;"><i class="fas fa-user-check"></i> You</span>' : 
            `<button type="button" class="btn btn-secondary btn-sm" style="padding:2px 8px; font-size:0.7rem;" onclick="switchGmailAccount('${t.gmail}')">Switch</button>`
          }
        </div>
      </div>
    `;
  });

  container.innerHTML = html;
}

async function loadRegisteredAccounts() {
  const dashList = document.getElementById('dashAccountsList');
  const gmailList = document.getElementById('gmailAccountsListContainer');

  try {
    const res = await fetch('api.php?action=get_users');
    const data = await res.json();
    if (data.success && Array.isArray(data.users)) {
      let dashHtml = '';
      let modalHtml = '';

      data.users.forEach(u => {
        const isActive = u.gmail === data.active_gmail;
        const itemHtml = `
          <div class="gmail-account-item ${isActive ? 'active' : ''}" onclick="switchGmailAccount('${u.gmail}')">
            <div style="display:flex; align-items:center; gap:10px;">
              <span style="font-size:1.3rem;">${u.avatar || '🧭'}</span>
              <div>
                <strong style="font-size:0.88rem; color:#fff;">${u.name || u.gmail}</strong>
                <div style="font-size:0.72rem; color:var(--text-muted);">${u.gmail} • ${u.home_district || 'Dhaka'}</div>
              </div>
            </div>
            ${isActive ? 
              '<span style="font-size:0.75rem; color:#10b981; font-weight:700;"><i class="fas fa-check-circle"></i> Active</span>' : 
              '<span style="font-size:0.75rem; color:var(--text-dim);"><i class="fas fa-arrow-right"></i> Switch</span>'
            }
          </div>
        `;
        dashHtml += itemHtml;
        modalHtml += itemHtml;
      });

      if (dashList) dashList.innerHTML = dashHtml || '<div style="color:var(--text-dim); font-size:0.82rem; text-align:center; padding:12px;">No other accounts yet.</div>';
      if (gmailList) gmailList.innerHTML = modalHtml || '<div style="color:var(--text-dim); font-size:0.82rem; text-align:center; padding:12px;">No accounts yet.</div>';
    }
  } catch (err) {
    if (dashList) dashList.innerHTML = '<div style="color:var(--text-dim); font-size:0.82rem;">Could not load accounts.</div>';
  }
}

// Expose functions globally for inline HTML event handlers
window.openAuthModal = openAuthModal;
window.switchAuthTab = switchAuthTab;
window.togglePasswordVisibility = togglePasswordVisibility;
window.switchGmailAccount = switchGmailAccount;
window.switchView = switchView;
window.openModal = openModal;
window.closeModal = closeModal;


/* ===================================================================
   TOAST NOTIFICATION COMPONENT
   =================================================================== */

function showToast(message, type = 'success') {
  const container = document.getElementById('toastContainer');
  if (!container) return;

  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  
  let icon = '<i class="fas fa-check-circle" style="color:#10b981;"></i>';
  if (type === 'warning') icon = '<i class="fas fa-exclamation-triangle" style="color:#f59e0b;"></i>';
  if (type === 'error') icon = '<i class="fas fa-times-circle" style="color:#ef4444;"></i>';

  toast.innerHTML = `${icon} <span>${message}</span>`;
  container.appendChild(toast);

  setTimeout(() => toast.classList.add('show'), 10);

  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 300);
  }, 3500);
}
