<?php

require_once 'config.php';

class openvpn
{

    protected $log = true;

    public function __construct($arg)
    {

        $this->init_uniq_vpn_config();

        $this->init_uniq_session_and_connect_to_it();

        if($arg == 'p' || $arg == 'd' || $arg == 'c' || $arg == 'r'){

            $status = $this->get_session_connectivity_status();

            switch ($arg){
                case 'p':{

                    switch ($status){
                        case 'Paused':{
                            break;
                        }
                        case 'Connected':{
                            $this->pause_vpn_connection();
                            if($this->get_session_connectivity_status() != 'Paused'){
                                throw new Exception('unable to pause vpn connection');
                            }
                            break;
                        }
                    }
                    break;
                }
                case 'c':{

                    switch ($status){
                        case 'Paused':{
                            $this->resume_vpn_connection();
                            if($this->get_session_connectivity_status() != 'Connected'){
                                throw new Exception('unable to connect vpn connection');
                            }
                            break;
                        }
                        case 'Connected':{
                            break;
                        }
                    }
                    break;
                }
                case 'r':{

                    $this->restart_vpn_connection();
                    if($this->get_session_connectivity_status() != 'Connected'){
                        throw new Exception('unable to restart vpn connection');
                    }
                    break;
                }
                case 'd':{

                    $this->disconnect_vpn_connection();
                    $session_status = $this->get_sessions_status();
                    if ($session_status != 'No Sessions Available') {

                        throw new Exception('unable to disconnect vpn connection');
                    }
                    break;
                }
            }

        }
        else{
            $this->show('provided argument is not valid: '.$arg.PHP_EOL);
        }

    }

    public function init_uniq_vpn_config()
    {

        if ($this->log)
            $this->show('initializing a uniq vpn config with name: '.PHP_EOL.vpn_name);


        $this->import_vpn_config_if_not_exist();
        $config_status = $this->get_vpn_config_status();
        if ($config_status == 'Imported More Than One Time') {

            $this->flush_all_configs_with_provided_vpn_name();
        }

        $this->import_vpn_config_if_not_exist();
        $config_status = $this->get_vpn_config_status();
        if ($config_status != 'Existed') {

            throw new Exception('unable to manage configs');
        }

        if ($this->log)
            $this->show('uniq vpn config is correct');

    }

    public function import_vpn_config_if_not_exist()
    {

        if ($this->log)
            $this->show('importing vpn config if not exist');

        $config_status = $this->get_vpn_config_status();
        if ($config_status == 'Not Exist') {
            $this->import_vpn_config();

        }

        if ($this->log)
            $this->show('check if imported config is correct');

        $config_status = $this->get_vpn_config_status();
        if ($config_status == 'Not Exist') {
            throw new Exception('problem while loading vpn config!!!');
        }

        if ($this->log)
            $this->show('new config successfully mounted');

    }

    public function get_vpn_config_status()
    {
        if ($this->log)
            $this->show('check for vpn config status');

        $count_of_configs_with_the_same_name = $this->get_count_number_of_provided_config_with_the_same_name();

        $status = null;
        if ($count_of_configs_with_the_same_name == 0) {
            $status = 'Not Exist';
        }
        if ($count_of_configs_with_the_same_name == 1) {
            $status = 'Existed';
        }
        if ($count_of_configs_with_the_same_name > 1) {
            $status = 'Imported More Than One Time';
        }

        if($status === null){
            throw new LogicException('config status did not match predefined logic');
        }

        if ($this->log)
            $this->show('current status of config: '.PHP_EOL.$status);

        return $status;

    }

    public function get_count_number_of_provided_config_with_the_same_name()
    {
        if ($this->log)
            $this->show('finding count of configs with the same name');

        $config_list = $this->get_vpn_config_list();

        $config_count = 0;
        foreach ($config_list as $config) {

            if (str_contains($config, vpn_name)) {
                $config_count++;
            }
        }

        if ($this->log)
            $this->show('current count of imported config with the same name: '.PHP_EOL.$config_count);

        return $config_count;
    }

    public function get_vpn_config_list()
    {
        if ($this->log)
            $this->show('finding all available openvpn configs');

        exec('openvpn3 configs-list', $config_list);

        if ($this->log)
            $this->show('current config list according to openvpn');

        if ($this->log)
        $this->show($config_list);

        return $config_list;
    }

    public function show($v){

        echo PHP_EOL;
        print_r($v);
        echo PHP_EOL;

    }

    public function import_vpn_config()
    {

        if ($this->log)
            $this->show('importing config with the name: '.PHP_EOL.vpn_name);

        exec('openvpn3 config-import --config ' . vpn_name, $import_config_result);

        if ($this->log)
            $this->show('imported config result according to openvpn');

        if ($this->log)
            $this->show($import_config_result);

    }

