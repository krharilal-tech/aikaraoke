<section class="container py-5" data-job-id="<?= (int) $job['id'] ?>">
  <div class="row justify-content-center">
    <div class="col-lg-9">

      <div class="glass-card p-4 p-md-5 mb-4">
        <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-3 mb-4">
          <img id="jobThumbnail" src="<?= e($job['thumbnail_url'] ?? '') ?>" alt=""
               class="rounded-3 <?= $job['thumbnail_url'] ? '' : 'd-none' ?>" style="width:120px;height:68px;object-fit:cover;">
          <div>
            <h1 class="h4 fw-bold mb-1" id="jobTitle"><?= e($job['title'] ?? 'Processing your video…') ?></h1>
            <div class="text-secondary small" id="jobMeta">
              <?= e($job['channel'] ?? '') ?>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="fw-semibold" id="stageMessage">Preparing…</span>
          <span class="text-secondary small" id="etaText"></span>
        </div>
        <div class="progress progress-ak mb-4">
          <div class="progress-bar" id="progressBar" role="progressbar" style="width: 0%"></div>
        </div>

        <ul class="stage-list mb-4" id="stageList">
          <?php foreach ($stages as $stage): ?>
            <li class="stage-item" data-state="<?= e($stage['state']) ?>">
              <span class="stage-dot"><i class="bi bi-circle"></i></span>
              <span><?= e($stage['label']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>

        <div id="emailNoticeBox" class="alert alert-info small d-flex align-items-center gap-2 mb-4 d-none">
          <i class="bi bi-envelope"></i>
          <span>This can take a while — we'll email you as soon as it's ready, so feel free to close this page.</span>
        </div>

        <div id="errorBox" class="alert alert-danger d-none"></div>

        <div id="completedBox" class="d-none">
          <h6 class="fw-semibold mb-3"><i class="bi bi-check-circle text-success me-1"></i> Your karaoke video is ready!</h6>
          <div id="expiredNotice" class="alert alert-warning d-none">
            <i class="bi bi-clock-history me-1"></i>
            This video was automatically removed 7 days after generation and is no longer available for download.
          </div>
          <div id="videoPlayerBox">
            <video id="karaokePreview" class="karaoke-preview mb-3" controls playsinline></video>
            <div class="d-flex gap-2">
              <a href="#" id="downloadVideoBtn" class="gradient-btn btn btn-lg"><i class="bi bi-download me-2"></i>Download MP4</a>
            </div>
          </div>
        </div>

        <h6 class="text-uppercase text-secondary small fw-bold mt-4 mb-2">Processing Log</h6>
        <div class="log-console" id="logConsole"></div>
      </div>

    </div>
  </div>
</section>
