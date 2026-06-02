<?php
namespace HLCC\Frontend;

if (!defined('ABSPATH')) exit;

/**
 * 自检提示模块（三重点精简版）
 *
 * 说明：
 *  - 每天只输出三个自检重点：
 *      1）清洁 / 用药；
 *      2）疼痛 / 不适监测；
 *      3）碰水 / 生活方式。
 *  - title 内会带 emoji，body 内用 <span class="hlcc-selfcheck-important"> 包住红色重点词。
 *  - day_index 以术后第几天为基准（0 为当天），超过配置范围时会使用该阶段的默认文案。
 */
class SelfcheckTips
{
    /**
     * 获取指定天数的自检提示（始终返回 3 条）。
     *
     * @param int $day_index 术后第几天（从 0 开始）
     * @return array<int,array{title:string,body:string}>
     */
    public static function get_for_day(int $day_index): array
    {
        if ($day_index < 0) {
            $day_index = 0;
        }

        if ($day_index <= 12) {
            return self::tips_for_day_0_12($day_index);
        }

        if ($day_index <= 20) {
            return self::tips_for_day_13_20($day_index);
        }

        if ($day_index <= 30) {
            return self::tips_for_day_21_30($day_index);
        }

        if ($day_index <= 45) {
            return self::tips_for_day_31_45($day_index);
        }

        if ($day_index <= 60) {
            return self::tips_for_day_46_60($day_index);
        }

        return self::tips_for_day_60_plus($day_index);
    }