    public function flush_all_configs_with_provided_vpn_name()
    {

        if ($this->log)
            $this->show('trying to dismount all configs with the same name');

        $config_list = $this->get_vpn_config_list();

        $config_paths_to_unset = [];
        foreach ($config_list as $key => $config) {
            if (str_contains($config, vpn_name)) {
                $config_paths_to_unset[] = $config_list[$key - 2];
            }
        }
        if (count($config_paths_to_unset) <= 0) {
            throw new Exception('more than one vpn name found but unable to find config paths');
        }

        foreach ($config_paths_to_unset as $config_path) {

            if (!$this->unset_vpn_config_with_path($config_path)) {
                throw new Exception('unable to delete config with path:' . PHP_EOL . $config_path . PHP_EOL);
            }
        }
    }

    public function unset_vpn_config_with_path($path)
    {

        if ($this->log)
            $this->show('dismounting config with path :'.PHP_EOL.$path);

        exec('./remove_config.sh ' . $path, $remove_config_with_path_result);

        if ($this->log)
            $this->show('result of removing config according to openvpn');

        if ($this->log)
            $this->show($remove_config_with_path_result);

        $is_config_removed = false;
        foreach ($remove_config_with_path_result as $value) {
            if (str_contains('Configuration removed.', $value)) {
                $is_config_removed = true;
            }
        }

        if ($this->log)
            $this->show('result of removing config: '.PHP_EOL.$is_config_removed);

        return $is_config_removed;
    }

    public function init_uniq_session_and_connect_to_it()
    {
        if ($this->log)
            $this->show('initializing uniq session with name: '.PHP_EOL.vpn_name);

        $session_status = $this->get_sessions_status();
        if ($this->log)
            $this->show('current session status: '.PHP_EOL.$session_status);

        if ($session_status == 'More Than One Session With The Same Name Available') {
            $this->terminate_all_sessions_with_the_same_name_as_vpn_name();
        }

        $session_status = $this->get_sessions_status();
        if ($this->log)
            $this->show('current session status: '.PHP_EOL.$session_status);

        if ($session_status == 'No Sessions Available') {

            $this->connect_to_vpn();
        }

        $session_status = $this->get_sessions_status();
        if ($this->log)
            $this->show('current session status: '.PHP_EOL.$session_status);

        if ($session_status == 'One Session Available') {
            return;
        }

        throw new LogicException('connect to uniq session logic have failed');
    }

    public function get_sessions_status()
    {
        if ($this->log)
            $this->show('finding sessions status');

        $sessions_list = $this->get_sessions_list();

        if ($sessions_list[0] == 'No sessions available') {
            $status = 'No Sessions Available';

            if ($this->log)
                $this->show('current session status is: '.PHP_EOL.$status);

            return $status;
        }

        $sessions_count = $this->get_count_of_sessions_with_the_same_name();

        $status = null;
        if ($sessions_count == 1) {
            $status = 'One Session Available';
        }
        elseif ($sessions_count > 1) {
            $status = 'More Than One Session With The Same Name Available';
        }

        if ($status === null){

            throw new LogicException('sessions status did not match provided logic');
        }

        if ($this->log)
            $this->show('current session status is: '.PHP_EOL.$status);

        return $status;
    }

    public function get_sessions_list()
    {

        if ($this->log)
            $this->show('finding all sessions info from openvpn');

        exec('openvpn3 sessions-list', $vpn_sessions);

        if ($this->log)
            $this->show('all sessions information according to openvpn');

        if ($this->log)
            $this->show($vpn_sessions);

        return $vpn_sessions;

    }

    public function get_count_of_sessions_with_the_same_name()
    {
        if ($this->log)
            $this->show('finding count of mounted sessions with the same name');

        $sessions_list = $this->get_sessions_list();

        $sessions_count = 0;
        foreach ($sessions_list as $value) {
            if (str_contains($value, vpn_name)) {
                $sessions_count++;
            }
        }

        if ($this->log)
            $this->show('current count of sessions with same name: '.PHP_EOL.$sessions_count);

        return $sessions_count;

    }

    public function terminate_all_sessions_with_the_same_name_as_vpn_name()
    {
        if ($this->log)
            $this->show('terminating all session with the same name as vpn name');

        $sessions_path_to_terminate = $this->get_sessions_path_to_terminate();

        foreach ($sessions_path_to_terminate as $path) {

            $this->terminate_session_whit_given_path($path);
        }

        if ($this->log)
            $this->show('all sessions with the same name as vpn name terminated successfully');
    }

