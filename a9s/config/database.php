<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, examples of configuring each database platform that is
    | supported by Laravel is shown below to make development simple.
    |
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DATABASE_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'pool' => [
                'max_connections' => 100,  // Ukuran pool
                'idle_timeout' => 60,     // Dalam detik
            ],
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                PDO::ATTR_TIMEOUT => 5, // 5 menit
                PDO::ATTR_PERSISTENT => true, // Koneksi persisten,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION wait_timeout=300, net_read_timeout=300, net_write_timeout=300",
            ]) : [],
            'retries' => 3, // Jumlah percobaan ulang
            'retry_delay' => 200, // Delay antar percobaan dalam detik
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        // 'sqlsrv' => [
        //     'driver' => 'sqlsrv',
        //     'url' => env('DATABASE_URL'),
        //     'host' => env('DB_HOST', 'localhost'),
        //     'port' => env('DB_PORT', '1433'),
        //     'database' => env('DB_DATABASE', 'forge'),
        //     'username' => env('DB_USERNAME', 'forge'),
        //     'password' => env('DB_PASSWORD', ''),
        //     'charset' => 'utf8',
        //     'prefix' => '',
        //     'prefix_indexes' => true,
        //     // 'encrypt' => env('DB_ENCRYPT', 'yes'),
        //     // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        // ],

        // 'sqlsrv' => [
        //     'driver' => 'sqlsrv',
        //     'url' => env('DATABASE_URL'),
        //     'host' =>'10.10.26.251',
        //     'port' => '1433',
        //     'database' => 'AS_WBKENCANA',
        //     'username' => 'sa',
        //     'password' => 'Server.2015',
        //     'charset' => 'utf8',
        //     'prefix' => '',
        //     'prefix_indexes' => true,
        //     // 'encrypt' => env('DB_ENCRYPT', 'yes'),
        //     'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'true'),
        // ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL_SVR'),
            'host' => env('DB_HOST_SVR', 'localhost'),
            'port' => env('DB_PORT_SVR', '1433'),
            'database' => env('DB_DATABASE_SVR', 'forge'),
            'username' => env('DB_USERNAME_SVR', 'forge'),
            'password' => env('DB_PASSWORD_SVR', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'encrypt' => env('DB_ENCRYPT', 'yes'),
            'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', true),
            'options' => [
                'TrustServerCertificate' => true,
                // Optional: 'Encrypt' => true,
            ],
        ],

        'KPN' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL_SVR'),
            'host' => env('DB_HOST_SVR', 'localhost'),
            'port' => env('DB_PORT_SVR', '1433'),
            'database' => 'as_kencana_kprs2',
            'username' => env('DB_USERNAME_SVR', 'forge'),
            'password' => env('DB_PASSWORD_SVR', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'encrypt' => env('DB_ENCRYPT', 'yes'),
            'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', true),
            'options' => [
                'TrustServerCertificate' => true,
                // Optional: 'Encrypt' => true,
            ],
        ],
        'KAS' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL_SVR'),
            'host' => env('DB_HOST_SVR', 'localhost'),
            'port' => env('DB_PORT_SVR', '1433'),
            'database' => 'as_kencana_kas2',
            'username' => env('DB_USERNAME_SVR', 'forge'),
            'password' => env('DB_PASSWORD_SVR', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'encrypt' => env('DB_ENCRYPT', 'yes'),
            'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', true),
            'options' => [
                'TrustServerCertificate' => true,
                // Optional: 'Encrypt' => true,
            ],
        ],
        'KUS' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL_SVR'),
            'host' => env('DB_HOST_SVR', 'localhost'),
            'port' => env('DB_PORT_SVR', '1433'),
            'database' => 'as_kencana_kus2',
            'username' => env('DB_USERNAME_SVR', 'forge'),
            'password' => env('DB_PASSWORD_SVR', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'encrypt' => env('DB_ENCRYPT', 'yes'),
            'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', true),
            'options' => [
                'TrustServerCertificate' => true,
                // Optional: 'Encrypt' => true,
            ],
        ],
        'ARP' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL_SVR'),
            'host' => env('DB_HOST_SVR', 'localhost'),
            'port' => env('DB_PORT_SVR', '1433'),
            'database' => 'as_kencana_arp2',
            'username' => env('DB_USERNAME_SVR', 'forge'),
            'password' => env('DB_PASSWORD_SVR', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'encrypt' => env('DB_ENCRYPT', 'yes'),
            'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', true),
            'options' => [
                'TrustServerCertificate' => true,
                // Optional: 'Encrypt' => true,
            ],        ],
        'KAP' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL_SVR'),
            'host' => env('DB_HOST_SVR', 'localhost'),
            'port' => env('DB_PORT_SVR', '1433'),
            'database' => 'as_kencana_kap2',
            'username' => env('DB_USERNAME_SVR', 'forge'),
            'password' => env('DB_PASSWORD_SVR', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'encrypt' => env('DB_ENCRYPT', 'yes'),
            'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', true),
            'options' => [
                'TrustServerCertificate' => true,
                // Optional: 'Encrypt' => true,
            ],
        ],
        'SMP' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL_SVR'),
            'host' => env('DB_HOST_SVR', 'localhost'),
            'port' => env('DB_PORT_SVR', '1433'),
            'database' => 'as_kencana_smp',
            'username' => env('DB_USERNAME_SVR', 'forge'),
            'password' => env('DB_PASSWORD_SVR', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'encrypt' => env('DB_ENCRYPT', 'yes'),
            'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', true),
            'options' => [
                'TrustServerCertificate' => true,
                // Optional: 'Encrypt' => true,
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];