    /**
     * Day 0–12：按天输出更细致的三重点。
     *
     * @param int $day
     * @return array<int,array{title:string,body:string}>
     */
    private static function tips_for_day_0_12(int $day): array
    {
        $map = [
            0 => [
                [
                    'title' => '🟥 清洁 & 用药 · 只能按压不能擦',
                    'body'  => '今天是治疗当天，创口非常脆弱，换药时只能用干净纱布轻轻<span class="hlcc-selfcheck-important">按压吸走药水和渗液，绝对不能擦、不能抹、不能来回拖动</span>。'
                ],
                [
                    'title' => '🟥 疼痛 & 不适 · 刺痛发热多属正常',
                    'body'  => '治疗区域在前两天出现刺痛、发热感通常属<span class="hlcc-selfcheck-important hlcc-imp-pain">正常炎症反应</span>，如疼痛<span class="hlcc-selfcheck-important hlcc-imp-pain">突然明显加重或伴随发烧、大面积红肿</span>，请尽快联系我们评估。'
                ],
                [
                    'title' => '🟥 碰水 & 生活方式 · 治疗区域绝对避水',
                    'body'  => '今天身体其他部位可以快速淋浴，但<span class="hlcc-selfcheck-important">治疗区域必须完全避开任何水、汗水和蒸汽</span>，严禁泡澡、桑拿、蒸汽房。'
                ],
            ],
            1 => [
                [
                    'title' => '🟥 清洁 & 用药 · 按压方式继续保持',
                    'body'  => '创面仍然很新鲜，换药时继续使用<span class="hlcc-selfcheck-important">按压式清洁</span>，只吸走旧药和渗液，不要来回抹擦或刮走结痂。'
                ],
                [
                    'title' => '🟥 疼痛 & 不适 · 酸胀感在可接受范围',
                    'body'  => '今天可能会有持续酸胀或轻微刺痛，只要在<span class="hlcc-selfcheck-important">可忍受范围且每天有逐渐缓解</span>，一般属于正常恢复过程。'
                ],
                [
                    'title' => '🟥 碰水 & 生活方式 · 治疗区继续不能碰水',
                    'body'  => '身体可以正常清洁，但<span class="hlcc-selfcheck-important">治疗区域继续保持干燥</span>，不要让洗澡水、汗水或护肤品直接接触。'
                ],
            ],
            2 => [
                [
                    'title' => '🟧 清洁 & 用药 · 出现薄薄痂皮属正常',
                    'body'  => '部分客人今天开始出现薄薄的痂皮，换药时仍然只<span class="hlcc-selfcheck-important hlcc-imp-friction">轻轻按压清洁，绝对不要抠、不要擦</span>。'
                ],
                [
                    'title' => '🟧 疼痛 & 不适 · 彩色/疤痕区可能更酸痛',
                    'body'  => '洗彩色纹身、清洗彩色眉毛以及疤痕修复区域在 D2–D3 可能会<span class="hlcc-selfcheck-important">突然感觉更酸痛或发胀</span>，多属正常，但如疼痛剧烈或无法入睡请尽快联系我们。'
                ],
                [
                    'title' => '🟧 碰水 & 生活方式 · 继续避免热水和出汗',
                    'body'  => '治疗区域仍然<span class="hlcc-selfcheck-important hlcc-imp-water">绝对不能碰水</span>，运动流汗、蒸桑拿、泡温泉都会增加色沉风险，建议暂时避免。'
                ],
            ],
            3 => [
                [
                    'title' => '🟧 清洁 & 用药 · 结痂初成要温柔',
                    'body'  => '痂皮逐渐固定，换药时只需<span class="hlcc-selfcheck-important hlcc-imp-friction">点按吸走旧药</span>，不要试图把药膏完全抹干净，更不要擦掉痂皮。'
                ],
                [
                    'title' => '🟧 疼痛 & 不适 · 轻微痒/紧绷感开始出现',
                    'body'  => '轻微发痒或绷紧感是皮肤在收紧，属正常反应，记得<span class="hlcc-selfcheck-important">不要抓、不要挠</span>，以免拉裂创口。'
                ],
                [
                    'title' => '🟧 碰水 & 生活方式 · 睡觉翻身要避免挤压',
                    'body'  => '今天睡觉时注意<span class="hlcc-selfcheck-important">不要压着治疗区域</span>，尤其是身体部位要避免与床单、衣物大面积摩擦或被挤压；如在眉部，则避免用手臂遮住或压脸睡。'
                ],
            ],
            4 => [
                [
                    'title' => '🟨 清洁 & 用药 · 初步结痂期继续按压',
                    'body'  => '进入较稳定的结痂期，清洁方式依然是<span class="hlcc-selfcheck-important">按压代替擦拭</span>，不要用力把药膏抹干净。'
                ],
                [
                    'title' => '🟨 疼痛 & 不适 · 痒感增加但不能抓',
                    'body'  => '很多人会感觉更痒，这说明皮肤在修复，请<span class="hlcc-selfcheck-important">只轻轻点按周围，不要抓挠或用力摩擦</span>。'
                ],
                [
                    'title' => '🟨 碰水 & 生活方式 · 如考虑更换蓝冠请先咨询',
                    'body'  => '部分客人会在 D4–D5 考虑更换为蓝冠愈创膏，如有此计划请<span class="hlcc-selfcheck-important">先联系我们确认适合与否</span>。'
                ],
            ],
            5 => [
                [
                    'title' => '🟨 清洁 & 用药 · 结痂更稳定但仍不能抠',
                    'body'  => '伤口表面更干爽，结痂看起来结实，也<span class="hlcc-selfcheck-important">绝对不能用手抠或摩擦</span>，清洁仍然是轻按吸走旧药即可。'
                ],
                [
                    'title' => '🟨 疼痛 & 不适 · 酸胀减轻但要留意异常',
                    'body'  => '大部分人酸胀感会比前几天轻，如反而<span class="hlcc-selfcheck-important">变得更痛、更胀或出现渗液增多</span>，请立即联系我们。'
                ],
                [
                    'title' => '🟨 碰水 & 生活方式 · 继续避免出汗和摩擦',
                    'body'  => '运动、厚被子睡觉、长时间闷热都会让区域大量出汗，建议<span class="hlcc-selfcheck-important">尽量保持干爽、避免闷汗</span>。'
                ],
            ],
            6 => [
                [
                    'title' => '🟨 清洁 & 用药 · 自然脱痂期越轻越好',
                    'body'  => '部分痂皮边缘开始翘起，属于自然脱痂过程，清洁时<span class="hlcc-selfcheck-important">只轻按不推、不搓</span>，让它自然脱落。'
                ],
                [
                    'title' => '🟨 疼痛 & 不适 · 轻微痒/紧绷属于正常修复',
                    'body'  => '痒感和紧绷感仍可能存在，只要<span class="hlcc-selfcheck-important">没有刀割样剧痛或大量渗液</span>，一般是正常表现。'
                ],
                [
                    'title' => '🟨 碰水 & 生活方式 · 仍然禁止治疗区碰水',
                    'body'  => '即使痂皮开始脱落，<span class="hlcc-selfcheck-important">治疗区域依然不能直接碰水</span>，洗澡时继续避开该区域。'
                ],
            ],
            7 => [
                [
                    'title' => '🟨 清洁 & 用药 · 大面积脱痂前夕要耐心',
                    'body'  => '部分区域会出现大片干痂，换药时<span class="hlcc-selfcheck-important hlcc-imp-friction">不要用力撕掉或抠掉</span>，只点按清洁即可。'
                ],
                [
                    'title' => '🟨 疼痛 & 不适 · “好痒想抓”要靠忍',
                    'body'  => '这一阶段感觉痒是因为新皮在长，<span class="hlcc-selfcheck-important hlcc-imp-friction">千万不要抓</span>，可以轻轻拍打周边或转移注意力。'
                ],
                [
                    'title' => '🟨 碰水 & 生活方式 · 睡觉衣物尽量宽松',
                    'body'  => '如治疗区域在身体部位，避免穿太紧、太硬的衣物摩擦该部位，睡觉时可选择<span class="hlcc-selfcheck-important hlcc-imp-friction">柔软宽松棉质</span>衣物；若在眉部，可留意不要让发箍、帽檐或眼罩长期压着伤口。'
                ],
            ],
            8 => [
                [
                    'title' => '🟧 清洁 & 用药 · 脱痂加速期更要温柔',
                    'body'  => '大范围开始脱痂时，清洁只需<span class="hlcc-selfcheck-important hlcc-imp-friction">轻轻按压吸走药膏</span>，不要顺手把痂皮推走。'
                ],
                [
                    'title' => '🟧 疼痛 & 不适 · 边缘发红多属正常',
                    'body'  => '痂皮脱落处边缘略红属正常，如出现<span class="hlcc-selfcheck-important">明显破皮渗血或黄绿色分泌物</span>，请立即联系我们。'
                ],
                [
                    'title' => '🟧 碰水 & 生活方式 · 仍然不可浸泡/游泳',
                    'body'  => '此阶段仍然<span class="hlcc-selfcheck-important">禁止泡澡、游泳和温泉</span>，避免新生皮长时间浸泡变皱、变软。'
                ],
            ],
            9 => [
                [
                    'title' => '🟩 清洁 & 用药 · 大部分痂已脱落',
                    'body'  => '多数区域已经脱痂，表面看起来较平滑，清洁仍旧<span class="hlcc-selfcheck-important">轻按清洁，不用来回擦洗</span>。'
                ],
                [
                    'title' => '🟩 疼痛 & 不适 · 颜色偏深/偏淡都是正常过渡',
                    'body'  => '刚脱痂的新生皮颜色会<span class="hlcc-selfcheck-important">暂时偏深或偏淡</span>，属于过渡期，不必过度紧张。'
                ],
                [
                    'title' => '🟩 碰水 & 生活方式 · 治疗区域仍禁直接冲水',
                    'body'  => '虽然表面看似平滑，但在 D11 前<span class="hlcc-selfcheck-important">治疗区域仍然不能直接用水冲洗</span>，洗澡时继续避开。'
                ],
            ],
            10 => [
                [
                    'title' => '🟩 清洁 & 用药 · 结痂七八成脱落',
                    'body'  => '今天大多数痂皮已经脱落七八成，清洁时继续<span class="hlcc-selfcheck-important">轻按而非擦拭</span>，不要用力揉搓新生皮。'
                ],
                [
                    'title' => '🟩 疼痛 & 不适 · 可以拍照给我们评估',
                    'body'  => '如果你感觉伤口已经比较稳定，欢迎<span class="hlcc-selfcheck-important">拍照发给我们</span>，由专业人员评估恢复情况。'
                ],
                [
                    'title' => '🟩 碰水 & 生活方式 · 仍视为“治疗区绝对避水期”',
                    'body'  => '尽管你会感觉差不多好了，但在 D11 前<span class="hlcc-selfcheck-important">治疗区域仍属于绝对不能碰水阶段</span>，身体其他部位可以正常洗澡。'
                ],
            ],
            11 => [
                [
                    'title' => '🟩 清洁 & 用药 · 用手心轻按即可',
                    'body'  => '大部分区域已稳定，清洁方式仍是<span class="hlcc-selfcheck-important">用手心轻按</span>，不用毛巾或浴球擦洗治疗部位。'
                ],
                [
                    'title' => '🟩 疼痛 & 不适 · 多数人只剩轻微紧绷',
                    'body'  => '一般只会剩下轻微紧绷或偶尔刺痒，如仍有<span class="hlcc-selfcheck-important">明显刺痛或突发肿胀</span>，请和我们联系。'
                ],
                [
                    'title' => '🟩 碰水 & 生活方式 · 再坚持一天不让治疗区碰水',
                    'body'  => '在 D11 结束前，<span class="hlcc-selfcheck-important">治疗区域仍维持完全避水</span>，全身淋浴时继续小心绕开。'
                ],
            ],
            12 => [
                [
                    'title' => '🟢 清洁 & 用药 · 可开始轻度接触微温水',
                    'body'  => 'Day12 起如无特别异常，在我们评估认可后，治疗区域可<span class="hlcc-selfcheck-important">短时间接触微温水</span>进行轻按式清洁。'
                ],
                [
                    'title' => '🟢 疼痛 & 不适 · 主要关注颜色与手感',
                    'body'  => '此阶段多以观察为主，留意<span class="hlcc-selfcheck-important">颜色是否均匀、手感是否平顺</span>，有明显凹凸或色块请拍照咨询。'
                ],
                [
                    'title' => '🟢 碰水 & 生活方式 · 仍严禁热水直接冲洗',
                    'body'  => '即使可以短暂接触水，依然<span class="hlcc-selfcheck-important">禁止热水或高温蒸汽直接冲到治疗区域</span>，以免出现色沉反黑。'
                ],
            ],
        ];

        if (isset($map[$day])) {
            return $map[$day];
        }

        // 超出 0–12 的范围时，默认用 Day12 的提示。
        return $map[12];
    }

