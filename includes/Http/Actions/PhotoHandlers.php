<?php
namespace HLCC\Http\Actions;

use HLCC\Support\Security;
use HLCC\Data\Repositories\TreatmentPhotoRepository;

if (!defined('ABSPATH'))
    exit;

/**
 * 照片对比相关的 HTTP 处理器
 * 
 * 从 PostHandlers.php 拆分，包含治疗照片上传、对比图生成等
 * @since 8.9.0
 */
final class PhotoHandlers
{
    /**
     * 注册照片相关的 Hooks
     */
    public static function register(): void
    {
        add_action('admin_post_hlcc_upload_treatment_photo', [self::class, 'upload_treatment_photo']);
        add_action('admin_post_hlcc_generate_treatment_compare', [self::class, 'generate_treatment_compare']);
        add_action('admin_post_hlcc_update_treatment_photo', [self::class, 'update_treatment_photo']);
        add_action('admin_post_hlcc_delete_treatment_photo', [self::class, 'delete_treatment_photo']);
        add_action('admin_post_hlcc_move_treatment_photo', [self::class, 'move_treatment_photo']);
        add_action('admin_post_hlcc_save_watermarks', [self::class, 'save_watermarks']);
    }

    /**
     * 上传治疗照片
     */
    public static function upload_treatment_photo(): void
    {
        Security::require_cap('edit_posts');
        Security::require_post();
        Security::verify_nonce('hlcc_upload_treatment_photo');

        $user_id = (int) ($_POST['user_id'] ?? 0);
        $course_id = (int) ($_POST['course_id'] ?? 0);

        if (!$user_id) {
            wp_die('缺少用户信息');
        }
        if (empty($_FILES['hlcc_photo']['name'])) {
            wp_die('请上传图片文件');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $file = $_FILES['hlcc_photo'];
        $overrides = ['test_form' => false];
        $movefile = wp_handle_upload($file, $overrides);

        if (isset($movefile['error'])) {
            wp_die('上传失败：' . esc_html($movefile['error']));
        }

        $filetype = wp_check_filetype($movefile['file'], null);
        $attachment = [
            'post_mime_type' => $filetype['type'],
            'post_title' => sanitize_file_name(wp_basename($movefile['file'])),
            'post_content' => '',
            'post_status' => 'inherit',
        ];
        $attach_id = wp_insert_attachment($attachment, $movefile['file']);
        if (is_wp_error($attach_id)) {
            wp_die('创建附件失败：' . esc_html($attach_id->get_error_message()));
        }
        $attach_data = wp_generate_attachment_metadata($attach_id, $movefile['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);

        $repo = new TreatmentPhotoRepository();
        $now = current_time('mysql');
        $repo->add($user_id, $course_id, (int) $attach_id, $now);

        $redirect = admin_url('admin.php?page=hlcc-photo-compare&user_id=' . $user_id . '&course_id=' . $course_id . '&photo_saved=1');
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * 生成治疗对比图
     */
    public static function generate_treatment_compare(): void
    {
        Security::require_cap('edit_posts');
        Security::require_post();
        Security::verify_nonce('hlcc_generate_treatment_compare');

        $user_id = (int) ($_POST['user_id'] ?? 0);
        $course_id = (int) ($_POST['course_id'] ?? 0);
        $orientation = isset($_POST['orientation']) && $_POST['orientation'] === 'vertical' ? 'vertical' : 'horizontal';

        if (!$user_id) {
            wp_die('缺少用户信息');
        }

        $repo = new TreatmentPhotoRepository();

        // 收集手动勾选的图片 ID
        $selected_ids = [];
        if (!empty($_POST['photo_ids']) && is_array($_POST['photo_ids'])) {
            foreach ($_POST['photo_ids'] as $pid) {
                $pid = (int) $pid;
                if ($pid > 0) {
                    $selected_ids[] = $pid;
                }
            }
            $selected_ids = array_values(array_unique($selected_ids));
        }

        // 准备用于生成对比图的图片记录
        $photos = [];

        if ($selected_ids && count($selected_ids) >= 2) {
            // 根据当前疗程的顺序，过滤出勾选的记录（保证时间线顺序）
            $list = $repo->list_for_course($user_id, $course_id, 200);
            if ($list) {
                $wanted = array_flip($selected_ids);
                foreach ($list as $row) {
                    $id = (int) $row['id'];
                    if (isset($wanted[$id])) {
                        $photos[] = $row;
                    }
                }
            }
        } else {
            // 没有足够勾选，则自动选用首张 + 最新一张
            $pair = $repo->first_and_latest($user_id, $course_id);
            if ($pair) {
                $photos = [$pair['first'], $pair['latest']];
            }
        }

        if (count($photos) < 2) {
            wp_die('未找到可用的图片对比组合。');
        }

        if (!function_exists('imagecreatetruecolor')) {
            wp_die('服务器不支持 GD 图形库，暂时无法生成对比图片。');
        }

        // 载入图片资源
        $images = [];
        $widths = [];
        $heights = [];

        foreach ($photos as $p) {
            $file = get_attached_file((int) $p['attachment_id']);
            if (!$file || !file_exists($file))
                continue;
            $img = @imagecreatefromstring(file_get_contents($file));
            if (!$img)
                continue;
            $images[] = $img;
            $widths[] = imagesx($img);
            $heights[] = imagesy($img);
        }

        if (count($images) < 2) {
            foreach ($images as $im) {
                imagedestroy($im);
            }
            wp_die('无法读取足够的图片内容。');
        }

        // 按横向 / 竖向两种方式排版
        $canvas = null;
        $canvas_w = 0;
        $canvas_h = 0;
        $dst_x = [];
        $dst_y = [];
        $dst_w = [];
        $dst_h = [];

        if ($orientation === 'vertical') {
            // 竖向对比图：宽度统一，高度叠加
            $width = max($widths);
            $scaled_heights = [];

            foreach ($images as $idx => $img) {
                $w = $widths[$idx];
                $h = $heights[$idx];
                $scale = $width > 0 ? ($width / $w) : 1.0;
                $scaled_heights[$idx] = (int) round($h * $scale);
            }

            $total_height = array_sum($scaled_heights);
            $canvas_w = $width;
            $canvas_h = $total_height;
            $canvas = imagecreatetruecolor($canvas_w, $canvas_h);
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 0, 0, 0));

            $offset_y = 0;
            foreach ($images as $idx => $img) {
                $w = $widths[$idx];
                $h = $heights[$idx];
                $dst_h_val = $scaled_heights[$idx];
                $dst_w_val = $width;
                imagecopyresampled($canvas, $img, 0, $offset_y, 0, 0, $dst_w_val, $dst_h_val, $w, $h);

                $dst_x[$idx] = 0;
                $dst_y[$idx] = $offset_y;
                $dst_w[$idx] = $dst_w_val;
                $dst_h[$idx] = $dst_h_val;

                $offset_y += $dst_h_val;
                imagedestroy($img);
            }
        } else {
            // 横向对比图：高度统一，宽度叠加
            $height = max($heights);
            $scaled_widths = [];

            foreach ($images as $idx => $img) {
                $w = $widths[$idx];
                $h = $heights[$idx];
                $scale = $height > 0 ? ($height / $h) : 1.0;
                $scaled_widths[$idx] = (int) round($w * $scale);
            }

            $total_width = array_sum($scaled_widths);
            $canvas_w = $total_width;
            $canvas_h = $height;
            $canvas = imagecreatetruecolor($canvas_w, $canvas_h);
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 0, 0, 0));

            $offset_x = 0;
            foreach ($images as $idx => $img) {
                $w = $widths[$idx];
                $h = $heights[$idx];
                $dst_w_val = $scaled_widths[$idx];
                $dst_h_val = $height;
                imagecopyresampled($canvas, $img, $offset_x, 0, 0, 0, $dst_w_val, $dst_h_val, $w, $h);

                $dst_x[$idx] = $offset_x;
                $dst_y[$idx] = 0;
                $dst_w[$idx] = $dst_w_val;
                $dst_h[$idx] = $dst_h_val;

                $offset_x += $dst_w_val;
                imagedestroy($img);
            }
        }

        // 从媒体库选择的小水印，只作用在第一张图片上
        $wm_img = null;
        $watermark_id = (int) ($_POST['hlcc_watermark_id'] ?? 0);

        if ($watermark_id > 0) {
            $wm_path = get_attached_file($watermark_id);
            if ($wm_path && file_exists($wm_path)) {
                $wm_img = @imagecreatefrompng($wm_path);
            }
        }

        // 兼容旧版：仍然允许直接上传水印文件
        if (!$wm_img && !empty($_FILES['hlcc_watermark']['tmp_name']) && is_uploaded_file($_FILES['hlcc_watermark']['tmp_name'])) {
            $wm_tmp = $_FILES['hlcc_watermark']['tmp_name'];
            $wm_img = @imagecreatefrompng($wm_tmp);
        }

        if ($wm_img && isset($dst_x[0], $dst_y[0], $dst_w[0], $dst_h[0])) {
            imagealphablending($canvas, true);
            imagesavealpha($canvas, true);
            imagealphablending($wm_img, true);
            imagesavealpha($wm_img, true);

            $wm_w = imagesx($wm_img);
            $wm_h = imagesy($wm_img);

            if ($wm_w > 0 && $wm_h > 0) {
                $first_x = (int) $dst_x[0];
                $first_y = (int) $dst_y[0];
                $first_w = (int) $dst_w[0];
                $first_h = (int) $dst_h[0];

                // 控制水印尺寸：大概占第一张图片短边的 20%，但不放大
                $base = min($first_w, $first_h);
                $target_w = (int) max(30, $base * 0.2);

                if ($wm_w > $target_w) {
                    $scale = $target_w / $wm_w;
                } else {
                    $scale = 1.0;
                }

                if ($scale < 1.0) {
                    $scaled_w = (int) round($wm_w * $scale);
                    $scaled_h = (int) round($wm_h * $scale);
                    $wm_scaled = imagecreatetruecolor($scaled_w, $scaled_h);
                    imagealphablending($wm_scaled, false);
                    imagesavealpha($wm_scaled, true);
                    imagecopyresampled($wm_scaled, $wm_img, 0, 0, 0, 0, $scaled_w, $scaled_h, $wm_w, $wm_h);
                    imagedestroy($wm_img);
                    $wm_img = $wm_scaled;
                    $wm_w = $scaled_w;
                    $wm_h = $scaled_h;
                }

                // 放到第一张图片的正中间（上下左右居中）
                $dst_x_center = (int) round($first_x + ($first_w - $wm_w) / 2);
                $dst_y_center = (int) round($first_y + ($first_h - $wm_h) / 2);

                imagecopy($canvas, $wm_img, $dst_x_center, $dst_y_center, 0, 0, $wm_w, $wm_h);
            }

            imagedestroy($wm_img);
        }

        $filename = 'hlcc-compare-user' . $user_id;
        if ($course_id)
            $filename .= '-course' . $course_id;
        $filename .= '.jpg';

        header('Content-Type: image/jpeg');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        imagejpeg($canvas, null, 90);
        imagedestroy($canvas);
        exit;
    }

