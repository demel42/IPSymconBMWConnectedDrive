<?php

declare(strict_types=1);

class BMWConnectedDrive extends IPSModule
{
    // Model
    public static $BMW_MODEL_ELECTRIC = 1;
    public static $BMW_MODEL_HYBRID = 2;
    public static $BMW_MODEL_STANDARD = 3;

    // chargingConnector
    public static $BMW_CONNECTOR_UNKNOWN = -1;
    public static $BMW_CONNECTOR_DISCONNECTED = 0;
    public static $BMW_CONNECTOR_CONNECTED = 1;

    // chargingStatus
    public static $BMW_CHARGING_UNKNOWN = -1;
    public static $BMW_CHARGING_NO = 0;
    public static $BMW_CHARGING_ACTIVE = 1;
    public static $BMW_CHARGING_ENDED = 2;
    public static $BMW_CHARGING_PAUSED = 3;

    // GoogleMap
    public static $BMW_GOOGLEMAP_ROADMAP = 0;
    public static $BMW_GOOGLEMAP_SATELLITE = 1;
    public static $BMW_GOOGLEMAP_HYBRID = 2;
    public static $BMW_GOOGLEMAP_TERRAIN = 3;

    // Area
    public static $BMW_AREA_GERMANY = 1;
    public static $BMW_AREA_SWITZERLAND = 2;
    public static $BMW_AREA_EUROPE = 3;
    public static $BMW_AREA_USA = 4;
    public static $BMW_AREA_CHINA = 5;
    public static $BMW_AREA_OTHER = 6;

    // Konfigurationen

    public static $apiHost = [
        'NorthAmerica' => 'b2vapi.bmwgroup.us',
        'RestOfWorld'  => 'b2vapi.bmwgroup.com',
    ];

    public static $oauthHost = [
        'NorthAmerica' => 'login.bmwusa.com',
        'RestOfWorld'  => 'customer.bmwgroup.com',
    ];

    public static $remoteServiceHost = [
        'NorthAmerica' => 'cocoapi.bmwgroup.us',
        'RestOfWorld'  => 'cocoapi.bmwgroup.com',
    ];

    public static $oauthAuthorization = [
        'NorthAmerica' => 'NTQzOTRhNGItYjZjMS00NWZlLWI3YjItOGZkM2FhOTI1M2FhOmQ5MmYzMWMwLWY1NzktNDRmNS1hNzdkLTk2NmY4ZjAwZTM1MQ==',
        'RestOfWorld'  => 'MzFjMzU3YTAtN2ExZC00NTkwLWFhOTktMzNiOTcyNDRkMDQ4OmMwZTMzOTNkLTcwYTItNGY2Zi05ZDNjLTg1MzBhZjY0ZDU1Mg==',
    ];

    public static $oauthCodeVerifier = [
        'NorthAmerica' => 'KDarcVUpgymBDCgHDH0PwwMfzycDxu1joeklioOhwXA',
        'RestOfWorld'  => '7PsmfPS5MpaNt0jEcPpi-B7M7u0gs1Nzw6ex0Y9pa-0',
    ];

    public static $oauthClientId = [
        'NorthAmerica' => '54394a4b-b6c1-45fe-b7b2-8fd3aa9253aa',
        'RestOfWorld'  => '31c357a0-7a1d-4590-aa99-33b97244d048',
    ];

    public static $oauthState = [
        'NorthAmerica' => 'rgastJbZsMtup49-Lp0FMQ',
        'RestOfWorld'  => 'cEG9eLAIi6Nv-aaCAniziE_B6FPoobva3qr5gukilYw',
    ];

    public static $oauth_login_endpoint = '/gcdm/oauth/authenticate';
    public static $oauth_token_endpoint = '/gcdm/oauth/token';

    public static $user_agent = __CLASS__;

    public static $oauth_redirect_uri = 'com.bmw.connected://oauth';
    public static $oauth_scope = 'openid profile email offline_access smacc vehicle_data perseus dlm svds cesim vsapi remote_services fupo authenticate_user';

    public static $remoteService_endpoint = '/eadrax-vrccs/v2/presentation/remote-commands';

    public static $legacy_app_id = 'dbf0a542-ebd1-4ff0-a9a7-55172fbfce35';

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('user', '');
        $this->RegisterPropertyString('password', '');
        $this->RegisterPropertyString('vin', '');
        $this->RegisterPropertyInteger('bmw_server', 1);
        $this->RegisterPropertyInteger('model', 1);
        $this->RegisterPropertyBoolean('active_climate', false);
        $this->RegisterPropertyBoolean('active_lock', false);
        $this->RegisterPropertyBoolean('active_lock_2actions', false);
        $this->RegisterPropertyBoolean('active_flash_headlights', false);
        $this->RegisterPropertyBoolean('active_vehicle_finder', false);
        $this->RegisterPropertyBoolean('active_lock_data', false);
        $this->RegisterPropertyBoolean('active_honk', false);
        $this->RegisterPropertyBoolean('active_picture', true);
        $this->RegisterPropertyBoolean('active_googlemap', false);
        $this->RegisterPropertyString('googlemap_api_key', '');
        $this->RegisterPropertyInteger('horizontal_mapsize', 600);
        $this->RegisterPropertyInteger('vertical_mapsize', 400);
        $this->RegisterPropertyBoolean('active_service', false);
        $this->RegisterPropertyBoolean('active_current_position', false);
        $this->RegisterPropertyInteger('UpdateInterval', 10);

        $this->RegisterTimer('BMWDataUpdate', 0, 'BMW_DataUpdate(' . $this->InstanceID . ');');

        $this->SetMultiBuffer('bmw_car_interface', '');
        $this->SetMultiBuffer('bmw_navigation_interface', '');
        $this->SetMultiBuffer('bmw_efficiency_interface', '');
        $this->SetMultiBuffer('bmw_chargingprofile_interface', '');
        $this->SetMultiBuffer('bmw_mapupdate_interface', '');
        $this->SetMultiBuffer('bmw_store_interface', '');
        $this->SetMultiBuffer('bmw_specs_interface', '');
        $this->SetMultiBuffer('bmw_service_interface', '');
        $this->SetMultiBuffer('bmw_service_partner_interface', '');
        $this->SetMultiBuffer('bmw_history_interface', '');
        $this->SetMultiBuffer('bmw_dynamic_interface', '');
        $this->SetMultiBuffer('bmw_image_interface', '');
        $this->SetMultiBuffer('bmw_image_interface', '');

        $this->RegisterAttributeString('ApiRefreshToken', '');
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $associations = [];
        $associations[] = [0, 'Start', '', 0x3ADF00];
        $this->RegisterProfileAssociation('BMW.Start', 'Execute', '', '', 0, 0, 0, 0, VARIABLETYPE_INTEGER, $associations);

        $associations = [];
        $associations[] = [0, $this->Translate('roadmap'), '', 0x3ADF00];
        $associations[] = [self::$BMW_GOOGLEMAP_SATELLITE, $this->Translate('satellite'), '', 0x3ADF00];
        $associations[] = [self::$BMW_GOOGLEMAP_HYBRID, $this->Translate('hybrid'), '', 0x3ADF00];
        $associations[] = [self::$BMW_GOOGLEMAP_TERRAIN, $this->Translate('terrain'), '', 0x3ADF00];
        $this->RegisterProfileAssociation('BMW.Googlemap', 'Car', '', '', 0, 3, 0, 0, VARIABLETYPE_INTEGER, $associations);

        $associations = [];
        $associations[] = [self::$BMW_CONNECTOR_UNKNOWN, $this->Translate('unknown'), '', 0xEE0000];
        $associations[] = [self::$BMW_CONNECTOR_DISCONNECTED, $this->Translate('disconnected'), '', -1];
        $associations[] = [self::$BMW_CONNECTOR_CONNECTED, $this->Translate('connected'), '', 0x228B22];
        $this->RegisterProfileAssociation('BMW.ConnectorStatus', '', '', '', 0, 0, 0, 0, VARIABLETYPE_INTEGER, $associations);

        $associations = [];
        $associations[] = [self::$BMW_CHARGING_UNKNOWN, $this->Translate('unknown'), '', 0xEE0000];
        $associations[] = [self::$BMW_CHARGING_NO, $this->Translate('no charging'), '', -1];
        $associations[] = [self::$BMW_CHARGING_ACTIVE, $this->Translate('charging active'), '', 0x228B22];
        $associations[] = [self::$BMW_CHARGING_ENDED, $this->Translate('charging ended'), '', 0x0000FF];
        $associations[] = [self::$BMW_CHARGING_PAUSED, $this->Translate('charging paused'), '', -1];
        $this->RegisterProfileAssociation('BMW.ChargingStatus', '', '', '', 0, 0, 0, 0, VARIABLETYPE_INTEGER, $associations);

        $this->RegisterProfile('BMW.Mileage', 'Distance', '', ' ' . $this->GetMileageUnit(), 0, 0, 0, 0, VARIABLETYPE_INTEGER);
        $this->RegisterVariableInteger('bmw_mileage', $this->Translate('mileage'), 'BMW.Mileage', 4);
        $this->RegisterProfile('BMW.TankCapacity', 'Gauge', '', ' Liter', 0, 0, 0, 0, VARIABLETYPE_FLOAT);
        $this->RegisterVariableFloat('bmw_tank_capacity', $this->Translate('tank capacity'), 'BMW.TankCapacity', 5);
        $this->RegisterProfile('BMW.RemainingRange', 'Gauge', '', ' km', 0, 0, 0, 0, VARIABLETYPE_FLOAT);
        $this->RegisterVariableFloat('bmw_remaining_range', $this->Translate('remaining range'), 'BMW.RemainingRange', 6);

