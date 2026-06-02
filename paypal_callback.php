<?php
/**
 * PayPal 支付回调处理文件
 * 用于验证支付并记录付费用户邮箱
 * 
 * 文件位置：上传到网站根目录，与 index.html 同级
 * 文件名：paypal-callback.php
 * 
 * 版本：v2.2 - 新增支付成功自动邮件通知
 */

// 禁用错误显示（生产环境）
error_reporting(0);
ini_set('display_errors', 0);

// 设置响应头
header('Content-Type: application/json');

// ========== 🔧 配置区域 ==========
$DOMAIN = 'https://calculator-web-black.vercel.app';  // 您的网站域名

$CLIENT_ID = 'BAwA7v8YaiZqngny0bCYt2WkPmpVLq3oVzHbFhSoSdpaQzLpwNb3dRrbWQ6wX1ifTEdIDpx0f08wTEY';
$CLIENT_SECRET = 'EHXt0cVU8xxeKbwIQmsFTlm-PwUOHsKk3LF7S5GNWtJMI1DVpE09MzjlwsGF5mS_JA3St01YG0aYYZJw';

// 付费用户记录文件路径
$USER_DATA_FILE = __DIR__ . '/pro_users.txt';

// 统计文件路径
$STAT_DATA_FILE = __DIR__ . '/stat_data.json';

// 商品金额
$PRODUCT_PRICE = '4.99';
$CURRENCY = 'USD';

// ========== 新增：邮件配置 ==========
// 发件人邮箱（建议使用您域名的邮箱，如 no-reply@您的域名.com）
$FROM_EMAIL = 'no-reply@calculator-web-black.vercel.app';
$FROM_NAME = 'Overseas Engineering Calculator';
$SUPPORT_EMAIL = 'zhikexin.111@outlook.com';  // 您的客服邮箱

// ========== ========== ========== ========== ==========
// ========== 邮件发送函数 ==========
// ========== ========== ========== ========== ==========

/**
 * 发送支付成功确认邮件
 * @param string $toEmail 收件人邮箱
 * @param string $orderId 订单号
 * @return bool 是否发送成功
 */
