// HLCC Frontend v9.3.9
const HLCC_VERSION = '9.3.9'; // Synchronized with PHP

/**
 * HandLock Care Center Frontend Boot
 * Includes Auto-Centering for Course Timeline
 */
document.addEventListener("DOMContentLoaded", () => {
  // 1) Auto-Center Active Timeline Item
  const scroller = document.querySelector(".hlcc-timeline-scroller");
  const activeItem = document.querySelector(".hlcc-timeline-card-item.is-active");

  if (scroller && activeItem) {
    const scrollerRect = scroller.getBoundingClientRect();
    const activeRect = activeItem.getBoundingClientRect();
    // Calculate center position
    const scrollLeft =
      activeItem.offsetLeft -
      scroller.offsetLeft -
      scrollerRect.width / 2 +
      activeRect.width / 2;

    // Smooth scroll to center
    setTimeout(() => {
      scroller.scrollTo({
        left: scrollLeft,
        behavior: "smooth",
      });
    }, 100); // Slight delay to ensure layout stability
  }
});

(function () {
  'use strict';

  var DOC = document;
  var BODY = DOC && DOC.body;
  if (!DOC || !BODY) return;

  function purgeRemovedFeatures() {
    [
      '.hlcc-fab-guestbook',
      '#hlcc-fab-guestbook-btn',
      '#hlcc-guestbook-modal',
      '#hlcc-push-v2-fab',
      '#hlcc-push-v2-guide',
      '#hlcc-lottery-fab-standalone',
      '#hlcc-lottery-panel',
      '#hlcc-lottery-win-card'
    ].forEach(function (selector) {
      DOC.querySelectorAll(selector).forEach(function (node) {
        if (node && node.parentNode) {
          node.parentNode.removeChild(node);
        }
      });
    });
  }

  purgeRemovedFeatures();

  try {
    var cleanupObserver = new MutationObserver(function () {
      purgeRemovedFeatures();
    });
    cleanupObserver.observe(BODY, { childList: true, subtree: true });
  } catch (e) { }

  try {
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.getRegistrations().then(function (registrations) {
        registrations.forEach(function (registration) {
          var scriptUrl = '';
          try {
            scriptUrl = String(registration.active && registration.active.scriptURL || registration.scope || '');
          } catch (e) { }
          if (scriptUrl.indexOf('hlcc-sw-v2.js') !== -1 || scriptUrl.indexOf('hlcc_sw_v2=1') !== -1) {
            registration.unregister();
          }
        });
      }).catch(function () { });
    }
  } catch (e) { }

  // ============================================================
  // [CORE] HLCC WORDS MODAL & GLOBAL EVENT DELEGATION (v8.5.3)
  // ============================================================
  window.hlccToggleWordsModal = function (show) {
    var modal = document.getElementById('hlcc-words-modal');
    if (!modal) return;
    if (show) {
      modal.style.display = 'flex';
      modal.setAttribute('aria-hidden', 'false');
      void modal.offsetWidth;
      modal.classList.add('hlcc-active');
      document.body.classList.add('hlcc-lock-scroll');
    } else {
      modal.classList.remove('hlcc-active');
      modal.setAttribute('aria-hidden', 'true');
      setTimeout(function () {
        if (!modal.classList.contains('hlcc-active')) modal.style.display = 'none';
      }, 300);
      document.body.classList.remove('hlcc-lock-scroll');
    }
  };

  function switchWordsTab(targetId) {
    var modal = document.getElementById('hlcc-words-modal');
    if (!modal) return;
    var tabBtns = modal.querySelectorAll('.hlcc-segment-btn');
    var tabs = modal.querySelectorAll('.hlcc-tab-content');

    tabBtns.forEach(function (btn) {
      btn.classList.toggle('active', btn.getAttribute('data-target') === targetId);
    });
    tabs.forEach(function (tab) {
      tab.classList.toggle('active', tab.id === 'hlcc-tab-' + targetId);
    });
  }

  // GLOBAL CLICK DELEGATION
  DOC.addEventListener('click', function (e) {
    var t = e.target;
    if (!t) return;

    // 1. Trigger Words Modal
    if (t.closest('#hlcc-words-trigger-btn')) {
      e.preventDefault();
      window.hlccToggleWordsModal(true);
      return;
    }

    // 2. Close Words Modal
    if (t.closest('.hlcc-modal-close') || t.closest('.hlcc-modal-backdrop')) {
      var wordsModal = t.closest('#hlcc-words-modal');
      if (wordsModal) {
        e.preventDefault();
        window.hlccToggleWordsModal(false);
        return;
      }
    }

    // 3. Switch Tabs
    var tabBtn = t.closest('.hlcc-segment-btn');
    if (tabBtn) {
      var target = tabBtn.getAttribute('data-target');
      if (target) {
        e.preventDefault();
        switchWordsTab(target);
      }
      return;
    }

  }, false);

  // -----------------------------
  // 1) Enforce no-future dates (UI-level clamp)
  // -----------------------------
  try {
    var now = new Date();
    var yyyy = now.getFullYear();
    var mm = String(now.getMonth() + 1).padStart(2, '0');
    var dd = String(now.getDate()).padStart(2, '0');
    var todayStr = yyyy + '-' + mm + '-' + dd;

    function clampDateInput(el) {
      if (!el || el.type !== 'date') return;
      el.setAttribute('max', todayStr);
      var v = el.value;
      if (v && v.length === 10 && v > todayStr) el.value = todayStr;
    }

    DOC.addEventListener('focusin', function (ev) {
      var t = ev.target;
      if (t && t.matches && t.matches('.hlcc-wrap input[type="date"]')) clampDateInput(t);
    }, true);

    DOC.addEventListener('change', function (ev) {
      var t = ev.target;
      if (t && t.matches && t.matches('.hlcc-wrap input[type="date"]')) clampDateInput(t);
    }, true);

    DOC.addEventListener('input', function (ev) {
      var t = ev.target;
      if (t && t.matches && t.matches('.hlcc-wrap input[type="date"]')) clampDateInput(t);
    }, true);

    DOC.addEventListener('blur', function (ev) {
      var t = ev.target;
      if (t && t.matches && t.matches('.hlcc-wrap input[type="date"]')) clampDateInput(t);
    }, true);

    Array.prototype.forEach.call(DOC.querySelectorAll('.hlcc-wrap input[type="date"]'), clampDateInput);
  } catch (e) { /* ignore */ }

  // -----------------------------

  // -----------------------------


  // 3) Mini video window for tutorial videos (best-effort)
  // -----------------------------
  try {
    function isLikelyVideo(url) {
      return /\.(mp4|webm|ogg)(\?|#|$)/i.test(url) || /m3u8(\?|#|$)/i.test(url);
    }

    function closeMini() {
      var ex = DOC.getElementById('hlcc-mini-video');
      if (ex && ex.parentNode) ex.parentNode.removeChild(ex);
    }

    function fmtTime(sec) {
      sec = Math.max(0, Math.floor(sec || 0));
      var m = Math.floor(sec / 60);
      var s = sec % 60;
      return String(m) + ':' + String(s).padStart(2, '0');
    }

    function openMini(url) {
      closeMini();

      var wrap = DOC.createElement('div');
      wrap.className = 'hlcc-mini-video';
      wrap.id = 'hlcc-mini-video';
      wrap.setAttribute('role', 'dialog');
      wrap.setAttribute('aria-label', '视频播放器');

      var head = DOC.createElement('div');
      head.className = 'hlcc-mini-video__head';

      var handle = DOC.createElement('div');
      handle.className = 'hlcc-mini-video__handle';

      var btns = DOC.createElement('div');
      btns.className = 'hlcc-mini-video__btns';

      var close = DOC.createElement('button');
      close.type = 'button';
      close.className = 'hlcc-mini-video__btn';
      close.setAttribute('aria-label', '关闭');
      close.textContent = '×';
      close.addEventListener('click', function () {
        try {
          var vv = DOC.querySelector('#hlcc-mini-video video');
          if (vv) vv.pause();
        } catch (e) { }
        closeMini();
      }, false);
      btns.appendChild(close);

      // Note: No play/pause button by design. Tap video area to toggle.

      var sound = DOC.createElement('button');
      sound.type = 'button';
      sound.className = 'hlcc-mini-video__btn';
      sound.textContent = '静音';
      sound.addEventListener('click', function () {
        try {
          var vv = DOC.querySelector('#hlcc-mini-video video');
          if (!vv) return;
          vv.muted = !vv.muted;
          sound.textContent = vv.muted ? '静音' : '有声';
          if (!vv.muted) { try { vv.play(); } catch (e) { } }
        } catch (e) { }
      }, false);
      btns.appendChild(sound);

      var fs = DOC.createElement('button');
      fs.type = 'button';
      fs.className = 'hlcc-mini-video__btn';
      fs.textContent = '全屏';
      fs.addEventListener('click', function () {
        try {
          var vv = DOC.querySelector('#hlcc-mini-video video');
          if (!vv) return;
          if (vv.requestFullscreen) vv.requestFullscreen();
          else if (vv.webkitEnterFullscreen) vv.webkitEnterFullscreen();
        } catch (e) { }
      }, false);
      btns.appendChild(fs);

      var left = DOC.createElement('div');
      left.className = 'hlcc-mini-video__head-left';
      left.appendChild(handle);

      head.appendChild(left);
      head.appendChild(btns);

      var v = DOC.createElement('video');
      v.controls = false; // keep inline on iOS as much as possible
      v.setAttribute('playsinline', 'playsinline');
      v.setAttribute('webkit-playsinline', 'webkit-playsinline');
      v.muted = true;
      v.setAttribute('muted', 'muted');
      v.preload = 'metadata';
      v.src = url;

      // Allow tap-to-toggle inside video area.
      v.addEventListener('click', function () {
        try {
          if (v.paused) v.play();
          else v.pause();
        } catch (e) { }
      }, false);

      var bar = DOC.createElement('div');
      bar.className = 'hlcc-mini-video__bar';

      var range = DOC.createElement('input');
      range.type = 'range';
      range.min = '0';
      range.max = '1000';
      range.value = '0';
      range.className = 'hlcc-mini-video__range';

      var time = DOC.createElement('div');
      time.className = 'hlcc-mini-video__time';
      time.textContent = '0:00 / 0:00';

      bar.appendChild(range);
      bar.appendChild(time);

      wrap.appendChild(head);
      wrap.appendChild(v);
      wrap.appendChild(bar);
      BODY.appendChild(wrap);

      // Sync progress
      function syncBar() {
        try {
          var d = v.duration || 0;
          var t = v.currentTime || 0;
          if (d > 0) {
            range.value = String(Math.round((t / d) * 1000));
            time.textContent = fmtTime(t) + ' / ' + fmtTime(d);
          } else {
            time.textContent = fmtTime(t) + ' / 0:00';
          }
        } catch (e) { }
      }
      v.addEventListener('timeupdate', syncBar, { passive: true });
      v.addEventListener('loadedmetadata', syncBar, { passive: true });
      v.addEventListener('durationchange', syncBar, { passive: true });

      range.addEventListener('input', function () {
        try {
          var d = v.duration || 0;
          if (d <= 0) return;
          var val = parseInt(range.value, 10);
          if (isNaN(val)) return;
          v.currentTime = (val / 1000) * d;
        } catch (e) { }
      }, { passive: true });

      // Drag to move (best-effort)
      (function enableDrag() {
        var startX = 0, startY = 0, originX = 0, originY = 0;
        var dragging = false;

        function getRect() {
          return wrap.getBoundingClientRect();
        }
        function clamp(n, min, max) {
          return Math.max(min, Math.min(max, n));
        }

        function onDown(ev) {
          // Ignore when clicking buttons
          var tgt = ev.target;
          if (tgt && tgt.closest && tgt.closest('.hlcc-mini-video__btns')) return;
          dragging = true;
          var p = (ev.touches && ev.touches[0]) ? ev.touches[0] : ev;
          startX = p.clientX;
          startY = p.clientY;
          var r = getRect();
          originX = r.left;
          originY = r.top;
          wrap.classList.add('is-dragging');
          ev.preventDefault();
        }
        function onMove(ev) {
          if (!dragging) return;
          var p = (ev.touches && ev.touches[0]) ? ev.touches[0] : ev;
          var dx = p.clientX - startX;
          var dy = p.clientY - startY;
          var x = originX + dx;
          var y = originY + dy;
          var maxX = window.innerWidth - wrap.offsetWidth;
          var maxY = window.innerHeight - wrap.offsetHeight;
          x = clamp(x, 6, maxX - 6);
          y = clamp(y, 6, maxY - 6);
          wrap.style.left = x + 'px';
          wrap.style.top = y + 'px';
          wrap.style.right = 'auto';
          wrap.style.bottom = 'auto';
          ev.preventDefault();
        }
        function onUp() {
          if (!dragging) return;
          dragging = false;
          wrap.classList.remove('is-dragging');
        }

        head.addEventListener('mousedown', onDown, false);
        head.addEventListener('touchstart', onDown, { passive: false });
        DOC.addEventListener('mousemove', onMove, false);
        DOC.addEventListener('touchmove', onMove, { passive: false });
        DOC.addEventListener('mouseup', onUp, false);
        DOC.addEventListener('touchend', onUp, false);
      })();

      try {
        var p = v.play();
        if (p && typeof p.catch === 'function') p.catch(function () { });
      } catch (e) { }
    }

    // Capture-mode click intercept: prevent Safari/Chrome from opening mp4 in native player.
    DOC.addEventListener('click', function (e) {
      var a = e.target && (e.target.closest ? e.target.closest('a.hlcc-video-link') : null);
      if (!a) return;

      var url = a.getAttribute('data-video') || a.getAttribute('href') || '';
      if (!url) return;
      if (!isLikelyVideo(url)) return;

      e.preventDefault();
      if (e.stopPropagation) e.stopPropagation();

      openMini(url);
    }, true);
  } catch (e3) { /* ignore */ }

  // Confirm helpers
  try {
    DOC.addEventListener('submit', function (e) {
      var form = e.target;
      if (!form || !form.getAttribute) return;
      var msg = form.getAttribute('data-hlcc-confirm');
      if (!msg) return;
      if (!confirm(msg)) {
        e.preventDefault();
        e.stopPropagation();
      }
    }, true);
  } catch (e4) { /* ignore */ }


  // -----------------------------
  // 4) [REMOVED] Pinch-zoom gestures enabled for better accessibility
  // -----------------------------
  try {
    // Previously blocked gesturestart and touchmove here.
    // Removed to comply with Web Design Guidelines (allow user zoom).
  } catch (e5) { /* ignore */ }

  // -----------------------------
  // 5) Page-level horizontal swipe to switch "项目"
  // -----------------------------
  try {
    var root = DOC.getElementById('hlcc-root');
    var main = DOC.getElementById('hlcc-main') || root;
    var strip = DOC.querySelector('.hlcc-course-strip');
    if (root && strip) {
      var pills = Array.prototype.slice.call(strip.querySelectorAll('form.hlcc-course-pill'));
      if (pills && pills.length > 1) {
        var touchStartX = 0;
        var touchStartY = 0;
        var trackingSwipe = false;

        function getActiveIndex() {
          for (var i = 0; i < pills.length; i++) {
            var p = pills[i];
            if (p && p.classList && p.classList.contains('is-active')) return i;
          }
          return -1;
        }

        root.addEventListener('touchstart', function (e) {
          if (!e.touches || e.touches.length !== 1) return;
          if (typeof isModalOpen === 'function' && isModalOpen()) return;

          var t = e.target;

          if (t.closest('.hlcc-fab-more')) {
            e.preventDefault();
            try {
              var g = document.getElementById('hlcc-fab-more-group');
              if (!g) return;
              var open = (g.style.display !== 'none');
              if (open) {
                g.style.display = 'none';
                // close all panels when collapsing
                ['hlcc-tutorial-panel', 'hlcc-phase-panel', 'hlcc-compare-panel'].forEach(function (id) {
                  var p = document.getElementById(id);
                  if (p && p.classList) { p.classList.remove('is-open'); p.setAttribute('aria-hidden', 'true'); }
                });
                var cl = document.getElementById('hlcc-fab-clicklayer');
                if (cl) {
                  cl.style.display = 'none';
                  cl.setAttribute('aria-hidden', 'true');
                }
                // 不要在这里移除 hlcc-lock-scroll，因为留言板可能仍处于打开状态
                // 滚动锁定由各自面板自行管理
              } else {
                g.style.display = '';
              }
            } catch (e) { }
            return;
          }
          // (modal removed) no special exclusion for swipe start

          trackingSwipe = true;
          touchStartX = e.touches[0].clientX;
          touchStartY = e.touches[0].clientY;
        }, { passive: true });

        // 让页面跟随手指左右滑动的视觉效果
        var swipeDX = 0;
        root.addEventListener('touchmove', function (e) {
          if (!trackingSwipe) return;
          if (!e.touches || e.touches.length !== 1) return;
          if (typeof isModalOpen === 'function' && isModalOpen()) return;

          var touch = e.touches[0];
          var dx = touch.clientX - touchStartX;
          var dy = touch.clientY - touchStartY;

          // 若纵向位移更大，则视为正常滚动，取消横向滑动跟随
          if (Math.abs(dy) > Math.abs(dx) && Math.abs(dy) > 10) {
            trackingSwipe = false;
            main.style.transition = '';
            main.style.transform = '';
            return;
          }

          swipeDX = dx;
          try {
            main.style.transition = 'none';
            main.style.transform = 'translate3d(' + dx + 'px, 0, 0)';
          } catch (tfErr) { /* ignore */ }
        }, { passive: false });



        root.addEventListener('touchend', function (e) {
          if (!trackingSwipe) return;
          trackingSwipe = false;
          if (!e.changedTouches || !e.changedTouches.length) return;
          if (typeof isModalOpen === 'function' && isModalOpen()) return;

          var touch = e.changedTouches[0];
          var dx = touch.clientX - touchStartX;
          var dy = touch.clientY - touchStartY;

          // Only react to clear horizontal swipes
          if (Math.abs(dx) < 50 || Math.abs(dx) <= Math.abs(dy)) {
            // 没达到切换条件，则轻轻归位
            main.style.transition = 'transform 0.18s ease-out';
            main.style.transform = 'translate3d(0, 0, 0)';
            return;
          }

          var activeIndex = getActiveIndex();
          if (activeIndex < 0) {
            main.style.transition = 'transform 0.18s ease-out';
            main.style.transform = 'translate3d(0, 0, 0)';
            return;
          }

          var targetIndex = activeIndex;
          if (dx < 0 && activeIndex < pills.length - 1) {
            // swipe left => go to next project
            targetIndex = activeIndex + 1;
          } else if (dx > 0 && activeIndex > 0) {
            // swipe right => go to previous project
            targetIndex = activeIndex - 1;
          }

          if (targetIndex === activeIndex) {
            main.style.transition = 'transform 0.18s ease-out';
            main.style.transform = 'translate3d(0, 0, 0)';
            return;
          }

          var targetForm = pills[targetIndex];
          if (!targetForm) {
            main.style.transition = 'transform 0.18s ease-out';
            main.style.transform = 'translate3d(0, 0, 0)';
            return;
          }

          // 成功识别为左右滑动后，增加一个轻微的滑出动画再切换项目
          try {
            var direction = dx < 0 ? -1 : 1;
            main.style.transition = 'transform 0.18s ease-out';
            main.style.transform = 'translate3d(' + (direction * window.innerWidth) + 'px, 0, 0)';
            setTimeout(function () {
              try { targetForm.submit(); } catch (subErr) { /* ignore */ }
            }, 160);
          } catch (animErr) {
            // 如果动画失败，直接提交表单
            try { targetForm.submit(); } catch (subErr2) { /* ignore */ }
          }
        }, { passive: true });

      }
    }
  } catch (e6) { /* ignore */ }




  // -----------------------------
  // 4) Tutorial FAB + floating panel + photo compare入口
  // -----------------------------
  try {
    function hlccOpenTutorial() {
      var panel = DOC.getElementById('hlcc-tutorial-panel');
      if (!panel) return;
      panel.classList.add('is-open');
      if (BODY && BODY.classList) BODY.classList.add('hlcc-lock');
      try { if (window.hlccShowModalCapture) window.hlccShowModalCapture(); } catch (e) { }
    }

    function hlccCloseTutorial() {
      var panel = DOC.getElementById('hlcc-tutorial-panel');
      if (!panel) return;
      panel.classList.remove('is-open');
      if (BODY && BODY.classList) BODY.classList.remove('hlcc-lock');
      // Stop any playing videos when closing the tutorial panel
      try { if (window.hlccStopTutorialVideos) window.hlccStopTutorialVideos(); } catch (e) { }
      // Wait for transition if needed (add timeout if aria-hidden/display logic is added later)
      setTimeout(function () {
        // Placeholder for strict display:none if needed, but for now relies on CSS visibility
        panel.setAttribute('aria-hidden', 'true');
      }, 300);
    }

    // -----------------------------
    // 4.1) Tutorial video mutex: only one video plays at a time
    // - HTML5 <video>: pause others on play
    // - Bilibili <iframe>: use an overlay play button and reload src to simulate pause
    // -----------------------------
    (function initTutorialVideoMutex() {
      function stripAutoplay(u) {
        try {
          return String(u || '').replace(/([?&])autoplay=\d+/g, '$1').replace(/[?&]$/, '');
        } catch (e) { return String(u || ''); }
      }
      function buildBiliUrl(base, autoplay) {
        var u = stripAutoplay(base);
        var sep = (u.indexOf('?') >= 0) ? '&' : '?';
        return u + sep + 'autoplay=' + (autoplay ? 1 : 0) + '&hlcc_ts=' + Date.now();
      }

      function stopAll(panel) {
        panel = panel || DOC.getElementById('hlcc-tutorial-panel');
        if (!panel) return;

        // Pause all HTML5 videos
        try {
          var vids = panel.querySelectorAll('video.hlcc-inline-video');
          if (vids && vids.length) {
            for (var i = 0; i < vids.length; i++) {
              try { vids[i].pause(); } catch (e1) { }
            }
          }
        } catch (e2) { }

        // Reset bilibili iframes to autoplay=0
        try {
          var wraps = panel.querySelectorAll('.hlcc-bili-wrap[data-hlcc-bili="1"]');
          if (wraps && wraps.length) {
            for (var j = 0; j < wraps.length; j++) {
              var w = wraps[j];
              w.classList.remove('is-playing');
              var base = w.getAttribute('data-hlcc-bili-src') || '';
              var iframe = w.querySelector('iframe.hlcc-bilibili-player');
              if (iframe && base) {
                try { iframe.setAttribute('src', buildBiliUrl(base, false)); } catch (e3) { }
              }
            }
          }
        } catch (e4) { }
      }

      function stopBiliOnly(panel) {
        panel = panel || DOC.getElementById('hlcc-tutorial-panel');
        if (!panel) return;
        try {
          var wraps = panel.querySelectorAll('.hlcc-bili-wrap[data-hlcc-bili="1"]');
          if (wraps && wraps.length) {
            for (var j = 0; j < wraps.length; j++) {
              var w = wraps[j];
              w.classList.remove('is-playing');
              var base = w.getAttribute('data-hlcc-bili-src') || '';
              var iframe = w.querySelector('iframe.hlcc-bilibili-player');
              if (iframe && base) {
                try { iframe.setAttribute('src', buildBiliUrl(base, false)); } catch (eB) { }
              }
            }
          }
        } catch (eC) { }
      }

      // Export for other modules (close/mutex manager)
      window.hlccStopTutorialVideos = function () { stopAll(); };

      // Delegate events
      DOC.addEventListener('click', function (ev) {
        var t = ev.target;
        if (!t || !t.closest) return;
        var playBtn = t.closest('.hlcc-bili-playbtn');
        if (playBtn) {
          var panel = DOC.getElementById('hlcc-tutorial-panel');
          var wrap = playBtn.closest('.hlcc-bili-wrap');
          if (!panel || !wrap) return;

          ev.preventDefault();
          ev.stopPropagation();

          // stop others first
          stopAll(panel);

          // start this one
          var base = wrap.getAttribute('data-hlcc-bili-src') || '';
          var iframe = wrap.querySelector('iframe.hlcc-bilibili-player');
          if (iframe && base) {
            wrap.classList.add('is-playing');
            try { iframe.setAttribute('src', buildBiliUrl(base, true)); } catch (e5) { }
          }
          return;
        }
      }, true);

      // HTML5 video mutex
      DOC.addEventListener('play', function (ev) {
        var v = ev.target;
        if (!v || !v.classList || !v.classList.contains('hlcc-inline-video')) return;
        var panel = DOC.getElementById('hlcc-tutorial-panel');
        if (!panel) return;

        // pause other videos
        try {
          var vids = panel.querySelectorAll('video.hlcc-inline-video');
          for (var i = 0; i < vids.length; i++) {
            if (vids[i] !== v) {
              try { vids[i].pause(); } catch (e6) { }
            }
          }
        } catch (e7) { }

        // stop bilibili iframes too (without pausing HTML5 videos)
        try { stopBiliOnly(panel); } catch (e8) { }
      }, true);
    })();

    function hlccOpenComparePanel() {
      var panel = DOC.getElementById('hlcc-compare-panel');
      if (!panel) return;
      panel.classList.add('is-open');
      panel.setAttribute('aria-hidden', 'false');
      if (BODY && BODY.classList) BODY.classList.add('hlcc-lock');
      try { if (window.hlccShowModalCapture) window.hlccShowModalCapture(); } catch (e) { }
    }

    function hlccCloseComparePanel() {
      var panel = DOC.getElementById('hlcc-compare-panel');
      if (!panel) return;
      panel.classList.remove('is-open');
      // Wait for transition (300ms) before hiding accessible state or locking
      setTimeout(function () {
        panel.setAttribute('aria-hidden', 'true');
        // Ensure display:none is NOT applied via class removal if CSS handles visibility
      }, 300);
      if (BODY && BODY.classList) BODY.classList.remove('hlcc-lock');
    }

    // Export closers for global modal manager
    window.hlccCloseTutorial = hlccCloseTutorial;
    window.hlccOpenTutorial = hlccOpenTutorial;
    window.hlccCloseComparePanel = hlccCloseComparePanel;
    window.hlccOpenComparePanel = hlccOpenComparePanel;
    // Direct listeners on compare FAB (better for mobile Safari)
    try {
      var compareFab = DOC.querySelector('.hlcc-fab-compare');
      if (compareFab) {
        var hlccToggleCompare = function (e) {
          if (e && e.preventDefault) e.preventDefault();
          var cPanel = DOC.getElementById('hlcc-compare-panel');
          if (cPanel && cPanel.classList && cPanel.classList.contains('is-open')) {
            hlccCloseComparePanel();
          } else {
            if (window.hlccCloseAllPanels) window.hlccCloseAllPanels('compare');
            hlccOpenComparePanel();
          }
        };
        compareFab.addEventListener('click', hlccToggleCompare, false);
        compareFab.addEventListener('touchend', hlccToggleCompare, false);
      }
    } catch (eFab) { /* ignore */ }



    var hlccFabLastTouch = 0;

    function hlccHandleFabEvent(e) {
      var t = e.target;
      if (!t || !t.closest) return;
      var g = DOC.getElementById('hlcc-fab-more-group');

      function hlccCloseMoreFabGroup() {
        if (!g) return;
        var isOpenNow = g.classList && g.classList.contains('is-open');
        if (!isOpenNow) return;

        g.classList.remove('is-open');
        // Wait for anim (300ms) then hide display to prevent clicks
        setTimeout(function () {
          if (!g.classList.contains('is-open')) g.style.display = 'none';
        }, 320);

        if (window.hlccCloseAllPanels) window.hlccCloseAllPanels();
        var cl = DOC.getElementById('hlcc-fab-clicklayer');
        if (cl) {
          cl.style.display = 'none';
          cl.setAttribute('aria-hidden', 'true');
        }
        // 不要在这里移除 hlcc-lock-scroll，因为留言板可能仍处于打开状态
        // 滚动锁定由各自面板自行管理
      }

      if (g && g.classList && g.classList.contains('is-open')) {
        if (t.closest('#hlcc-fab-clicklayer')) {
          e.preventDefault();
          hlccCloseMoreFabGroup();
          return;
        }
        if (!t.closest('.hlcc-fab')) {
          hlccCloseMoreFabGroup();
          return;
        }
      }


      // Toggle from FAB - more (show/hide 3 FABs)
      if (t.closest('.hlcc-fab-more')) {
        e.preventDefault();
        try {
          if (!g) return;

          var isOpen = g.classList.contains('is-open'); // Check class, not display

          if (isOpen) {
            // -- CLOSE --
            hlccCloseMoreFabGroup();
          } else {
            // -- OPEN --
            g.style.display = 'block'; // Ensure it's in layout
            // Force reflow
            window.requestAnimationFrame(function () {
              window.requestAnimationFrame(function () {
                g.classList.add('is-open');
              });
            });

            // Open clicklayer logic if needed
            var cl = DOC.getElementById('hlcc-fab-clicklayer');
            if (cl) {
              cl.style.display = 'block';
              cl.setAttribute('aria-hidden', 'false');
            }
          }
        } catch (err) { }
        return;
      }

      // Toggle from FAB - tutorial
      if (t.closest('.hlcc-fab-tutorial')) {
        e.preventDefault();
        var panel = DOC.getElementById('hlcc-tutorial-panel');
        if (panel && panel.classList && panel.classList.contains('is-open')) {
          hlccCloseTutorial();
        } else {
          if (window.hlccCloseAllPanels) window.hlccCloseAllPanels('tutorial');
          hlccOpenTutorial();
        }
        return;
      }


      // Close button inside tutorial panel
      if (t.closest('[data-hlcc-close="tutorial"]')) {
        e.preventDefault();
        hlccCloseTutorial();
        return;
      }

      // Close button inside photo compare panel
      if (t.closest('[data-hlcc-close="compare"]')) {
        e.preventDefault();
        hlccCloseComparePanel();
        return;
      }

      // Switch main photo from gallery thumb
      if (t.closest('.hlcc-compare-thumb')) {
        var btn = t.closest('.hlcc-compare-thumb');
        var gallery = btn.closest('.hlcc-compare-gallery');
        if (gallery) {
          var mainImg = gallery.querySelector('[data-hlcc-compare-main]');
          var mainLabel = gallery.querySelector('[data-hlcc-compare-label]');
          var url = btn.getAttribute('data-hlcc-url');
          var label = btn.getAttribute('data-hlcc-label');
          if (mainImg && url) {
            mainImg.setAttribute('src', url);
            // Fix for wp_get_attachment_image: clear srcset so src takes effect
            if (mainImg.hasAttribute('srcset')) mainImg.removeAttribute('srcset');
          }
          if (mainLabel && label) mainLabel.textContent = label;
          var allThumbs = gallery.querySelectorAll('.hlcc-compare-thumb');
          if (allThumbs && allThumbs.length) {
            for (var i = 0; i < allThumbs.length; i++) {
              allThumbs[i].classList.remove('is-active');
            }
          }
          btn.classList.add('is-active');
        }
        return;
      }


      // Open / close inline QR for adding treatment photos
      if (t.closest('[data-hlcc-open-qr="compare"]')) {
        if (e && e.preventDefault) e.preventDefault();
        var qrBox = DOC.querySelector('[data-hlcc-qr-inline="compare"]');
        if (qrBox && qrBox.classList) {
          qrBox.classList.add('is-open');
          qrBox.setAttribute('aria-hidden', 'false');
          try {
            qrBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
          } catch (err) { }
        }
        return;
      }

      if (t.closest('[data-hlcc-close-qr="compare"]')) {
        if (e && e.preventDefault) e.preventDefault();
        var qrBox2 = DOC.querySelector('[data-hlcc-qr-inline="compare"]');
        if (qrBox2 && qrBox2.classList) {
          qrBox2.classList.remove('is-open');
          qrBox2.setAttribute('aria-hidden', 'true');
        }
        return;
      }

      // Click / tap overlay background of photo compare panel to close
      var overlay = DOC.getElementById('hlcc-compare-panel');
      if (overlay && overlay.classList && overlay.classList.contains('is-open') && t === overlay) {
        e.preventDefault();
        hlccCloseComparePanel();
        return;
      }

      // Click / tap overlay background of tutorial panel to close (ADDED)
      var tOverlay = DOC.getElementById('hlcc-tutorial-panel');
      if (tOverlay && tOverlay.classList && tOverlay.classList.contains('is-open') && t === tOverlay) {
        e.preventDefault();
        hlccCloseTutorial();
        return;
      }
    }

    DOC.addEventListener('click', function (e) {
      if (hlccFabLastTouch && Date.now && (Date.now() - hlccFabLastTouch) < 500) {
        return;
      }
      hlccHandleFabEvent(e);
    }, true);

    DOC.addEventListener('touchend', function (e) {
      hlccFabLastTouch = Date.now ? Date.now() : 0;
      hlccHandleFabEvent(e);
    }, true);

  } catch (e7) { /* ignore */ }
  // -----------------------------
  // 5) Phase switch FAB + double warning + soft progress guard
  // -----------------------------
  try {
    var hlccPhaseStep = 1;

    function hlccGetCurrentProgress() {
      try {
        var span = DOC.querySelector('.hlcc-progress-pct');
        if (!span) return null;
        var m = span.textContent && span.textContent.match(/(\d+)/);
        return m ? parseInt(m[1], 10) : null;
      } catch (e) {
        return null;
      }
    }

    function hlccShowToast(message) {
      try {
        var container = DOC.querySelector('.hlcc-toast-container');
        if (!container) {
          container = DOC.createElement('div');
          container.className = 'hlcc-toast-container';
          if (BODY) BODY.appendChild(container);
          else DOC.body.appendChild(container);
        }
        var toast = DOC.createElement('div');
        toast.className = 'hlcc-toast';
        toast.textContent = message || '';
        container.appendChild(toast);

        requestAnimationFrame(function () {
          toast.classList.add('hlcc-toast-visible');
        });

        setTimeout(function () {
          toast.classList.remove('hlcc-toast-visible');
          setTimeout(function () {
            if (toast.parentNode === container) {
              container.removeChild(toast);
            }
          }, 250);
        }, 3500);
      } catch (e) { }
    }

    function hlccOpenPhasePanel(step) {
      var panel = DOC.getElementById('hlcc-phase-panel');
      if (!panel) return;
      hlccPhaseStep = step || 1;

      var pill = panel.querySelector('.hlcc-phase-pill');
      var title = panel.querySelector('.hlcc-phase-title');
      var sub = panel.querySelector('.hlcc-phase-sub');
      var yesBtn = panel.querySelector('[data-hlcc-phase="yes"]');

      if (!pill || !title || !yesBtn) return;

      if (hlccPhaseStep === 1) {
        pill.textContent = '⚠️ 警告';
        title.textContent = '行楽技术部门是否通知您可以提前进入下一阶段？';
        if (sub) sub.textContent = '';
        yesBtn.classList.remove('hlcc-btn-danger');
        yesBtn.classList.add('hlcc-btn-primary');
      } else {
        pill.textContent = '⚠️ 二次确认';
        title.textContent = '是否确认您可以提前进入下一阶段？';
        if (sub) {
          sub.textContent = '仅在已获得行楽技术部门确认的情况下才可进行该操作。';
        }
        yesBtn.classList.remove('hlcc-btn-primary');
        yesBtn.classList.add('hlcc-btn-danger');
      }

      panel.classList.add('is-open');
      panel.setAttribute('aria-hidden', 'false');
      if (BODY && BODY.classList) BODY.classList.add('hlcc-lock');
      try { if (window.hlccShowModalCapture) window.hlccShowModalCapture(); } catch (e) { }
    }

    function hlccClosePhasePanel() {
      var panel = DOC.getElementById('hlcc-phase-panel');
      if (!panel) return;
      panel.classList.remove('is-open');

      setTimeout(function () {
        panel.setAttribute('aria-hidden', 'true');
      }, 300);

      if (BODY && BODY.classList) BODY.classList.remove('hlcc-lock');
      hlccPhaseStep = 1;
    }
    window.hlccClosePhasePanel = hlccClosePhasePanel;
    window.hlccOpenPhasePanel = hlccOpenPhasePanel;

    function hlccSubmitPhaseAdvance() {
      var form = DOC.getElementById('hlcc-phase-advance-form');
      if (!form) return;
      var pct = hlccGetCurrentProgress();
      var threshold = 60;

      if (pct !== null && pct < threshold) {
        hlccShowToast('当前阶段恢复进度未达建议标准（低于 ' + threshold + '%），请确认已获得行楽技术部门指导后再进行提前切换。');
      }

      var modeInput = form.querySelector('#hlcc-phase-mode');
      if (modeInput) modeInput.value = 'advance';
      form.submit();
    }

    function hlccSubmitPhaseReset() {
      var form = DOC.getElementById('hlcc-phase-advance-form');
      if (!form) return;
      var modeInput = form.querySelector('#hlcc-phase-mode');
      if (modeInput) modeInput.value = 'auto';
      form.submit();
    }

    DOC.addEventListener('click', function (e) {
      var t = e.target;
      if (!t || !t.closest) return;

      // Open / toggle from stage FAB
      if (t.closest('.hlcc-fab-stage')) {
        e.preventDefault();
        var panel = DOC.getElementById('hlcc-phase-panel');
        if (panel && panel.classList && panel.classList.contains('is-open')) {
          hlccClosePhasePanel();
        } else {
          if (window.hlccCloseAllPanels) window.hlccCloseAllPanels('phase');
          hlccOpenPhasePanel(1);
        }
        return;
      }

      // NO button (both steps)
      if (t.closest('#hlcc-phase-panel [data-hlcc-phase="no"]')) {
        e.preventDefault();
        hlccClosePhasePanel();
        return;
      }

      // RESET to system phase
      if (t.closest('#hlcc-phase-panel [data-hlcc-phase="reset"]')) {
        e.preventDefault();
        hlccClosePhasePanel();
        hlccSubmitPhaseReset();
        return;
      }

      // YES button
      if (t.closest('#hlcc-phase-panel [data-hlcc-phase="yes"]')) {
        e.preventDefault();
        if (hlccPhaseStep === 1) {
          hlccOpenPhasePanel(2);
        } else {
          hlccClosePhasePanel();
          hlccSubmitPhaseAdvance();
        }
        return;
      }

      // Click on overlay background (outside inner card) closes panel
      var panelEl = DOC.getElementById('hlcc-phase-panel');
      if (panelEl && panelEl.classList.contains('is-open') && t === panelEl) {
        e.preventDefault();
        hlccClosePhasePanel();
        return;
      }
    }, true);
  } catch (e8) { /* ignore */ }


  // -----------------------------
  // 5.5) Global modal mutex manager (tutorial/phase/compare)
  // -----------------------------
  try {
    var CAP_ID = 'hlcc-modal-capture';
    function ensureCapture() {
      var cap = DOC.getElementById(CAP_ID);
      if (!cap) {
        cap = DOC.createElement('div');
        cap.id = CAP_ID;
        cap.className = 'hlcc-modal-capture';
        DOC.body.appendChild(cap);
        cap.addEventListener('click', function (ev) {
          // click outside closes all
          if (window.hlccCloseAllPanels) window.hlccCloseAllPanels();
        }, false);
      }
      return cap;
    }

    function isOpen(id) {
      var el = DOC.getElementById(id);
      return !!(el && el.classList && el.classList.contains('is-open'));
    }

    function updateCapture() {
      try {
        var cap = ensureCapture();
        var any = isOpen('hlcc-tutorial-panel') || isOpen('hlcc-compare-panel') || isOpen('hlcc-phase-panel');
        cap.style.display = any ? 'block' : 'none';
      } catch (e) { }
    }
    window.hlccUpdateModalCapture = updateCapture;

    window.hlccCloseAllPanels = function (except) {
      // except: 'tutorial'|'phase'|'compare' (optional)
      try {
        if (except !== 'tutorial' && window.hlccCloseTutorial && isOpen('hlcc-tutorial-panel')) window.hlccCloseTutorial();
      } catch (e) { }
      try {
        if (except !== 'compare' && window.hlccCloseComparePanel && isOpen('hlcc-compare-panel')) window.hlccCloseComparePanel();
      } catch (e) { }
      try {
        if (except !== 'phase' && window.hlccClosePhasePanel && isOpen('hlcc-phase-panel')) window.hlccClosePhasePanel();
      } catch (e) { }
      // update capture visibility
      updateCapture();
    };

    // When any panel opens, show capture (transparent, no dim)
    window.hlccShowModalCapture = function () {
      try {
        var cap = ensureCapture();
        cap.style.display = 'block';
      } catch (e) { }
    };
    window.hlccHideModalCaptureIfNone = function () {
      try { updateCapture(); } catch (e) { }
    };

  } catch (eCap) { /* ignore */ }



  // -----------------------------
  // 6) Prevent double-tap zoom on HLCC area
  // -----------------------------
  try {
    var lastTouchEnd = 0;
    DOC.addEventListener('touchend', function (e) {
      var root = DOC.getElementById('hlcc-root');
      if (!root || !e.target || !e.target.closest) return;
      if (!e.target.closest('#hlcc-root')) return;

      var now = Date.now();
      if (now - lastTouchEnd <= 350) {
        e.preventDefault();
      }
      lastTouchEnd = now;
    }, true);
  } catch (e9) { /* ignore */ }

})();