        $model = $this->ReadPropertyInteger('model');
        if ($model != self::$BMW_MODEL_STANDARD) { // standard, no electric
            $this->RegisterVariableFloat('bmw_remaining_electric_range', $this->Translate('remaining electric range'), 'BMW.RemainingRange', 6);
            $this->RegisterProfile('BMW.ChargingLevel', '', '', ' %', 0, 0, 0, 0, VARIABLETYPE_FLOAT);
            $this->RegisterVariableFloat('bmw_charging_level', $this->Translate('current battery charge level (SoC)'), 'BMW.ChargingLevel', 6);
            $this->RegisterVariableInteger('bmw_connector_status', $this->Translate('connector status'), 'BMW.ConnectorStatus', 6);
            $this->RegisterVariableInteger('bmw_charging_status', $this->Translate('charging status'), 'BMW.ChargingStatus', 6);
            $this->RegisterVariableInteger('bmw_charging_end', $this->Translate('charging end'), '~UnixTimestampTime', 6);
            $this->RegisterProfile('BMW.StateofCharge', '', '', ' kWh', 0, 0, 0, 1, VARIABLETYPE_FLOAT);
            $this->RegisterVariableFloat('bmw_socMax', $this->Translate('maximum net load capacity (SoH)'), 'BMW.StateofCharge', 8);
            $this->RegisterProfile('BMW.BatteryCapacity', '', '', ' %', 0, 0, 0, 0, VARIABLETYPE_FLOAT);
            $this->RegisterVariableFloat('bmw_battery_size', $this->Translate('gross batteryіcapacity'), 'BMW.ChargingLevel', 6);

            $this->RegisterProfile('BMW.Distance', '', '', ' ' . $this->GetMileageUnit(), 0, 0, 0, 0, VARIABLETYPE_FLOAT);
            $this->RegisterVariableFloat('lasttrip_distance', $this->Translate('Last trip: distance'), 'BMW.Distance', 209);
            $this->RegisterProfile('BMW.Duration', '', '', ' min', 0, 0, 0, 0, VARIABLETYPE_FLOAT);
            $this->RegisterVariableFloat('lasttrip_duration', $this->Translate('Last trip: duration'), 'BMW.Duration', 210);
            $this->RegisterProfile('BMW.Consumption', '', '', ' l/100 km', 0, 0, 0, 1, VARIABLETYPE_FLOAT);
            $this->RegisterVariableFloat('lasttrip_avg_consumed', $this->Translate('Last trip: avrg consumption'), 'BMW.Consumption', 211);
            $this->RegisterProfile('BMW.ElectricRatio', '', '', ' %', 0, 0, 0, 0, VARIABLETYPE_FLOAT);
            $this->RegisterVariableFloat('lasttrip_electric_ratio', $this->Translate('Last trip: electric ratio'), 'BMW.ElectricRatio', 212);
            $this->RegisterVariableInteger('lasttrip_tstamp', $this->Translate('Last trip: end timestamp'), '~UnixTimestamp', 213);

            $this->RegisterVariableFloat('lifetime_distance', $this->Translate('Life time: distance'), 'BMW.Distance', 214);
            $this->RegisterProfile('BMW.SavedLiters', '', '', ' Liter', 0, 0, 0, 0, VARIABLETYPE_FLOAT);
            $this->RegisterVariableFloat('lifetime_save_liters', $this->Translate('Life time: saved liters'), 'BMW.SavedLiters', 215);
            $this->RegisterVariableInteger('lifetime_reset_tstamp', $this->Translate('Life time: reset timestamp'), '~UnixTimestampDate', 216);

            $associations = [];
            $associations[] = [0, json_decode('"\u2606\u2606\u2606\u2606\u2606"'), '', -1];
            $associations[] = [1, json_decode('"\u2605\u2606\u2606\u2606\u2606"'), '', -1];
            $associations[] = [2, json_decode('"\u2605\u2605\u2606\u2606\u2606"'), '', -1];
            $associations[] = [3, json_decode('"\u2605\u2605\u2605\u2606\u2606"'), '', -1];
            $associations[] = [4, json_decode('"\u2605\u2605\u2605\u2605\u2606"'), '', -1];
            $associations[] = [5, json_decode('"\u2605\u2605\u2605\u2605\u2605"'), '', -1];
            $this->RegisterProfileAssociation('BMW.Efficiency', '', '', '', 0, 3, 0, 0, VARIABLETYPE_INTEGER, $associations);

            $this->RegisterVariableInteger('effeciency_consumption', $this->Translate('Efficiency: consumption'), 'BMW.Efficiency', 217);
            $this->RegisterVariableInteger('effeciency_driving', $this->Translate('Efficiency: driving mode'), 'BMW.Efficiency', 218);
            $this->RegisterVariableInteger('effeciency_charging', $this->Translate('Efficiency: charging behaviour'), 'BMW.Efficiency', 219);
            $this->RegisterVariableInteger('effeciency_electric', $this->Translate('Efficiency: electric driving'), 'BMW.Efficiency', 220);
        } else {
            $this->UnregisterVariable('bmw_remaining_electric_range');
            $this->UnregisterVariable('bmw_charging_level');
            $this->UnregisterVariable('bmw_connector_status');
            $this->UnregisterVariable('bmw_charging_status');
            $this->UnregisterVariable('bmw_charging_end');
            $this->UnregisterVariable('bmw_socMax');

            $this->UnregisterVariable('lasttrip_km');
            $this->UnregisterVariable('lasttrip_duration');
            $this->UnregisterVariable('lasttrip_avg_consumed');
            $this->UnregisterVariable('lasttrip_electric_ratio');
            $this->UnregisterVariable('lasttrip_tstamp');

            $this->UnregisterVariable('lifetime_distance');
            $this->UnregisterVariable('lifetime_save_liters');
            $this->UnregisterVariable('lifetime_reset_tstamp');
        }

        $this->RegisterVariableString('bmw_history', $this->Translate('course'), '~HTMLBox', 7);

        // Variablen löschen, Zugriff nun via BMW_GetRawData() unter gleichem Variablennamen
        $this->UnregisterVariable('bmw_dynamic_interface');
        $this->UnregisterVariable('bmw_navigation_interface');
        $this->UnregisterVariable('bmw_efficiency_interface');
        $this->UnregisterVariable('bmw_image_interface');
        $this->UnregisterVariable('bmw_mapupdate_interface');
        $this->UnregisterVariable('bmw_history_interface');
        $this->UnregisterVariable('bmw_car_interface');
        $this->UnregisterVariable('bmw_store_interface');
        $this->UnregisterVariable('bmw_specs_interface');
        $this->UnregisterVariable('bmw_service_interface');
        $this->UnregisterVariable('bmw_service_partner_interface');
        $this->UnregisterVariable('bmw_remote_services_interface');
        $this->UnregisterVariable('bmw_chargingprofile_interface');

