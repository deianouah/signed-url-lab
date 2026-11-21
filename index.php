<?php
require __DIR__ . '/lib.php';
$user = current_user();
$loginFailed = isset($_GET['err']);
$ttl = (int)cfg('ttl');
?>
<!doctype html>
<html lang="en" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Signed URL Lab</title>
  <style>
    body { font-family: system-ui, "Segoe UI", Tahoma, sans-serif; max-width: 760px; margin: 2rem auto; padding: 0 1rem; line-height: 1.7; }
    form, .card { border: 1px solid #ccc; border-radius: 10px; padding: 1rem; margin: 1rem 0; }
    input, button { font: inherit; padding: .45rem .8rem; margin: .2rem 0; }
    button { cursor: pointer; }
    video { width: 100%; max-width: 640px; background: #000; border-radius: 8px; }
    code { display: block; direction: ltr; text-align: left; word-break: break-all; background: #f3f3f3; padding: .6rem; border-radius: 6px; font-size: .85rem; }
    .err { color: #b00020; }
    .ok { color: #0a7a2f; }
    .muted { color: #666; font-size: .9rem; }
  </style>
</head>
<body>
  <h1>Signed URL Lab</h1>

<?php if ($user === null): ?>
  <form method="post" action="login.php">
    <h3>Sign In</h3>
    <?php if ($loginFailed): ?><p class="err">Invalid login credentials.</p><?php endif; ?>
    <div><input name="username" placeholder="Username" required></div>
    <div><input name="password" type="password" placeholder="Password" required></div>
    <button type="submit">Sign In</button>
    <p class="muted">
      Test accounts:<br>
      <b>sub</b> / sub123 - subscribed<br>
      <b>free</b> / free123 - not subscribed
    </p>
  </form>

<?php else: ?>
  <div class="card">
    Welcome <b><?= htmlspecialchars($user['id']) ?></b> -
    <?php if ($user['subscribed']): ?>
      <span class="ok">Active subscription</span>
    <?php else: ?>
      <span class="err">Not subscribed</span>
    <?php endif; ?>
    - <a href="logout.php">Sign Out</a>
  </div>

  <button id="play">Request Signed URL and Play Video</button>
  <p id="status" class="muted"></p>

  <video id="video" controls playsinline></video>

  <div class="card">
    <b>Signed URL (for testing with external tools):</b>
    <code id="url">- Not requested yet -</code>
    <p class="muted">Valid for <?= $ttl ?> seconds. Paste the URL into curl, IDM, or yt-dlp and inspect the result.</p>
  </div>

  <script>
    const video = document.getElementById('video');
    const statusEl = document.getElementById('status');
    const urlEl = document.getElementById('url');
    let timer = null;

    const messages = {
      401: 'You must sign in first.',
      403: 'No active subscription, so the link was rejected.',
      404: 'The file was not found.'
    };

    document.getElementById('play').addEventListener('click', async () => {
      clearInterval(timer);
      statusEl.className = 'muted';
      statusEl.textContent = 'Requesting signed URL...';
      try {
        const r = await fetch('link.php?f=video.mp4', { credentials: 'same-origin' });
        const data = await r.json();
        if (!r.ok) {
          statusEl.className = 'err';
          statusEl.textContent = 'HTTP ' + r.status + ' — ' + (messages[r.status] || data.error) + ' (' + data.error + ')';
          urlEl.textContent = '- No signed URL issued -';
          video.removeAttribute('src');
          video.load();
          return;
        }
        urlEl.textContent = data.url;
        video.src = data.url;
        video.play().catch(() => {});

        let left = data.expires_in;
        const tick = () => {
          statusEl.className = left > 0 ? 'ok' : 'err';
          statusEl.textContent = left > 0
            ? 'Signed URL issued. Expires in ' + left + ' seconds.'
            : 'The signed URL has expired. Any new request using it (including video seeking) will return 403.';
          if (left <= 0) clearInterval(timer);
          left--;
        };
        tick();
        timer = setInterval(tick, 1000);
      } catch (e) {
        statusEl.className = 'err';
        statusEl.textContent = 'Connection error: ' + e.message;
      }
    });
  </script>
<?php endif; ?>
</body>
</html>
