<?php

namespace Plugin\MotionPayWebhook\Controllers;

use App\Http\Controllers\PluginController;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WebhookController extends PluginController
{
    /**
     * 处理 MotionPay Webhook 推送
     */
    public function handle(Request $request)
    {
        // 检查插件状态
        if ($error = $this->beforePluginAction()) {
            return response('Plugin disabled', 403);
        }

        // 获取原始请求体
        $rawBody = $request->getContent();
        if (empty($rawBody)) {
            Log::warning('[MotionPay Webhook] 收到空请求体');
            return response('Empty body', 400);
        }

        // 验证签名（密钥未配置时拒绝所有请求）
        $secret = $this->getConfig('webhook_secret', '');
        if (empty($secret)) {
            Log::error('[MotionPay Webhook] 签名密钥未配置，拒绝请求');
            return response('Webhook secret not configured', 500);
        }

        $receivedSignature = $request->header('X-Webhook-Signature', '');
        $expectedSignature = hash_hmac('sha256', $rawBody, $secret);

        if (!hash_equals($expectedSignature, $receivedSignature)) {
            Log::warning('[MotionPay Webhook] 签名验证失败', [
                'received' => substr($receivedSignature, 0, 16) . '...',
                'ip' => $request->ip(),
            ]);
            return response('Invalid signature', 403);
        }

        // 解析 payload
        $payload = json_decode($rawBody, true);
        if (!$payload || !isset($payload['event'])) {
            Log::warning('[MotionPay Webhook] 无效的 JSON 数据');
            return response('Invalid JSON', 400);
        }

        // 时间戳防重放检查（必须包含，5分钟窗口）
        $timestamp = $payload['timestamp'] ?? null;
        if (!$timestamp || abs(time() - (int) $timestamp) > 300) {
            Log::warning('[MotionPay Webhook] 请求缺少时间戳或已过期');
            return response('Request expired', 403);
        }

        // 分发事件
        return match ($payload['event']) {
            'complain.new' => $this->handleComplaint($payload['data'] ?? []),
            'order.paid' => $this->handleOrderPaid($payload['data'] ?? []),
            default => response('Unknown event', 200),
        };
    }

    /**
     * 处理投诉事件 → 自动封禁用户
     */
    private function handleComplaint(array $data)
    {
        $tradeNo = $data['out_trade_no'] ?? '';
        $complainTradeNo = $data['trade_no'] ?? '';
        $title = $data['title'] ?? '';
        $money = $data['money'] ?? '0';

        if (empty($tradeNo)) {
            Log::info('[MotionPay Webhook] 投诉缺少商户订单号，跳过', ['data' => $data]);
            return response('OK', 200);
        }

        // 幂等性检查：同一投诉不重复处理
        $cacheKey = 'motionpay_complain:' . $complainTradeNo;
        if (Cache::has($cacheKey)) {
            return response('OK', 200);
        }
        Cache::put($cacheKey, true, 86400 * 7); // 7天去重

        // 通过商户订单号（Xboard 的 trade_no）查找订单
        $order = Order::where('trade_no', $tradeNo)->first();
        if (!$order) {
            Log::info('[MotionPay Webhook] 未找到关联订单', ['out_trade_no' => $tradeNo]);
            return response('OK', 200);
        }

        $user = User::find($order->user_id);
        if (!$user) {
            Log::info('[MotionPay Webhook] 未找到关联用户', ['user_id' => $order->user_id]);
            return response('OK', 200);
        }

        // 记录投诉日志
        Log::channel('daily')->info('[MotionPay Webhook] 收到支付投诉', [
            'motionpay_trade_no' => $complainTradeNo,
            'xboard_trade_no' => $tradeNo,
            'user_id' => $user->id,
            'user_email' => $user->email,
            'title' => $title,
            'money' => $money,
            'type' => $data['type'] ?? '',
        ]);

        // 自动封禁
        if ($this->getConfig('auto_ban_enabled', true)) {
            if ($user->banned) {
                Log::info('[MotionPay Webhook] 用户已处于封禁状态', ['user_id' => $user->id]);
                return response('OK', 200);
            }

            // 保护管理员账户
            if ($user->is_admin) {
                Log::warning('[MotionPay Webhook] 跳过管理员封禁', ['user_id' => $user->id]);
                return response('OK', 200);
            }

            $user->banned = true;
            $user->save();

            // 撤销所有登录令牌，强制立即下线
            $user->tokens()->delete();

            Log::channel('daily')->warning('[MotionPay Webhook] 已自动封禁用户', [
                'user_id' => $user->id,
                'email' => $user->email,
                'reason' => "支付投诉: {$title}",
                'order_amount' => $money,
            ]);
        }

        return response('OK', 200);
    }

    /**
     * 处理新订单支付事件（仅记录日志）
     */
    private function handleOrderPaid(array $data)
    {
        if (!$this->getConfig('ban_on_order_paid', false)) {
            return response('OK', 200);
        }

        Log::info('[MotionPay Webhook] 收到新订单通知', [
            'trade_no' => $data['trade_no'] ?? '',
            'out_trade_no' => $data['out_trade_no'] ?? '',
            'money' => $data['money'] ?? '',
        ]);

        return response('OK', 200);
    }
}