// ---------------------------------------------------------
// 5) Details/Summary Accordion Animation (Course More, Why Care, SelfCheck)
//    Now using requestAnimationFrame to ensure smooth open.
// ---------------------------------------------------------
(function () {
  try {
    document.addEventListener('click', function (e) {
      if (!e.target) return;
      var summary = e.target.closest('summary');
      if (!summary) return;
      var details = summary.parentElement;
      if (!details || details.tagName !== 'DETAILS') return;

      // Whitelist classes
      if (!details.classList.contains('hlcc-why-details') &&
        !details.classList.contains('hlcc-course-more') &&
        !details.classList.contains('hlcc-selfcheck-details')) return;

      e.preventDefault();

      if (details.open) {
        // --- CLOSING ---
        // 1. Add is-closing to trigger animation
        details.classList.add('is-closing');
        // 2. Remove is-open
        details.classList.remove('is-open');

        // 3. Wait for animation (350ms) then clean up
        setTimeout(function () {
          if (details.classList.contains('is-closing')) {
            details.removeAttribute('open');
            details.classList.remove('is-closing');
          }
        }, 360);

      } else {
        // --- OPENING ---
        // 1. Set open=true
        details.setAttribute('open', '');

        // 2. Add is-open with delay/RAF to ensure transition triggers
        window.requestAnimationFrame(function () {
          setTimeout(function () {
            // Force reflow just in case
            void details.offsetHeight;
            details.classList.add('is-open');
          }, 20);
        });
      }
    }, false);
  } catch (e) { console.error(e); }
})();


