# HandLock Care Center

🩹 `HandLock Care Center` 是一个用于洗纹身术后护理与客户恢复管理的自托管 WordPress 插件。

它最初服务于诊所运营场景，把客户疗程管理、恢复期指引、移动端登录、知识库 / FAQ 管理、以及治疗前后照片对比整合到同一个插件里。

这个仓库是该项目停止运营后的开源净化版本。发布前已经移除了生产导出数据、客户资料、部署专用地址以及其他不适合公开的内部内容。

## ✨ 项目概览

如果你需要一个基于 WordPress 的恢复管理门户，这个项目可以作为参考或二次开发基础。它主要覆盖：

- 客户账号与疗程记录管理
- 术后不同阶段的护理内容展示
- 常见问题 / 内部知识库内容维护
- App 相关登录会话与令牌刷新流程
- 治疗前后照片存储与对比
- 插件自有数据的备份与恢复

## 🚀 快速开始

```bash
git clone https://github.com/CNkeysmo/handlock-care-center.git
cd handlock-care-center
```

将本目录复制到你的 WordPress 插件目录：

```text
wp-content/plugins/handlock-care-center
```

然后在 WordPress 后台启用插件，并创建一个包含以下 shortcode 的页面：

```text
[hlcc_care_center]
```

## 🧩 这个项目能做什么

这个插件面向诊所或运营方，用来搭建围绕洗纹身恢复流程的客户门户，包括：

- 创建和管理客户账号
- 创建疗程记录与恢复时间线
- 在前端展示分阶段术后护理指引
- 管理常见客户问题对应的 FAQ / Wiki 内容
- 管理移动端登录状态与 token 刷新逻辑
- 存储、查看和对比治疗照片
- 备份与恢复插件自身数据

## 📦 运行要求

- WordPress
- PHP 7.4 或以上
- MySQL 或 MariaDB

## 🔧 本地安装

### 方式 1：直接复制插件目录

1. 将当前文件夹复制到 `wp-content/plugins/handlock-care-center`
2. 在 WordPress 后台启用插件
3. 创建一个页面，并插入 shortcode `[hlcc_care_center]`
4. 按你的部署环境补充后台配置，例如 Android APK 下载地址等可选项

### 方式 2：开发时使用软链接

```bash
ln -s /absolute/path/to/handlock-care-center /path/to/wordpress/wp-content/plugins/handlock-care-center
```

之后照常在 WordPress 后台启用即可。

## 📁 目录结构

- `assets/`：前端、后台、图标、manifest 等静态资源
- `includes/`：插件主要 PHP 源码
- `data/`：可公开的草稿资料或模板内容
- `scripts/`：维护或运营辅助脚本

## 📌 当前状态

这是一个已经停止内部运营、现转为公开参考的项目版本。

- 代码可以被继续研究、复用与扩展
- 不承诺持续的产品化维护
- 仍可用于整理思路、二次开发或提交清理性质的改进

## 🔐 开源说明

为了适合公开发布，当前仓库已经做过一轮净化处理：

- `data/` 目录不包含生产环境 SQL 导出
- 默认下载地址已留空，需要你按自己的部署环境配置
- 仓库不包含客户资料、治疗照片、运营备份等真实业务数据
- 如需复用 logo、字体、水印、头像或其他素材，请自行确认再分发权利

## 🤝 贡献说明

欢迎提交与以下方向相关的改进：

- 文档整理
- 代码清理
- 可移植性优化
- 明显 bug 修复

提交 Pull Request 前建议先确认：

1. 改动范围清晰，便于 review
2. 不要提交私有数据、备份文件、SQL 导出或凭据
3. 如有 WordPress、PHP 或数据库前置假设，请写明

详细说明可见 [CONTRIBUTING.md](CONTRIBUTING.md)。

## 📄 许可证

本项目使用 `GPL-2.0-or-later` 许可证。

这意味着：

- 你可以使用、学习、修改和再分发这份代码
- 你发布的衍生版本需要遵循 GPL 兼容的开源方式
- 项目按“无担保”方式提供

完整许可证内容见 [LICENSE](LICENSE)。