    /**
     * Day 13–20：新生皮保护 + 热水高风险期。
     */
    private static function tips_for_day_13_20(int $day): array
    {
        return [
            [
                'title' => '🌱 清洁 & 用药 · 温和清洁为主',
                'body'  => '此阶段以<span class="hlcc-selfcheck-important">温和清洁和保湿</span>为主，可用手心配合微温水轻按清洗，避免去角质、磨砂或含酸类产品。'
            ],
            [
                'title' => '🌱 疼痛 & 不适 · 颜色变化属过渡期',
                'body'  => '新生皮颜色可能<span class="hlcc-selfcheck-important">看起来偏红、偏深或有色带</span>，多属正常过渡，可拍照记录变化。'
            ],
            [
                'title' => '🔥 碰水 & 生活方式 · 热水蒸汽仍是高风险',
                'body'  => 'Day20 前热水和蒸汽仍然是<span class="hlcc-selfcheck-important">最容易造成色沉反黑</span>的因素，避免长时间淋热水澡、桑拿和蒸汽房。'
            ],
        ];
    }

    
    /**
     * Day 21–30：进入长期护理的起点。
     */
    private static function tips_for_day_21_30(int $day): array
    {
        return [
            [
                'title' => '☀️💧🔥 防晒 + 热水 + 高温环境',
                'body'  => '治疗区域仍需注意：<span class="hlcc-selfcheck-important hlcc-imp-sun">持续做好防晒与遮挡</span>，避免长时间暴晒；同时<span class="hlcc-selfcheck-important hlcc-imp-hot">避免任何“洗起来觉得暖”的热水直接冲洗治疗区域（一般 38–40°C 以上）</span>，以及长时间待在<span class="hlcc-selfcheck-important hlcc-imp-hot">桑拿、蒸汽房等高温环境</span>，冬天洗澡也不要久泡和久冲热水。'
            ],
            [
                'title' => '🧬 皮肤自然代谢 · 深浅变化属正常',
                'body'  => '20 天后进入<span class="hlcc-selfcheck-important">皮肤深层代谢期</span>，颜色可能短暂偏深、发黄或出现过渡色，多数属于自然代谢过程，请不要使用去角质、酸类或强效美白产品去「加速」褪色。'
            ],
            [
                'title' => '🍲 生活方式 & 饮食 · 减少热气煎炸负担',
                'body'  => '建议以清淡饮食为主，尽量<span class="hlcc-selfcheck-important">少吃热气煎炸、油腻和辛辣重口味食物</span>，避免过度饮酒、频繁熬夜和剧烈出汗，这些都会增加炎症负担，让恢复变慢。'
            ],
        ];
    }

