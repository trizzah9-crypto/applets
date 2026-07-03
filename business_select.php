<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/png" sizes="192x192" href="./Images/logo.png" />

<title>Select Business</title>
<style>
  :root{
    --bg: #f4f7f8;
    --surface: rgba(255,255,255,0.82);
    --surface-strong: #ffffff;
    --text: #163033;
    --muted: #6c7a7d;
    --brand: #027d69;
    --brand-dark: #025649;
    --line: rgba(6, 74, 78, 0.10);
    --shadow: 0 18px 50px rgba(0,0,0,0.08);
    --shadow-soft: 0 8px 18px rgba(0,0,0,0.06);
    --radius: 18px;
  }

  * { box-sizing: border-box; }

  body {
    margin: 0;
    min-height: 100vh;
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    background:
      radial-gradient(circle at top, rgba(2,125,105,0.08), transparent 30%),
      linear-gradient(180deg, #f9fbfb 0%, var(--bg) 100%);
    color: var(--text);
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 40px 18px;
  }

  .page-shell {
    width: 100%;
    max-width: 760px;
  }

  .topbar {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    margin-bottom: 28px;
    text-align: center;
  }

  .logo {
    width: 150px;
    max-width: 42vw;
    height: auto;
    display: block;
    filter: drop-shadow(0 6px 20px rgba(0,0,0,0.08));
  }

  h2 {
    margin: 0;
    font-size: clamp(1.5rem, 3vw, 2.15rem);
    line-height: 1.15;
    font-weight: 800;
    letter-spacing: -0.02em;
    color: #004549;
  }

  .subtitle {
    margin: 0;
    color: var(--muted);
    font-size: 0.98rem;
    max-width: 560px;
  }

  #list {
    width: 100%;
    background: var(--surface);
    border: 1px solid rgba(255,255,255,0.7);
    border-radius: 24px;
    box-shadow: var(--shadow);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    overflow: hidden;
  }

  #list.loading,
  #list.error,
  #list.empty {
    padding: 28px 22px;
    text-align: center;
    font-weight: 600;
    color: var(--muted);
    min-height: 120px;
    display: grid;
    place-items: center;
  }

  #list.error {
    color: #b42318;
  }

  .business-item {
    display: grid;
    grid-template-columns: 72px 1fr auto;
    gap: 16px;
    align-items: center;
    padding: 18px 18px;
    border-bottom: 1px solid var(--line);
    transition: transform 0.18s ease, background-color 0.18s ease, box-shadow 0.18s ease;
    background: rgba(255,255,255,0.55);
  }

  .business-item:last-child {
    border-bottom: none;
  }

  .business-item:hover {
    background: rgba(2, 125, 105, 0.04);
  }

  .business-logo-wrap {
    width: 72px;
    height: 72px;
    border-radius: 18px;
    background: linear-gradient(180deg, rgba(2,125,105,0.10), rgba(2,125,105,0.04));
    border: 1px solid rgba(2,125,105,0.10);
    display: grid;
    place-items: center;
    overflow: hidden;
    flex: 0 0 auto;
    box-shadow: var(--shadow-soft);
  }

  .business-logo {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }

  .business-logo-placeholder {
    width: 100%;
    height: 100%;
    display: grid;
    place-items: center;
    font-weight: 800;
    color: #0e4f4f;
    background: linear-gradient(135deg, rgba(2,125,105,0.12), rgba(2,125,105,0.04));
    letter-spacing: 0.04em;
  }

  .business-info {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 7px;
  }

  .name-row {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
    flex-wrap: wrap;
  }

  .business-name {
    font-size: 1.08rem;
    font-weight: 800;
    color: #023f42;
    line-height: 1.2;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 100%;
  }

  .business-role {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
    color: #085f54;
    background: rgba(2,125,105,0.10);
    border: 1px solid rgba(2,125,105,0.14);
    text-transform: capitalize;
    white-space: nowrap;
  }

  .meta-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
    color: var(--muted);
    font-size: 0.92rem;
  }

  .meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
  }

  .meta-icon {
    width: 16px;
    height: 16px;
    flex: 0 0 16px;
    color: var(--brand);
  }

  .meta-value {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .meta-value a {
    color: var(--brand);
    text-decoration: none;
    font-weight: 600;
  }

  .meta-value a:hover {
    text-decoration: underline;
  }

  .business-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
  }

  button.pick {
    appearance: none;
    border: none;
    background: linear-gradient(180deg, var(--brand), var(--brand-dark));
    color: #fff;
    padding: 11px 18px;
    border-radius: 14px;
    font-weight: 800;
    font-size: 0.95rem;
    cursor: pointer;
    box-shadow: 0 10px 24px rgba(2, 125, 105, 0.22);
    transition: transform 0.18s ease, box-shadow 0.18s ease, opacity 0.18s ease;
    min-width: 96px;
  }

  button.pick:hover {
    transform: translateY(-1px);
    box-shadow: 0 14px 28px rgba(2, 125, 105, 0.26);
  }

  button.pick:active {
    transform: translateY(0);
    opacity: 0.96;
  }

  .empty-link {
    color: var(--brand);
    text-decoration: none;
    font-weight: 700;
  }

  .empty-link:hover {
    text-decoration: underline;
  }

  @media (max-width: 640px) {
    body {
      padding: 24px 14px 34px;
    }

    .business-item {
      grid-template-columns: 62px 1fr;
      grid-template-areas:
        "logo info"
        "actions actions";
      gap: 14px 12px;
      padding: 16px;
    }

    .business-logo-wrap {
      width: 62px;
      height: 62px;
      border-radius: 16px;
    }

    .business-info {
      grid-area: info;
    }

    .business-actions {
      grid-area: actions;
      justify-content: stretch;
    }

    button.pick {
      width: 100%;
      min-width: 0;
    }

    .business-name {
      white-space: normal;
    }

    .meta-item {
      white-space: normal;
    }
  }

  @media (prefers-reduced-motion: reduce) {
    * {
      scroll-behavior: auto !important;
      transition: none !important;
      animation: none !important;
    }
  }