        $this->ValidateConfiguration();
    }

    private function ValidateConfiguration()
    {
        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');
        $vin = $this->ReadPropertyString('vin');

        $this->SetUpdateIntervall();

        //check user and password
        if ($user == '' || $password == '' || $vin == '') {
            $this->SetStatus(205);
            return;
        }

        $model = $this->ReadPropertyInteger('model');
        if ($model == self::$BMW_MODEL_ELECTRIC) {
            $this->SendDebug(__FUNCTION__, 'electric selected', 0);
        }
        if ($model == self::$BMW_MODEL_HYBRID) {
            $this->SendDebug(__FUNCTION__, 'hybrid selected', 0);
        }
        if ($model == self::$BMW_MODEL_STANDARD) {
            $this->SendDebug(__FUNCTION__, 'standard, no electric selected', 0);
        }
        $active_climate = $this->ReadPropertyBoolean('active_climate');
        $active_lock = $this->ReadPropertyBoolean('active_lock');
        $active_lock_2actions = $this->ReadPropertyBoolean('active_lock_2actions');
        $active_flash_headlights = $this->ReadPropertyBoolean('active_flash_headlights');
        $active_vehicle_finder = $this->ReadPropertyBoolean('active_vehicle_finder');
        $active_lock_data = $this->ReadPropertyBoolean('active_lock_data');
        $active_honk = $this->ReadPropertyBoolean('active_honk');
        $active_picture = $this->ReadPropertyBoolean('active_picture');
        $active_googlemap = $this->ReadPropertyBoolean('active_googlemap');
        $active_service = $this->ReadPropertyBoolean('active_service');
        $active_current_position = $this->ReadPropertyBoolean('active_current_position');

        if ($active_climate) {
            $this->RegisterVariableInteger('bmw_start_air_conditioner', $this->Translate('start air conditioner'), 'BMW.Start', 20);
            $this->EnableAction('bmw_start_air_conditioner');
        } else {
            $this->UnregisterVariable('bmw_start_air_conditioner');
        }
        if ($active_lock_2actions) {
            $this->RegisterVariableInteger('bmw_start_lock', $this->Translate('lock door'), 'BMW.Start', 21);
            $this->RegisterVariableInteger('bmw_start_unlock', $this->Translate('unlock door'), 'BMW.Start', 22);
            $this->EnableAction('bmw_start_lock');
            $this->EnableAction('bmw_start_unlock');
        } elseif ($active_lock) {
            $this->RegisterVariableBoolean('bmw_start_lock', $this->Translate('lock'), '~Lock', 21);
            $this->EnableAction('bmw_start_lock');
        } else {
            $this->UnregisterVariable('bmw_start_lock');
            $this->UnregisterVariable('bmw_start_unlock');
        }
        if ($active_flash_headlights) {
            $this->RegisterVariableInteger('bmw_start_flash_headlights', $this->Translate('flash headlights'), 'BMW.Start', 23);
            $this->EnableAction('bmw_start_flash_headlights');
        } else {
            $this->UnregisterVariable('bmw_start_flash_headlights');
        }
        if ($active_honk) {
            $this->RegisterVariableInteger('bmw_start_honk', $this->Translate('honk'), 'BMW.Start', 24);
            $this->EnableAction('bmw_start_honk');
        } else {
            $this->UnregisterVariable('bmw_start_honk');
        }

        if ($active_vehicle_finder) {
            $this->RegisterVariableInteger('bmw_start_vehicle_finder', $this->Translate('search vehicle'), 'BMW.Start', 25);
            $this->RegisterVariableString('bmw_position_request_status', $this->Translate('position request status'), '', 27);
            $this->EnableAction('bmw_start_vehicle_finder');
        } else {
            $this->UnregisterVariable('bmw_start_vehicle_finder');
            $this->UnregisterVariable('bmw_position_request_status');
        }
        if ($active_picture) {
            $this->RegisterVariableString('bmw_car_picture', $this->Translate('picture'), '~HTMLBox', 1);
            $this->RegisterVariableInteger('bmw_car_picture_zoom', $this->Translate('car zoom'), '~Intensity.100', 2);
            $this->EnableAction('bmw_car_picture_zoom');
            $this->RegisterProfile('BMW.Perspective', 'Eyes', '', '°', 0, 360, 30, 0, VARIABLETYPE_INTEGER);
            $this->RegisterVariableInteger('bmw_perspective', $this->Translate('perspective'), 'BMW.Perspective', 3);
            $this->EnableAction('bmw_perspective');
        } else {
            $this->UnregisterVariable('bmw_car_picture');
            $this->UnregisterVariable('bmw_car_picture_zoom');
            $this->UnregisterVariable('bmw_perspective');
        }

        if ($active_googlemap) {
            $this->RegisterVariableString('bmw_car_googlemap', $this->Translate('map'), '~HTMLBox', 10);
            $this->RegisterVariableInteger('bmw_googlemap_maptype', $this->Translate('map type'), 'BMW.Googlemap', 11);
            $this->RegisterVariableInteger('bmw_googlemap_zoom', $this->Translate('map zoom'), '~Intensity.100', 12);
            $this->EnableAction('bmw_googlemap_maptype');
            $this->EnableAction('bmw_googlemap_zoom');
        } else {
            $this->UnregisterVariable('bmw_car_googlemap');
            $this->UnregisterVariable('bmw_googlemap_maptype');
            $this->UnregisterVariable('bmw_googlemap_zoom');
        }

        if ($active_current_position) {
            $this->RegisterProfile('BMW.Location', 'Car', '', ' °', 0, 0, 0, 5, VARIABLETYPE_FLOAT);
            $this->RegisterVariableFloat('bmw_current_latitude', $this->Translate('current latitude'), 'BMW.Location', 13);
            $this->RegisterVariableFloat('bmw_current_longitude', $this->Translate('current longitude'), 'BMW.Location', 14);
        } else {
            $this->UnregisterVariable('bmw_current_latitude');
            $this->UnregisterVariable('bmw_current_longitude');
        }

        if ($active_service) {
            $this->RegisterVariableString('bmw_service', $this->Translate('Service'), '~HTMLBox', 16);
        } else {
            $this->UnregisterVariable('bmw_service');
        }

        if ($active_lock_data) {
            $this->RegisterVariableBoolean('bmw_doorDriverFront', $this->Translate('door driver front'), '~Lock', 30);
            $this->RegisterVariableBoolean('bmw_doorDriverRear', $this->Translate('door driver rear'), '~Lock', 31);
            $this->RegisterVariableBoolean('bmw_doorPassengerFront', $this->Translate('door passenger front'), '~Lock', 32);
            $this->RegisterVariableBoolean('bmw_doorPassengerRear', $this->Translate('door passenger rear'), '~Lock', 33);
            $this->RegisterVariableBoolean('bmw_windowDriverFront', $this->Translate('window driver front'), '~Lock', 34);
            $this->RegisterVariableBoolean('bmw_windowDriverRear', $this->Translate('window driver rear'), '~Lock', 35);
            $this->RegisterVariableBoolean('bmw_windowPassengerFront', $this->Translate('window passenger front'), '~Lock', 36);
            $this->RegisterVariableBoolean('bmw_windowPassengerRear', $this->Translate('window passenger rear'), '~Lock', 37);
            $this->RegisterVariableBoolean('bmw_trunk', $this->Translate('trunk'), '~Lock', 38);
            $this->RegisterVariableBoolean('bmw_rearWindow', $this->Translate('rear window'), '~Lock', 39);
            $this->RegisterVariableBoolean('bmw_convertibleRoofState', $this->Translate('convertible roof'), '~Lock', 40);
            $this->RegisterVariableBoolean('bmw_hood', $this->Translate('hood'), '~Lock', 41);
            $this->RegisterVariableBoolean('bmw_doorLockState', $this->Translate('door lock state'), '~Lock', 42);
        } else {
            $this->UnregisterVariable('bmw_doorDriverFront');
            $this->UnregisterVariable('bmw_doorDriverRear');
            $this->UnregisterVariable('bmw_doorPassengerFront');
            $this->UnregisterVariable('bmw_doorPassengerRear');
            $this->UnregisterVariable('bmw_windowDriverFront');
            $this->UnregisterVariable('bmw_windowDriverRear');
            $this->UnregisterVariable('bmw_windowPassengerFront');
            $this->UnregisterVariable('bmw_windowPassengerRear');
            $this->UnregisterVariable('bmw_trunk');
            $this->UnregisterVariable('bmw_rearWindow');
            $this->UnregisterVariable('bmw_convertibleRoofState');
            $this->UnregisterVariable('bmw_hood');
            $this->UnregisterVariable('bmw_doorLockState');
        }

        $this->RegisterVariableInteger('bmw_last_status_update', $this->Translate('last status update'), '~UnixTimestamp', 99);

        // Status Aktiv
        $this->SetStatus(102);
    }

    public function SetUpdateIntervall(int $Minutes = null)
    {
        if (!($Minutes > 0)) {
            $Minutes = $this->ReadPropertyInteger('UpdateInterval');
        }
        $interval = $Minutes * 60 * 1000;
        $this->SendDebug(__FUNCTION__, 'minutes=' . $Minutes, 0);
        $this->SetTimerInterval('BMWDataUpdate', $interval);
    }

    public function DataUpdate()
    {
        $active_picture = $this->ReadPropertyBoolean('active_picture');
        $model = $this->ReadPropertyInteger('model');

        $this->GetVehicleStatus();
        $this->GetDynamicData();
        if ($model != self::$BMW_MODEL_STANDARD) { // standard, no electric
            $this->GetNavigationData();
            $this->GetEfficiency();
        }
        $this->GetMapUpdate();

        if ($active_picture) {
            $angle = GetValue($this->GetIDForIdent('bmw_perspective'));
            $zoom = GetValue($this->GetIDForIdent('bmw_car_picture_zoom'));
            $this->GetCarPictureForAngle(intval($angle), intval($zoom));
        }

        $this->GetStore();
        $this->GetService();
        $this->GetServicePartner();

        if ($model != self::$BMW_MODEL_STANDARD) { // standard, no electric
            $this->GetChargingProfile();
        }

        $this->GetRemoteServices();
    }

    protected function GetMileageUnit()
    {
        return 'km';
    }

    private function GetBMWServerURL($mode)
    {
        $region = $this->GetRegion();
        $url = 'https://' . self::$apiHost[$region];
        return $url;

        if ($mode == 1) {
            $area = $this->ReadPropertyInteger('bmw_server');
            switch ($area) {
            case self::$BMW_AREA_GERMANY:
                $url = 'https://www.bmw-connecteddrive.de';
                break;
            case self::$BMW_AREA_SWITZERLAND:
                $url = 'https://www.bmw-connecteddrive.ch';
                break;
            case self::$BMW_AREA_USA:
                $url = 'https://b2vapi.bmwgroup.us';
                break;
            default:
                $url = 'https://b2vapi.bmwgroup.com';
                break;
            }
        } else {
            $region = $this->GetRegion();
            $url = 'https://' . self::$apiHost[$region];
        }
        return $url;
    }

    public function GetToken()
    {
        $this->SetBuffer('Token_1', '');
        $this->GetToken_1();
        $this->GetToken_2();
    }

    private function GetToken_1()
    {
        $data = $this->GetBuffer('Token_1');
        if ($data != false) {
            $jtoken = json_decode($data, true);
            $access_token = isset($jtoken['access_token']) ? $jtoken['access_token'] : '';
            $expiration = isset($jtoken['expiration']) ? $jtoken['expiration'] : 0;
            if ($expiration > time()) {
                $this->SendDebug(__FUNCTION__, 'old access_token=' . $access_token . ', valid until ' . date('d.m.y H:i:s', $expiration), 0);
                return $access_token;
            }
            $this->SendDebug(__FUNCTION__, 'access_token expired', 0);
        } else {
            $this->SendDebug(__FUNCTION__, 'no/empty buffer "Token_1"', 0);
        }

        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');

        $auth_api = 'https://customer.bmwgroup.com/gcdm/oauth/authenticate';
        $this->SendDebug(__FUNCTION__, 'url=' . $auth_api, 0);

        $header = [
            'Content-Type: application/x-www-form-urlencoded'
        ];
        $this->SendDebug(__FUNCTION__, 'header=' . print_r($header, true), 0);

        $postfields = [
            'username'      => $user,
            'password'      => $password,
            'client_id'     => self::$legacy_app_id,
            'redirect_uri'  => 'https://www.bmw-connecteddrive.com/app/default/static/external-dispatch.html',
            'response_type' => 'token',
            'locale'        => 'DE-de'
        ];
        $this->SendDebug(__FUNCTION__, 'postfields=' . print_r($postfields, true), 0);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $auth_api);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_COOKIESESSION, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if (empty($response) || $response === false || !empty($curl_error)) {
            $this->SendDebug(__FUNCTION__, 'empty answer from Bearerinterface: ' . $curl_error, 0);
            return false;
        }

        // extract token
        preg_match('/access_token=([\w\d]+).*token_type=(\w+).*expires_in=(\d+)/', $response, $matches);

        // check token type
        if (empty($matches[2]) || $matches[2] !== 'Bearer') {
            $this->SendDebug(__FUNCTION__, 'no remote token received - username or password might be wrong: ' . $response, 0);
            return false;
        }

        $access_token = $matches[1];
        $expiration = time() + $matches[3] - 60;
        $this->SendDebug(__FUNCTION__, 'set access_token=' . $access_token . ', valid until ' . date('d.m.y H:i:s', $expiration), 0);

        $jtoken = [
            'access_token'            => $access_token,
            'expiration'              => $expiration
        ];
        $this->SetBuffer('Token_1', json_encode($jtoken));
        return $access_token;
    }

    // Token_2 -> ApiAccessToken
    // $this->SetStatus(self::$IS_NOLOGIN);

    private function GetRegion()
    {
        $area = $this->ReadPropertyInteger('bmw_server');
        switch ($area) {
        case self::$BMW_AREA_USA:
            $region = 'NorthAmerica';
            break;
        default:
            $region = 'RestOfWorld';
            break;
        }
        return $region;
    }

    private function Login()
    {
        $this->WriteAttributeString('ApiRefreshToken', '');
        $this->SetBuffer('Token_2', '');

        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');
        $region = $this->GetRegion();

        $oauth_client_id = self::$oauthClientId[$region];
        $oauth_state = self::$oauthState[$region];
        $oauth_host = self::$oauthHost[$region];
        $oauth_authorization = self::$oauthAuthorization[$region];
        $oauth_code_verifier = self::$oauthCodeVerifier[$region];

        $auth_url = 'https://' . $oauth_host . self::$oauth_login_endpoint;
        $token_url = 'https://' . $oauth_host . self::$oauth_token_endpoint;

        $postfields = [
            'client_id'       => $oauth_client_id,
            'response_type'   => 'code',
            'redirect_uri'    => self::$oauth_redirect_uri,
            'state'           => $oauth_state,
            'nonce'           => 'login_nonce',
            'scope'           => self::$oauth_scope,
            'grant_type'      => 'authorization_code',
            'username'        => $user,
            'password'        => $password,
        ];

        $header = [
            'User-Agent: ' . self::$user_agent,
            'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
        ];

        $this->SendDebug(__FUNCTION__, 'url=' . $auth_url, 0);
        $this->SendDebug(__FUNCTION__, '....header=' . print_r($header, true), 0);
        $this->SendDebug(__FUNCTION__, '....postfields=' . print_r($postfields, true), 0);

        $time_start = microtime(true);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $auth_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2TLS);
        curl_setopt($ch, CURLOPT_HTTP09_ALLOWED, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));
        curl_setopt($ch, CURLOPT_COOKIEFILE, '');
        curl_setopt($ch, CURLOPT_HEADER, true);

        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // CHECK HTTP-CODE

        if ($response != false) {
            $curl_info = curl_getinfo($ch);
            $header_size = $curl_info['header_size'];
            $header = substr($response, 0, $header_size);
            $body = substr($response, $header_size);
            preg_match_all('|Set-Cookie: (.*);|U', $header, $results);
            $cookies = explode(';', implode(';', $results[1]));
        } else {
            $header = '';
            $body = '';
            $cookies = [];
        }

        $duration = round(microtime(true) - $time_start, 2);

        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);
        $this->SendDebug(__FUNCTION__, ' => header=' . $header, 0);
        $this->SendDebug(__FUNCTION__, ' => body=' . $body, 0);
        $this->SendDebug(__FUNCTION__, ' => cookies=' . print_r($cookies, true), 0);

        $jdata = json_decode($body, true);
        if ($jdata == false || isset($jdata['redirect_to']) == false) {
            $this->SendDebug(__FUNCTION__, 'missing element "redirect_to" in "' . $body . '"', 0);
            return false;
        }

        $redirect_uri = substr($jdata['redirect_to'], strlen('redirect_uri='));
        $redirect_parts = parse_url($redirect_uri);
        if ($redirect_parts == false || isset($redirect_parts['query']) == false) {
            $this->SendDebug(__FUNCTION__, 'missing element "query" in "' . $redirect_uri . '"', 0);
            return false;
        }

        parse_str($redirect_parts['query'], $redirect_opts);
        foreach (['client_id', 'response_type', 'state', 'scope', 'authorization'] as $key) {
            if (isset($redirect_opts[$key]) == false) {
                $this->SendDebug(__FUNCTION__, 'missing element "' . $key . '" in "' . $redirect_parts['query'] . '"', 0);
                return false;
            }
        }
        $this->SendDebug(__FUNCTION__, 'login#1 succedded, authorization="' . $redirect_opts['authorization'] . '"', 0);

        $header = [
            'User-Agent: ' . self::$user_agent,
            'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
        ];
        foreach ($cookies as $cookie) {
            $header[] = 'Cookie: ' . $cookie;
        }

        $postfields = [
            'client_id'     => $redirect_opts['client_id'],
            'response_type' => $redirect_opts['response_type'],
            'redirect_uri'  => self::$oauth_redirect_uri,
            'state'         => $redirect_opts['state'],
            'nonce'         => 'login_nonce',
            'scope'         => $redirect_opts['scope'],
            'authorization' => $redirect_opts['authorization'],
        ];

        $this->SendDebug(__FUNCTION__, 'url=' . $auth_url, 0);
        $this->SendDebug(__FUNCTION__, '....header=' . print_r($header, true), 0);
        $this->SendDebug(__FUNCTION__, '....postfields=' . print_r($postfields, true), 0);

        $time_start = microtime(true);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));

        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response != false) {
            $curl_info = curl_getinfo($ch);
            $header_size = $curl_info['header_size'];
            $header = substr($response, 0, $header_size);
            $body = substr($response, $header_size);
            preg_match_all('|Set-Cookie: (.*);|U', $header, $results);
            $cookies = explode(';', implode(';', $results[1]));
        } else {
            $header = '';
            $body = '';
            $cookies = [];
        }

        $duration = round(microtime(true) - $time_start, 2);

        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);
        $this->SendDebug(__FUNCTION__, ' => header=' . $header, 0);
        $this->SendDebug(__FUNCTION__, ' => body=' . $body, 0);
        $this->SendDebug(__FUNCTION__, ' => cookies=' . print_r($cookies, true), 0);

        $code = false;
        foreach (explode(PHP_EOL, $header) as $line) {
            if (preg_match('/^location: (.*)$/', $line, $r)) {
                $p = parse_url($r[1]);
                if (isset($p['query'])) {
                    parse_str($p['query'], $o);
                    if (isset($o['code'])) {
                        $code = $o['code'];
                    }
                }
            }
        }
        if ($code == false) {
            $this->SendDebug(__FUNCTION__, 'missing element "code" in "' . $header . '"', 0);
            return false;
        }

        $this->SendDebug(__FUNCTION__, 'login#2 succedded, code=' . $code, 0);

        $header = [
            'User-Agent: ' . self::$user_agent,
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . $oauth_authorization,
        ];
        foreach ($cookies as $cookie) {
            $header[] = 'Cookie: ' . $cookie;
        }

        $postfields = [
            'redirect_uri'  => self::$oauth_redirect_uri,
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'code_verifier' => $oauth_code_verifier,
        ];

        $this->SendDebug(__FUNCTION__, 'url=' . $token_url, 0);
        $this->SendDebug(__FUNCTION__, '....header=' . print_r($header, true), 0);
        $this->SendDebug(__FUNCTION__, '....postfields=' . print_r($postfields, true), 0);

        $time_start = microtime(true);

        curl_setopt($ch, CURLOPT_URL, $token_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));

        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response != false) {
            $curl_info = curl_getinfo($ch);
            $header_size = $curl_info['header_size'];
            $header = substr($response, 0, $header_size);
            $body = substr($response, $header_size);
        } else {
            $header = '';
            $body = '';
        }

        $duration = round(microtime(true) - $time_start, 2);

        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);
        $this->SendDebug(__FUNCTION__, ' => header=' . $header, 0);
        $this->SendDebug(__FUNCTION__, ' => body=' . $body, 0);

        curl_close($ch);

        echo 'errno=' . $cerrno . '(' . $cerror . '), httpcode=' . $httpcode . ', header=' . $header . ', body=' . $body . PHP_EOL;

        $jdata = json_decode($body, true);
        if ($jdata == false) {
            $this->SendDebug(__FUNCTION__, 'malformed body "' . $body . '"', 0);
            return false;
        }
        foreach (['access_token', 'refresh_token', 'expires_in'] as $key) {
            if (isset($jdata[$key]) == false) {
                $this->SendDebug(__FUNCTION__, 'missing element "' . $key . '" in "' . $body . '"', 0);
                return false;
            }
        }

        $access_token = $jdata['access_token'];
        $refresh_token = $jdata['refresh_token'];
        $expiration = time() + $jdata['expires_in'];
        $this->SendDebug(__FUNCTION__, 'new access_token=' . $access_token . ', refresh_token=' . $refresh_token . ', valid until ' . date('d.m.y H:i:s', $expiration), 0);
        $jtoken = [
            'access_token' => $access_token,
            'expiration'   => $expiration,
        ];
        $this->SetBuffer('Token_2', json_encode($jtoken));
        $this->WriteAttributeString('ApiRefreshToken', $refresh_token);
        return $access_token;
    }

    private function RefreshToken()
    {
        $region = $this->GetRegion();
        $oauth_authorization = self::$oauthAuthorization[$region];
        $oauth_host = self::$oauthHost[$region];

        $token_url = 'https://' . $oauth_host . self::$oauth_token_endpoint;

        $refresh_token = $this->ReadAttributeString('ApiRefreshToken');
        if ($refresh_token == false) {
            $access_token = $this->Login();
            if ($access_token == false) {
                $this->SendDebug(__FUNCTION__, 'login failed', 0);
            }
            return $access_token;
        } else {
            $this->SendDebug(__FUNCTION__, 'refresh_token=' . $refresh_token, 0);
        }

        $header = [
            'User-Agent: ' . self::$user_agent,
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . $oauth_authorization,
        ];

        $postfields = [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refresh_token,
        ];

        $this->SendDebug(__FUNCTION__, 'url=' . $token_url, 0);
        $this->SendDebug(__FUNCTION__, '....header=' . print_r($header, true), 0);
        $this->SendDebug(__FUNCTION__, '....postfields=' . print_r($postfields, true), 0);

        $time_start = microtime(true);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $token_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2TLS);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));
        curl_setopt($ch, CURLOPT_HEADER, true);

        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response != false) {
            $curl_info = curl_getinfo($ch);
            $header_size = $curl_info['header_size'];
            $header = substr($response, 0, $header_size);
            $body = substr($response, $header_size);
        } else {
            $header = '';
            $body = '';
        }

        $duration = round(microtime(true) - $time_start, 2);

        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);
        $this->SendDebug(__FUNCTION__, ' => header=' . $header, 0);
        $this->SendDebug(__FUNCTION__, ' => body=' . $body, 0);

        curl_close($ch);

        $jdata = json_decode($body, true);
        if ($jdata == false) {
            $this->SendDebug(__FUNCTION__, 'malformed body "' . $body . '"', 0);
            return false;
        }
        if (isset($jdata['error'])) {
            $this->SendDebug(__FUNCTION__, 'error=' . $jdata['error'], 0);
            return false;
        }

        foreach (['access_token', 'refresh_token', 'expires_in'] as $key) {
            if (isset($jdata[$key]) == false) {
                $this->SendDebug(__FUNCTION__, 'missing element "' . $key . '" in "' . $body . '"', 0);
                return false;
            }
        }

        $access_token = $jdata['access_token'];
        $refresh_token = $jdata['refresh_token'];
        $expiration = time() + $jdata['expires_in'];
        $this->SendDebug(__FUNCTION__, 'new access_token=' . $access_token . ', refresh_token=' . $refresh_token . ', valid until ' . date('d.m.y H:i:s', $expiration), 0);
        $jtoken = [
            'access_token' => $access_token,
            'expiration'   => $expiration,
        ];
        $this->SetBuffer('Token_2', json_encode($jtoken));
        $this->WriteAttributeString('ApiRefreshToken', $refresh_token);
        return $access_token;
    }

    private function GetToken_2()
    {
        $data = $this->GetBuffer('Token_2');
        if ($data != false) {
            $jtoken = json_decode($data, true);
            $access_token = isset($jtoken['access_token']) ? $jtoken['access_token'] : '';
            $expiration = isset($jtoken['expiration']) ? $jtoken['expiration'] : 0;
            if ($expiration > time()) {
                $this->SendDebug(__FUNCTION__, 'old access_token=' . $access_token . ', valid until ' . date('d.m.y H:i:s', $expiration), 0);
                return $access_token;
            }
            $this->SendDebug(__FUNCTION__, 'access_token expired', 0);
        } else {
            $this->SendDebug(__FUNCTION__, 'no/empty buffer "Token_2"', 0);
        }
        $access_token = $this->RefreshToken();
        return $access_token;
    }

    /**
     * Get Vehicle Data.
     *
     * @return mixed
     */
    public function GetVehicleData()
    {
        $command = '/webapi/v1/user/vehicles/';
        $response = $this->SendBMWAPI($command, '', 2);
        $data = json_decode((string) $response);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);

        return $data;
    }

    /**
     * Get Vehicle Info.
     *
     * @return mixed
     */
    public function GetVehicleInfo()
    {
        $command = '/api/me/vehicles/v2';
        $response = $this->SendBMWAPI($command, '', 2);
        $this->SetMultiBuffer('bmw_car_interface', $response);
        $data = json_decode((string) $response);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);

        return $data;
    }

    /**
     * Get Navigation Data.
     *
     * @return mixed
     */
    public function GetNavigationData()
    {
        $model = $this->ReadPropertyInteger('model');
        if ($model == self::$BMW_MODEL_STANDARD) { // standard, no electric
            $this->SetMultiBuffer('bmw_navigation_interface', '');
            return false;
        }

        $vin = $this->ReadPropertyString('vin');
        $command = '/api/vehicle/navigation/v1/' . $vin;

        $response = $this->SendBMWAPI($command, '', 1);
        $this->SetMultiBuffer('bmw_navigation_interface', $response);

        $data = json_decode((string) $response);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);

        $model = $this->ReadPropertyInteger('model');
        if ($model != self::$BMW_MODEL_STANDARD) { // standard, no electric
            if (isset($data->socmax)) {
                $socmax = floatval($data->socmax);
                $this->SetValue('bmw_socMax', $socmax);
            } elseif (isset($data->socMax)) {
                $socMax = floatval($data->socMax);
                $this->SetValue('bmw_socMax', $socMax);
            }
            if (isset($data->battery_size_max)) {
                $battery_size_max = floatval($data->battery_size_max);
                $this->SetValue('bmw_battery_size', $battery_size_max);
            }
        }

        return $data;
    }

    /**
     * Set car position to google map.
     *
     * @param $maptype
     * @param $zoom
     * @param null $latitude
     * @param null $longitude
     */
    protected function SetGoogleMap($maptype, $zoom, $latitude = null, $longitude = null)
    {
        $active_googlemap = $this->ReadPropertyBoolean('active_googlemap');
        $active_current_position = $this->ReadPropertyBoolean('active_current_position');

        if (!$active_googlemap) {
            return;
        }

        if (empty($latitude) || empty($longitude)) {
            if ($active_current_position) {
                $latitude = GetValue($this->GetIDForIdent('bmw_current_latitude'));
                $longitude = GetValue($this->GetIDForIdent('bmw_current_longitude'));
            }
            if ($latitude == '' || $longitude == '') {
                $data = $this->GetDynamicData();
                $carinfo = $data->attributesMap;
                if (isset($carinfo->gps_lng)) {
                    $longitude = $carinfo->gps_lng;
                }
                if (isset($carinfo->gps_lat)) {
                    $latitude = $carinfo->gps_lat;
                }
            }
        }
        if ($latitude != '' && $longitude != '') {
            $pos = number_format(floatval($latitude), 6, '.', '') . ',' . number_format(floatval($longitude), 6, '.', '');
            $horizontal_size = $this->ReadPropertyInteger('horizontal_mapsize');
            $vertical_value = $this->ReadPropertyInteger('vertical_mapsize');
            $markercolor = 'red';
            $api_key = $this->ReadPropertyString('googlemap_api_key');
            $url = 'https://maps.google.com/maps/api/staticmap?key=' . $api_key;
            $url .= '&center=' . rawurlencode($pos);
            // zoom 0 world - 21 building
            if ($zoom > 0) {
                $url .= '&zoom=' . rawurlencode(strval($zoom));
            }
            $url .= '&size=' . rawurlencode(strval($horizontal_size) . 'x' . strval($vertical_value));
            $url .= '&maptype=' . rawurlencode(strval($maptype));
            $url .= '&markers=' . rawurlencode('color:' . strval($markercolor) . '|' . strval($pos));
            $url .= '&sensor=true';

            $this->SendDebug(__FUNCTION__, 'url=' . $url, 0);
            $ausgabe = '<img src="' . $url . '" />';
            $this->SetValue('bmw_car_googlemap', $ausgabe); //Stringvariable HTML-Box
        }
    }

    /**
     * Set Google map type.
     *
     * @param $value
     */
    protected function SetGoogleMapType($value)
    {
        $active_googlemap = $this->ReadPropertyBoolean('active_googlemap');

        if (!$active_googlemap) {
            return;
        }

        $zoom_value = GetValue($this->GetIDForIdent('bmw_googlemap_zoom'));
        $zoom = round(($zoom_value / 100) * 21);
        $this->SetGoogleMap($this->GetGoogleMapType($value), $zoom);
    }

    /**
     * Get Google map type.
     *
     * @param $value
     *
     * @return string
     */
    protected function GetGoogleMapType($value)
    {
        $maptype = 'roadmap';
        if ($value == self::$BMW_GOOGLEMAP_ROADMAP) {
            $maptype = 'roadmap';
        } elseif ($value == self::$BMW_GOOGLEMAP_SATELLITE) {
            $maptype = 'satellite';
        } elseif ($value == self::$BMW_GOOGLEMAP_HYBRID) {
            $maptype = 'hybrid';
        } elseif ($value == self::$BMW_GOOGLEMAP_TERRAIN) {
            $maptype = 'terrain';
        }
        $this->SendDebug(__FUNCTION__, 'map type=' . $maptype, 0);
        return $maptype;
    }

    /**
     * Set Map Zoom.
     *
     * @param $zoom
     */
    protected function SetMapZoom($zoom)
    {
        $active_googlemap = $this->ReadPropertyBoolean('active_googlemap');
        $active_current_position = $this->ReadPropertyBoolean('active_current_position');

        if (!$active_googlemap || !$active_current_position) {
            return;
        }

        $latitude = GetValue($this->GetIDForIdent('bmw_current_latitude'));
        $longitude = GetValue($this->GetIDForIdent('bmw_current_longitude'));
        $maptype = $this->GetGoogleMapType(GetValue($this->GetIDForIdent('bmw_googlemap_maptype')));
        $this->SetGoogleMap($maptype, $zoom, $latitude, $longitude);
    }

    /**
     * Get Efficiency.
     *
     * @return mixed
     */
    public function GetEfficiency()
    {
        $model = $this->ReadPropertyInteger('model');
        if ($model == self::$BMW_MODEL_STANDARD) { // standard, no electric
            $this->SetMultiBuffer('bmw_efficiency_interface', '');
            return false;
        }

        $vin = $this->ReadPropertyString('vin');
        $command = '/api/vehicle/efficiency/v1/' . $vin;
        $response = $this->SendBMWAPI($command, '', 1);
        $this->SetMultiBuffer('bmw_efficiency_interface', $response);

        $model = $this->ReadPropertyInteger('model');
        if ($model != self::$BMW_MODEL_STANDARD) { // standard, no electric
            $data = json_decode((string) $response);
            $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);

            if (isset($data->lastTripList)) {
                $lastTripList = $data->lastTripList;
                $this->SendDebug(__FUNCTION__, 'lastTripList=' . print_r($lastTripList, true), 0);
                foreach ($lastTripList as $lastTrip) {
                    $name = $lastTrip->name;
                    $val = $lastTrip->lastTrip;
                    $this->SendDebug(__FUNCTION__, 'name=' . $name . ', val=' . $val, 0);

                    switch ($name) {
                        case 'LASTTRIP_DELTA_KM':
                            $this->SetValue('lasttrip_distance', $val);
                            break;
                        case 'LASTTRIP_DELTA_TIME':
                            $this->SetValue('lasttrip_duration', $val);
                            break;
                        case 'COMBINED_AVG_CONSUMED_LITERS_OVERALL':
                            $this->SetValue('lasttrip_avg_consumed', $val);
                            break;
                        case 'LASTTRIP_TIME_SEGMENT_END':
                            $ts = strtotime($val);
                            $this->SetValue('lasttrip_tstamp', $ts);
                            break;
                        case 'LASTTRIP_RATIO_ELECTRIC_DRIVEN_DISTANCE':
                            $this->SetValue('lasttrip_electric_ratio', $val);
                            break;
                    }
                }
            }
            if (isset($data->lifeTimeList)) {
                $lifeTimeList = $data->lifeTimeList;
                $this->SendDebug(__FUNCTION__, 'lifeTimeList=' . print_r($lifeTimeList, true), 0);
                foreach ($lifeTimeList as $lifeTime) {
                    $name = $lifeTime->name;
                    $val = $lifeTime->lifeTime;
                    $this->SendDebug(__FUNCTION__, 'name=' . $name . ', val=' . $val, 0);

                    switch ($name) {
                        case 'CUMULATED_ELECTRIC_DRIVEN_DISTANCE':
                            $this->SetValue('lifetime_distance', $val);
                            break;
                        case 'SAVED_LITERS_OVERALL':
                            $this->SetValue('lifetime_save_liters', $val);
                            break;
                        case 'TIMESTAMP_STATISTICS_RESET':
                            $ts = strtotime($val);
                            $this->SetValue('lifetime_reset_tstamp', $ts);
                            break;
                    }
                }
            }
            if (isset($data->characteristicList)) {
                $characteristicList = $data->characteristicList;
                $this->SendDebug(__FUNCTION__, 'characteristicList=' . print_r($characteristicList, true), 0);
                foreach ($characteristicList as $characteristic) {
                    $name = $characteristic->characteristic;
                    $val = $characteristic->quantity;
                    $this->SendDebug(__FUNCTION__, 'name=' . $name . ', val=' . $val, 0);

                    switch ($name) {
                        case 'CONSUMPTION':
                            $this->SetValue('effeciency_consumption', $val);
                            break;
                        case 'DRIVING_MODE':
                            $this->SetValue('effeciency_driving', $val);
                            break;
                        case 'CHARGING_BEHAVIOUR':
                            $this->SetValue('effeciency_charging', $val);
                            break;
                        case 'ELECTRIC_DRIVING':
                            $this->SetValue('effeciency_electric', $val);
                            break;
                    }
                }
            }
        }

        return $response;
    }

    /**
     * Get Charging Profile.
     *
     * @return mixed
     */
    public function GetChargingProfile()
    {
        $vin = $this->ReadPropertyString('vin');
        $command = '/api/vehicle/remoteservices/chargingprofile/v1/' . $vin;
        $response = $this->SendBMWAPI($command, '', 2);
        $this->SetMultiBuffer('bmw_chargingprofile_interface', $response);
        return $response;
    }

    /**
     * Get map update.
     *
     * @return mixed
     */
    public function GetMapUpdate()
    {
        $vin = $this->ReadPropertyString('vin');
        $command = '/api/me/service/mapupdate/download/v1/' . $vin;
        $response = $this->SendBMWAPI($command, '', 1);
        $this->SetMultiBuffer('bmw_mapupdate_interface', $response);
        return $response;
    }

    /**
     * Get Store Values.
     *
     * @return mixed
     */
    public function GetStore()
    {
        $vin = $this->ReadPropertyString('vin');
        $command = '/api/store/v2/' . $vin . '/offersAndPortfolios';
        $response = $this->SendBMWAPI($command, '', 2);
        $data = json_decode((string) $response, true);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);
        $this->SetMultiBuffer('bmw_store_interface', $response);
        return $response;
    }

    /**
     * Get service.
     *
     * @return mixed
     */
    public function GetService()
    {
        $vin = $this->ReadPropertyString('vin');
        $command = '/api/vehicle/service/v1/' . $vin;
        $response = $this->SendBMWAPI($command, '', 2);
        $data = json_decode((string) $response, true);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);
        $this->SetMultiBuffer('bmw_service_interface', $response);
        return $response;
    }

    /**
     * Get servive partner.
     *
     * @return mixed
     */
    public function GetServicePartner()
    {
        $vin = $this->ReadPropertyString('vin');
        $command = '/api/vehicle/servicepartner/v1/' . $vin;
        $response = $this->SendBMWAPI($command, '', 2);
        $data = json_decode((string) $response, true);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);
        $this->SetMultiBuffer('bmw_service_partner_interface', $response);
        return $response;
    }

    /**
     * Get Remote Service.
     *
     * @return mixed
     */
    public function GetRemoteServices()
    {
        $vin = $this->ReadPropertyString('vin');
        $command = '/webapi/v1/user/vehicles/' . $vin . '/serviceExecutionHistory';

        $response = $this->SendBMWAPI($command, '', 2);
        $this->SetMultiBuffer('bmw_history_interface', $response);
        $data = json_decode((string) $response, true);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);

        $services = [
            'CLIMATE_NOW'       => 'climate now',
            'CLIMATE_CONTROL'   => 'climate control',
            'DOOR_LOCK'         => 'door lock',
            'DOOR_UNLOCK'       => 'door unlock',
            'LIGHT_FLASH'       => 'light flash',
            'HORN_BLOW'         => 'horn blow',
            'VEHICLE_FINDER'    => 'find vehicle',
            'CHARGE_NOW'        => 'charge now',
            'CHARGING_CONTROL'  => 'charging control',
            'CHARGE_PREFERENCE' => 'charge preferences',
        ];
        $status = [
            'SUCCESS'       => 'success',
            'PENDING'       => 'pending',
            'INITIATED'     => 'initiated',
            'FAILED'        => 'failed',
            'ERROR'         => 'error',
            'CANCELLED'     => 'cancelled',
            'EXECUTED'      => 'executed',
            'NOT_EXECUTED'  => 'not_executed',
        ];
        $client = [
            'PORTAL'      => 'Web-Portal',
            'CDP'         => 'Web-Portal',
            'MOBILE_APP'  => 'App',
            'SmartPhone'  => 'App',
        ];

        $html = 'Keine Information zum Verlauf vorhanden';
        if ($data != '') {
            if (isset($data['errors'])) {
                $html = 'Datenabruf ist nicht möglich';
                $this->SendDebug(__CLASS__ . '::' . __FUNCTION__, 'got error: ' . json_encode($data), 0);
                $this->LogMessage(__CLASS__ . '::' . __FUNCTION__ . ': got error: ' . json_encode($data), KL_ERROR);
            } elseif (isset($data['serviceExecutionHistory'])) {
                $history = $data['serviceExecutionHistory'];
                $this->SendDebug(__FUNCTION__, 'history=' . print_r($history, true), 0);

                $html = "<style>\n";
                $html .= "th, td { padding: 2px 10px; text-align: left; } \n";
                $html .= "</style>\n";
                $html .= "<table>\n";
                $html .= '<tr>';
                $html .= '<th>' . $this->Translate('Moment') . '</th>';
                $html .= '<th>' . $this->Translate('Remote service') . '</th>';
                $html .= '<th>' . $this->Translate('State') . '</th>';
                $html .= '<th>' . $this->Translate('Channel') . '</th>';
                $html .= '</tr>';

                foreach ($history as $entry) {
                    $this->SendDebug(__FUNCTION__, 'entry=' . print_r($entry, true), 0);
                    $initiatedAt = $entry['initiatedAt'];
                    $_ts = strtotime($initiatedAt);
                    $ts = date('d.m. H:i:s', $_ts);

                    $_rst = $entry['serviceType'];
                    if (isset($services[$_rst])) {
                        $rst = $this->Translate($services[$_rst]);
                    } else {
                        $this->SendDebug(__CLASS__ . '::' . __FUNCTION__, 'unknown service "' . $_rst . '"', 0);
                        $this->LogMessage(__CLASS__ . '::' . __FUNCTION__ . ': unknown service "' . $_rst . '"', KL_DEBUG);
                        $rst = $this->Translate('unknown service') . ' "' . $_rst . '"';
                    }

                    $_st = $entry['status'];
                    if (isset($status[$_st])) {
                        $st = $this->Translate($status[$_st]);
                    } else {
                        $this->SendDebug(__CLASS__ . '::' . __FUNCTION__, 'unknown status "' . $_st . '"', 0);
                        $this->LogMessage(__CLASS__ . '::' . __FUNCTION__ . ': unknown status "' . $_st . '"', KL_DEBUG);
                        $st = $this->Translate('unknown status') . ' "' . $_st . '"';
                    }

                    $_clnt = $entry['client'];
                    if (isset($client[$_clnt])) {
                        $clnt = $this->Translate($client[$_clnt]);
                    } else {
                        $this->SendDebug(__CLASS__ . '::' . __FUNCTION__, 'unknown client "' . $_clnt . '"', 0);
                        $this->LogMessage(__CLASS__ . '::' . __FUNCTION__ . ': unknown client "' . $_clnt . '"', KL_DEBUG);
                        $clnt = $this->Translate('unknown client') . ' "' . $_clnt . '"';
                    }

                    $html .= "<tr>\n";
                    $html .= '<td>' . $ts . "</td>\n";
                    $html .= '<td>' . $rst . "</td>\n";
                    $html .= '<td>' . $st . "</td>\n";
                    $html .= '<td>' . $clnt . "</td>\n";
                    $html .= "</tr>\n";
                }
                $html .= "</table>\n";
            }
        }
        $this->SetValue('bmw_history', $html);

        return $response;
    }

    /**
     * Get dynamic data.
     *
     * @return mixed
     */
    public function GetDynamicData()
    {
        $active_lock = $this->ReadPropertyBoolean('active_lock');
        $active_lock_data = $this->ReadPropertyBoolean('active_lock_data');
        $active_googlemap = $this->ReadPropertyBoolean('active_googlemap');
        $active_service = $this->ReadPropertyBoolean('active_service');
        $active_current_position = $this->ReadPropertyBoolean('active_current_position');

        $vin = $this->ReadPropertyString('vin');
        $command = '/api/vehicle/dynamic/v1/' . $vin . '?offset=' . date('Z') / -60;
        $response = $this->SendBMWAPI($command, '', 2);
        $this->SetMultiBuffer('bmw_dynamic_interface', $response);

        $data = json_decode((string) $response);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);

        if (isset($data->attributesMap)) {
            $carinfo = $data->attributesMap;
            if (isset($carinfo->mileage)) {
                $mileage = $carinfo->mileage;
                $this->SetValue('bmw_mileage', $mileage);
            }
            if ($active_lock) {
                if (isset($carinfo->door_lock_state)) {
                    $doorLockState = $carinfo->door_lock_state;
                    $this->SetLockState('bmw_start_lock', $doorLockState);
                }
            }
            if ($active_lock_data) {
                if (isset($carinfo->door_driver_front)) {
                    $doorDriverFront = $carinfo->door_driver_front;
                    $this->SetLockState('bmw_doorDriverFront', $doorDriverFront);
                }
                if (isset($carinfo->door_driver_rear)) {
                    $doorDriverRear = $carinfo->door_driver_rear;
                    $this->SetLockState('bmw_doorDriverRear', $doorDriverRear);
                }
                if (isset($carinfo->door_passenger_front)) {
                    $doorPassengerFront = $carinfo->door_passenger_front;
                    $this->SetLockState('bmw_doorPassengerFront', $doorPassengerFront);
                }
                if (isset($carinfo->door_passenger_rear)) {
                    $doorPassengerRear = $carinfo->door_passenger_rear;
                    $this->SetLockState('bmw_doorPassengerRear', $doorPassengerRear);
                }
                if (isset($carinfo->window_driver_front)) {
                    $windowDriverFront = $carinfo->window_driver_front;
                    $this->SetLockState('bmw_windowDriverFront', $windowDriverFront);
                }
                if (isset($carinfo->window_driver_rear)) {
                    $windowDriverRear = $carinfo->window_driver_rear;
                    $this->SetLockState('bmw_windowDriverRear', $windowDriverRear);
                }
                if (isset($carinfo->window_passenger_front)) {
                    $windowPassengerFront = $carinfo->window_passenger_front;
                    $this->SetLockState('bmw_windowPassengerFront', $windowPassengerFront);
                }
                if (isset($carinfo->window_passenger_rear)) {
                    $windowPassengerRear = $carinfo->window_passenger_rear;
                    $this->SetLockState('bmw_windowPassengerRear', $windowPassengerRear);
                }
                if (isset($carinfo->trunk_state)) {
                    $trunk = $carinfo->trunk_state;
                    $this->SetLockState('bmw_trunk', $trunk);
                }
                if (isset($carinfo->rear_window)) {
                    $rearWindow = $carinfo->rear_window;
                    $this->SetLockState('bmw_rearWindow', $rearWindow);
                }
                if (isset($carinfo->convertible_roof_state)) {
                    $convertibleRoofState = $carinfo->convertible_roof_state;
                    $this->SetLockState('bmw_convertibleRoofState', $convertibleRoofState);
                }
                if (isset($carinfo->hood_state)) {
                    $hood = $carinfo->hood_state;
                    $this->SetLockState('bmw_hood', $hood);
                }
                if (isset($carinfo->door_lock_state)) {
                    $doorLockState = $carinfo->door_lock_state;
                    $this->SetLockState('bmw_doorLockState', $doorLockState);
                }
            }
            if (isset($carinfo->remaining_fuel)) {
                $remainingFuel = floatval($carinfo->remaining_fuel);
                $this->SetValue('bmw_tank_capacity', $remainingFuel);
            }
            if (isset($carinfo->beRemainingRangeFuel)) {
                $remaining_range = floatval($carinfo->beRemainingRangeFuel);
                $this->SetValue('bmw_remaining_range', $remaining_range);
            }

            $model = $this->ReadPropertyInteger('model');
            if ($model != self::$BMW_MODEL_STANDARD) { // standard, no electric
                if (isset($carinfo->beRemainingRangeElectricKm)) {
                    $electric_range = floatval($carinfo->beRemainingRangeElectricKm);
                    $this->SetValue('bmw_remaining_electric_range', $electric_range);
                }
                if (isset($carinfo->chargingLevelHv)) {
                    $charging_level = floatval($carinfo->chargingLevelHv);
                    $this->SetValue('bmw_charging_level', $charging_level);
                }

                $connector_status = self::$BMW_CONNECTOR_UNKNOWN;
                if (isset($carinfo->connectorStatus)) {
                    switch ($carinfo->connectorStatus) {
                        case 'DISCONNECTED':
                            $connector_status = self::$BMW_CONNECTOR_DISCONNECTED;
                            break;
                        case 'CONNECTED':
                            $connector_status = self::$BMW_CONNECTOR_CONNECTED;
                            break;
                        default:
                            $this->SendDebug(__FUNCTION__, 'unknown connectorStatus "' . $carinfo->connectorStatus . '"', 0);
                            break;
                    }
                }

                $charging_status = self::$BMW_CHARGING_UNKNOWN;
                if (isset($carinfo->charging_status)) {
                    switch ($carinfo->charging_status) {
                        case 'NOCHARGING':
                            $charging_status = self::$BMW_CHARGING_NO;
                            break;
                        case 'CHARGINGACTIVE':
                            $charging_status = self::$BMW_CHARGING_ACTIVE;
                            break;
                        case 'CHARGINGENDED':
                            $charging_status = self::$BMW_CHARGING_ENDED;
                            break;
                        case 'CHARGINGPAUSED':
                            $charging_status = self::$BMW_CHARGING_PAUSED;
                            break;
                        default:
                            $this->SendDebug(__FUNCTION__, 'unknown charging_status "' . $carinfo->charging_status . '"', 0);
                            break;
                    }
                }

                $charging_end = 0;
                if ($connector_status == self::$BMW_CONNECTOR_CONNECTED && $charging_status == self::$BMW_CHARGING_ACTIVE) {
                    if (isset($carinfo->chargingTimeRemaining)) {
                        $chargingTimeRemaining = floatval($carinfo->chargingTimeRemaining);
                        if ($chargingTimeRemaining > 0) {
                            $dateTime = new DateTime(date('Y-m-d H:i:s', time()));
                            $addMinutes = 'PT' . $chargingTimeRemaining . 'M';
                            $dateTime->add(new DateInterval($addMinutes));
                            $charging_end = $dateTime->format('U');
                        }
                    }
                }

                $this->SetValue('bmw_connector_status', $connector_status);
                $this->SetValue('bmw_charging_status', $charging_status);
                $this->SetValue('bmw_charging_end', $charging_end);
            }

            if (isset($carinfo->gps_lng) && isset($carinfo->gps_lat)) {
                $longitude = $carinfo->gps_lng;
                $latitude = $carinfo->gps_lat;

                if ($active_googlemap) {
                    $maptype = $this->GetGoogleMapType(GetValue($this->GetIDForIdent('bmw_googlemap_maptype')));
                    $zoom = GetValue($this->GetIDForIdent('bmw_googlemap_zoom'));
                    $this->SetGoogleMap($maptype, $zoom, $latitude, $longitude);
                }
                if ($active_current_position) {
                    $this->SetValue('bmw_current_latitude', $latitude);
                    $this->SetValue('bmw_current_longitude', $longitude);
                }
            }
        }
        if (isset(json_decode((string) $response)->vehicleMessages->cbsMessages)) {
            if ($active_service) {
                $html = "<style>\n";
                $html .= "th, td { padding: 2px 10px; text-align: left; } \n";
                $html .= "</style>\n";
                $html .= "<table>\n";
                $html .= '<tr>';
                $html .= '<th>' . $this->Translate('Service type') . '</th>';
                $html .= '<th>' . $this->Translate('Description') . '</th>';
                $html .= '<th>' . $this->Translate('Date') . '</th>';
                $html .= '<th>' . $this->Translate('Kilometer') . '</th>';
                $html .= '</tr>';

                $service = json_decode((string) $response)->vehicleMessages->cbsMessages;
                foreach ($service as $key => $servicemessage) {
                    $description = $servicemessage->description;
                    $text = $servicemessage->text;
                    $date = $servicemessage->date;
                    if (isset($servicemessage->unitOfLengthRemaining)) {
                        $dist = $servicemessage->unitOfLengthRemaining;
                    } else {
                        $dist = '-';
                    }

                    $html .= "<tr>\n";
                    $html .= '<td>' . $text . '</td>';
                    $html .= '<td>' . $description . '</td>';
                    $html .= '<td>' . $date . '</td>';
                    $html .= '<td>' . $dist . '</td>';
                    $html .= "</tr>\n";
                }
                $html .= '</table>';
                $this->SetValue('bmw_service', $html);
            }
        }
        return $data;
    }

    /**
     * set lock state.
     *
     * @param $ident
     * @param $command
     */
    protected function SetLockState($ident, $command)
    {
        $this->SendDebug(__FUNCTION__, 'ident=' . $ident . ', command=' . $command, 0);
        switch ($command) {
            case 'CLOSED':
            case 'LOCKED':
            case 'SECURED':
                $this->SetValue($ident, true);
                break;
            default:
                $this->SetValue($ident, false);
                break;
        }
    }

    /**
     * Get Vehicle Status.
     *
     * @return mixed
     */
    public function GetVehicleStatus()
    {
        $active_current_position = $this->ReadPropertyBoolean('active_current_position');
        $active_vehicle_finder = $this->ReadPropertyBoolean('active_vehicle_finder');
        $active_lock = $this->ReadPropertyBoolean('active_lock');
        $active_lock_data = $this->ReadPropertyBoolean('active_lock_data');

        $vin = $this->ReadPropertyString('vin');
        $command = '/webapi/v1/user/vehicles/' . $vin . '/status';

        $instID = IPS_GetInstanceListByModuleID('{45E97A63-F870-408A-B259-2933F7EABF74}')[0];
        if (IPS_GetKernelVersion() >= 5) {
            $loc = json_decode(IPS_GetProperty($instID, 'Location'), true);
            $home_lon = $loc['longitude'];
            $home_lat = $loc['latitude'];
        } else {
            $home_lon = IPS_GetProperty($instID, 'Longitude');
            $home_lat = IPS_GetProperty($instID, 'Latitude');
        }

        $command .= '?deviceTime=' . date('Y-m-d\TH:i:s', time());
        $command .= '&dlat=' . number_format($home_lat, 6, '.', '');
        $command .= '&dlon=' . number_format($home_lon, 6, '.', '');

        $response = $this->SendBMWAPI($command, '', 2);
        $data = json_decode((string) $response);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);

        if (isset($data->vehicleStatus)) {
            $carinfo = $data->vehicleStatus;
            $current_vin = $carinfo->vin;
            if ($vin == $current_vin) {
                $this->SendDebug(__FUNCTION__, 'vehicleStatus=' . print_r($carinfo, true), 0);
                if (isset($carinfo->mileage)) {
                    $mileage = $carinfo->mileage;
                    $this->SetValue('bmw_mileage', $mileage);
                }

                if (isset($carinfo->remainingFuel)) {
                    $remainingFuel = $carinfo->remainingFuel;
                    $this->SetValue('bmw_tank_capacity', $remainingFuel);
                }

                if ($active_current_position) {
                    if (isset($carinfo->position)) {
                        $position = $carinfo->position;
                        $this->SendDebug(__FUNCTION__, 'position=' . print_r($position, true), 0);
                        if (isset($position->status)) {
                            if ($active_vehicle_finder) {
                                $this->SetValue('bmw_position_request_status', $this->Translate($position->status));
                            }

                            if ($position->status == 'OK' && isset($position->lat) && isset($position->lon)) {
                                $this->SetValue('bmw_current_latitude', $position->lat);
                                $this->SetValue('bmw_current_longitude', $position->lon);
                            }
                        }
                    }
                }

                if ($active_lock) {
                    if (isset($carinfo->door_lock_state)) {
                        $doorLockState = $carinfo->door_lock_state;
                        $this->SetLockState('bmw_start_lock', $doorLockState);
                    }
                }
                if ($active_lock_data) {
                    if (isset($carinfo->doorDriverFront)) {
                        $doorDriverFront = $carinfo->doorDriverFront;
                        $this->SetLockState('bmw_doorDriverFront', $doorDriverFront);
                    }
                    if (isset($carinfo->doorDriverFront)) {
                        $doorDriverRear = $carinfo->doorDriverFront;
                        $this->SetLockState('bmw_doorDriverRear', $doorDriverRear);
                    }
                    if (isset($carinfo->doorPassengerFront)) {
                        $doorPassengerFront = $carinfo->doorPassengerFront;
                        $this->SetLockState('bmw_doorPassengerFront', $doorPassengerFront);
                    }
                    if (isset($carinfo->doorPassengerRear)) {
                        $doorPassengerRear = $carinfo->doorPassengerRear;
                        $this->SetLockState('bmw_doorPassengerRear', $doorPassengerRear);
                    }
                    if (isset($carinfo->windowDriverFront)) {
                        $windowDriverFront = $carinfo->windowDriverFront;
                        $this->SetLockState('bmw_windowDriverFront', $windowDriverFront);
                    }
                    if (isset($carinfo->windowDriverRear)) {
                        $windowDriverRear = $carinfo->windowDriverRear;
                        $this->SetLockState('bmw_windowDriverRear', $windowDriverRear);
                    }
                    if (isset($carinfo->windowPassengerFront)) {
                        $windowPassengerFront = $carinfo->windowPassengerFront;
                        $this->SetLockState('bmw_windowPassengerFront', $windowPassengerFront);
                    }
                    if (isset($carinfo->windowPassengerRear)) {
                        $windowPassengerRear = $carinfo->windowPassengerRear;
                        $this->SetLockState('bmw_windowPassengerRear', $windowPassengerRear);
                    }
                    if (isset($carinfo->trunk)) {
                        $trunk = $carinfo->trunk;
                        $this->SetLockState('bmw_trunk', $trunk);
                    }
                    if (isset($carinfo->rearWindow)) {
                        $rearWindow = $carinfo->rearWindow;
                        $this->SetLockState('bmw_rearWindow', $rearWindow);
                    }
                    if (isset($carinfo->convertibleRoofState)) {
                        $convertibleRoofState = $carinfo->convertibleRoofState;
                        $this->SetLockState('bmw_convertibleRoofState', $convertibleRoofState);
                    }
                    if (isset($carinfo->hood)) {
                        $hood = $carinfo->hood;
                        $this->SetLockState('bmw_hood', $hood);
                    }
                    if (isset($carinfo->doorLockState)) {
                        $doorLockState = $carinfo->doorLockState;
                        $this->SetLockState('bmw_doorLockState', $doorLockState);
                    }
                }

                $ts = 0;
                if (isset($carinfo->updateTime)) {
                    $ts = strtotime($carinfo->updateTime);
                }
                $this->SetValue('bmw_last_status_update', $ts);
            }
        }

        return $data;
    }

    /**
     * Get car picture.
     *
     * @return bool
     */
    public function GetCarPicture()
    {
        $active_picture = $this->ReadPropertyBoolean('active_picture');
        if (!$active_picture) {
            return '';
        }

        $angle = 0;
        $zoom = 100;
        $response = $this->GetCarPictureForAngle($angle, $zoom);
        $this->SetMultiBuffer('bmw_image_interface', $response);
        return $response;
    }

    /**
     * Get car picture for angle.
     *
     * @param int $angle
     * @param int $zoom
     *
     * @return bool
     */
    public function GetCarPictureForAngle(int $angle, int $zoom)
    {
        $active_picture = $this->ReadPropertyBoolean('active_picture');

        $vin = $this->ReadPropertyString('vin');
        $command = '/api/vehicle/image/v1/' . $vin . '?startAngle=' . $angle . '&stepAngle=10&width=780';
        $response = $this->SendBMWAPI($command, '', 2);
        $this->SetMultiBuffer('bmw_image_interface', $response);
        $images = json_decode((string) $response);
        if (isset($images->vin) && $active_picture) {
            $picture_url = false;
            $picture_vin = $images->vin;
            if ($vin == $picture_vin) {
                $images_angle = $images->angleUrls;
                $picture_angle = $angle;
                foreach ($images_angle as $key => $image_angle) {
                    $angle = $image_angle->angle;
                    if ($picture_angle == $angle) {
                        $picture_url = $image_angle->url;
                    }
                }
                if ($picture_url) {
                    $HTML = '<!DOCTYPE html>' . PHP_EOL . '
							<html>' . PHP_EOL . '
							<body>' . PHP_EOL . '
							<img src="' . $picture_url . '" alt="car picture" width="' . $zoom . '%" height="' . $zoom . '%">' . PHP_EOL . '
							</body>' . PHP_EOL . '
							</html>';
                    $this->SetValue('bmw_car_picture', $HTML);
                }
            }
        } else {
            $picture_url = false;
        }
        return $picture_url;
    }

    /**
     * Set car picture zoom.
     *
     * @param $zoom
     */
    public function SetCarPictureZoom(int $zoom)
    {
        $angle = GetValue($this->GetIDForIdent('bmw_perspective'));
        $this->GetCarPictureForAngle(intval($angle), $zoom);
    }

    /**
     * Get last trip.
     *
     * @return mixed
     */
    public function GetLastTrip()
    {
        $vin = $this->ReadPropertyString('vin');
        $command = '/webapi/v1/user/vehicles/' . $vin . '/statistics/lastTrip';
        $response = $this->SendBMWAPI($command, '', 2);
        $data = json_decode((string) $response);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);

        return $data;
    }

    /**
     * Get charging times.
     *
     * @return mixed
     */
    public function GetChargingTimes()
    {
        $vin = $this->ReadPropertyString('vin');
        $command = '/webapi/v1/user/vehicles/' . $vin . '/chargingprofile';
        $response = $this->SendBMWAPI($command, '', 2);
        $data = json_decode((string) $response);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);

        return $data;
    }

    /**
     * Get vehicle destinations.
     *
     * @return mixed
     */
    public function GetVehicleDestinations()
    {
        $vin = $this->ReadPropertyString('vin');
        $command = '/webapi/v1/user/vehicles/' . $vin . '/destinations';
        $response = $this->SendBMWAPI($command, '', 2);
        $data = json_decode((string) $response);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);

        return $data;
    }

    /**
     * Get all trip details.
     *
     * @return mixed
     */
    public function GetAllTripDetails()
    {
        $vin = $this->ReadPropertyString('vin');
        $command = '/webapi/v1/user/vehicles/' . $vin . '/statistics/allTrips';
        $response = $this->SendBMWAPI($command, '', 2);
        $data = json_decode((string) $response);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);

        return $data;
    }

    /**
     * Generate a polyline displaying the predicted range of the vehicle.
     *
     * @return mixed
     */
    public function GetRangeMap()
    {
        $vin = $this->ReadPropertyString('vin');
        $command = '/webapi/v1/user/vehicles/' . $vin . '/rangemap';
        $response = $this->SendBMWAPI($command, '', 2);
        $data = json_decode((string) $response);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);

        return $data;
    }

    /**
     * Sending information to the car.
     *
     * @param $service
     *
     * @return mixed
     */
    public function GetRequestStatus(string $service)
    {
        $vin = $this->ReadPropertyString('vin');
        $command = '/webapi/v1/user/vehicles/' . $vin . '/serviceExecutionStatus?serviceType=' . $service;
        $response = $this->SendBMWAPI($command, '', 2);
        $data = json_decode((string) $response);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);

        return $data;
    }

    /**
     * Instructs the car to perform an action.
     *
     * @param $service
     * @param $action
     *
     * @return mixed
     */
    protected function ExecuteService($service, $action)
    {
        $this->SendDebug(__FUNCTION__, 'service=' . $service . ', action=' . $action, 0);
        $vin = $this->ReadPropertyString('vin');
        $region = $this->GetRegion();
        $url = 'https://' . self::$remoteServiceHost[$region] . self::$remoteService_endpoint . '/' . $vin . '/' . strtolower(preg_replace('/_/', '-', $action));

        $instID = IPS_GetInstanceListByModuleID('{45E97A63-F870-408A-B259-2933F7EABF74}')[0];
        if (IPS_GetKernelVersion() >= 5) {
            $loc = json_decode(IPS_GetProperty($instID, 'Location'), true);
            $home_lon = $loc['longitude'];
            $home_lat = $loc['latitude'];
        } else {
            $home_lon = IPS_GetProperty($instID, 'Longitude');
            $home_lat = IPS_GetProperty($instID, 'Latitude');
        }

        $url .= '?deviceTime=' . date('Y-m-d\TH:i:s', time());
        $url .= '&dlat=' . number_format($home_lat, 6, '.', '');
        $url .= '&dlon=' . number_format($home_lon, 6, '.', '');

        $postfields = [
            'serviceType'   => $action
        ];
        $response = $this->SendBMWAPI($url, $postfields, 2);
        return $response;
    }

    /**
     * Initiate Charging.
     *
     * @return mixed
     */
    public function InitiateCharging()
    {
        $service = 'serviceType=CHARGE_NOW';
        $vin = $this->ReadPropertyString('vin');
        $command = '/webapi/v1/user/vehicles/' . $vin . '/serviceType=CHARGE_NOW';
        $result = $this->SendBMWAPI($command, '', 2);
        return $result;
    }

    /**
     * Start climate control.
     *
     * @return mixed
     */
    public function StartClimateControl()
    {
        $service = 'RCN';
        $action = 'CLIMATE_NOW';
        $result = $this->ExecuteService($service, $action);
        $this->SendDebug(__FUNCTION__, 'service=' . $service . ', action=' . $action . ', result=' . $result, 0);
        return $result;
    }

    /**
     * lock doors.
     *
     * @return mixed
     */
    public function LockTheDoors()
    {
        $service = 'RDL';
        $action = 'DOOR_LOCK';
        $result = $this->ExecuteService($service, $action);
        $this->SendDebug(__FUNCTION__, 'service=' . $service . ', action=' . $action . ', result=' . $result, 0);
        return $result;
    }

    /**
     * unlock doors.
     *
     * @return mixed
     */
    public function UnlockTheDoors()
    {
        $service = 'RDU';
        $action = 'DOOR_UNLOCK';
        $result = $this->ExecuteService($service, $action);
        $this->SendDebug(__FUNCTION__, 'service=' . $service . ', action=' . $action . ', result=' . $result, 0);
        return $result;
    }

    /**
     * If you can't find the vehicle, or need to illuminate something in its vicinity, you can briefly activate the headlights.
     *
     * @return mixed
     */
    public function FlashHeadlights()
    {
        $service = 'RLF';
        $action = 'LIGHT_FLASH';
        $result = $this->ExecuteService($service, $action);
        $this->SendDebug(__FUNCTION__, 'service=' . $service . ', action=' . $action . ', result=' . $result, 0);
        return $result;
    }

    /**
     * honk.
     *
     * @return mixed
     */
    public function Honk()
    {
        $service = 'RHB';
        $action = 'HORN_BLOW';
        $result = $this->ExecuteService($service, $action);
        $this->SendDebug(__FUNCTION__, 'service=' . $service . ', action=' . $action . ', result=' . $result, 0);
        return $result;
    }

    /**
     * Find vehicle.
     *
     * @return mixed
     */
    public function FindVehicle()
    {
        $service = 'RVF';
        $action = 'VEHICLE_FINDER';
        $result = $this->ExecuteService($service, $action);
        $this->SendDebug(__FUNCTION__, 'service=' . $service . ', action=' . $action . ', result=' . $result, 0);
        return $result;
    }

    protected function SendBMWAPI($url, $postfields, $mode)
    {
        if (!preg_match('/^https:/', $url)) {
            $url = $this->GetBMWServerURL($mode) . $url;
        }

        $this->SendDebug(__FUNCTION__, 'url=' . $url, 0);

        switch ($mode) {
            case '1':
                $access_token = $this->GetToken_1();
                break;
            case '2':
                $access_token = $this->GetToken_2();
                break;
        }

        if ($access_token == false) {
            $this->SendDebug(__FUNCTION__, 'no token', 0);
            return false;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);

        $header = [
            'Accept: application/json',
            'Authorization: Bearer ' . $access_token,
        ];
        $this->SendDebug(__FUNCTION__, 'header=' . print_r($header, true), 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        if ($postfields != '') {
            curl_setopt($ch, CURLOPT_POST, true);
            $this->SendDebug(__FUNCTION__, 'postfields=' . print_r($postfields, true), 0);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpcode != 200) {
            $this->SendDebug(__FUNCTION__, "got http-code $httpcode", 0);
        }
        if ($response === false) {
            $curl_error = curl_error($ch);
            $this->SendDebug(__FUNCTION__, 'curl error: ' . $curl_error, 0);
        } else {
            if (in_array($httpcode, [401, 405])) {
                $this->SendDebug(__FUNCTION__, 'ignore binary response', 0);
                $response = '';
            } else {
                $this->SendDebug(__FUNCTION__, 'response=' . $response, 0);
            }
        }
        curl_close($ch);
        return $response;
    }

    public function RequestAction($Ident, $Value)
    {
        $active_lock_2actions = $this->ReadPropertyBoolean('active_lock_2actions');

        $this->SetValue($Ident, $Value);
        switch ($Ident) {
            case 'bmw_start_air_conditioner':
                $this->StartClimateControl();
                break;
            case 'bmw_start_lock':
                if ($active_lock_2actions) {
                    $this->LockTheDoors();
                } else {
                    if ($Value) {
                        $this->LockTheDoors();
                    } else {
                        $this->UnlockTheDoors();
                    }
                }
                break;
            case 'bmw_start_unlock':
                $this->UnlockTheDoors();
                break;
            case 'bmw_start_flash_headlights':
                $this->FlashHeadlights();
                break;
            case 'bmw_start_vehicle_finder':
                $this->FindVehicle();
                break;
            case 'bmw_perspective':
                $zoom = GetValue($this->GetIDForIdent('bmw_car_picture_zoom'));
                $this->GetCarPictureForAngle($Value, intval($zoom));
                break;
            case 'bmw_start_honk':
                $this->Honk();
                break;
            case 'bmw_googlemap_maptype':
                $this->SetGoogleMapType($Value);
                break;
            case 'bmw_googlemap_zoom':
                $zoom = round(($Value / 100) * 21);
                $this->SetMapZoom($zoom);
                break;
            case 'bmw_car_picture_zoom':
                $this->SetCarPictureZoom($Value);
                break;

            default:
                $this->SendDebug(__FUNCTION__, 'Invalid ident', 0);
        }
    }

    //Profile
    protected function RegisterProfile($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits, $Vartype)
    {
        if (!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, $Vartype); // 0 boolean, 1 int, 2 float, 3 string,
        } else {
            $profile = IPS_GetVariableProfile($Name);
            if ($profile['ProfileType'] != $Vartype) {
                $this->SendDebug(__FUNCTION__, 'Variable profile type does not match for profile ' . $Name, 0);
            }
        }

        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        IPS_SetVariableProfileDigits($Name, $Digits); //  Nachkommastellen
        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize); // string $ProfilName, float $Minimalwert, float $Maximalwert, float $Schrittweite
    }

    protected function RegisterProfileAssociation($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $Stepsize, $Digits, $Vartype, $Associations)
    {
        if (count($Associations) === 0) {
            $MinValue = 0;
            $MaxValue = 0;
        }
        /*
        else {
            //undefiened offset
            $MinValue = $Associations[0][0];
            $MaxValue = $Associations[sizeof($Associations)-1][0];
        }
         */
        $this->RegisterProfile($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $Stepsize, $Digits, $Vartype);

        //boolean IPS_SetVariableProfileAssociation ( string $ProfilName, float $Wert, string $Name, string $Icon, integer $Farbe )
        foreach ($Associations as $Association) {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
        }
    }

    /*
     * Configuration Form
     */

    /**
     * build configuration form.
     *
     * @return string
     */
    public function GetConfigurationForm()
    {
        // return current form
        return json_encode([
            'elements' => $this->FormHead(),
            'actions'  => $this->FormActions(),
            'status'   => $this->FormStatus()
        ]);
    }

    /**
     * return form configurations on configuration step.
     *
     * @return array
     */
    protected function FormHead()
    {
        $form = [
            [
                'type'  => 'Image',
                'image' => 'data:image/png;base64, iVBORw0KGgoAAAANSUhEUgAAAHgAAAB4CAYAAAA5ZDbSAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyZpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuNi1jMDY3IDc5LjE1Nzc0NywgMjAxNS8wMy8zMC0yMzo0MDo0MiAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENDIDIwMTUgKFdpbmRvd3MpIiB4bXBNTTpJbnN0YW5jZUlEPSJ4bXAuaWlkOkUzMUZBNTAyRkEzRjExRThBOTYyRjEyNDhDNUJFMjE0IiB4bXBNTTpEb2N1bWVudElEPSJ4bXAuZGlkOkUzMUZBNTAzRkEzRjExRThBOTYyRjEyNDhDNUJFMjE0Ij4gPHhtcE1NOkRlcml2ZWRGcm9tIHN0UmVmOmluc3RhbmNlSUQ9InhtcC5paWQ6RTMxRkE1MDBGQTNGMTFFOEE5NjJGMTI0OEM1QkUyMTQiIHN0UmVmOmRvY3VtZW50SUQ9InhtcC5kaWQ6RTMxRkE1MDFGQTNGMTFFOEE5NjJGMTI0OEM1QkUyMTQiLz4gPC9yZGY6RGVzY3JpcHRpb24+IDwvcmRmOlJERj4gPC94OnhtcG1ldGE+IDw/eHBhY2tldCBlbmQ9InIiPz6YCI7LAAA8IElEQVR42uxd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'
            ],
            [
                'type'    => 'Select',
                'name'    => 'model',
                'caption' => 'mode of driving',
                'options' => [
                    [
                        'label' => $this->Translate('electric'),
                        'value' => self::$BMW_MODEL_ELECTRIC
                    ],
                    [
                        'label' => $this->Translate('hybrid'),
                        'value' => self::$BMW_MODEL_HYBRID
                    ],
                    [
                        'label' => $this->Translate('standard'),
                        'value' => self::$BMW_MODEL_STANDARD
                    ]
                ]

            ],
            [
                'type'    => 'Select',
                'name'    => 'bmw_server',
                'caption' => 'BMW area',
                'options' => [
                    [
                        'label' => $this->Translate('Germany'),
                        'value' => self::$BMW_AREA_GERMANY
                    ],
                    [
                        'label' => $this->Translate('Switzerland'),
                        'value' => self::$BMW_AREA_SWITZERLAND
                    ],
                    [
                        'label' => $this->Translate('Europe'),
                        'value' => self::$BMW_AREA_EUROPE
                    ],
                    [
                        'label' => $this->Translate('USA'),
                        'value' => self::$BMW_AREA_USA
                    ],
                    [
                        'label' => $this->Translate('China'),
                        'value' => self::$BMW_AREA_CHINA
                    ],
                    [
                        'label' => $this->Translate('Rest of the World'),
                        'value' => self::$BMW_AREA_OTHER
                    ]
                ]

            ],
            [
                'type'  => 'Label',
                'label' => 'BMW Connected Drive login credentials'
            ],
            [
                'name'    => 'user',
                'type'    => 'ValidationTextBox',
                'caption' => 'User'
            ],
            [
                'name'    => 'password',
                'type'    => 'PasswordTextBox',
                'caption' => 'Password'
            ],
            [
                'name'    => 'vin',
                'type'    => 'ValidationTextBox',
                'caption' => 'VIN'
            ],
            [
                'type'    => 'ExpansionPanel',
                'caption' => 'Update interval',
                'items'   => [
                    [
                        'type'  => 'Label',
                        'label' => 'Update interval in minutes'
                    ],
                    [
                        'name'    => 'UpdateInterval',
                        'type'    => 'IntervalBox',
                        'caption' => 'minutes'
                    ]
                ]
            ],
            [
                'type'    => 'ExpansionPanel',
                'caption' => 'options',
                'items'   => [
                    [
                        'name'    => 'active_climate',
                        'type'    => 'CheckBox',
                        'caption' => 'air conditioner'
                    ],
                    [
                        'name'    => 'active_lock',
                        'type'    => 'CheckBox',
                        'caption' => 'lock car'
                    ],
                    [
                        'name'    => 'active_lock_2actions',
                        'type'    => 'CheckBox',
                        'caption' => 'lock car (separate actions)'
                    ],
                    [
                        'name'    => 'active_flash_headlights',
                        'type'    => 'CheckBox',
                        'caption' => 'flash headlights'
                    ],
                    [
                        'name'    => 'active_honk',
                        'type'    => 'CheckBox',
                        'caption' => 'honk'
                    ],
                    [
                        'name'    => 'active_vehicle_finder',
                        'type'    => 'CheckBox',
                        'caption' => 'search vehicle'
                    ],
                    [
                        'name'    => 'active_picture',
                        'type'    => 'CheckBox',
                        'caption' => 'show picture of car'
                    ],
                    [
                        'name'    => 'active_service',
                        'type'    => 'CheckBox',
                        'caption' => 'show service messages'
                    ],
                    [
                        'name'    => 'active_lock_data',
                        'type'    => 'CheckBox',
                        'caption' => 'show detailed lock state'
                    ]
                ]
            ],
            [
                'type'    => 'ExpansionPanel',
                'caption' => 'map',
                'items'   => [
                    [
                        'name'    => 'active_googlemap',
                        'type'    => 'CheckBox',
                        'caption' => 'show car position in map'
                    ],
                    [
                        'name'    => 'googlemap_api_key',
                        'type'    => 'ValidationTextBox',
                        'caption' => 'GoogleMap API-Key'
                    ],
                    [
                        'type'  => 'Label',
                        'label' => ' ... size of the map'
                    ],
                    [
                        'name'    => 'horizontal_mapsize',
                        'type'    => 'NumberSpinner',
                        'caption' => ' ... horizontal'
                    ],
                    [
                        'name'    => 'vertical_mapsize',
                        'type'    => 'NumberSpinner',
                        'caption' => ' ... vertical'
                    ],
                    [
                        'name'    => 'active_current_position',
                        'type'    => 'CheckBox',
                        'caption' => 'show current position, latitude / longitude'
                    ]
                ]
            ]
        ];
        return $form;
    }

    /**
     * return form actions by token.
     *
     * @return array
     */
    protected function FormActions()
    {
        $form = [
            [
                'type'  => 'Label',
                'label' => 'Get token for communication with BMW Connected Drive'
            ],
            [
                'type'    => 'Button',
                'label'   => 'Get token',
                'onClick' => 'BMW_GetToken($id);'
            ],
            [
                'type'  => 'Label',
                'label' => 'Get car data from BMW'
            ],
            [
                'type'    => 'Button',
                'label'   => 'Update data',
                'onClick' => 'BMW_DataUpdate($id);'
            ]
        ];
        return $form;
    }

    /**
     * return from status.
     *
     * @return array
     */
    protected function FormStatus()
    {
        $form = [
            [
                'code'    => 101,
                'icon'    => 'inactive',
                'caption' => 'Creating instance.'
            ],
            [
                'code'    => 102,
                'icon'    => 'active',
                'caption' => 'BMW accessible.'
            ],
            [
                'code'    => 104,
                'icon'    => 'inactive',
                'caption' => 'interface closed.'
            ],
            [
                'code'    => 204,
                'icon'    => 'error',
                'caption' => 'connection to BMW lost.'
            ],
            [
                'code'    => 205,
                'icon'    => 'error',
                'caption' => 'field must not be empty.'
            ]
        ];

        return $form;
    }

    //Add this Polyfill for IP-Symcon 4.4 and older
    protected function SetValue($Ident, $Value)
    {
        if (IPS_GetKernelVersion() >= 5) {
            @$varID = $this->GetIDForIdent($Ident);
            if ($varID == false) {
                $this->SendDebug(__FUNCTION__, 'missing variable ' . $Ident, 0);
                return;
            }
            @$ret = parent::SetValue($Ident, $Value);
            if ($ret == false) {
                $this->SendDebug(__FUNCTION__, 'mismatch of value "' . $Value . '" for variable ' . $Ident, 0);
            }
        } else {
            SetValue($this->GetIDForIdent($Ident), $Value);
        }
    }

    public function GetRawData(string $name)
    {
        $data = $this->GetMultiBuffer($name);
        $this->SendDebug(__FUNCTION__, 'name=' . $name . ', size=' . strlen($data) . ', data=' . $data, 0);
        return $data;
    }

    // inspired by Nall-chan
    //   https://github.com/Nall-chan/IPSSqueezeBox/blob/6bbdccc23a0de51bb3fbc114cefc3acf23c27a14/libs/SqueezeBoxTraits.php
    public function __get($name)
    {
        $n = strpos($name, 'Multi_');
        if (strpos($name, 'Multi_') === 0) {
            $curCount = $this->GetBuffer('BufferCount_' . $name);
            if ($curCount == false) {
                $curCount = 0;
            }
            $data = '';
            for ($i = 0; $i < $curCount; $i++) {
                $data .= $this->GetBuffer('BufferPart' . $i . '_' . $name);
            }
        } else {
            $data = $this->GetBuffer($name);
        }
        return unserialize($data);
    }

    public function __set($name, $value)
    {
        $data = serialize($value);
        $n = strpos($name, 'Multi_');
        if (strpos($name, 'Multi_') === 0) {
            $oldCount = $this->GetBuffer('BufferCount_' . $name);
            if ($oldCount == false) {
                $oldCount = 0;
            }
            $parts = str_split($data, 8000);
            $newCount = count($parts);
            $this->SetBuffer('BufferCount_' . $name, strval($newCount));
            for ($i = 0; $i < $newCount; $i++) {
                $this->SetBuffer('BufferPart' . $i . '_' . $name, $parts[$i]);
            }
            for ($i = $newCount; $i < $oldCount; $i++) {
                $this->SetBuffer('BufferPart' . $i . '_' . $name, '');
            }
        } else {
            $this->SetBuffer($name, $data);
        }
    }

    private function SetMultiBuffer($name, $value)
    {
        if (IPS_GetKernelVersion() >= 5) {
            $this->{'Multi_' . $name} = $value;
        } else {
            $this->SetBuffer($name, $value);
        }
    }

    private function GetMultiBuffer($name)
    {
        if (IPS_GetKernelVersion() >= 5) {
            $value = $this->{'Multi_' . $name};
        } else {
            $value = $this->GetBuffer($name);
        }
        return $value;
    }
}
