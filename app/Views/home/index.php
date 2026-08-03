<section class="hero-section">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-7">
        <span class="badge rounded-pill badge-tint mb-3 px-3 py-2">
          <i class="bi bi-stars text-gradient me-1"></i> AI-powered karaoke, end to end
        </span>
        <h1 class="hero-title mb-3">Turn any YouTube song<br>into a karaoke video.</h1>
        <p class="hero-subtitle mb-4">
          Paste a link. We separate the vocals, sync the lyrics word-by-word, generate an
          original AI background, and render a studio-quality karaoke MP4 &mdash; no editing required.
        </p>

        <div class="glass-card p-4 p-md-5" id="generateCard">
          <form id="generateForm" novalidate>
            <label for="youtubeUrl" class="form-label fw-semibold">YouTube video URL</label>
            <div class="input-group input-group-lg mb-2">
              <span class="input-group-text bg-transparent border-0 ps-0"><i class="bi bi-youtube text-danger fs-4"></i></span>
              <input
                type="url"
                class="form-control form-control-ak"
                id="youtubeUrl"
                name="youtube_url"
                placeholder="https://www.youtube.com/watch?v=..."
                autocomplete="off"
                required
              >
            </div>
            <div class="invalid-feedback d-block text-danger small mb-3" id="urlError" style="display:none !important;"></div>

            <div class="form-check form-switch mb-4">
              <input class="form-check-input" type="checkbox" id="keepVocals" name="keep_vocals">
              <label class="form-check-label text-secondary" for="keepVocals">
                Keep original vocals (lyric-video mode instead of karaoke)
              </label>
            </div>

            <button type="submit" class="gradient-btn btn btn-lg w-100" id="generateBtn">
              <i class="bi bi-magic me-2"></i>Generate Karaoke
            </button>
            <p class="text-secondary small text-center mt-3 mb-0">
              Max video length: <?= (int) ($maxDurationSeconds / 60) ?> minutes. We only use the audio track.
            </p>
          </form>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="glass-card p-4">
          <h6 class="text-uppercase text-secondary small fw-bold mb-4">How it works</h6>
          <div class="d-flex flex-column gap-3">
            <div class="d-flex align-items-start gap-3">
              <span class="step-icon"><i class="bi bi-link-45deg"></i></span>
              <div>
                <div class="fw-semibold">1. Paste your link</div>
                <div class="text-secondary small">We fetch the audio, title, and thumbnail.</div>
              </div>
            </div>
            <div class="d-flex align-items-start gap-3">
              <span class="step-icon"><i class="bi bi-soundwave"></i></span>
              <div>
                <div class="fw-semibold">2. AI does the work</div>
                <div class="text-secondary small">Vocal removal, lyric sync, and background art &mdash; automatically.</div>
              </div>
            </div>
            <div class="d-flex align-items-start gap-3">
              <span class="step-icon"><i class="bi bi-image"></i></span>
              <div>
                <div class="fw-semibold">3. Pick a background</div>
                <div class="text-secondary small">Choose from 3 unique AI-generated scenes.</div>
              </div>
            </div>
            <div class="d-flex align-items-start gap-3">
              <span class="step-icon"><i class="bi bi-download"></i></span>
              <div>
                <div class="fw-semibold">4. Preview &amp; download</div>
                <div class="text-secondary small">Get a ready-to-sing 1080p MP4.</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