    /**
     * 更新治疗照片信息
     */
    public static function update_treatment_photo(): void
    {
        Security::require_cap('edit_posts');
        Security::require_post();
        Security::verify_nonce('hlcc_update_treatment_photo');

        $user_id = (int) ($_POST['user_id'] ?? 0);
        $course_id = (int) ($_POST['course_id'] ?? 0);
        $photo_id = (int) ($_POST['photo_id'] ?? 0);
        $shot_at_raw = (string) ($_POST['shot_at'] ?? '');

        if (!$photo_id || $shot_at_raw === '') {
            wp_die('缺少必要参数。');
        }

        $shot_at = str_replace('T', ' ', $shot_at_raw) . ':00';

        $repo = new TreatmentPhotoRepository();
        $repo->update_shot_at($photo_id, $shot_at);

        $redirect = admin_url('admin.php?page=hlcc-photo-compare&user_id=' . $user_id . '&course_id=' . $course_id);
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * 删除治疗照片
     */
    public static function delete_treatment_photo(): void
    {
        Security::require_cap('edit_posts');
        Security::require_post();
        Security::verify_nonce('hlcc_delete_treatment_photo');

        $user_id = (int) ($_POST['user_id'] ?? 0);
        $course_id = (int) ($_POST['course_id'] ?? 0);
        $photo_id = (int) ($_POST['photo_id'] ?? 0);

        if ($photo_id) {
            $repo = new TreatmentPhotoRepository();
            $row = $repo->get($photo_id);
            if ($row && !empty($row['attachment_id'])) {
                $attachment_id = (int) $row['attachment_id'];
                if ($attachment_id > 0) {
                    // 同时从媒体库删除这张图片
                    wp_delete_attachment($attachment_id, true);
                }
            }
            $repo->delete($photo_id);
        }

        $redirect = admin_url('admin.php?page=hlcc-photo-compare&user_id=' . $user_id . '&course_id=' . $course_id);
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * 移动照片顺序
     */
    public static function move_treatment_photo(): void
    {
        Security::require_cap('edit_posts');
        Security::require_post();
        Security::verify_nonce('hlcc_move_treatment_photo');

        $user_id = (int) ($_POST['user_id'] ?? 0);
        $course_id = (int) ($_POST['course_id'] ?? 0);
        $photo_id = (int) ($_POST['photo_id'] ?? 0);
        $direction = isset($_POST['direction']) && $_POST['direction'] === 'up' ? 'up' : 'down';

        if ($photo_id) {
            $repo = new TreatmentPhotoRepository();
            $repo->move($photo_id, $direction);
        }

        $redirect = admin_url('admin.php?page=hlcc-photo-compare&user_id=' . $user_id);
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * 保存水印设置
     */
    public static function save_watermarks(): void
    {
        Security::require_cap('manage_options');
        Security::require_post();
        Security::verify_nonce('hlcc_save_watermarks');

        $ids = isset($_POST['watermark_ids']) ? (array) $_POST['watermark_ids'] : [];
        $clean = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $clean[$id] = $id;
            }
        }
        $clean_ids = array_values($clean);

        update_option('hlcc_watermark_ids', $clean_ids);

        $back = admin_url('admin.php?page=hlcc-watermarks&saved=1');
        wp_safe_redirect($back);
        exit;
    }
}