    public function get_sessions_path_to_terminate()
    {
        if ($this->log)
            $this->show('finding all sessions with the same name to terminate');

        $sessions_list = $this->get_sessions_list();

        $sessions_to_terminate_path = [];
        foreach ($sessions_list as $key => $value) {
            if (str_contains($value, vpn_name)) {

                $key_to_terminate = $key - 3;
                $session_path = explode('Path: ', $sessions_list[$key_to_terminate]);
                $sessions_to_terminate_path[] = $session_path[1];
            }
        }
        if ($this->log)
            $this->show('all sessions with same name to terminate are: ');

        if ($this->log)
            $this->show($sessions_to_terminate_path);

        return $sessions_to_terminate_path;
    }

    public function terminate_session_whit_given_path($path)
    {
        if ($this->log)
            $this->show('terminating session with path: '.PHP_EOL.$path);

        exec('openvpn3 session-manage --disconnect --path ' . $path . ' ', $vpn_disconnect);

        if ($this->log)
            $this->show($vpn_disconnect);

        if ($vpn_disconnect[0] != 'Initiated session shutdown.') {
            throw new Exception('unable to terminate sessions with path: ' . PHP_EOL . $path . PHP_EOL);
        }

        if ($this->log)
            $this->show('session terminated successfully');

    }

    public function connect_to_vpn()
    {

        if($this->log)
            $this->show('connecting to ' . vpn_name);

        exec('./connect.sh ' . vpn_name . ' ' . vpn_username . ' ' . vpn_password, $session_start_result);

        if ($this->log)
            $this->show('result of connection according to openvpn');

        if ($this->log)
            $this->show($session_start_result);
    }

    public function get_session_connectivity_status()
    {
        if ($this->log)
            $this->show('finding vpn session connectivity status');

        /*
         * session status according to openvpn
         *  connected
         *  connection paused
         * */

        $session_info = $this->get_session_info();
        $session_status = $session_info['status'];

        $status = null;
        if($session_status == 'connection paused'){
            $status ='Paused';
        }

        if($session_status == 'connected'){
            $status ='Connected';
        }

        if ($status === null){
            throw new LogicException('finding connection status logic have been failed current status'.PHP_EOL.$session_status.PHP_EOL);
        }

        if ($this->log)
            $this->show('current status of connection is: '.PHP_EOL.$status);

        return $status;

    }

    public function get_session_info()
    {
        if ($this->log)
            $this->show('finding current session status');

        $sessions_list = $this->get_sessions_list();

        $index_of_session = null;

        foreach ($sessions_list as $key => $value) {

            if (str_contains($value, vpn_name)) {
                $index_of_session = $key;
                break;
            }
        }

        if ($index_of_session === null) {
            throw new LogicException('could not find index of mounted session');
        }

        $raw_session_path = $sessions_list[$index_of_session - 3];
        $raw_session_owner = $sessions_list[$index_of_session - 1];
        $raw_session_status = $sessions_list[$index_of_session + 2];

        $session_path = explode('Path: ', $raw_session_path)[1];

        $session_raw_owner = explode('Owner: ', $raw_session_owner)[1];
        $session_raw_owner = explode('Device: ', $session_raw_owner);
        $session_owner = $session_raw_owner[0];
        $session_device = $session_raw_owner[1];

        $session_status = explode('Status: Connection, Client ', $raw_session_status)[1];

        $session_info = [
            'path' => trim($session_path),
            'owner' => trim($session_owner),
            'status' => trim($session_status),
            'device' => trim($session_device),
        ];

        if ($this->log)
            $this->show('current session information are: ');

        if ($this->log)
            $this->show($session_info);

        return $session_info;
    }

    public function pause_vpn_connection(){

        if ($this->log)
            $this->show('pausing vpn connection');

        exec('openvpn3 session-manage --pause --config '.vpn_name.' ',$vpn_pause);

        if ($this->log)
            $this->show('result of pausing vpn according to open vpn');

        if ($this->log)
            $this->show($vpn_pause);

    }

    public function resume_vpn_connection(){

        if ($this->log)
            $this->show('resuming vpn connection');

        exec('openvpn3 session-manage --resume --config '.vpn_name.' ',$vpn_resume);

        if ($this->log)
            $this->show('result of resuming vpn according to openvpn');

        if ($this->log)
            $this->show($vpn_resume);
    }

    public function restart_vpn_connection(){

        if ($this->log)
            $this->show('restarting vpn connection');

        exec('openvpn3 session-manage --restart --config '.vpn_name.' ',$vpn_resume);


        if ($this->log)
            $this->show('result of restarting connection according to openvpn');

        if ($this->log)
            $this->show($vpn_resume);
    }

    public function disconnect_vpn_connection(){

        if ($this->log)
            $this->show('disconnecting vpn connection');

        exec('openvpn3 session-manage --disconnect --config '.vpn_name.' ',$vpn_disconnect);

        if ($this->log)
            $this->show('result of disconnecting vpn according to openvpn');

        if ($this->log)
            $this->show($vpn_disconnect);

    }
}



//print_r($argv);

if(array_key_exists(1,$argv)){

    $o = new openvpn($argv[1]);
}


