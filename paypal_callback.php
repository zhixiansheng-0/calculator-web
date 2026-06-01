<?php
/**
 * PayPal 支付回调处理文件
 * 用于验证支付并记录付费用户邮箱
 * 
 * 文件位置：上传到网站根目录，与 index.html 同级
 * 文件名：paypal-callback.php
 * 
 * 版本：v2.0 - 新增访问&交易统计模块
 */

// 禁用错误显示（生产环境）
error_reporting(0);
ini_set('display_errors', 0);

// 设置响应头
header('Content-Type: application/json');

// ========== 🔧 配置区域 ==========
$CLIENT_ID = 'BAwA7v8YaiZqngny0bCYt2WkPmpVLq3oVzHbFhSoSdpaQzLpwNb3dRrbWQ6wX1ifTEdIDpx0f08wTEY';
$CLIENT_SECRET = 'EHXt0cVU8xxeKbwIQmsFTlm-PwUOHsKk3LF7S5GNWtJMI1DVpE09MzjlwsGF5mS_JA3St01YG0aYYZJw';

// 付费用户记录文件路径
$USER_DATA_FILE = __DIR__ . '/pro_users.txt';

// ========== 新增：统计文件路径 ==========
$STAT_DATA_FILE = __DIR__ . '/stat_data.json';

// 商品金额
$PRODUCT_PRICE = '4.99';
$CURRENCY = 'USD';