    /**
     * Day 31–45：巩固阶段，颜色仍在细微变化。
     */
    private static function tips_for_day_31_45(int $day): array
    {
        return [
            [
                'title' => '☀️💧🔥 防晒 + 热水 + 高温环境依然重要',
                'body'  => '即使已经超过 30 天，治疗区域仍然怕<span class="hlcc-selfcheck-important hlcc-imp-hot">任何“洗起来觉得暖”的热水直接冲洗（一般 38–40°C 以上）</span>和<span class="hlcc-selfcheck-important hlcc-imp-hot">长时间高温闷焗</span>，请继续避免久泡、久冲热水澡，并<span class="hlcc-selfcheck-important hlcc-imp-sun">坚持防晒和物理遮挡</span>，减少反黑和色沉的机会。'
            ],
            [
                'title' => '🧬 颜色 & 质感 · 接受渐变过程',
                'body'  => '此阶段皮肤可能出现<span class="hlcc-selfcheck-important">短暂的「回深」「发黄」或局部暗沉</span>，属于颜色重新分布和代谢过程，如有疑虑可以拍照记录，我们可协助判断是否正常。'
            ],
            [
                'title' => '🍲 生活习惯 · 维持清淡与适度运动',
                'body'  => '继续保持<span class="hlcc-selfcheck-important">清淡饮食、适度运动和足够睡眠</span>，避免长期高糖、高油和熬夜；良好习惯有助于维持本次疗程的淡化效果。'
            ],
        ];
    }

