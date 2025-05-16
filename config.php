<?php
require_once 'db.php'; // Include the database connection
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('SITE_URL', 'https://dispatchingpost.rf.gd/MultiPost/'); // Your actual site URL

// Create uploads directory if it doesn't exist
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// New dynamic config loader function
function getAccountConfig($userId = null) {
    global $pdo; // Use the PDO connection from db.php
    
    if (!$userId && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    
    if (!$userId) {
        return [
            'facebook' => [],
            'instagram' => [],
            'pixelfed' => [],
            'telegram' => [],
            'whatsapp' => []
        ];
    }
    
    $config = [
        'facebook' => [],
        'instagram' => [],
        'pixelfed' => [],
        'telegram' => [],
        'whatsapp' => []
    ];
    
    // Get default accounts first
    $stmt = $pdo->prepare("SELECT * FROM social_accounts WHERE user_id = ? AND is_default = 1");
    $stmt->execute([$userId]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $platform = $row['platforms'];
        
        $accountData = [
            'id' => $row['id'],
            'account_name' => $row['account_name'],
            'access_token' => $row['access_token'],
            'page_id' => $row['page_id'] ?? null,
            'user_id_platform' => $row['user_id_platform'] ?? null,
            'instance_url' => $row['instance_url'] ?? null,
            'api_key' => $row['api_key'] ?? null,
            'bot_token' => $row['bot_token'] ?? null,
            'channel_id' => $row['channel_id'] ?? null,
            'phone_number_id' => $row['phone_number_id'] ?? null,
            'business_account_id' => $row['business_account_id'] ?? null,
            'account_data' => $row['account_data'] ? json_decode($row['account_data'], true) : null,
            'client_id' => $row['client_id'] ?? null,
            'client_secret' => $row['client_secret'] ?? null,
            'is_default' => true
        ];
        
        $config[$platform][] = $accountData;
    }
    
    // Get non-default accounts
    $stmt = $pdo->prepare("SELECT * FROM social_accounts WHERE user_id = ? AND is_default = 0");
    $stmt->execute([$userId]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $platform = $row['platforms'];
        
        $accountData = [
            'id' => $row['id'],
            'account_name' => $row['account_name'],
            'access_token' => $row['access_token'],
            'page_id' => $row['page_id'] ?? null,
            'user_id_platform' => $row['user_id_platform'] ?? null,
            'instance_url' => $row['instance_url'] ?? null,
            'api_key' => $row['api_key'] ?? null,
            'bot_token' => $row['bot_token'] ?? null,
            'channel_id' => $row['channel_id'] ?? null,
            'phone_number_id' => $row['phone_number_id'] ?? null,
            'business_account_id' => $row['business_account_id'] ?? null,
            'account_data' => $row['account_data'] ? json_decode($row['account_data'], true) : null,
            'client_id' => $row['client_id'] ?? null,
            'client_secret' => $row['client_secret'] ?? null,
            'is_default' => false
        ];
        
        $config[$platform][] = $accountData;
    }
    
    return $config;
}
   // Keep a fallback for backward compatibility
$config = [
    'facebook' => [
        'access_token' => 'EAAPZBynojvtoBOwre4IKUgn6Yvah7NFQINM8o6ZC9LoUidVCyTx0eqMoJbJ3y2sjbPabVH4Psf2dtrV1uaUNIGsTpJXus8cL82uLIb7L0hpM3GTf8ZCCYKtUI7Y9hR3T8oGFXW1GNbeytGF2Kc1DOaHgCtPyGgERZBx6PcmzT4kVvZCdG9jam3m6iYSAJc48smjljIUiUmkPedkXuxhgnxAZDZD',
        'page_id' => '520691997803813',
    ],
    'instagram' => [
        'access_token' => 'EAB2yk8YQ95MBO5q2MxMuwWZCancH3LuzjlE6R93DuY2N7ivZBuby1bhg6ZBEzOiTfxPhptOugFqMdeu9gsMeuBBzBVr47ZC4K1ipKQ7D0TMQHNgMiPW4k8nGmM2TEwm0R9QPJt7QrhxlwLlzdvcPU0vRuEHxD7LoURenZAZAozouTMIahqxnY1LRD411FPzAaC09vmiRPcv0jMZCN6ShRAC',
        'user_id' => '17841473070112462',
    ],
    // Default configurations for the new platforms (as fallback)
    'pixelfed' => [
        'instance_url' => 'https://pixelfed.social',
        'access_token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIyMzAxMiIsImp0aSI6Ijc1YmQ2MGZlNWMwYTBlODY4MmRiNDc4YjM2NDAyMzE5YjE5YmVhOGU2YTRmMmM3NmY5MDYxOTM4ZTU2Y2VjMDRmYTFiN2E4ZmM2ZWVjMzgwIiwiaWF0IjoxNzQ3MDA3ODYwLjc1ODM4MiwibmJmIjoxNzQ3MDA3ODYwLjc1ODM4MywiZXhwIjoxNzc4NTQzODYwLjc1NjkyMywic3ViIjoiNDU3NDUzIiwic2NvcGVzIjpbInJlYWQiLCJ3cml0ZSIsImZvbGxvdyJdfQ.RG_JWM24-m7lG5NC58iV7S1EGsV5P0vRcINzY_hTWGDwA9y0v2aHgJ6SB1Af5cOYDRZCOSyKeInjxXF0CTNZ-6OIVUGJzwFsrLMj8qhWKEMu3zbf9whHsCGDyR0VM8AwTW_KIdlh1aevKOzzGRkADu1nPV84OZU7_igJVVOs-oIKoGsSbdpo_NZm-X1ppBGlFPCVaLoh6d2kgER4zYP5EXe1dkGtcJ1sH1X8ZEO4P_hnIfz6-XhEnplGLaXM92T74rxpbSorGUT7f37jn4wjmFGb09AJBPd18g0me1reGa7odo74EEfypi2OJN5WN2otvq409Fyug0jky8kCXKAHgBnEcKj7dGAtDMxgin3-nwI11Yvwj8VkfjeMewxijruBALN03QswUif3Q527QZZOoyZJ3VQndYGq67_1FAgJTM9ITTBhKlrUfdkaLGVvMEYP0Mxfx3e6yeY--jGQhhtK3_udwmN6VzXvPLTaU4Ey4EqAnGhStliQbudn7aZef8KrjEVrJ1plxB4T9yxII4_34IOm0LLhy3qFnolo77eBKc9yj4CZUsw09hw1RwHhBpmKOFwXYEbwl2Non10qTBcb7isvLwBe4Mbu6IZq0RVcgekLLEWVVlKTcNG1S0Se-R0UW_D_jlrrcr-ZomN5RLQYUqY45usdEW8CynALNFhkFtE', // To be filled by the user
        'api_key' => '1684391', // To be filled by the user
    ],
    'telegram' => [
        'bot_token' => '7420359805:AAERN0ne0SuXC93jWJxLdKf_WRMZWYKePqg', 
        'channel_id' => '5123549684', // To be filled by the user
    ],
    'whatsapp' => [
        'phone_number_id' => '+14155238886', // To be filled by the user
        'access_token' => 'c3a0cde1f528bae04da29e7ecbed912a', // To be filled by the user
        'business_account_id' => 'ACdd92e202ea810341d2c819e7a27c99e2', // To be filled by the user
    ],
];
?>