// ========== ========== ========== ========== ==========
// ========== 新增：统计模块 ==========
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
            'hourly' => [],      // 按小时存储: Y-m-d H:00:00 => {page_view, popup_click, pay_link_click, activate_click}
            'daily' => [],       // 按日期存储: Y-m-d => {date, paid_count, revenue, page_views, popup_clicks, pay_link_clicks, activate_clicks}
            'hourly_paid' => []  // 按小时存储付费记录: Y-m-d H:00:00 => {count, revenue}
        ];
        file_put_contents($STAT_DATA_FILE, json_encode($initData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

/**
 * 记录事件统计
 * @param string $event_type 事件类型: page_view, popup_click, pay_link_click, activate_click
 */
function recordStatEvent($event_type) {
    global $STAT_DATA_FILE;
    initStatFile();
    
    $statData = json_decode(file_get_contents($STAT_DATA_FILE), true);
    if (!$statData) {
        $statData = [];
    }
    
    $hourKey = getCurrentHourTimestamp();
    $dateKey = getCurrentDate();
    $hourKeyStr = date('Y-m-d H:00:00', $hourKey);
    
    // 初始化小时数据
    if (!isset($statData['hourly'][$hourKeyStr])) {
        $statData['hourly'][$hourKeyStr] = [
            'page_view' => 0,
            'popup_click' => 0,
            'pay_link_click' => 0,
            'activate_click' => 0
        ];
    }
    
    // 初始化每日数据
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
    
    // 更新小时统计
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
    
    // 更新汇总统计
    $summaryMap = [
        'page_view' => 'total_page_views',
        'popup_click' => 'total_popup_clicks',
        'pay_link_click' => 'total_pay_link_clicks',
        'activate_click' => 'total_activate_clicks'
    ];
    
    if (isset($summaryMap[$event_type])) {
        $statData['summary'][$summaryMap[$event_type]]++;
    }
    
    // 清理旧数据（保留最近90天）
    $ninetyDaysAgo = strtotime('-90 days');
    foreach ($statData['hourly'] as $key => $value) {
        $keyTime = strtotime($key);
        if ($keyTime && $keyTime < $ninetyDaysAgo) {
            unset($statData['hourly'][$key]);
        }
    }
    foreach ($statData['daily'] as $key => $value) {
        if (isset($value['date']) && strtotime($value['date']) < $ninetyDaysAgo) {
            unset($statData['daily'][$key]);
        }
    }
    
    file_put_contents($STAT_DATA_FILE, json_encode($statData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * 记录付费统计
 * @param string $email 用户邮箱
 * @param float $amount 金额
 */
function recordPaidStat($email, $amount) {
    global $STAT_DATA_FILE;
    initStatFile();
    
    $statData = json_decode(file_get_contents($STAT_DATA_FILE), true);
    if (!$statData) {
        $statData = [];
    }
    
    $hourKey = getCurrentHourTimestamp();
    $dateKey = getCurrentDate();
    $hourKeyStr = date('Y-m-d H:00:00', $hourKey);
    
    // 初始化小时付费数据
    if (!isset($statData['hourly_paid'][$hourKeyStr])) {
        $statData['hourly_paid'][$hourKeyStr] = [
            'count' => 0,
            'revenue' => 0
        ];
    }
    
    // 更新小时付费统计
    $statData['hourly_paid'][$hourKeyStr]['count']++;
    $statData['hourly_paid'][$hourKeyStr]['revenue'] += floatval($amount);
    
    // 更新每日统计
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
    $statData['daily'][$dateKey]['paid_count']++;
    $statData['daily'][$dateKey]['revenue'] += floatval($amount);
    
    // 更新汇总统计
    $statData['summary']['total_paid_count']++;
    $statData['summary']['total_revenue'] += floatval($amount);
    
    // 清理旧数据
    $ninetyDaysAgo = strtotime('-90 days');
    foreach ($statData['hourly_paid'] as $key => $value) {
        $keyTime = strtotime($key);
        if ($keyTime && $keyTime < $ninetyDaysAgo) {
            unset($statData['hourly_paid'][$key]);
        }
    }
    
    file_put_contents($STAT_DATA_FILE, json_encode($statData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * 获取统计数据
 * @return array 统计数据
 */
function getStatistics() {
    global $STAT_DATA_FILE;
    initStatFile();
    
    $statData = json_decode(file_get_contents($STAT_DATA_FILE), true);
    if (!$statData) {
        $statData = [];
    }
    
    // 计算今日数据
    $today = getCurrentDate();
    $todayData = [
        'page_views' => 0,
        'popup_clicks' => 0,
        'pay_link_clicks' => 0,
        'activate_clicks' => 0,
        'paid_count' => 0,
        'revenue' => 0
    ];
    
    if (isset($statData['daily'][$today])) {
        $todayData = $statData['daily'][$today];
    }
    
    // 计算高峰时段（最近7天）
    $peakHour = '';
    $maxCount = 0;
    $sevenDaysAgo = strtotime('-7 days');
    foreach ($statData['hourly_paid'] as $hour => $data) {
        $hourTime = strtotime($hour);
        if ($hourTime && $hourTime >= $sevenDaysAgo) {
            if ($data['count'] > $maxCount) {
                $maxCount = $data['count'];
                $peakHour = $hour;
            }
        }
    }
    
    return [
        'summary' => $statData['summary'] ?? [
            'total_page_views' => 0,
            'total_popup_clicks' => 0,
            'total_pay_link_clicks' => 0,
            'total_activate_clicks' => 0,
            'total_paid_count' => 0,
            'total_revenue' => 0
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
 * 记录付费用户邮箱到文件（防重复）
 */
function recordPaidUser($email, $orderId, $amount) {
    global $USER_DATA_FILE;
    
    $users = [];
    if (file_exists($USER_DATA_FILE)) {
        $content = file_get_contents($USER_DATA_FILE);
        $lines = array_filter(array_map('trim', explode("\n", $content)));
        foreach ($lines as $line) {
            $parts = explode('|', $line);
            if (!empty($parts[0])) {
                $users[] = $parts[0];
            }
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
    
    if (!file_exists($USER_DATA_FILE)) {
        return false;
    }
    
    $content = file_get_contents($USER_DATA_FILE);
    $lines = array_filter(array_map('trim', explode("\n", $content)));
    foreach ($lines as $line) {
        $parts = explode('|', $line);
        if (!empty($parts[0]) && $parts[0] === $email) {
            return true;
        }
    }
    
    return false;
}

// ========== ========== ========== ========== ==========
// ========== 请求处理入口 ==========
// ========== ========== ========== ========== ==========

// 获取请求方法
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];

// ========== 新增：统计上报接口 ==========
if ($method === 'POST' && strpos($requestUri, 'action=stat') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    $eventType = $input['event'] ?? '';
    
    $allowedEvents = ['page_view', 'popup_click', 'pay_link_click', 'activate_click'];
    if (in_array($eventType, $allowedEvents)) {
        recordStatEvent($eventType);
        echo json_encode(['success' => true, 'message' => 'Event recorded']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid event type']);
    }
    exit;
}

// ========== 新增：统计查询接口 ==========
if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_stats') {
    $stats = getStatistics();
    echo json_encode(['success' => true, 'data' => $stats]);
    exit;
}

// ========== 原有：GET 请求（前端检查状态）==========
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

// ========== 原有：POST 请求（前端支付完成后记录）==========
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    // ========== 原有：管理员手动解锁 ==========
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
    
    // ========== 原有：普通支付记录 ==========
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
    
    // ========== 新增：记录付费统计 ==========
    recordPaidStat($paidEmail, $amount);
    
    // ========== 原有：记录付费用户 ==========
    if (recordPaidUser($paidEmail, $orderId, $amount)) {
        echo json_encode(['success' => true, 'message' => 'Payment recorded']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to record user']);
    }
    exit;
}

// 其他请求
echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);