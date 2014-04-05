#!/usr/bin/env php
<?php

require('common.php');
require('soap_config.php');

if (empty($argv[1]) || !preg_match('~[a-zA-Z0-9\-]{2,16}~', $argv[1])) {
    echo "Usage: php ispcreate.php sitename:[a-zA-Z0-9\\-]{2,16}\n";
    exit;
}

$user = $argv[1];
$pass = password();

$client = new SoapClient(null, array(
    'location'   => $soap_location,
    'uri'        => $soap_uri,
    'trace'      => 1,
    'exceptions' => 1
));


try {
    if ($session_id = $client->login($username, $password)) {
        echo "Logged successfull. Session ID:" . $session_id . "\n";
    }

    // create domain
    $params = array(
        'server_id'             => $server_id,
        'ip_address'            => '*',
        'domain'                => $user . '.' . $domain,
        'type'                  => 'vhost',
        'parent_domain_id'      => 0,
        'vhost_type'            => 'name',
        'hd_quota'              => -1,
        'traffic_quota'         => -1,
        'cgi'                   => 'y',
        'ssi'                   => 'n',
        'suexec'                => 'y',
        'errordocs'             => 1,
        'is_subdomainwww'       => 1,
        'subdomain'             => 'www.',
        'php'                   => 'fast-cgi',
        'ruby'                  => 'n',
        'redirect_type'         => '',
        'redirect_path'         => '',
        'ssl'                   => 'n',
        'ssl_state'             => '',
        'ssl_locality'          => '',
        'ssl_organisation'      => '',
        'ssl_organisation_unit' => '',
        'ssl_country'           => '',
        'ssl_domain'            => $user . '.' . $domain    ,
        'ssl_request'           => '',
        'ssl_cert'              => '',
        'ssl_bundle'            => '',
        'ssl_action'            => '',
        'stats_password'        => '',
        'stats_type'            => 'webalizer',
        'allow_override'        => 'All',
        'apache_directives'     => '',
        'php_open_basedir'      => '/',
        'custom_php_ini'        => '',
        'backup_interval'       => '',
        'backup_copies'         => 1,
        'active'                => 'y',
        'traffic_quota_lock'    => 'n',
        'pm_process_idle_timeout' => 10,
        'pm_max_requests' => 0,
    );

    $domain_id = $client->sites_web_domain_add($session_id, $client_id, $params, $readonly = false);

    echo "Web Domain ID: " . $domain_id . "\n";

    // create database user
    $params = array(
        'server_id'         => $server_id,
        'database_user'     => "c{$client_id}_{$user}",
        'database_password' => $pass
    );

    $database_user_id = $client->sites_database_user_add($session_id, $client_id, $params);

    echo "Database user ID: " . $database_user_id . "\n";

    //create database

    $params = array(
        'server_id'           => $server_id,
        'parent_domain_id'    => $domain_id,
        'type'                => 'mysql',
        'database_name'       => "c{$client_id}_{$user}",
        'database_user_id'    => $database_user_id,
        'database_ro_user_id' => '0',
        'database_charset'    => 'UTF8',
        'remote_access'       => 'y',
        'remote_ips'          => '',
        'backup_interval'     => 'none',
        'backup_copies'       => 1,
        'active'              => 'y'
    );

    $database_id = $client->sites_database_add($session_id, $client_id, $params);

    echo "Database ID: " . $database_id . "\n";

    //create ftp user
    $params = array(
        'server_id'        => $server_id,
        'parent_domain_id' => $domain_id,
        'username'         => "c{$client_id}_{$user}",
        'password'         => $pass,
        'quota_size'       => -1,
        'active'           => 'y',
        'uid'              => 'web' . $domain_id,
        'gid'              => 'client' . $client_id,
        'dir'              => '/var/www/clients/client' . $client_id . '/web' . $domain_id,
        'quota_files'      => -1,
        'ul_ratio'         => -1,
        'dl_ratio'         => -1,
        'ul_bandwidth'     => -1,
        'dl_bandwidth'     => -1
    );

    $affected_rows = $client->sites_ftp_user_add($session_id, $client_id, $params);

    echo "FTP User ID: " . $affected_rows . "\n";

    //create ssh user
    $params = array(
        'server_id'        => $server_id,
        'parent_domain_id' => $domain_id,
        'username'         => "c{$client_id}_{$user}",
        'password'         => $pass,
        'quota_size'       => -1,
        'active'           => 'y',
        'puser'            => 'web' . $domain_id,
        'pgroup'           => 'client' . $client_id,
        'shell'            => '/bin/bash',
        'dir'              => '/var/www/clients/client' . $client_id . '/web' . $domain_id,
        'chroot'           => ''
    );

    $affected_rows = $client->sites_shell_user_add($session_id, $client_id, $params);

    echo "Shell User ID: " . $affected_rows . "\n";

    //update domain

	$domain_record = $client->sites_web_domain_get($session_id, $domain_id);

	//* Change parameters
	$domain_record['custom_php_ini'] =
        'safe_mode = Off'.
        'upload_max_filesize = 40M'.
        'post_max_size = 40M'.
        'error_reporting = 2047'.
        'display_errors = 1';

	$domain_record['apache_directives'] =
	    'TimeOut 1800'.
	    'FcgidIOTimeout 1800'.
	    'DocumentRoot /var/www/clients/client'.$client_id.'/web'.$domain_id.'/web/public/'.
	    'DirectoryIndex index.php';

	$domain_record['document_root'] = '/var/www/clients/client'.$client_id.'/web'.$domain_id.'/web/public/';

	$client->sites_web_domain_update($session_id, $client_id, $domain_id, $domain_record);

	echo "Domain updated\n";

    echo "\nCredantials:\n\n";
    echo "Username: c{$client_id}_{$user}\n";
    echo "Password: {$pass}\n";

    echo "\nDone!\n\n";



} catch (SoapFault $e) {
    echo $client->__getLastResponse();
    die('SOAP Error: ' . $e->getMessage());
}

?>