function sendActivationEmail($toEmail, $orderId) {
    global $FROM_EMAIL, $FROM_NAME, $SUPPORT_EMAIL, $DOMAIN;
    
    $subject = "Your PRO License is Ready — Overseas Engineering Calculator";
    
    // HTML邮件内容
    $htmlContent = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Your PRO License is Ready</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 2px solid #f5a623;
        }
        .header h1 {
            color: #1a1a2e;
            font-size: 24px;
            margin: 0;
        }
        .content {
            padding: 30px 0;
        }
        .button {
            display: inline-block;
            background: linear-gradient(95deg, #f5a623, #e68a2e);
            color: #1c1b1a;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 40px;
            font-weight: bold;
            margin: 20px 0;
        }
        .steps {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
        }
        .step {
            margin-bottom: 15px;
            padding-left: 28px;
            position: relative;
        }
        .step:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #28a745;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #888;
        }
        .highlight {
            color: #f5a623;
            font-weight: bold;
        }
        .note {
            background: #fff3e0;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
        }
        .features {
            background: #e8f5e9;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
        }
        .feature-item {
            margin-bottom: 10px;
            padding-left: 28px;
            position: relative;
        }
        .feature-item:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #28a745;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔧 Overseas Engineering Calculator</h1>
        <p style="color: #666;">PRO License Confirmation</p>
    </div>
    
    <div class="content">
        <p>Dear Valued Customer,</p>
        
        <p><strong>Thank you for your purchase!</strong> Your PRO license has been successfully activated. You now have full access to all professional features of Overseas Engineering Calculator.</p>
        
        <div class="note">
            ⚡ <strong>Important:</strong> If you have not already done so, please follow the steps below to complete your license activation.
        </div>
        
        <div style="text-align: center;">
            <a href="' . $DOMAIN . '" class="button" style="color: #1c1b1a;">🔓 Access Your PRO Account →</a>
        </div>
        
        <div class="steps">
            <p style="font-weight: bold; margin-bottom: 12px;">📋 How to Activate Your PRO License:</p>
            <div class="step"><strong>Step 1:</strong> Click the button above to visit our website</div>
            <div class="step"><strong>Step 2:</strong> Click the <strong>"UPGRADE TO PRO"</strong> button on the page</div>
            <div class="step"><strong>Step 3:</strong> Enter <strong>the same email address</strong> you used for payment</div>
            <div class="step"><strong>Step 4:</strong> Click the <strong>"I have paid, Activate PRO"</strong> button</div>
            <div class="step"><strong>Step 5:</strong> Your PRO features will be unlocked immediately</div>
        </div>
        
        <div class="features">
            <p style="font-weight: bold; margin-bottom: 12px;">✨ Your PRO Features Include:</p>
            <div class="feature-item">Unlimited calculation history</div>
            <div class="feature-item">ANSI pipe data (Full Sch40/Sch80 tables)</div>
            <div class="feature-item">H-Beam & rectangular tube library</div>
            <div class="feature-item">Ad-free experience</div>
            <div class="feature-item">Visa form PDF export (no watermark)</div>
            <div class="feature-item">Batch PDF export</div>
            <div class="feature-item">Priority email support</div>
        </div>
        
        <div class="note">
            💡 <strong>Quick Tip:</strong> Bookmark our website for easy access:<br>
            <a href="' . $DOMAIN . '" style="color: #f5a623;">' . $DOMAIN . '</a>
        </div>
        
        <p style="margin-top: 20px;">Need assistance? Our support team is here to help:</p>
        <ul style="margin-left: 20px;">
            <li>📧 <strong>Email support:</strong> <a href="mailto:' . $SUPPORT_EMAIL . '" style="color: #f5a623;">' . $SUPPORT_EMAIL . '</a></li>
            <li>⏱️ <strong>Response time:</strong> Within 24 hours (business days)</li>
            <li>🔄 <strong>7-day money-back guarantee:</strong> Not satisfied? Contact us within 7 days for a full refund</li>
        </ul>
        
        <p style="margin-top: 20px;">Thank you for choosing Overseas Engineering Calculator. We\'re committed to providing you with the best engineering toolkit for your overseas projects.</p>
        
        <p>Best regards,<br><strong>Overseas Engineering Calculator Team</strong></p>
    </div>
    
    <div class="footer">
        <p>© 2025 Overseas Engineering Calculator Pro | Lifetime License · One-time Payment</p>
        <p>Order ID: ' . htmlspecialchars($orderId) . '</p>
        <p><a href="' . $DOMAIN . '" style="color: #888;">Visit our website</a></p>
    </div>
