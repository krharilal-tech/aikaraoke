<section class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-9">
      <h1 class="h3 fw-bold mb-1"><i class="bi bi-gear-fill me-2 text-gradient"></i>Settings</h1>
      <p class="text-secondary mb-4">Configure API keys, AI models, and tool paths used by the processing pipeline.</p>

      <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type'] === 'success' ? 'success' : 'danger') ?> mb-4"><?= e($flash['message']) ?></div>
      <?php endif; ?>

      <form method="post" action="<?= e(base_url('settings')) ?>">
        <?= csrf_field() ?>

        <div class="glass-card p-4 mb-4">
          <h6 class="text-uppercase text-secondary small fw-bold mb-3">API Keys</h6>

          <div class="mb-3">
            <label class="form-label" for="OPENAI_API_KEY">OpenAI API Key</label>
            <input type="password" class="form-control form-control-ak" id="OPENAI_API_KEY" name="OPENAI_API_KEY"
                   placeholder="<?= $openaiKeySet ? 'sk-•••••••••••••••••••• (already set — leave blank to keep)' : 'sk-...' ?>" autocomplete="off">
            <div class="form-text">Used for AI background image generation. Stored in <code>.env</code>, never in the database.</div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" for="MUSIXMATCH_API_KEY">Musixmatch API Key <span class="text-secondary">(optional)</span></label>
              <input type="password" class="form-control form-control-ak" id="MUSIXMATCH_API_KEY" name="MUSIXMATCH_API_KEY"
                     placeholder="<?= $musixmatchKeySet ? 'already set — leave blank to keep' : 'optional' ?>" autocomplete="off">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="GENIUS_ACCESS_TOKEN">Genius Access Token <span class="text-secondary">(optional)</span></label>
              <input type="password" class="form-control form-control-ak" id="GENIUS_ACCESS_TOKEN" name="GENIUS_ACCESS_TOKEN"
                     placeholder="<?= $geniusKeySet ? 'already set — leave blank to keep' : 'optional' ?>" autocomplete="off">
            </div>
          </div>
        </div>

        <div class="glass-card p-4 mb-4">
          <h6 class="text-uppercase text-secondary small fw-bold mb-3">AI Models</h6>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" for="image_model">OpenAI Image Model</label>
              <input type="text" class="form-control form-control-ak" id="image_model" name="image_model" value="<?= e($settings['image_model']) ?>">
              <div class="form-text">Generates the 3 background images. Recommended: <code>gpt-image-1</code>.</div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="prompt_model">OpenAI Prompt Model</label>
              <input type="text" class="form-control form-control-ak" id="prompt_model" name="prompt_model" value="<?= e($settings['prompt_model']) ?>">
              <div class="form-text">Writes the 3 image prompts from the song's title/lyrics. A cost-efficient model (e.g. <code>gpt-4o-mini</code>) is plenty for this — it's a short creative-writing task run on every job, not a reasoning task.</div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="demucs_model">Demucs Model</label>
              <input type="text" class="form-control form-control-ak" id="demucs_model" name="demucs_model" value="<?= e($settings['demucs_model']) ?>">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="vocal_bleed_back_percent">Vocal Bleed-back %</label>
              <input type="number" min="0" max="100" class="form-control form-control-ak" id="vocal_bleed_back_percent" name="vocal_bleed_back_percent" value="<?= e($settings['vocal_bleed_back_percent']) ?>">
              <div class="form-text">Blends this much of the removed vocals stem back into the instrumental track. Demucs sometimes misclassifies reedy instruments (e.g. nadaswaram, shehnai) as vocals and drops them entirely — a small amount recovers them, at the cost of making the original singing faintly audible underneath. <code>0</code> disables blending (cleanest instrumental, but misclassified instruments stay lost).</div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="whisperx_model">WhisperX Model</label>
              <input type="text" class="form-control form-control-ak" id="whisperx_model" name="whisperx_model" value="<?= e($settings['whisperx_model']) ?>">
            </div>
            <div class="col-md-6 mb-3">
              <?php
                $languageOptions = [
                    'auto' => 'Auto-detect',
                    'ta' => 'Tamil',
                    'ml' => 'Malayalam',
                    'hi' => 'Hindi',
                    'en' => 'English',
                ];
              ?>
              <label class="form-label" for="transcription_language">Transcription Language</label>
              <select class="form-control form-control-ak" id="transcription_language" name="transcription_language">
                <?php foreach ($languageOptions as $code => $label): ?>
                  <option value="<?= e($code) ?>" <?= $settings['transcription_language'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Only used when WhisperX has to transcribe from scratch (no lyrics provider matched). Auto-detect can misidentify the language on short or noisy audio — pin it if you mostly process one language. Tamil has no dedicated word-alignment model in WhisperX, so Tamil word timing is estimated (evenly spread across each transcribed phrase) rather than precisely aligned; Malayalam and Hindi both align precisely.</div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="max_video_length_seconds">Maximum Video Length (seconds)</label>
              <input type="number" min="30" max="3600" class="form-control form-control-ak" id="max_video_length_seconds" name="max_video_length_seconds" value="<?= e($settings['max_video_length_seconds']) ?>">
            </div>
          </div>
        </div>

        <div class="glass-card p-4 mb-4">
          <h6 class="text-uppercase text-secondary small fw-bold mb-3">Background Images</h6>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" for="background_source">Background Source</label>
              <select class="form-control form-control-ak" id="background_source" name="background_source">
                <option value="openai" <?= $settings['background_source'] === 'openai' ? 'selected' : '' ?>>AI Generated (OpenAI)</option>
                <option value="local" <?= $settings['background_source'] === 'local' ? 'selected' : '' ?>>Local Folder</option>
              </select>
              <div class="form-text">"Local Folder" picks 3 random images from the folder below instead of generating new ones — no OpenAI key, cost, or billing limit involved.</div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="local_backgrounds_path">Local Backgrounds Folder</label>
              <input type="text" class="form-control form-control-ak" id="local_backgrounds_path" name="local_backgrounds_path" value="<?= e($settings['local_backgrounds_path']) ?>">
              <div class="form-text">Drop <code>.png</code>/<code>.jpg</code>/<code>.webp</code> files here. Only used when Background Source is "Local Folder".</div>
            </div>
          </div>
        </div>

        <div class="glass-card p-4 mb-4">
          <h6 class="text-uppercase text-secondary small fw-bold mb-3">YouTube Download</h6>
          <div class="mb-3">
            <label class="form-label" for="youtube_cookies">YouTube Cookies (cookies.txt)</label>
            <textarea class="form-control form-control-ak" id="youtube_cookies" name="youtube_cookies" rows="4" spellcheck="false"><?= e($settings['youtube_cookies']) ?></textarea>
            <div class="form-text">
              The GPU worker now gets past "Sign in to confirm you're not a bot" on its own
              (a proof-of-origin token provider runs alongside it) — leave this blank unless
              that stops working. If it does, log into YouTube in your own browser, export
              cookies in Netscape format using a browser extension (e.g. "Get cookies.txt
              LOCALLY"), and paste the full file contents here. Treat it as a temporary
              backstop, not a fix: a real account's session tokens rotate independently of
              their listed expiry, so a static export goes stale within days regardless of how
              it was exported.
            </div>
          </div>
        </div>

        <div class="glass-card p-4 mb-4">
          <h6 class="text-uppercase text-secondary small fw-bold mb-3">Tool Paths</h6>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" for="ffmpeg_path">FFmpeg Path</label>
              <input type="text" class="form-control form-control-ak" id="ffmpeg_path" name="ffmpeg_path" value="<?= e($settings['ffmpeg_path']) ?>">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="ffprobe_path">FFprobe Path</label>
              <input type="text" class="form-control form-control-ak" id="ffprobe_path" name="ffprobe_path" value="<?= e($settings['ffprobe_path']) ?>">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="python_path">Python Path</label>
              <input type="text" class="form-control form-control-ak" id="python_path" name="python_path" value="<?= e($settings['python_path']) ?>">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="yt_dlp_path">yt-dlp Path</label>
              <input type="text" class="form-control form-control-ak" id="yt_dlp_path" name="yt_dlp_path" value="<?= e($settings['yt_dlp_path']) ?>">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="temp_storage_path">Temporary Storage Path</label>
              <input type="text" class="form-control form-control-ak" id="temp_storage_path" name="temp_storage_path" value="<?= e($settings['temp_storage_path']) ?>">
            </div>
          </div>
        </div>

        <button type="submit" class="gradient-btn btn btn-lg"><i class="bi bi-check2-circle me-2"></i>Save Settings</button>
      </form>
    </div>
  </div>
</section>