    /**
     * Day 46–60：长期维护期，稳住已经取得的效果。
     */
    private static function tips_for_day_46_60(int $day): array
    {
        return [
            [
                'title' => '☀️💧🔥 防晒 + 热水 + 高温环境 · 进入习惯化阶段',
                'body'  => '从现在开始，建议把<span class="hlcc-selfcheck-important hlcc-imp-sun">防晒和遮挡</span>、<span class="hlcc-selfcheck-important hlcc-imp-hot">避免任何“洗起来觉得暖”的热水直接冲洗治疗区域（一般 38–40°C 以上）</span>、以及<span class="hlcc-selfcheck-important hlcc-imp-hot">不长时间待在高温闷热环境</span>当作日常习惯，长期坚持能明显降低色素反弹和反黑风险。'
            ],
            [
                'title' => '🧬 颜色 & 质感 · 建议阶段性回访',
                'body'  => '如果你对目前的淡化程度、边缘柔和度或局部质感仍有疑问，可以<span class="hlcc-selfcheck-important hlcc-imp-photo">拍照发给我们评估</span>，必要时安排复查或下一步治疗计划。'
            ],
            [
                'title' => '🍲 生活方式 · 好习惯帮助效果更稳定',
                'body'  => '长期来看，<span class="hlcc-selfcheck-important">清淡饮食、不熬夜、少抽烟少喝酒</span>，对纹身清洗、洗眉和疤痕管理的帮助，往往比单次治疗更大。'
            ],
        ];
    }

    /**
     * Day 60+：第 60–100 天及之后的长期维稳期。
     */
    private static function tips_for_day_60_plus(int $day): array
    {
        return [
            [
                'title' => '🌤️ 皮肤仍在深层代谢 · 防晒与高温控制要继续',
                'body'  => '距离治疗已经超过 60 天，皮肤仍在进行<span class="hlcc-selfcheck-important">深层色素代谢和结构重塑</span>，请继续<span class="hlcc-selfcheck-important hlcc-imp-sun">做好防晒与遮挡</span>，避免长时间暴晒、<span class="hlcc-selfcheck-important hlcc-imp-hot">任何“洗起来觉得暖”的热水直接冲洗治疗区域（一般 38–40°C 以上）</span>和蒸汽桑拿等高温刺激，以免新皮肤出现反黑或色沉回流。'
            ],
            [
                'title' => '🧬 颜色进入二次代谢期 · 深浅波动多属正常',
                'body'  => '在 60–100 天内，颜色有可能<span class="hlcc-selfcheck-important">短暂变深、偏黄或出现局部暗沉</span>，多数属于正常「二次代谢期」的过渡表现，请避免使用去角质、酸类、美白精华等刺激性产品，给皮肤足够时间自然稳定。'
            ],
            [
                'title' => '🍽️ 生活方式 & 饮食 · 减少煎炸辛辣和暴饮暴食',
                'body'  => '长期效果与生活习惯高度相关，建议保持清淡饮食和规律作息，尽量<span class="hlcc-selfcheck-important">少吃煎炸油腻、辛辣重口味和高糖高脂食物</span>，避免频繁熬夜和过度饮酒，让皮肤在更稳定的状态下慢慢恢复。'
            ],
        ];
    }
}
