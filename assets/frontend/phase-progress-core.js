/**
 * 分阶段进度条实时更新模块
 * 
 * 混合式实时更新方案:
 * 1. 页面加载时从服务器获取基准数据
 * 2. 前端每分钟计算一次当前精确进度
 * 3. 零额外服务器负载,零额外流量消耗
 */

(function () {
    'use strict';

    // 基准数据(从服务器传入)
    let baseData = null;
    let updateInterval = null;

    /**
     * 初始化实时进度更新
     * @param {Object} data - 基准数据 {procedure_date, project_key, cycle_days, phase_config}
     */
    window.hlccInitRealtimeProgress = function (data) {
        baseData = data;

        // 初始化折叠功能
        initPhaseCollapse();

        // 立即更新一次
        updateRealtimeProgress();

        // 每秒更新一次 (实时进度感)
        if (updateInterval) clearInterval(updateInterval);
        updateInterval = setInterval(updateRealtimeProgress, 1000);
    };

    /**
     * 更新实时进度
     */
    function updateRealtimeProgress() {
        if (!baseData) return;

        const progress = calculateRealtimeProgress();

        // 更新三个阶段的进度条
        updatePhaseProgress('inflammation', progress.inflammation);
        updatePhaseProgress('scab', progress.scab);
        updatePhaseProgress('recovery', progress.recovery);

        // 更新当天进度提示
        updateDayProgress(progress.currentDay, progress.dayProgress);
    }

    /**
     * 计算实时进度（兼容新旧模式）
     */
    function calculateRealtimeProgress() {
        const now = new Date();
        let procedureTime;

        // 优先使用 datetime（新模式 - 精确时间）
        if (baseData.procedure_datetime) {
            procedureTime = new Date(baseData.procedure_datetime);
        }
        // 降级到 date（旧模式 - 中午锚点，按满24小时递增）
        else if (baseData.procedure_date) {
            procedureTime = new Date(baseData.procedure_date + 'T12:00:00');
        } else {
            return null;
        }

        const exactDays = (now - procedureTime) / (1000 * 60 * 60 * 24);

        const currentDay = Math.floor(exactDays);
        const dayProgress = (exactDays % 1) * 100; // 当天完成度 0-100%

        return {
            currentDay: currentDay,
            dayProgress: dayProgress,
            inflammation: calculatePhaseProgress('inflammation', exactDays),
            scab: calculatePhaseProgress('scab', exactDays),
            recovery: calculatePhaseProgress('recovery', exactDays)
        };
    }

    /**
     * 判断 advance override 是否已被自然天数追上（应自动解除）。
     * 前端独立计算，确保每秒更新时实时检测。
     */
    function isOverrideResolved(exactDays) {
        if (!baseData.phase_override || !baseData.phase_override_at) return false;

        var phases = ['inflammation', 'scab', 'recovery'];
        var overrideIdx = phases.indexOf(baseData.phase_override);
        if (overrideIdx === -1) return false;

        // 推断 override 设置时的系统阶段
        var procStr = baseData.procedure_datetime || (baseData.procedure_date + 'T12:00:00');
        var procedureTime = new Date(procStr.replace(/-/g, '/')); // iOS compat
        var overrideTime = new Date(baseData.phase_override_at.replace(/-/g, '/'));
        var daysAtOverride = Math.max(0, (overrideTime - procedureTime) / (1000 * 60 * 60 * 24));
        var dayAtOverride = Math.floor(daysAtOverride);

        var systemAtOverrideIdx;
        if (dayAtOverride <= 5) systemAtOverrideIdx = 0; // inflammation
        else if (dayAtOverride <= 12) systemAtOverrideIdx = 1; // scab
        else systemAtOverrideIdx = 2; // recovery

        // 只有 advance override 才会自动解除
        var wasAdvance = overrideIdx > systemAtOverrideIdx;
        if (!wasAdvance) return false;

        // 当前系统阶段是否已追上
        var currentDay = Math.floor(exactDays);
        var currentSystemIdx;
        if (currentDay <= 5) currentSystemIdx = 0;
        else if (currentDay <= 12) currentSystemIdx = 1;
        else currentSystemIdx = 2;

        return currentSystemIdx >= overrideIdx;
    }

    /**
     * 计算单个阶段的进度
     */
    function calculatePhaseProgress(phaseName, exactDays) {
        const config = baseData.phase_config[phaseName];
        if (!config) return { progress: 0, status: 'pending' };

        // Handle Override (Priority)
        if (baseData.phase_override && ['inflammation', 'scab', 'recovery'].includes(baseData.phase_override)) {
            const phases = ['inflammation', 'scab', 'recovery'];
            const overrideIdx = phases.indexOf(baseData.phase_override);
            const currentIdx = phases.indexOf(phaseName);

            // 检查 advance override 是否已被自然天数追上
            const resolved = isOverrideResolved(exactDays);

            if (resolved) {
                // Override 已解除：仅对 override 阶段保留 override_at 进度（防止跳变）
                if (currentIdx === overrideIdx && baseData.phase_override_at) {
                    const overrideTime = new Date(baseData.phase_override_at.replace(/-/g, '/')); // iOS compat
                    const now = new Date();
                    const diffDays = Math.max(0, (now - overrideTime) / (1000 * 60 * 60 * 24));

                    let totalDays = 1;
                    if (phaseName === 'inflammation') totalDays = 6;
                    else if (phaseName === 'scab') totalDays = 7;
                    else if (phaseName === 'recovery') totalDays = Math.max(1, baseData.cycle_days - 12);

                    const progress = Math.min(100, (diffDays / totalDays) * 100);
                    return { progress: progress, status: progress >= 100 ? 'completed' : 'active' };
                }
                // 其余阶段：跳过 override 逻辑，fall through 到自然计算
            } else {
                // Override 仍活跃 — 原有逻辑
                // Phase is BEFORE the overridden active phase -> Force Completed (100%)
                if (currentIdx < overrideIdx) {
                    return { progress: 100, status: 'completed' };
                }

                // Phase is AFTER the overridden active phase -> Force Pending (0%)
                if (currentIdx > overrideIdx) {
                    return { progress: 0, status: 'pending' };
                }

                // Phase IS the overridden active phase -> Calculate based on Override Time
                if (currentIdx === overrideIdx) {
                    if (baseData.phase_override_at) {
                        const overrideTime = new Date(baseData.phase_override_at.replace(/-/g, '/')); // iOS compat
                        const now = new Date();
                        const diffTime = now - overrideTime;
                        const diffDays = Math.max(0, diffTime / (1000 * 60 * 60 * 24));

                        let totalDays = 1;
                        if (phaseName === 'inflammation') totalDays = 6;
                        else if (phaseName === 'scab') totalDays = 7;
                        else if (phaseName === 'recovery') totalDays = Math.max(1, baseData.cycle_days - 12);

                        const progress = Math.min(100, (diffDays / totalDays) * 100);
                        return { progress: progress, status: progress >= 100 ? 'completed' : 'active' };
                    } else {
                        return { progress: 0, status: 'active' };
                    }
                }
            }
        }

        const currentDay = Math.floor(exactDays);

        if (phaseName === 'inflammation') {
            // 炎症期: Day 0-5 (6天)
            // Day 0 开始 0%, Day 5 结束 100%
            if (currentDay < 6) {
                const progress = Math.min(100, (exactDays / 6) * 100);
                return { progress: progress, status: 'active' };
            } else {
                return { progress: 100, status: 'completed' };
            }
        } else if (phaseName === 'scab') {
            // 结痂期: Day 6-12 (7天)
            // Day 6 开始 0%, Day 12 结束 100%
            if (currentDay < 6) {
                return { progress: 0, status: 'pending' };
            } else if (currentDay < 13) {
                const daysInPhase = exactDays - 6; // Day 6 = 0, Day 12 = 6
                const progress = Math.min(100, (daysInPhase / 7) * 100);
                return { progress: progress, status: 'active' };
            } else {
                return { progress: 100, status: 'completed' };
            }
        } else if (phaseName === 'recovery') {
            // 康复期: Day 13+
            // Day 13 开始 0%
            const totalDays = Math.max(1, baseData.cycle_days - 12);
            if (currentDay < 13) {
                return { progress: 0, status: 'pending' };
            } else if (currentDay < baseData.cycle_days) {
                const daysInPhase = exactDays - 13; // Day 13 = 0
                const progress = Math.min(100, (daysInPhase / totalDays) * 100);
                return { progress: progress, status: 'active' };
            } else {
                return { progress: 100, status: 'completed' };
            }
        }

        return { progress: 0, status: 'pending' };
    }

    /**
     * 更新阶段进度条UI
     */
    function updatePhaseProgress(phaseName, data) {
        const container = document.querySelector(`[data-phase="${phaseName}"]`);
        if (!container) return;

        // 更新进度条宽度
        const progressBar = container.querySelector('.hlcc-phase-progress-bar');
        if (progressBar) {
            progressBar.style.width = data.progress.toFixed(7) + '%';
        }

        // 更新进度百分比文字 (7位小数, 前2位大字, 后5位小字)
        const progressText = container.querySelector('.hlcc-phase-progress-pct');
        if (progressText) {
            const progressStr = data.progress.toFixed(7);
            // 找到小数点位置
            const dotIndex = progressStr.indexOf('.');
            if (dotIndex !== -1) {
                // 主体: 整数 + 小数点 + 前2位小数
                const mainPart = progressStr.substring(0, dotIndex + 3);
                // 尾部: 后5位小数
                const smallPart = progressStr.substring(dotIndex + 3);
                progressText.innerHTML = `${mainPart}<small>${smallPart}</small>%`;
            } else {
                progressText.textContent = progressStr + '%';
            }
        }

        // 更新状态class
        container.setAttribute('data-status', data.status);

        // 状态变化处理 (自动展开/折叠)
        const oldStatus = container.getAttribute('data-status-prev');
        if (oldStatus !== data.status) {
            handlePhaseStatusChange(container, phaseName, oldStatus, data.status);
            container.setAttribute('data-status-prev', data.status);
        }
    }

    /**
     * 更新当天进度提示
     */
    function updateDayProgress(currentDay, dayProgress) {
        const dayProgressEl = document.querySelector('.hlcc-day-progress-detail');
        if (!dayProgressEl) return;

        // Check if we are in Recovery Phase (Day 13+)
        // Or checking active status. Recovery is Phase 3.
        // baseData.cycle_days contains total days.

        // If current day > 12 (Phase Scab ends at 12), we are in Recovery.
        if (currentDay > 12) {
            const remaining = Math.max(0, baseData.cycle_days - currentDay);
            dayProgressEl.textContent = `剩余 ${remaining} 天`;
        } else {
            // For Inflammation and Scab, show hours passed
            const hoursInDay = Math.floor((dayProgress / 100) * 24);
            dayProgressEl.textContent = `第 ${currentDay} 天, 今日已过 ${hoursInDay} 小时`;
        }
    }

    /**
     * 清理定时器
     */
    window.hlccCleanupRealtimeProgress = function () {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
        }
    };

    /**
     * 初始化阶段折叠功能
     */
    function initPhaseCollapse() {
        const phaseItems = document.querySelectorAll('.hlcc-phase-item[data-collapsible="true"]');

        phaseItems.forEach(item => {
            const header = item.querySelector('.hlcc-phase-header-clickable');
            const phase = item.dataset.phase;

            if (!header || !phase) return;

            // 添加点击事件
            header.addEventListener('click', function () {
                togglePhaseExpanded(item, phase);
            });

            // 恢复之前的展开状态
            const wasExpanded = localStorage.getItem(`hlcc-phase-${phase}-expanded`) === 'true';
            if (wasExpanded) {
                item.classList.add('hlcc-phase-expanded');
            }
        });
    }

    /**
     * 切换阶段展开/折叠状态
     */
    function togglePhaseExpanded(item, phase) {
        const isExpanded = item.classList.toggle('hlcc-phase-expanded');

        // 保存状态到localStorage
        localStorage.setItem(`hlcc-phase-${phase}-expanded`, isExpanded);
    }



    /**
     * 处理阶段状态变化
     */
    function handlePhaseStatusChange(container, phaseName, oldStatus, newStatus) {
        // 如果从pending变为active,自动展开
        if (oldStatus === 'pending' && newStatus === 'active') {
            container.classList.add('hlcc-phase-expanded');
            localStorage.setItem(`hlcc-phase-${phaseName}-expanded`, 'true');
        }

        // 如果从active变为completed,自动折叠
        if (oldStatus === 'active' && newStatus === 'completed') {
            container.classList.remove('hlcc-phase-expanded');
            localStorage.setItem(`hlcc-phase-${phaseName}-expanded`, 'false');
        }

        // 更新collapsible属性: active时不可折叠,其他状态可折叠
        if (newStatus === 'active') {
            container.setAttribute('data-collapsible', 'false');
        } else {
            container.setAttribute('data-collapsible', 'true');
        }
    }


    // Auto-init if data is provided before script load
    if (typeof window.hlccProgressData !== 'undefined') {
        window.hlccInitRealtimeProgress(window.hlccProgressData);
    }
})();
