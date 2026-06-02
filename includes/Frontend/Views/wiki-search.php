<?php
/**
 * 百科全书前台搜索组件 (精简版)
 * 
 * 只保留搜索栏，轻量级设计
 * 
 * @since 9.0.0
 */

if (!defined('ABSPATH'))
    exit;
?>

<!-- Wiki Search (v9.0.1 精简版) -->
<div class="hlcc-wiki-bar" id="hlcc-wiki-container">
    <div class="hlcc-wiki-search-row">
        <span
            class="hlcc-wiki-label"><?php echo \HLCC\Support\Helpers::get_icon('book-open', 'hlcc-wiki-label-icon'); ?>
            自检百科</span>
        <div class="hlcc-wiki-search-wrap">
            <input type="text" id="hlcc-wiki-search-input" class="hlcc-wiki-input" placeholder="输入关键词搜索护理知识..."
                autocomplete="off" maxlength="100">
            <span class="hlcc-wiki-search-icon" id="hlcc-wiki-search-icon">
                <?php echo \HLCC\Support\Helpers::get_icon('search', 'hlcc-wiki-icon-svg'); ?>
            </span>
            <span class="hlcc-wiki-loading" id="hlcc-wiki-loading" style="display: none;">
                <span class="hlcc-wiki-spinner"></span>
            </span>
        </div>
        <button type="button" class="hlcc-wiki-ask-btn" id="hlcc-wiki-open-request">
            <?php echo \HLCC\Support\Helpers::get_icon('message-circle', 'hlcc-wiki-ask-icon'); ?>
            <span>提问</span>
        </button>
    </div>

    <!-- 搜索建议下拉 -->
    <div class="hlcc-wiki-suggestions" id="hlcc-wiki-suggestions" style="display: none;"></div>
</div>

<!-- 词条详情弹窗 (简洁悬浮窗) -->
<div class="hlcc-wiki-popup" id="hlcc-wiki-modal" style="display: none;">
    <div class="hlcc-wiki-popup-header">
        <h4 class="hlcc-wiki-popup-title" id="hlcc-wiki-modal-title"></h4>
        <button type="button" class="hlcc-wiki-popup-close"
            id="hlcc-wiki-modal-close"><?php echo \HLCC\Support\Helpers::get_icon('x', 'hlcc-wiki-close-icon'); ?></button>
    </div>
    <div class="hlcc-wiki-popup-body" id="hlcc-wiki-modal-body"></div>
</div>

<!-- 问题提交弹窗 (简洁悬浮窗) -->
<div class="hlcc-wiki-popup hlcc-wiki-popup-sm" id="hlcc-wiki-request-modal" style="display: none;">
    <div class="hlcc-wiki-popup-header">
        <h4 class="hlcc-wiki-popup-title">提交你的问题</h4>
        <button type="button" class="hlcc-wiki-popup-close"
            id="hlcc-wiki-request-close"><?php echo \HLCC\Support\Helpers::get_icon('x', 'hlcc-wiki-close-icon'); ?></button>
    </div>
    <div class="hlcc-wiki-popup-body">
        <textarea id="hlcc-wiki-request-input" class="hlcc-wiki-textarea" placeholder="例如：脱痂后皮肤发红正常吗？" maxlength="500"
            rows="2"></textarea>
        <div class="hlcc-wiki-popup-actions">
            <button type="button" class="hlcc-wiki-btn-cancel" id="hlcc-wiki-request-cancel">取消</button>
            <button type="button" class="hlcc-wiki-btn-submit" id="hlcc-wiki-request-submit">提交</button>
        </div>
    </div>
</div>