/* NeNe Clear — セットアップウィザード クライアント挙動 (#271)
 *
 * CSP（script-src 'self'）下で動くよう外部ファイル化している（インライン不可）。
 * 役割: パスワード表示切替 / DB アダプタ切替 / ホストプリセット & コントロール
 * パネル図 / 利用形態（single/multi）切替 / 送信時のローディング
 * （実際の疎通確認 + サブステップ進捗アニメーション）。
 */
(function () {
  'use strict';

  var EYE = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 10s3-5.5 8-5.5S18 10 18 10s-3 5.5-8 5.5S2 10 2 10z"/><circle cx="10" cy="10" r="2.4"/></svg>';
  var EYEOFF = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l14 14"/><path d="M8.2 8.3A2.4 2.4 0 0 0 10 12.4M5.5 5.7C3.4 7 2 10 2 10s3 5.5 8 5.5c1.4 0 2.6-.4 3.7-1M16 12.7c1.3-1.3 2-2.7 2-2.7s-3-5.5-8-5.5c-.5 0-1 .05-1.4.13"/></svg>';
  var CHECK = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5l4 4 8-9"/></svg>';
  var XMARK = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M5 5l10 10M15 5L5 15"/></svg>';
  var SPIN = '<span class="spinner"></span>';
  var CHEVRON = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 7.5l5 5 5-5"/></svg>';

  function el(id) { return document.getElementById(id); }

  /* ---------- パスワード表示切替 ---------- */
  Array.prototype.forEach.call(document.querySelectorAll('.pw-eye'), function (btn) {
    btn.addEventListener('click', function () {
      var inp = el(btn.dataset.pw);
      if (!inp) { return; }
      var show = inp.type === 'password';
      inp.type = show ? 'text' : 'password';
      btn.innerHTML = show ? EYEOFF : EYE;
    });
  });

  /* ---------- DB アダプタ切替（mysql / sqlite） ---------- */
  var adapterSelect = el('db_adapter');
  var mysqlFields = el('mysqlFields');
  var sqliteNote = el('sqliteNote');

  function syncAdapter() {
    if (!adapterSelect) { return; }
    var sqlite = adapterSelect.value === 'sqlite';
    if (mysqlFields) {
      if (sqlite) { mysqlFields.setAttribute('hidden', ''); }
      else { mysqlFields.removeAttribute('hidden'); }
    }
    if (sqliteNote) {
      if (sqlite) { sqliteNote.removeAttribute('hidden'); }
      else { sqliteNote.setAttribute('hidden', ''); }
    }
  }
  if (adapterSelect) { adapterSelect.addEventListener('change', syncAdapter); syncAdapter(); }

  /* ---------- ホストプリセット + コントロールパネル図 ---------- */
  var hostInput = el('db_host');
  var cpHost = el('cpHost'), cpDb = el('cpDb'), cpUser = el('cpUser'), cpNote = el('cpNote');

  Array.prototype.forEach.call(document.querySelectorAll('.host-chip'), function (chip) {
    chip.addEventListener('click', function () {
      Array.prototype.forEach.call(document.querySelectorAll('.host-chip'), function (c) {
        c.classList.toggle('on', c === chip);
      });
      var d = chip.dataset;
      if (cpHost) { cpHost.textContent = d.host; }
      if (cpDb) { cpDb.textContent = d.db || 'yourname_clear'; }
      if (cpUser) { cpUser.textContent = d.user || 'yourname'; }
      if (cpNote) { cpNote.innerHTML = d.note + ' 黄色の<b>ホスト名</b>を下のフォームにそのまま貼り付けてください。'; }
      // 「その他」以外は記入例をホスト欄に流し込む（XXX 部分は要編集）。
      if (hostInput && d.id !== 'other') { hostInput.value = d.host; }
    });
  });

  var cpToggle = el('cpToggle'), cpDiagram = el('cpDiagram');
  if (cpToggle && cpDiagram) {
    cpToggle.innerHTML = 'コントロールパネルのどこを見る？' + CHEVRON;
    cpToggle.addEventListener('click', function () {
      var open = cpDiagram.hasAttribute('hidden');
      if (open) { cpDiagram.removeAttribute('hidden'); cpToggle.classList.add('open'); }
      else { cpDiagram.setAttribute('hidden', ''); cpToggle.classList.remove('open'); }
    });
  }

  /* ---------- 利用形態（single/multi） ---------- */
  var singleFields = el('singleFields');

  function syncTenant() {
    var checked = document.querySelector('input[name="tenant_mode"]:checked');
    var mode = checked ? checked.value : 'single';
    Array.prototype.forEach.call(document.querySelectorAll('.opt-card[data-tenant]'), function (card) {
      card.classList.toggle('on', card.getAttribute('data-tenant') === mode);
    });
    if (singleFields) {
      if (mode === 'multi') { singleFields.setAttribute('hidden', ''); }
      else { singleFields.removeAttribute('hidden'); }
    }
  }
  Array.prototype.forEach.call(document.querySelectorAll('input[name="tenant_mode"]'), function (r) {
    r.addEventListener('change', syncTenant);
  });
  syncTenant();

  /* ---------- アップロード取得: 選択ファイル名を表示 ---------- */
  var fileInput = el('payloadFile');
  var drop = el('upDrop');
  var fileName = el('upFileName');
  if (fileInput && drop) {
    fileInput.addEventListener('change', function () {
      if (fileInput.files && fileInput.files.length > 0) {
        drop.classList.add('has-file');
        if (fileName) {
          fileName.textContent = fileInput.files[0].name;
          fileName.removeAttribute('hidden');
        }
      } else {
        drop.classList.remove('has-file');
        if (fileName) { fileName.setAttribute('hidden', ''); }
      }
    });
  }

  /* ---------- 送信時のローディング（疎通確認 + 進捗） ---------- */
  // フォーム別サブステップ。実際の作業（接続・スキーマ・.env）はサーバー側で
  // 1 リクエストにまとまっているため、各行はアニメーション上の段階表現。
  // 成否は POST 応答の data-error から正しく反映する。
  var SUBSTEPS = {
    dbForm: [
      { t: '接続を確認しています', d: 'データベースサーバーへ接続' },
      { t: 'テーブルを作成しています', d: 'スキーマを適用中' },
      { t: '設定を保存しています', d: '.env を書き出し中' }
    ],
    adminForm: [
      { t: '組織と管理者を作成しています', d: 'アカウントを登録中' },
      { t: 'セットアップを完了しています', d: '最終処理中' }
    ],
    acquireForm: [
      { t: 'ファイルをアップロードしています', d: 'ZIP を送信中' },
      { t: 'ハッシュを照合しています', d: 'SHA-256 を検証中' },
      { t: 'アプリを展開しています', d: 'ファイルを配置中' }
    ]
  };
  var STEP_MS = 720;

  var view = el('izView'), loading = el('izLoading');

  function buildSubsteps(key) {
    var ul = el('substeps');
    ul.innerHTML = (SUBSTEPS[key] || SUBSTEPS.dbForm).map(function (s) {
      return '<li data-ss class="ss-pending"><span class="ss-ic"></span>' +
        '<div><div class="ss-t">' + s.t + '</div><div class="ss-d">' + s.d + '</div></div>' +
        '<span class="ss-meta">待機中</span></li>';
    }).join('');
    return ul.querySelectorAll('[data-ss]').length;
  }

  function paint(active, doneAll, failIdx) {
    var lis = el('substeps').querySelectorAll('[data-ss]');
    Array.prototype.forEach.call(lis, function (li, n) {
      var state;
      if (failIdx === n) { state = 'fail'; }
      else if (doneAll || n < active) { state = 'done'; }
      else if (n === active) { state = 'active'; }
      else { state = 'pending'; }
      li.className = state === 'fail' ? 'ss-active' : 'ss-' + state;
      var ic = li.querySelector('.ss-ic'), meta = li.querySelector('.ss-meta');
      if (state === 'fail') { ic.innerHTML = XMARK; ic.style.color = 'var(--danger)'; meta.textContent = '失敗'; }
      else if (state === 'done') { ic.innerHTML = CHECK; meta.textContent = '完了'; }
      else if (state === 'active') { ic.innerHTML = SPIN; meta.textContent = '実行中…'; }
      else { ic.innerHTML = ''; meta.textContent = '待機中'; }
    });
    var bar = el('ldBar');
    if (bar) { bar.style.width = Math.round(((doneAll ? lis.length : active) / lis.length) * 100) + '%'; }
  }

  function render(html) {
    document.open();
    document.write(html);
    document.close();
  }

  function run(form) {
    var total = buildSubsteps(form.id);
    view.setAttribute('hidden', '');
    loading.removeAttribute('hidden');

    var active = 0;
    paint(active, false);
    // 体感のための段階送り（最後の行は応答が返るまで「実行中…」のまま保持）。
    var advancer = setInterval(function () {
      if (active < total - 1) { active++; paint(active, false); }
    }, STEP_MS);

    var minDelay = new Promise(function (r) { setTimeout(r, STEP_MS * total + 350); });
    var request = fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
      .then(function (resp) { return resp.text().then(function (html) { return { resp: resp, html: html }; }); });

    Promise.all([request, minDelay]).then(function (out) {
      clearInterval(advancer);
      var resp = out[0].resp, html = out[0].html;
      var doc = new DOMParser().parseFromString(html, 'text/html');
      var hadError = doc.body && doc.body.getAttribute('data-error') === '1';

      if (hadError) {
        // 接続/検証に失敗 → 進行中の段を「失敗」にしてからエラー画面を表示。
        paint(active, false, active);
        setTimeout(function () { render(html); }, 650);
      } else {
        paint(total, true);
        setTimeout(function () {
          if (resp.redirected) { window.location.href = resp.url; }
          else { render(html); }
        }, 600);
      }
    }).catch(function () {
      clearInterval(advancer);
      window.location.reload();
    });
  }

  ['dbForm', 'adminForm', 'acquireForm'].forEach(function (id) {
    var f = el(id);
    if (f) {
      f.addEventListener('submit', function (e) {
        e.preventDefault();
        run(f);
      });
    }
  });
})();