</body>
</html>';
    
    // 纯文本备用版本
    $textContent = "============================================================\n";
    $textContent .= "      OVERSEAS ENGINEERING CALCULATOR - PRO LICENSE CONFIRMATION\n";
    $textContent .= "============================================================\n\n";
    $textContent .= "Dear Valued Customer,\n\n";
    $textContent .= "Thank you for your purchase! Your PRO license has been successfully activated.\n\n";
    $textContent .= "============================================================\n";
    $textContent .= "  HOW TO ACTIVATE YOUR PRO LICENSE\n";
    $textContent .= "============================================================\n\n";
    $textContent .= "Step 1: Visit our website\n";
    $textContent .= "        👉 " . $DOMAIN . "\n\n";
    $textContent .= "Step 2: Click the \"UPGRADE TO PRO\" button\n\n";
    $textContent .= "Step 3: Enter the SAME email address you used for payment\n\n";
    $textContent .= "Step 4: Click the \"I have paid, Activate PRO\" button\n\n";
    $textContent .= "Step 5: Your PRO features will be unlocked immediately\n\n";
    $textContent .= "============================================================\n";
    $textContent .= "  YOUR PRO FEATURES\n";
    $textContent .= "============================================================\n\n";
    $textContent .= "✓ Unlimited calculation history\n";
    $textContent .= "✓ ANSI pipe data (Full Sch40/Sch80 tables)\n";
    $textContent .= "✓ H-Beam & rectangular tube library\n";
    $textContent .= "✓ Ad-free experience\n";
    $textContent .= "✓ Visa form PDF export (no watermark)\n";
    $textContent .= "✓ Batch PDF export\n";
    $textContent .= "✓ Priority email support\n\n";
    $textContent .= "============================================================\n";
    $textContent .= "  NEED HELP?\n";
    $textContent .= "============================================================\n\n";
    $textContent .= "📧 Email: " . $SUPPORT_EMAIL . "\n";
    $textContent .= "⏱️ Response: Within 24 hours\n";
    $textContent .= "🔄 7-day money-back guarantee\n\n";
    $textContent .= "============================================================\n";
    $textContent .= "  QUICK ACCESS\n";
    $textContent .= "============================================================\n\n";
    $textContent .= "Website: " . $DOMAIN . "\n";
    $textContent .= "Bookmark it for easy access!\n\n";
    $textContent .= "Best regards,\n";
    $textContent .= "Overseas Engineering Calculator Team\n";
    $textContent .= "============================================================\n";
    $textContent .= "© 2025 Overseas Engineering Calculator Pro | Lifetime License\n";
    $textContent .= "Order ID: " . $orderId . "\n";
    
    // 邮件头
    $boundary = md5(time());
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "From: " . $FROM_NAME . " <" . $FROM_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . $SUPPORT_EMAIL . "\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"" . $boundary . "\"\r\n";
    
    // 邮件正文（多部分）
    $message = "--" . $boundary . "\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $textContent . "\r\n\r\n";
    $message .= "--" . $boundary . "\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $htmlContent . "\r\n\r\n";
    $message .= "--" . $boundary . "--";
    
    // 发送邮件
    try {
        $result = mail($toEmail, $subject, $message, $headers);
        // 记录邮件发送日志
        $logEntry = date('Y-m-d H:i:s') . " | MAIL | " . $toEmail . " | " . ($result ? "SUCCESS" : "FAILED") . "\n";
        file_put_contents(__DIR__ . '/email_log.txt', $logEntry, FILE_APPEND);
        return $result;
    } catch (Exception $e) {
        // 邮件发送失败不影响支付记录
        $logEntry = date('Y-m-d H:i:s') . " | MAIL | " . $toEmail . " | ERROR: " . $e->getMessage() . "\n";
        file_put_contents(__DIR__ . '/email_log.txt', $logEntry, FILE_APPEND);
        return false;
    }
}

// ========== ========== ========== ========== ==========
// ========== 统计模块函数 ==========
// ========== ========== ========== ========== ==========

/**
 * 获取当前小时时间戳（整点）
 */
function getCurrentHourTimestamp() {
    return strtotime(date('Y-m-d H:00:00'));
}

/**
 * 获取当前日期（Y-m-d）
 */
function getCurrentDate() {
    return date('Y-m-d');
}

/**
 * 初始化统计文件
 */