</style>
</head>
<body>
  <div class="page-shell">
    <div class="topbar">
      <img src="Images/logo.png" alt="Logo" class="logo">
      <div>
        <h2>Select a Business to Manage</h2>
       </div>
    </div>

    <div id="list" class="loading">Loading businesses...</div>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    function escapeHtml(str) {
      return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function businessLogoHtml(b) {
      const logo = (b.receipt_logo || '').trim();

      if (logo) {
        return `<img class="business-logo" src="${escapeHtml(logo)}" alt="${escapeHtml(b.name)} logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';">`;
      }

      const initial = (b.name || '?').trim().charAt(0).toUpperCase();
      return `<div class="business-logo-placeholder">${escapeHtml(initial)}</div>`;
    }

    function phoneHtml(phone) {
      if (!phone) {
        return '';
      }
      return `
        <div class="meta-item">
          <svg class="meta-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M7.5 3.5h2.2c.6 0 1.1.4 1.2 1l.7 3.4c.1.5-.1 1.1-.6 1.4l-1.8 1.3c1 2 2.7 3.7 4.7 4.7l1.3-1.8c.3-.5.9-.7 1.4-.6l3.4.7c.6.1 1 .6 1 1.2v2.2c0 .8-.6 1.5-1.4 1.6-8.8 1.1-16-6.1-14.9-14.9.1-.8.8-1.4 1.6-1.4Z" fill="currentColor"/>
          </svg>
          <span class="meta-value">${escapeHtml(phone)}</span>
        </div>
      `;
    }

    $.getJSON('ajax/get_businesses.php', function(res) {
      const $list = $('#list');
      $list.removeClass('loading error empty');

      if (!res || res.status !== 'ok') {
        $list.addClass('error').text((res && res.message) ? res.message : 'Error loading businesses');
        return;
      }

      const bs = Array.isArray(res.businesses) ? res.businesses : [];

      if (bs.length === 0) {
        $list.addClass('empty').html('No businesses yet. <a class="empty-link" href="create_first_business.php">Create one</a>');
        return;
      }

      let html = '';
      bs.forEach(b => {
        html += `
          <div class="business-item" data-id="${escapeHtml(b.id)}" data-role="${escapeHtml(b.role || '')}">
            <div class="business-logo-wrap">
              ${businessLogoHtml(b)}
            </div>

            <div class="business-info">
              <div class="name-row">
                <span class="business-name">${escapeHtml(b.name || 'Unnamed Business')}</span>
                <span class="business-role">${escapeHtml(b.role || 'member')}</span>
              </div>

              <div class="meta-list">
                ${phoneHtml(b.business_phone)}
              </div>
            </div>

            <div class="business-actions">
              <button class="pick" type="button">Open</button>
            </div>
          </div>
        `;
      });

      $list.html(html);
    }).fail(function() {
      $('#list').removeClass('loading').addClass('error').text('Failed to load businesses');
    });

    $(document).on('click', '.pick', function() {
      const $parent = $(this).closest('.business-item');
      const id = $parent.data('id');
      const role = $parent.data('role');

      $.post('ajax/set_business.php', { business_id: id, role: role }, function(r) {
        if (r && r.status === 'ok') {
          location.href = 'dashboard.php';
        } else {
          alert((r && r.message) ? r.message : 'Failed to set business');
        }
      }, 'json').fail(function() {
        alert('Network error. Please try again.');
      });
    });
  </script>
</body>
</html>