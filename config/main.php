<?php

use App\Components\Google\GoogleFCM;
use App\Modules\Calc\CalcModule;
use App\Modules\Customer\CustomerModule;
use App\Modules\Podis\PodisModule;
use App\Modules\LDAP\LDAPModule;
use App\Modules\Partner\PartnerModule;
use App\Modules\SuccessManagement\SuccessManagementModule;
use App\Modules\SupportChat\SupportChatModule;

$imports = require __DIR__.'/imports.php';

$mainConfig =  [
    'name' => 'яЭнергетик',
    'basePath' => __DIR__.'/..',
    'sourceLanguage' => 'ru',
    'localeClass' => AppLocale::class,
    'aliases' => [
        'bootstrap' => __DIR__.'/../../vendor/crisu83/yiistrap',
        'static' => __DIR__.'/../../static',
        'vendor' => __DIR__.'/../../vendor',
    ],
    'preload' => [
        'appAutoloader',
        'log',
    ],
    'components' => [
        'appAutoloader' => [
            'class' => AppAutoloader::class,
        ],
        'appName' => [
            'class' => AppName::class,
        ],
        'archive' => [
            'class' => Archive::class,
        ],
        'ascue' => [
            'class' => ConfigHelper::getEnvAsBoolean('YAENERGETIK_MULTIPLE_ASCUE', false)
                ? AscueMultipleClient::class
                : AscueClient::class,
        ],
        'authDb' => [
            'class' => CDbConnection::class,
            'connectionString' => ConfigHelper::getDsnFromEnv(
                'mysql', [
                    'host' => 'YAENERGETIK_DB_AUTH_HOST',
                    'port' => 'YAENERGETIK_DB_AUTH_PORT',
                    'dbname' => 'YAENERGETIK_DB_AUTH',
                ]
            ),
            'username' => ConfigHelper::getEnv('YAENERGETIK_DB_USER'),
            'password' => ConfigHelper::getEnv('YAENERGETIK_DB_PASS'),
            'charset' => 'utf8',
            'enableParamLogging' => YII_DEBUG,
            'enableProfiling' => YII_DEBUG,
        ],
        'db' => [
            'class' => DbConnectionAdapter::class,
            'connectionString' => ConfigHelper::getDsnFromEnv(
                'mysql', [
                    'host' => 'YAENERGETIK_DB_HOST',
                    'port' => 'YAENERGETIK_DB_PORT',
                    'dbname' => 'YAENERGETIK_DB_MAIN',
                ]
            ),
            'username' => ConfigHelper::getEnv('YAENERGETIK_DB_USER'),
            'password' => ConfigHelper::getEnv('YAENERGETIK_DB_PASS'),
            'charset' => 'utf8',
            'enableParamLogging' => YII_DEBUG,
            'enableProfiling' => YII_DEBUG,
        ],
        'erbo' => [
            'class' => Erbo::class,
            'projectId' => ConfigHelper::getEnv('YAENERGETIK_TRACKER_ID'),
            'trackerUrl' => ConfigHelper::getEnv('YAENERGETIK_TRACKER_URL'),
        ],
        'fcm' => [
            'class' => GoogleFCM::class,
        ],
        'format' => [
            'class' => Formatter::class,
        ],
        'messages' => [
            'class' => PhpMessageSource::class,
        ],
        'log' => [
            'class' => CLogRouter::class,
            'routes' => [
                [
                    'class' => CFileLogRoute::class,
                    'levels' => 'error, warning',
                ],
                [
                    'class' => CFileLogRoute::class,
                    'categories' => 'application.*',
                    'levels' => 'trace',
                    'logFile' => 'trace.log',
                ],
                [
                    'class' => CFileLogRoute::class,
                    'enabled' => ConfigHelper::getEnvAsBoolean('YAENERGETIK_CHARGE_DEBUG', YII_DEBUG),
                    'categories' => 'application.charge.*',
                    'logFile' => 'charge.log',
                    'maxLogFiles' => 10,
                ],
                [
                    'class' => TitledRawFileLogRoute::class,
                    'categories' => 'ga.transactions',
                    'logFile' => 'ga.log',
                    'firstLine' => '﻿date      time      timestamp      PaymentTransactionId      clientId      userId      PaymentValue      GA request',
                ],
                [
                    'class' => TitledRawFileLogRoute::class,
                    'categories' => 'balance.emails',
                    'logFile' => 'balance-emails.log',
                    'firstLine' => '﻿datetime      type      userId      destination',
                ],
            ],
        ],
        'mailer' => [
            'class' => Mailer::class,
            'defaultDomain' => 'yaenergetik.ru',
        ],
        'mailNotification' => [
            'class' => MailNotificationCollector::class,
            'address' => ConfigHelper::getEnv('MAILNOTIF_ADDR'),
            'mailbox' => ConfigHelper::getEnv('MAILNOTIF_BOX'),
            'username' => ConfigHelper::getEnv('MAILNOTIF_USER'),
            'password' => ConfigHelper::getEnv('MAILNOTIF_PASS'),
        ],
        'minio' => [
            'class' => MinioClient::class,
            'apiUrl' => ConfigHelper::getEnv('MINIO_CONSOLE_API_URL', 'http://192.168.1.197:9000/api/v1'),
            'accessKey' => ConfigHelper::getEnv('MINIO_ADMIN_KEY', 'minioadmin'),
            'secretKey' => ConfigHelper::getEnv('MINIO_ADMIN_SECRET', 'minioadmin'),
        ],
        'network' => [
            'class' => NetworkClient::class,
        ],
        'partnerProgramManager' => [
            'class' => PartnerProgramManager::class,
        ],
        'pdf' => [
            'class' => Pdf::class,
            'binary' => ConfigHelper::getEnv('WKHTMLTOPDF_BIN'),
            'processTimeout' => 180,
        ],
        'reportFactory' => [
            'class' => ReportFactory::class,
        ],
        'search' => [
            'class' => ElasticSearch::class,
            'hosts' => ConfigHelper::getEnvAsArray('ELASTICSEARCH_HOSTS'),
        ],
        'settingsStorage' => [
            'class' => SettingsStorage::class,
        ],
        'taskQueue' => [
            'class' => TaskQueue::class,
        ],
        've' => [
            'class' => ValueEncryption::class,
            'key' => ConfigHelper::getEnv('VALUE_ENCRYPTION_KEY'),
        ],
        'ybg' => [
            'class' => BackgroundServer::class,
            'socket' => ConfigHelper::getEnv('YBG_SOCKET'),
        ],
        'assetManager' => [
            'class' => AssetManager::class,
            'config' => [
                'res' => AssetTypes::getAlias(AssetTypes::TYPE_RES),
                'js' => AssetTypes::getAlias(AssetTypes::TYPE_JS),
                'css' => AssetTypes::getAlias(AssetTypes::TYPE_CSS),
                'kojs' => AssetTypes::getAlias(AssetTypes::TYPE_KOJS),
                'langPackage' => 'application.lang.js.project',
            ]
        ],
        'branding' => [
            'class' => BrandingProvider::class,
            'user' => Yii::app()->user->getModel(), // null если не авторизован
            'partnerManager' => Yii::app()->partnerProgramManager,
            'defaultAppName' => 'My Application',
            'sourceLanguage' => 'en',
        ],
        'databaseResolver' => ['class' => DatabaseResolver::class],
        'jsonResponseHandler' => ['class' => JsonResponseHandler::class],
        'redirectHandler' => ['class' => RedirectHandler::class],
        'languageInitializer' => ['class' => LanguageInitializer::class],
        'csrfHandler' => ['class' => CsrfHandler::class],
    ],
    'import' => $imports,
    'modules' => [
        'calc' => [
            'defaultController' => 'price',
            'class' => CalcModule::class,
        ],
        'customer' => [
            'class' => CustomerModule::class,
        ],
        'partner' => [
            'class' => PartnerModule::class,
        ],
        'podis' => [
            'class' => PodisModule::class,
        ],
        'ldap' => [
            'class' => LDAPModule::class,
        ],
        'supportChat' => [
            'class' => SupportChatModule::class,
        ],
        'successManagement' => [
            'class' => SuccessManagementModule::class,
        ],
    ],
    'params' => [
        'YiiMailer' => [
            'layout' => 'mail',
            'layoutPath' => 'application.views.layouts',
            'savePath' => 'application.runtime.mail',
            'testMode' => ConfigHelper::getEnvAsBoolean('YAENERGETIK_MAILER_TEST'),
        ],
        'systemSbisAccount' => [
            'username' => ConfigHelper::getEnv('YAENERGETIK_SBIS_USERNAME'),
            'password' => ConfigHelper::getEnv('YAENERGETIK_SBIS_PASSWORD'),
            'accountId' => ConfigHelper::getEnv('YAENERGETIK_SBIS_ACCOUNTID'),
        ],
        'exportAnalyticsKey' => ConfigHelper::getEnv('EXPORT_ANALYTICS_KEY') ?? false,
        'infoEmail' => ConfigHelper::getEnv('YAENERGETIK_INFO_MAIL_ADDR', 'info@example.ru'),
        'supportEmail' => ConfigHelper::getEnv('YAENERGETIK_SUPPORT_MAIL_ADDR', 'support@example.ru'),
        'adminEmail' => ConfigHelper::getEnv('YAENERGETIK_ADMIN_MAIL_ADDR', 'admin@example.ru'),
        'billingEmail' => ConfigHelper::getEnv('YAENERGETIK_BILLING_MAIL_ADDR', 'billing@example.ru'),
        'billingReportsEmail' => ConfigHelper::getEnv('YAENERGETIK_BILLING_REPORTS_MAIL_ADDR', 'billing-reports@example.ru'),
        'reportEmail' => ConfigHelper::getEnv('YAENERGETIK_REPORT_MAIL_ADDR', 'report@example.ru'),
        'userEmail' => ConfigHelper::getEnv('YAENERGETIK_USER_MAIL_ADDR', 'user@example.ru'),
        'systemEmail' => ConfigHelper::getEnv('YAENERGETIK_SYSTEM_MAIL_ADDR', 'system@example.ru'),
        'boxEnvironment' => ConfigHelper::getEnvAsBoolean('YAENERGETIK_BOX', false),
        'maxTasksCount' => ConfigHelper::getEnv('TASK_QUEUE_MAX_TASKS_COUNT', 3000),
        'multipleAscueServers' => ConfigHelper::getEnvAsBoolean('YAENERGETIK_MULTIPLE_ASCUE', false),
        'sendReportsFromNetwork' => ConfigHelper::getEnvAsBoolean('YAENERGETIK_SEND_REPORTS_FROM_NETWORK', false),
    ],
];
if (file_exists(__DIR__ . '/main-local.php')) {
    $mainConfig = CMap::mergeArray($mainConfig, require __DIR__ . '/main-local.php');
}

return $mainConfig;
