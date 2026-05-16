<div align="center">

# 🛡️ MotionPay Xboard Complain AutoBan

**收到支付投诉，自动封禁用户 — 保护你的业务不受恶意退款困扰**

[![Xboard Plugin](https://img.shields.io/badge/Xboard-Plugin-6366f1?style=for-the-badge&logo=data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJ3aGl0ZSI+PHBhdGggZD0iTTEyIDJMNCA3djEwbDggNSA4LTVWN2wtOC01eiIvPjwvc3ZnPg==)](https://github.com/cedar2025/Xboard)
[![License: MIT](https://img.shields.io/badge/License-MIT-22c55e?style=for-the-badge)](LICENSE)
[![Version](https://img.shields.io/badge/Version-1.0.0-f59e0b?style=for-the-badge)](config.json)
[![MotionPay](https://img.shields.io/badge/MotionPay-Webhook-0ea5e9?style=for-the-badge)](https://motionpay.net)

[English](#english) · [安装指南](#-安装) · [配置说明](#%EF%B8%8F-配置) · [常见问题](#-常见问题)

</div>

---

## 💡 这个插件解决什么问题？

当你使用 [MotionPay](https://motionpay.net) 作为支付通道时，用户可能会在微信/支付宝发起恶意投诉或退款请求。如果不及时封禁，可能会导致用户滥用（白嫖）您的机场订阅。

**MotionPay Guard** 通过实时接收 MotionPay 的 Webhook 通知，在收到投诉的瞬间自动封禁关联用户，从源头阻止滥用行为。

```
用户发起支付投诉 → MotionPay 推送 Webhook → 插件自动封禁该用户 ✅
```

## ✨ 功能特性

- 🔒 **HMAC-SHA256 签名验证** — 使用时序安全比较，防止伪造请求
- ⏱️ **时间戳防重放** — 自动拒绝超过 5 分钟的过期请求
- ⚡ **投诉即封禁** — 收到投诉通知后毫秒级自动封禁关联用户
- 📋 **完整审计日志** — 每次操作均记录用户信息、投诉原因、订单金额
- 🎛️ **灵活开关** — 可随时开关自动封禁，也可仅记录日志不执行封禁
- 🔌 **即插即用** — 标准 Xboard 插件架构，无需修改任何核心代码

## 📦 安装

### 方式一：Git Clone（推荐）

```bash
cd /path/to/xboard/plugins
git clone https://github.com/MotionNetwork/motionpay-xboard-complain-autoban.git MotionPayWebhook
```

### 方式二：手动下载

1. 下载本仓库的 ZIP 文件
2. 解压后将文件夹重命名为 `MotionPayWebhook`
3. 放入 Xboard 的 `plugins/` 目录

```
plugins/
└── MotionPayWebhook/
    ├── Plugin.php
    ├── config.json
    ├── routes/
    │   └── api.php
    ├── Controllers/
    │   └── WebhookController.php
    └── README.md
```

4. 登录 Xboard 管理后台 → **插件管理** → 找到 **MotionPay Webhook** → **启用**

## ⚙️ 配置

### Step 1: 配置 Xboard 插件

启用插件后，在 Xboard 管理后台的插件设置中配置：

| 配置项 | 说明 | 默认值 |
|--------|------|--------|
| **Webhook 签名密钥** | 与 MotionPay 后台设置的密钥保持一致 | 空 |
| **收到投诉自动封禁用户** | 开启后收到投诉自动封禁关联用户 | ✅ 开启 |
| **收到新订单通知** | 开启后在日志中记录新订单信息 | ❌ 关闭 |

### Step 2: 配置 MotionPay 后台

登录 MotionPay 商户后台 → **个人资料** → **Webhook 通知**：

1. **启用 Webhook** — 打开总开关
2. **Webhook URL** — 填入你的 Xboard 地址：
   ```
   https://你的域名/api/v1/motionpay/webhook
   ```
3. **签名密钥** — 设置密钥（务必与 Xboard 插件配置中一致）
4. **订阅事件** — 勾选 🚨 **投诉通知**
5. 点击 **保存设置**

> ⚠️ **重要**：两端的签名密钥必须完全一致，否则签名验证会失败。

## 🔄 工作流程

```
┌──────────────┐     Webhook POST      ┌──────────────────────┐
│              │ ───────────────────▶  │                      │
│  MotionPay   │   complain.new        │   Xboard Plugin      │
│  支付系统     │   + HMAC签名          │   MotionPay Guard    │
│              │ ◀───────────────────  │                      │
└──────────────┘     HTTP 200 OK       └──────────┬───────────┘
                                                   │
                                          ① 验证签名 (HMAC-SHA256)
                                          ② 防重放检查 (5分钟窗口)
                                          ③ out_trade_no → 匹配订单
                                          ④ 找到关联用户
                                          ⑤ 封禁用户 (banned = 1)
                                          ⑥ 记录审计日志
```

## 📝 日志查看

所有操作自动记录到 Xboard 日志系统：

```bash
# 查看所有 MotionPay Webhook 日志
grep "MotionPay Webhook" storage/logs/laravel-*.log

# 仅查看封禁记录
grep "已自动封禁用户" storage/logs/laravel-*.log
```

**日志示例：**

```log
[2026-05-16 20:30:00] daily.INFO: [MotionPay Webhook] 收到支付投诉
  {"motionpay_trade_no":"20260516203001","xboard_trade_no":"XB202605161234",
   "user_id":123,"user_email":"user@example.com","title":"商品质量问题","money":"99.00"}

[2026-05-16 20:30:00] daily.WARNING: [MotionPay Webhook] 已自动封禁用户
  {"user_id":123,"email":"user@example.com","reason":"支付投诉: 商品质量问题","order_amount":"99.00"}
```

## 🔒 安全架构

| 安全层 | 实现方式 | 说明 |
|--------|----------|------|
| **传输层** | HTTPS | 建议 Webhook URL 使用 HTTPS |
| **认证层** | HMAC-SHA256 | `hash_equals` 时序安全比较，防止侧信道攻击 |
| **防重放** | 时间戳校验 | 拒绝 `timestamp` 与服务器时间差 > 300 秒的请求 |
| **数据校验** | JSON 验证 | 自动拒绝空请求体和无效 JSON |

## ❓ 常见问题

<details>
<summary><b>Q: 封禁错了用户怎么办？</b></summary>

登录 Xboard 管理后台 → 用户管理 → 搜索该用户 → 取消封禁。所有封禁操作均有完整日志可追溯。
</details>

<details>
<summary><b>Q: 如何只记录日志不自动封禁？</b></summary>

在 Xboard 插件设置中关闭「收到投诉自动封禁用户」开关。投诉仍会记录到日志中，方便你手动处理。
</details>

<details>
<summary><b>Q: 签名验证失败？</b></summary>

- 确保 Xboard 插件和 MotionPay 后台的签名密钥完全一致
- 注意密钥前后是否有多余空格
- 检查 Xboard 日志中的详细错误信息
</details>

<details>
<summary><b>Q: 没有收到 Webhook 通知？</b></summary>

1. 确认 MotionPay 后台已开启 Webhook 总开关
2. 确认已勾选「投诉通知」事件
3. 确认 Webhook URL 格式正确（`https://你的域名/api/v1/motionpay/webhook`）
4. 检查服务器防火墙是否允许外部 POST 请求
</details>

<details>
<summary><b>Q: 支持哪些 Webhook 事件？</b></summary>

| 事件 | 说明 | 自动操作 |
|------|------|----------|
| `complain.new` | 新投诉通知 | 自动封禁用户 |
| `order.paid` | 订单支付成功 | 仅记录日志 |
</details>

## 🗂️ 文件结构

```
MotionPayWebhook/
├── Plugin.php              # 插件主类
├── config.json             # 插件配置定义
├── routes/
│   └── api.php             # API 路由注册
├── Controllers/
│   └── WebhookController.php   # Webhook 处理控制器
├── README.md               # 本文档
└── LICENSE                 # MIT 许可证
```

## 🤝 Contributing

欢迎提交 Issue 和 Pull Request！

1. Fork 本仓库
2. 创建你的功能分支 (`git checkout -b feature/amazing-feature`)
3. 提交更改 (`git commit -m 'Add amazing feature'`)
4. 推送分支 (`git push origin feature/amazing-feature`)
5. 创建 Pull Request

## 📄 License

本项目基于 [MIT License](LICENSE) 开源。

---

<div align="center">

**由 [MotionPay](https://motionpay.net) 开发维护**

如果这个插件对你有帮助，请给一个 ⭐ Star！

</div>

---

<a name="english"></a>

## 🇬🇧 English

### What is this?

MotionPay Guard is an Xboard plugin that automatically bans users when a payment complaint is received from MotionPay. It protects your business from malicious refund requests and chargebacks.

### Quick Start

1. Clone to `plugins/MotionPayWebhook/`
2. Enable in Xboard admin → Plugin Management
3. Configure webhook secret (must match MotionPay settings)
4. Set webhook URL in MotionPay: `https://your-domain/api/v1/motionpay/webhook`
5. Subscribe to complaint notifications

### How it works

1. User files a payment complaint on WeChat/Alipay
2. MotionPay sends a `complain.new` webhook to your Xboard
3. Plugin verifies the HMAC-SHA256 signature
4. Matches the order via `out_trade_no` → finds the user
5. Automatically bans the user (`banned = 1`)
6. Logs the action with full audit trail