// -----------------------------
// 8) [ADDED] Compare Panel Toggle (from inline onclick refactor)
// -----------------------------
(function () {
  try {
    // We use a small timeout or wait for load to ensure DOM is ready, 
    // though boot.js usually runs deferred.
    var initToggle = function () {
      var btn = document.getElementById('hlcc-fab-compare-toggle');
      if (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          try {
            var p = document.getElementById('hlcc-compare-panel');
            if (!p || !p.classList) return;
            var isOpen = p.classList.contains('is-open');
            if (isOpen) {
              p.classList.remove('is-open');
              p.setAttribute('aria-hidden', 'true');
            } else {
              p.classList.add('is-open');
              p.setAttribute('aria-hidden', 'false');
            }
          } catch (err) { }
        });
      }
    };

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initToggle);
    } else {
      initToggle();
    }
  } catch (e) { }

  /* 
   * Team Message Modal Logic is now handled inline in care-center.php 
   * to strictly avoid caching and loading issues.
   */

})();
/**
 * v7.9.0: 系统留言编辑功能
 */

// 打开系统留言编辑模态框
function hlccEditSystemMessage(systemId) {
  const messageEl = document.querySelector(`.hlcc-gm-item[data-id="${systemId}"]`);
  if (!messageEl) return;

  const contentEl = messageEl.querySelector('.hlcc-gm-content');
  const currentContent = contentEl ? contentEl.innerText.trim() : '';

  document.getElementById('hlcc-system-edit-id').value = systemId;
  document.getElementById('hlcc-system-edit-textarea').value = currentContent;
  document.getElementById('hlcc-system-edit-title').textContent =
    systemId === -1 ? '编辑客户留言' : '编辑功能更新';

  document.getElementById('hlcc-system-edit-modal').style.display = 'block';
}

// 关闭系统留言编辑模态框
function hlccCloseSystemEditModal() {
  document.getElementById('hlcc-system-edit-modal').style.display = 'none';
  document.getElementById('hlcc-system-edit-textarea').value = '';
  document.getElementById('hlcc-system-edit-id').value = '';
}
