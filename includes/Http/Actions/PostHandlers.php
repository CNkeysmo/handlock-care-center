<?php
namespace HLCC\Http\Actions;

if (!defined('ABSPATH'))
    exit;

/**
 * HTTP 处理器统一注册入口
 * 
 * v8.9.0 重构：将原有的 PostHandlers 拆分为多个模块化的 Handler 类
 * 此文件现在作为统一注册入口，调用各个子模块的 register() 方法
 * 
 * 子模块列表：
 * - CourseHandlers     疗程管理（创建/更新/删除/切换）
 * - CustomerHandlers   客户管理（创建/删除/账户设置）
 * - PhotoHandlers      照片对比（上传/删除/生成对比图）
 * - TutorialHandlers   教程管理（标题/步骤 CRUD）
 * - BackupHandlers     备份恢复（生成/验证/恢复）
 * - CareContentHandlers 护理内容（按天/按阶段保存）
 * 
 * @since 8.9.0
 */
final class PostHandlers
{
    /**
     * 注册所有 HTTP 处理器
     * 
     * 调用各个子模块的 register() 方法
     */
    public static function register(): void
    {
        // 疗程管理
        CourseHandlers::register();

        // 客户管理
        CustomerHandlers::register();

        // 照片对比
        PhotoHandlers::register();

        // 教程管理
        TutorialHandlers::register();

        // 备份恢复 - 已移除，使用外部备份方案
        // BackupHandlers::register();

        // 护理内容
        CareContentHandlers::register();

        // 百科全书 (v9.0.0)
        WikiHandlers::register();
    }
}
