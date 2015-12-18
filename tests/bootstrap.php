<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

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

require_once __DIR__ . '/../vendor/autoload.php';