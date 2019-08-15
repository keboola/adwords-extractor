<?php

declare(strict_types=1);

defined('EX_AW_CLIENT_ID') || define('EX_AW_CLIENT_ID', getenv('EX_AW_CLIENT_ID')
    ? getenv('EX_AW_CLIENT_ID') : '');

defined('EX_AW_CLIENT_SECRET') || define('EX_AW_CLIENT_SECRET', getenv('EX_AW_CLIENT_SECRET')
    ? getenv('EX_AW_CLIENT_SECRET') : '');

defined('EX_AW_DEVELOPER_TOKEN') || define('EX_AW_DEVELOPER_TOKEN', getenv('EX_AW_DEVELOPER_TOKEN')
    ? getenv('EX_AW_DEVELOPER_TOKEN') : '');

defined('EX_AW_REFRESH_TOKEN') || define('EX_AW_REFRESH_TOKEN', getenv('EX_AW_REFRESH_TOKEN')
    ? getenv('EX_AW_REFRESH_TOKEN') : '');

defined('EX_AW_CUSTOMER_ID') || define('EX_AW_CUSTOMER_ID', getenv('EX_AW_CUSTOMER_ID')
    ? getenv('EX_AW_CUSTOMER_ID') : '');

defined('EX_AW_TEST_ACCOUNT_ID') || define('EX_AW_TEST_ACCOUNT_ID', getenv('EX_AW_TEST_ACCOUNT_ID')
    ? getenv('EX_AW_TEST_ACCOUNT_ID') : '');

defined('EX_AW_USER_AGENT') || define('EX_AW_USER_AGENT', getenv('EX_AW_USER_AGENT')
    ? getenv('EX_AW_USER_AGENT') : 'phpunit AdWords Extractor Testing');

require_once __DIR__ . '/../vendor/autoload.php';
