<?php
if (!defined('ABSPATH'))
  exit;

// $redirect is provided by Shortcodes::render()
$logout_url = wp_logout_url(home_url('/'));
$is_app_mode = isset($_GET['hlcc_app']) && (string) $_GET['hlcc_app'] === '1';
$android_apk_url = (string) get_option('hlcc_android_apk_url', '');
$android_apk_url = trim($android_apk_url);
?>

<div class="hlcc-wrap<?php echo $is_app_mode ? ' hlcc-wrap-app' : ''; ?>">
  <div class="hlcc-topbar">
    <div class="hlcc-brand">
      <div class="hlcc-brand-line1">HANDLock X</div>
      <div class="hlcc-brand-line2">RECOVERY SYSTEM</div>
    </div>
    <?php if (!$is_app_mode): ?>
      <div class="hlcc-top-actions">
        <a class="hlcc-link" href="<?php echo esc_url($logout_url); ?>">Exit</a>
      </div>
    <?php endif; ?>
  </div>

  <div class="hlcc-card">
    <h2 class="hlcc-h2">客户登录</h2>
    <p class="hlcc-muted">请使用已分配的账号登录护理系统。</p>
    <?php if ($is_app_mode): ?>
      <p class="hlcc-muted" style="margin-top:6px;">已启用 App 模式，登录后将自动保持会话。</p>
    <?php endif; ?>

    <div class="hlcc-login">
      <?php
      $form_html = wp_login_form([
        'echo' => false,
        'remember' => true,
        'redirect' => $redirect ?: home_url('/'),
        'form_id' => 'hlcc-loginform',
        'label_username' => '用户名 / 手机号',
        'label_password' => '密码',
        'label_remember' => '记住我',
        'label_log_in' => '登录',
      ]);

      $app_mode_hidden = $is_app_mode ? '<input type="hidden" name="hlcc_app_mode" value="1" />' : '';

      // 为前台客户登录表单增加一个隐藏字段，用于后续在 login_redirect 钩子中识别。
      $form_html = preg_replace(
        '#(<form[^>]*>)#',
        '$1<input type="hidden" name="hlcc_front_login" value="1" />' . $app_mode_hidden,
        $form_html,
        1
      );

      // 如果登录失败（例如密码错误），在同一界面给出简单提示。
      $login_error = isset($_GET['hlcc_login']) ? sanitize_text_field((string) $_GET['hlcc_login']) : '';
      if ($login_error === 'failed') {
        echo '<div class="hlcc-alert hlcc-alert-error">用户名或密码不正确，请重新输入。</div>';
      }

      echo $form_html;
      ?>
    </div>

  </div>

  <?php if ($android_apk_url !== ''): ?>
    <div style="margin:16px 0 12px; text-align:center;">
      <a
        href="<?php echo esc_url($android_apk_url); ?>"
        style="display:inline-flex; align-items:center; justify-content:center; min-width:220px; padding:12px 22px; border-radius:999px; background:#111827; color:#fff; text-decoration:none; font-size:15px; font-weight:600; line-height:1.2; box-shadow:0 8px 20px rgba(17,24,39,.16);">
        下载安卓版本
      </a>
      <p class="hlcc-muted" style="margin-top:8px;">安卓用户可下载安装 HLCC App。</p>
    </div>
  <?php endif; ?>

  <div class="hlcc-muted hlcc-version">行楽客户康复系统HLCC
    v<?php echo esc_html(defined('HLCC_VERSION') ? HLCC_VERSION : '8.3.21'); ?> By GINO</div>
</div>

<!-- HLCC Frontend v<?php echo esc_html(defined('HLCC_VERSION') ? HLCC_VERSION : '8.3.21'); ?> -->

<?php if ($is_app_mode): ?>
  <style>
    .hlcc-wrap-app {
      padding: 12px 12px calc(12px + env(safe-area-inset-bottom));
    }
    .hlcc-wrap-app .hlcc-topbar {
      margin-bottom: 10px;
    }
    .hlcc-wrap-app .hlcc-card {
      border-radius: 14px;
      padding: 16px;
    }
    .hlcc-wrap-app .login-username input,
    .hlcc-wrap-app .login-password input {
      min-height: 44px;
      font-size: 16px;
    }
    .hlcc-wrap-app .button.wp-submit {
      min-height: 44px;
      width: 100%;
      font-size: 16px;
    }
  </style>
<?php endif; ?>
