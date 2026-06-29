<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Map Viewer | Arteche · Ready to Serve | Community Intelligence</title>

    <!-- Bootstrap 5 + icons -->
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
      rel="stylesheet"
    />
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
      rel="stylesheet"
    />

    <!-- Leaflet GIS Map CSS -->
    <link 
      rel="stylesheet" 
      href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
      crossorigin="" 
    />

    <style>
      :root {
        --primary: #0f2a44; /* deep navy */
        --secondary: #1f6aa5; /* strong blue */
        --accent: #2dd4bf; /* fresh teal */
        --light-bg: #f4f7fb;
        --border-light: #e9edf2;
        --stat-red: #c92a2a;
        --stat-white: #ffffff;
      }

      body {
        font-family: "Segoe UI", Roboto, system-ui, sans-serif;
        background-color: var(--light-bg);
        color: #1e2f4e;
        display: flex;
        flex-direction: column;
        min-height: 100vh;
      }

      /* Navbar – deep navy */
      .navbar {
        background: #0b1f33;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        padding: 0.7rem 0;
      }
      .navbar-brand {
        font-weight: 700;
        letter-spacing: -0.3px;
      }
      .navbar-brand i {
        color: #ff6b6b;
      }

      /* Map Section Styles */
      .map-wrapper {
        flex: 1;
        display: flex;
        flex-direction: column;
        position: relative;
      }

      #map {
        width: 100%;
        height: 600px;
        min-height: 65vh;
        border-radius: 18px;
        box-shadow: 0 8px 28px rgba(0, 0, 0, 0.06);
        border: 1px solid rgba(0, 0, 0, 0.05);
        background: #eef3f9;
        z-index: 1;
      }

      .section-title-redesigned {
        font-weight: 700;
        color: #07273c;
        border-left: 6px solid #c92a2a;
        padding-left: 1.1rem;
        margin: 2rem 0 1.5rem 0;
        font-size: 1.6rem;
      }

      .map-sidebar-card {
        background: white;
        border-radius: 18px;
        padding: 1.5rem;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.02);
        border: 1px solid rgba(0, 0, 0, 0.03);
        height: 100%;
      }

      .legend-indicator {
        width: 16px;
        height: 16px;
        border-radius: 4px;
        display: inline-block;
      }

      .wilayah-chip {
        background: #f0f5fa;
        border-radius: 50px;
        padding: 0.45rem 1.2rem;
        font-size: 0.85rem;
        font-weight: 500;
        color: #194a6a;
        display: inline-block;
        margin: 0.2rem 0.2rem;
        border: 1px solid #dae2ed;
      }

      /* Footer */
      .footer {
        background: #0a1e2c;
        color: #b6ccda;
        padding: 2.8rem 0 2rem;
        margin-top: auto; /* Pushes footer to bottom if page content is short */
        border-top: 5px solid #9f2c2c;
      }

      .survey-badge {
        background: #2dd4bf;
        color: #011c1c;
        border-radius: 30px;
        padding: 0.4rem 1.5rem;
        font-weight: 600;
      }
    </style>
  </head>
  <body>
    <!-- NAVBAR WITH MATCHING GLASSMORPHISM STYLE -->
    <nav
      class="navbar navbar-expand-lg navbar-dark sticky-top"
      style="
        background: rgba(11, 31, 51, 0.75);
        backdrop-filter: blur(12px) saturate(180%);
        -webkit-backdrop-filter: blur(12px) saturate(180%);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
      "
    >
      <div class="container">
        <a class="navbar-brand" href="../../../public/index.php">
          <i class="bi bi-flag-fill me-1" style="color: #ffb4b4"></i>
          AR<span style="color: #ffd966">TECHE</span> · CIS
        </a>
        <button
          class="navbar-toggler"
          type="button"
          data-bs-toggle="collapse"
          data-bs-target="#mainNav"
        >
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="mainNav">
          <ul class="navbar-nav">
            <li class="nav-item">
              <!-- Relative path back up out of app/shared/components/ to public/ -->
              <a class="nav-link" href="../../../public/index.html">Home</a>
            </li>
            <li class="nav-item">
              <a class="nav-link active" href="#">Map</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="../../../public/about.html">About</a>
            </li>
            <li class="nav-item">
              <a class="nav-link btn" href="../../../public/on_boarding.html">Login</a>
            </li>
          </ul>
        </div>
      </div>
    </nav>

    <!-- === MAIN MAP CONTAINER === -->
    <div class="container my-4 map-wrapper">
      <div class="d-flex justify-content-between align-items-center flex-wrap mb-1">
        <h2 class="section-title-redesigned mt-0 mb-2">
          <i class="bi bi-map-fill me-2" style="color: #b52b2b"></i>Poverty Mapping & Household GIS Data
        </h2>
        <span class="survey-badge mb-2">
          <i class="bi bi-geo-alt-fill me-1"></i> Interactive Spatial Intelligence
        </span>
      </div>

      <div class="row g-4 mb-4">
        <!-- Interactive Map Frame -->
        <div class="col-lg-8">
          <div id="map"></div>
        </div>

        <!-- Sidebar Filters/Metadata Control Panel -->
        <div class="col-lg-4">
          <div class="map-sidebar-card d-flex flex-column justify-content-between">
            <div>
              <h5 class="fw-bold mb-3 text-primary">
                <i class="bi bi-sliders me-2"></i>Map Control Center
              </h5>
              <p class="small text-muted">
                Analyze and visualize prioritized resource allocations, poverty risk indexes, and house profiling across covered cluster zones.
              </p>
              <hr class="my-3" style="border-color: #dee5ed;" />

              <!-- GIS Filter options mockup placeholder -->
              <div class="mb-4">
                <label class="form-label small fw-bold text-uppercase tracking-wider text-secondary">Visual Layers</label>
                <select class="form-select border-2" style="border-radius: 10px;">
                  <option selected>Household Density Layer</option>
                  <option>Poverty Index Heatmap</option>
                  <option>Barangay Boundaries</option>
                  <option>Infrastructure Status</option>
                </select>
              </div>

              <!-- Interactive Legend System -->
              <div class="mb-4">
                <label class="form-label small fw-bold text-uppercase tracking-wider text-secondary d-block mb-2">Legend Metrics</label>
                <div class="d-flex align-items-center gap-2 mb-2">
                  <span class="legend-indicator" style="background-color: #c92a2a;"></span>
                  <span class="small fw-medium">High Priority Action Areas</span>
                </div>
                <div class="d-flex align-items-center gap-2 mb-2">
                  <span class="legend-indicator" style="background-color: #1f6aa5;"></span>
                  <span class="small fw-medium">Stable Profiling Status</span>
                </div>
                <div class="d-flex align-items-center gap-2 mb-2">
                  <span class="legend-indicator" style="background-color: #2dd4bf;"></span>
                  <span class="small fw-medium">Newly Surveyed Sectors</span>
                </div>
              </div>
            </div>

            <div>
              <label class="form-label small fw-bold text-uppercase tracking-wider text-secondary d-block mb-1">Target Jurisdictions</label>
              <div>
                <span class="wilayah-chip m-1">Balud</span>
                <span class="wilayah-chip m-1">Central</span>
                <span class="wilayah-chip m-1">Rawis</span>
                <span class="wilayah-chip m-1">Garden</span>
              </div>
              <p class="small text-muted mt-3 mb-0">
                <i class="bi bi-info-circle-fill me-1 text-primary"></i> Data is continuously updated via community census collectors.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- === FOOTER === -->
    <footer class="footer">
      <div class="container">
        <div class="row">
          <div class="col-md-8">
            <h5 style="color: white">
              <i class="bi bi-flag-fill me-2" style="color: #ffaaaa"></i>
              Arteche Community Intelligence System
            </h5>
            <p style="color: #aac7db">
              Serving all barangays of Arteche, Eastern Samar. Committed to 
              responsive and transparent governance.<br />
              All data protected under the Data Privacy Act of 2012 (RA 10173).
            </p>
          </div>
          <div class="col-md-4 text-md-end">
            <i class="bi bi-telephone me-2"></i> 055-321-7890<br />
            <i class="bi bi-envelope me-2"></i> cis@arteche.gov.ph<br />
            <span class="badge bg-light text-dark mt-2">#ArtecheCares</span>
          </div>
        </div>
        <hr class="mt-4" style="border-color: #3d637b" />
        <div class="text-center pt-3">
          <p class="mb-0">
            © 2026 Municipality of Arteche – Community Intelligence System. All
            rights reserved.
          </p>
          <p class="small mt-2">
            <i class="bi bi-geo-alt-fill text-danger"></i> Arteche, Eastern Samar, Philippines
          </p>
        </div>
      </div>
    </footer>

    <!-- Scripts Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Leaflet GIS Map Library JS Engine -->
    <script 
      src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
      integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
      crossorigin="">
    </script>

    <script>
      // Initialize Map Centered on General Coordinates for Arteche, Eastern Samar
      // Lat/Lng approx: 12.2638° N, 125.4153° E
      const map = L.map('map').setView([12.2638, 125.4153], 13);

      // Add OpenStreetMap Tile Sandbox Layers
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap contributors'
      }).addTo(map);

      // Example Mock Marker - Central Arteche Municipal Area
      const municipalHallMarker = L.marker([12.2638, 125.4153]).addTo(map);
      municipalHallMarker.bindPopup("<b>Arteche Municipal Government Hall</b><br>Command Center and Intelligence Hub.").openPopup();
    </script>
  </body>
</html>