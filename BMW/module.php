<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen
require_once __DIR__ . '/../libs/images.php';  // eingebettete Images

class BMWConnectedDrive extends IPSModule
{
	use BMWConnectedDriveCommonLib;
	use BMWConnectedDriveImagesLib;

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
            $this->RegisterProfile('BMW.BatteryCapacity', '', '', ' kWh', 0, 0, 0, 1, VARIABLETYPE_FLOAT);
            $this->RegisterVariableFloat('bmw_battery_size', $this->Translate('gross batteryіcapacity'), 'BMW.BatteryCapacity', 6);
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
        $this->SendDebug(__FUNCTION__, 'start ...', 0);

        $time_start = microtime(true);

        $active_picture = $this->ReadPropertyBoolean('active_picture');
        $model = $this->ReadPropertyInteger('model');

        $this->GetVehicleData();
        $this->GetVehicleStatus();
        $this->GetDynamicData();
        if ($model != self::$BMW_MODEL_STANDARD) { // standard, no electric
            $this->GetNavigationData();
            $this->GetEfficiency();
            $this->GetChargingProfile();
        }

        $this->GetRemoteServices();

        $this->GetMapUpdate();

        if ($active_picture) {
            $angle = GetValue($this->GetIDForIdent('bmw_perspective'));
            $zoom = GetValue($this->GetIDForIdent('bmw_car_picture_zoom'));
            $this->GetCarPictureForAngle(intval($angle), intval($zoom));
        }

        $this->GetStore();
        $this->GetService();
        $this->GetServicePartner();

        $this->GetLastTrip();
        $this->GetAllTripDetails();
        $this->GetVehicleDestinations();

        $this->GetRangeMap();

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, '... finished in ' . $duration . 's', 0);
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
        $this->WriteAttributeString('ApiRefreshToken', '');
        $this->SetBuffer('Token_2', '');
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

        $this->SetBuffer('Token_1', '');

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
        $this->WriteAttributeString('ApiRefreshToken', $refresh_token);
        $this->SetBuffer('Token_2', json_encode($jtoken));
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

        $this->WriteAttributeString('ApiRefreshToken', '');
        $this->SetBuffer('Token_2', '');

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
        $this->WriteAttributeString('ApiRefreshToken', $refresh_token);
        $this->SetBuffer('Token_2', json_encode($jtoken));
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
        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
        $command = '/webapi/v1/user/vehicles/';
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
        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
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
        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
        $model = $this->ReadPropertyInteger('model');
        if ($model == self::$BMW_MODEL_STANDARD) { // standard, no electric
            $this->SetMultiBuffer('bmw_efficiency_interface', '');
            return false;
        }

        $vin = $this->ReadPropertyString('vin');
        $command = '/webapi/v1/user/vehicles/' . $vin . '/efficiency';
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
        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
        $vin = $this->ReadPropertyString('vin');
        $command = '/webapi/v1/user/vehicles/' . $vin . '/chargingprofile';
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
        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
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
        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
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
        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
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
        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
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
        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
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
        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
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
        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
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
        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
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
        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
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
        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
        $vin = $this->ReadPropertyString('vin');
        $command = '/webapi/v1/user/vehicles/' . $vin . '/statistics/lastTrip';
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
        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
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
        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
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
        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
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
        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
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
        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
        $service = 'serviceType=CHARGE_NOW';
        $vin = $this->ReadPropertyString('vin');
        $command = '/webapi/v1/user/vehicles/' . $vin . '/serviceType=CHARGE_NOW';
        $result = $this->SendBMWAPI($command, '', 2);
        $this->SendDebug(__FUNCTION__, 'service=' . $service . ', result=' . $result, 0);
        return $result;
    }

    /**
     * Start climate control.
     *
     * @return mixed
     */
    public function StartClimateControl()
    {
        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
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
        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
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
        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
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
        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
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
        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
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
        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
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
            $this->SendDebug(__FUNCTION__, 'got http-code ' . $httpcode . '(' . $this->HttpCode2Text($httpcode) . ')', 0);
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
				'image' => 'data:image/png;base64,' . $this->GetBrandImage()
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

    public function GetRawData(string $name)
    {
        $data = $this->GetMultiBuffer($name);
        $this->SendDebug(__FUNCTION__, 'name=' . $name . ', size=' . strlen($data) . ', data=' . $data, 0);
        return $data;
    }
}