function initStatFile() {
    global $STAT_DATA_FILE;
    if (!file_exists($STAT_DATA_FILE)) {
        $initData = [
            'summary' => [
                'total_page_views' => 0,
                'total_popup_clicks' => 0,
                'total_pay_link_clicks' => 0,
                'total_activate_clicks' => 0,
                'total_paid_count' => 0,
                'total_revenue' => 0
            ],
            'hourly' => [],
            'daily' => [],
            'hourly_paid' => []
        ];
        file_put_contents($STAT_DATA_FILE, json_encode($initData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

/**
 * 记录事件统计
 */
function recordStatEvent($event_type) {
    global $STAT_DATA_FILE;
    initStatFile();
    
    $statData = json_decode(file_get_contents($STAT_DATA_FILE), true);
    if (!$statData) $statData = [];
    
    $hourKey = getCurrentHourTimestamp();
    $dateKey = getCurrentDate();
    $hourKeyStr = date('Y-m-d H:00:00', $hourKey);
    
    if (!isset($statData['hourly'][$hourKeyStr])) {
        $statData['hourly'][$hourKeyStr] = [
            'page_view' => 0,
            'popup_click' => 0,
            'pay_link_click' => 0,
            'activate_click' => 0
        ];
    }
    
    if (!isset($statData['daily'][$dateKey])) {
        $statData['daily'][$dateKey] = [
            'date' => $dateKey,
            'page_views' => 0,
            'popup_clicks' => 0,
            'pay_link_clicks' => 0,
            'activate_clicks' => 0,
            'paid_count' => 0,
            'revenue' => 0
        ];
    }
    
    $fieldMap = [
        'page_view' => 'page_view',
        'popup_click' => 'popup_click',
        'pay_link_click' => 'pay_link_click',
        'activate_click' => 'activate_click'
    ];
    
    if (isset($fieldMap[$event_type])) {
        $statData['hourly'][$hourKeyStr][$fieldMap[$event_type]]++;
        $statData['daily'][$dateKey][$fieldMap[$event_type] . 's']++;
    }
    
    $summaryMap = [
        'page_view' => 'total_page_views',
        'popup_click' => 'total_popup_clicks',
        'pay_link_click' => 'total_pay_link_clicks',
        'activate_click' => 'total_activate_clicks'
    ];
    
    if (isset($summaryMap[$event_type])) {
        $statData['summary'][$summaryMap[$event_type]]++;
    }
    
    // 清理90天前数据
    $ninetyDaysAgo = strtotime('-90 days');
    foreach ($statData['hourly'] as $key => $value) {
        if (strtotime($key) < $ninetyDaysAgo) unset($statData['hourly'][$key]);
    }
    foreach ($statData['daily'] as $key => $value) {
        if (isset($value['date']) && strtotime($value['date']) < $ninetyDaysAgo) unset($statData['daily'][$key]);
    }
    
    file_put_contents($STAT_DATA_FILE, json_encode($statData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * 记录付费统计
 */
function recordPaidStat($email, $amount) {
    global $STAT_DATA_FILE;
    initStatFile();
    
    $statData = json_decode(file_get_contents($STAT_DATA_FILE), true);
    if (!$statData) $statData = [];
    
    $hourKey = getCurrentHourTimestamp();
    $dateKey = getCurrentDate();
    $hourKeyStr = date('Y-m-d H:00:00', $hourKey);
    
    if (!isset($statData['hourly_paid'][$hourKeyStr])) {
        $statData['hourly_paid'][$hourKeyStr] = ['count' => 0, 'revenue' => 0];
    }
    $statData['hourly_paid'][$hourKeyStr]['count']++;
    $statData['hourly_paid'][$hourKeyStr]['revenue'] += floatval($amount);
    
    if (!isset($statData['daily'][$dateKey])) {
        $statData['daily'][$dateKey] = [
            'date' => $dateKey,
            'page_views' => 0, 'popup_clicks' => 0,
            'pay_link_clicks' => 0, 'activate_clicks' => 0,
            'paid_count' => 0, 'revenue' => 0
        ];
    }
    $statData['daily'][$dateKey]['paid_count']++;
    $statData['daily'][$dateKey]['revenue'] += floatval($amount);
    
    $statData['summary']['total_paid_count']++;
    $statData['summary']['total_revenue'] += floatval($amount);
    
    $ninetyDaysAgo = strtotime('-90 days');
    foreach ($statData['hourly_paid'] as $key => $value) {
        if (strtotime($key) < $ninetyDaysAgo) unset($statData['hourly_paid'][$key]);
    }
    
    file_put_contents($STAT_DATA_FILE, json_encode($statData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * 获取统计数据
 */
function getStatistics() {
    global $STAT_DATA_FILE;
    initStatFile();
    
    $statData = json_decode(file_get_contents($STAT_DATA_FILE), true);
    if (!$statData) $statData = [];
    
    $today = getCurrentDate();
    $todayData = $statData['daily'][$today] ?? [
        'page_views' => 0, 'popup_clicks' => 0,
        'pay_link_clicks' => 0, 'activate_clicks' => 0,
        'paid_count' => 0, 'revenue' => 0
    ];
    
    $peakHour = '';
    $maxCount = 0;
    $sevenDaysAgo = strtotime('-7 days');
    foreach (($statData['hourly_paid'] ?? []) as $hour => $data) {
        if (strtotime($hour) >= $sevenDaysAgo && $data['count'] > $maxCount) {
            $maxCount = $data['count'];
            $peakHour = $hour;
        }
    }
    
    return [
        'summary' => $statData['summary'] ?? [
            'total_page_views' => 0, 'total_popup_clicks' => 0,
            'total_pay_link_clicks' => 0, 'total_activate_clicks' => 0,
            'total_paid_count' => 0, 'total_revenue' => 0
        ],
        'today' => $todayData,
        'daily' => array_values($statData['daily'] ?? []),
        'hourly' => $statData['hourly'] ?? [],
        'hourly_paid' => $statData['hourly_paid'] ?? [],
        'peak_hour' => $peakHour,
        'peak_count' => $maxCount
    ];
}

// ========== ========== ========== ========== ==========
// ========== 原有授权模块函数 ==========
// ========== ========== ========== ========== ==========

/**
 * 获取 PayPal Access Token
 */
function getPayPalAccessToken($clientId, $clientSecret) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.paypal.com/v1/oauth2/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_USERPWD, $clientId . ':' . $clientSecret);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("PayPal Token Error: HTTP $httpCode - $response");
        return null;
    }
    
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

/**
 * 验证 PayPal 订单
 */
function verifyPayPalOrder($orderId, $accessToken, $expectedAmount, $expectedCurrency) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.paypal.com/v2/checkout/orders/{$orderId}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("Order Verification Error: HTTP $httpCode - $response");
        return ['valid' => false, 'error' => 'Order verification failed'];
    }
    
    $order = json_decode($response, true);
    
    if (($order['status'] ?? '') !== 'COMPLETED') {
        return ['valid' => false, 'error' => 'Order not completed'];
    }
    
    $amount = $order['purchase_units'][0]['amount']['value'] ?? '';
    $currency = $order['purchase_units'][0]['amount']['currency_code'] ?? '';
    
    if (floatval($amount) != floatval($expectedAmount) || $currency !== $expectedCurrency) {
        error_log("Amount mismatch: expected $expectedAmount $expectedCurrency, got $amount $currency");
        return ['valid' => false, 'error' => 'Amount mismatch'];
    }
    
    $email = $order['purchase_units'][0]['custom_id'] ?? '';
    if (empty($email) && isset($order['payer']['email_address'])) {
        $email = $order['payer']['email_address'];
    }
    
    return ['valid' => true, 'email' => $email, 'order' => $order];
}

/**
 * 记录付费用户邮箱到文件
 */
function recordPaidUser($email, $orderId, $amount) {
    global $USER_DATA_FILE;
    
    $users = [];
    if (file_exists($USER_DATA_FILE)) {
        $content = file_get_contents($USER_DATA_FILE);
        $lines = array_filter(array_map('trim', explode("\n", $content)));
        foreach ($lines as $line) {
            $parts = explode('|', $line);
            if (!empty($parts[0])) $users[] = $parts[0];
        }
    }
    
    if (in_array($email, $users)) {
        file_put_contents(__DIR__ . '/payment_log.txt', date('Y-m-d H:i:s') . " | DUPLICATE | $email | $orderId | $amount\n", FILE_APPEND);
        return true;
    }
    
    $newLine = $email . '|' . $orderId . '|' . date('Y-m-d H:i:s') . "\n";
    $result = file_put_contents($USER_DATA_FILE, $newLine, FILE_APPEND | LOCK_EX);
    
    file_put_contents(__DIR__ . '/payment_log.txt', date('Y-m-d H:i:s') . " | SUCCESS | $email | $orderId | $amount\n", FILE_APPEND);
    
    return $result !== false;
}

/**
 * 检查邮箱是否已付费
 */
function isPaidUser($email) {
    global $USER_DATA_FILE;
    
    if (!file_exists($USER_DATA_FILE)) return false;
    
    $content = file_get_contents($USER_DATA_FILE);
    $lines = array_filter(array_map('trim', explode("\n", $content)));
    foreach ($lines as $line) {
        $parts = explode('|', $line);
        if (!empty($parts[0]) && $parts[0] === $email) return true;
    }
    return false;
}

// ========== ========== ========== ========== ==========
// ========== 请求处理入口 ==========
// ========== ========== ========== ========== ==========

$method = $_SERVER['REQUEST_METHOD'];

// 统计上报接口检测
$isStatRequest = false;
if ($method === 'POST') {
    if (isset($_GET['action']) && $_GET['action'] === 'stat') {
        $isStatRequest = true;
    }
    if (!$isStatRequest) {
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['action']) && $input['action'] === 'stat') {
            $isStatRequest = true;
        }
    }
}

// 统计上报接口
if ($method === 'POST' && $isStatRequest) {
    $input = json_decode(file_get_contents('php://input'), true);
    $eventType = $input['event'] ?? $_GET['event'] ?? '';
    
    $allowedEvents = ['page_view', 'popup_click', 'pay_link_click', 'activate_click'];
    if (in_array($eventType, $allowedEvents)) {
        recordStatEvent($eventType);
        echo json_encode(['success' => true, 'message' => 'Event recorded', 'event' => $eventType]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid event type']);
    }
    exit;
}

// 统计查询接口
if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_stats') {
    $stats = getStatistics();
    echo json_encode(['success' => true, 'data' => $stats]);
    exit;
}

// GET 请求（前端检查状态）
if ($method === 'GET') {
    $email = $_GET['email'] ?? '';
    $action = $_GET['action'] ?? '';
    
    if ($action === 'check' && !empty($email)) {
        $isPro = isPaidUser($email);
        echo json_encode(['status' => $isPro ? 'pro' : 'free', 'email' => $email]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Email required']);
    }
    exit;
}

// POST 请求（支付记录）
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    // 管理员手动解锁
    if ($action === 'admin_unlock') {
        $email = $input['email'] ?? '';
        if (empty($email)) {
            echo json_encode(['success' => false, 'error' => 'Email required']);
            exit;
        }
        if (recordPaidUser($email, 'admin_' . time(), '4.99')) {
            echo json_encode(['success' => true, 'message' => 'User recorded']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to write file']);
        }
        exit;
    }
    
    // 普通支付记录
    $orderId = $input['order_id'] ?? '';
    $email = $input['email'] ?? '';
    $amount = $input['amount'] ?? '4.99';
    
    if (empty($orderId) || empty($email)) {
        echo json_encode(['success' => false, 'error' => 'Missing order_id or email']);
        exit;
    }
    
    $accessToken = getPayPalAccessToken($CLIENT_ID, $CLIENT_SECRET);
    if ($accessToken) {
        $verification = verifyPayPalOrder($orderId, $accessToken, $PRODUCT_PRICE, $CURRENCY);
        if (!$verification['valid']) {
            echo json_encode(['success' => false, 'error' => $verification['error']]);
            exit;
        }
        $paidEmail = $verification['email'] ?: $email;
    } else {
        $paidEmail = $email;
    }
    
    // 记录付费统计
    recordPaidStat($paidEmail, $amount);
    
    // 记录付费用户
    if (recordPaidUser($paidEmail, $orderId, $amount)) {
        // ========== 新增：支付成功后发送确认邮件 ==========
        sendActivationEmail($paidEmail, $orderId);
        
        echo json_encode(['success' => true, 'message' => 'Payment recorded']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to record user']);
    }
    exit;
}

// 其他请求
echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);