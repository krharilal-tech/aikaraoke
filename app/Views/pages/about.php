<section class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-9">

      <h1 class="fw-bold mb-2">About Us</h1>
      <p class="text-secondary mb-5">Who we are and what Karaokai does.</p>

      <div class="glass-card p-4 p-md-5">
        <p>
          Karaokai is based in Thiruvananthapuram, Kerala, India, and operates karaokai.in — a service that turns a
          YouTube video into a ready-to-sing karaoke video.
        </p>

        <h5 class="fw-bold mt-4">What we do</h5>
        <p>
          Paste a YouTube link, optionally pick the song's language, and Karaokai does the rest: it separates the
          vocals from the instrumental, transcribes and time-syncs the lyrics, generates a background visual, and
          renders a finished karaoke video with the lyrics burned in — ready to preview and download, usually within
          minutes.
        </p>

        <h5 class="fw-bold mt-4">Why we built it</h5>
        <p>
          Making a decent karaoke track normally means hunting for a pre-made instrumental that may not exist for
          the exact song you want, or manually removing vocals and timing lyrics yourself. Karaokai automates that
          entire pipeline — AI-based vocal separation, transcription, and lyric synchronization — so anyone can turn
          a song they already have on YouTube into a karaoke video for personal use, without any audio editing
          experience.
        </p>

        <h5 class="fw-bold mt-4">How it works, briefly</h5>
        <ul>
          <li>You submit a YouTube URL</li>
          <li>Vocals are separated from the instrumental using AI-based audio source separation</li>
          <li>Lyrics are fetched from a lyrics database or transcribed directly from the audio when no match is
            found</li>
          <li>A background visual is generated or picked, and the final karaoke video is rendered with synchronized
            lyrics</li>
        </ul>
        <p>
          Pricing is credit-based — see our <a href="<?= e(base_url('pricing')) ?>">Pricing</a> page for details —
          and every new account starts with one free credit to try it out.
        </p>

        <h5 class="fw-bold mt-4">Get in touch</h5>
        <p class="mb-0">
          Questions, feedback, or anything else — see our <a href="<?= e(base_url('contact')) ?>">Contact Us</a>
          page.
        </p>
      </div>

    </div>
  </div>
</section>
