<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen
require_once __DIR__ . '/../libs/local.php';   // lokale Funktionen
require_once __DIR__ . '/../libs/images.php';  // eingebettete Images

class BMWConnectedDrive extends IPSModule
{
    use BMWConnectedDriveCommonLib;
    use BMWConnectedDriveLocalLib;
    use BMWConnectedDriveImagesLib;

    // Model
    private static $BMW_MODEL_ELECTRIC = 1;
    private static $BMW_MODEL_HYBRID = 2;
    private static $BMW_MODEL_COMBUSTION = 3;

    // Ladekabel/Stecker
    private static $BMW_CONNECTOR_STATE_UNKNOWN = -1;
    private static $BMW_CONNECTOR_STATE_DISCONNECTED = 0;
    private static $BMW_CONNECTOR_STATE_CONNECTED = 1;

    // Ladezustand
    private static $BMW_CHARGING_STATE_UNKNOWN = -1;
    private static $BMW_CHARGING_STATE_NOT = 0;
    private static $BMW_CHARGING_STATE_ACTIVE = 1;
    private static $BMW_CHARGING_STATE_ENDED = 2;
    private static $BMW_CHARGING_STATE_PAUSED = 3;
    private static $BMW_CHARGING_STATE_FULLY = 4;
    private static $BMW_CHARGING_STATE_PARTIAL = 5;
    private static $BMW_CHARGING_STATE_ERROR = 6;
    private static $BMW_CHARGING_STATE_INVALID = 7;
    private static $BMW_CHARGING_STATE_PLUGGED_IN = 8;

    // Tür
    private static $BMW_DOOR_STATE_UNKNOWN = 0;
    private static $BMW_DOOR_STATE_OPEN = 1;
    private static $BMW_DOOR_STATE_CLOSED = 2;

    // Türverschluss
    private static $BMW_DOOR_CLOSURE_UNKNOWN = 0;
    private static $BMW_DOOR_CLOSURE_UNLOCKED = 1;
    private static $BMW_DOOR_CLOSURE_LOCKED = 2;
    private static $BMW_DOOR_CLOSURE_SECURED = 3;
    private static $BMW_DOOR_CLOSURE_SELECTIVLOCKED = 4;

    // Fenster
    private static $BMW_WINDOW_STATE_UNKNOWN = 0;
    private static $BMW_WINDOW_STATE_OPEN = 1;
    private static $BMW_WINDOW_STATE_INTERMEDIATE = 2;
    private static $BMW_WINDOW_STATE_CLOSED = 3;

    // Motorhaube
    private static $BMW_TRUNK_STATE_UNKNOWN = 0;
    private static $BMW_TRUNK_STATE_OPEN = 1;
    private static $BMW_TRUNK_STATE_CLOSED = 2;

    // Kofferraum
    private static $BMW_HOOD_STATE_UNKNOWN = 0;
    private static $BMW_HOOD_STATE_OPEN = 1;
    private static $BMW_HOOD_STATE_INTERMEDIATE = 2;
    private static $BMW_HOOD_STATE_CLOSED = 3;

    // Schiebedach
    private static $BMW_SUNROOF_STATE_UNKNOWN = 0;
    private static $BMW_SUNROOF_STATE_OPEN = 1;
    private static $BMW_SUNROOF_STATE_OPEN_TILT = 2;
    private static $BMW_SUNROOF_STATE_INTERMEDIATE = 3;
    private static $BMW_SUNROOF_STATE_CLOSED = 4;

    // Carbiodach / convertible roof
    // OPEN / CLOSED / CLOSEDSECURED / OPENSECURED / HARDTOPMOUNTED / INTERMEDIATEPOSITION / LOADINGPOSITION / LOADINGPOSITIONIMMEDIATE

    // GoogleMapType
    private static $BMW_GOOGLEMAP_TYPE_ROADMAP = 0;
    private static $BMW_GOOGLEMAP_TYPE_SATELLITE = 1;
    private static $BMW_GOOGLEMAP_TYPE_HYBRID = 2;
    private static $BMW_GOOGLEMAP_TYPE_TERRAIN = 3;

    // GoogleMapZoom
    private static $BMW_GOOGLEMAP_ZOOM_MIN = 0;
    private static $BMW_GOOGLEMAP_ZOOM_MAX = 21;

    // Land
    private static $BMW_COUNTRY_OTHER = 0;
    private static $BMW_COUNTRY_GERMANY = 1;
    private static $BMW_COUNTRY_SWITZERLAND = 2;
    private static $BMW_COUNTRY_EUROPE = 3;
    private static $BMW_COUNTRY_USA = 4;

    // Marken
    private static $BMW_BRAND_BMW = 0;
    private static $BMW_BRAND_MINI = 1;

    // Ansicht des Fahrzeugbilds
    // extrem komische Werte, sind aber so
    private static $BMW_CARVIEW_FRONTSIDE = 'VehicleStatus';
    private static $BMW_CARVIEW_FRONT = 'VehicleInfo';
    private static $BMW_CARVIEW_SIDE = 'ChargingHistory';

    // Konfigurationen
    private static $server_urls_eadrax = [
        'NorthAmerica' => 'cocoapi.bmwgroup.us',
        'RestOfWorld'  => 'cocoapi.bmwgroup.com',
    ];

    private static $ocp_apim_key = [
        'NorthAmerica' => '31e102f5-6f7e-7ef3-9044-ddce63891362',
        'RestOfWorld'  => '4f1c85a3-758f-a37d-bbb6-f8704494acfa',
    ];

    private static $x_user_agent_fmt = 'android(v1.07_20200330);%s;1.7.0(11152)';

    private static $oauth_config_endpoint = '/eadrax-ucs/v1/presentation/oauth/config';
    private static $oauth_authenticate_endpoint = '/gcdm/oauth/authenticate';
    private static $oauth_token_endpoint = '/gcdm/oauth/token';

    private static $vehicles_endpoint = '/eadrax-vcs/v1/vehicles';

    private static $remoteService_endpoint = '/eadrax-vrccs/v2/presentation/remote-commands';
    private static $remoteServiceStatus_endpoint = '/eadrax-vrccs/v2/presentation/remote-commands/eventStatus';
    private static $remoteServicePosition_endpoint = '/eadrax-vrccs/v2/presentation/remote-commands/eventPosition';
    private static $remoteServiceHistory_endpoint = '/eadrax-vrccs/v2/presentation/remote-history';

    private static $vehicle_img_endpoint = '/eadrax-ics/v3/presentation/vehicles/%s/images';
    private static $vehicle_poi_endpoint = '/eadrax-dcs/v1/send-to-car/send-to-car';

    private static $charging_statistics_endpoint = '/eadrax-chs/v1/charging-statistics';
    private static $charging_sessions_endpoint = '/eadrax-chs/v1/charging-sessions';

    private static $UpdateRemoteHistoryInterval = 5;

    public function InstallVarProfiles(bool $reInstall = false)
    {
        $this->SendDebug(__FUNCTION__, 'reInstall=' . $this->bool2str($reInstall), 0);

        $associations = [];
        $associations[] = ['Wert' => false, 'Name' => $this->Translate('No'), 'Farbe' => -1];
        $associations[] = ['Wert' => true, 'Name' => $this->Translate('Yes'), 'Farbe' => 0xEE0000];
        $this->CreateVarProfile('BMW.YesNo', VARIABLETYPE_BOOLEAN, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [];
        $associations[] = ['Wert' => 0, 'Name' => $this->Translate('Start'), 'Farbe' => 0x3ADF00];
        $this->CreateVarProfile('BMW.TriggerRemoteService', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, 'Execute', $associations, $reInstall);

        $associations = [];
        $associations[] = ['Wert' => self::$BMW_GOOGLEMAP_TYPE_ROADMAP, 'Name' => $this->Translate('roadmap'), 'Farbe' => 0x3ADF00];
        $associations[] = ['Wert' => self::$BMW_GOOGLEMAP_TYPE_SATELLITE, 'Name' => $this->Translate('satellite'), 'Farbe' => 0x3ADF00];
        $associations[] = ['Wert' => self::$BMW_GOOGLEMAP_TYPE_HYBRID, 'Name' => $this->Translate('hybrid'), 'Farbe' => 0x3ADF00];
        $associations[] = ['Wert' => self::$BMW_GOOGLEMAP_TYPE_TERRAIN, 'Name' => $this->Translate('terrain'), 'Farbe' => 0x3ADF00];
        $this->CreateVarProfile('BMW.Googlemap', VARIABLETYPE_INTEGER, '', 0, 3, 0, 0, 'Car', $associations, $reInstall);

        $associations = [];
        $associations[] = ['Wert' => self::$BMW_CONNECTOR_STATE_UNKNOWN, 'Name' => $this->Translate('unknown'), 'Farbe' => 0xEE0000];
        $associations[] = ['Wert' => self::$BMW_CONNECTOR_STATE_DISCONNECTED, 'Name' => $this->Translate('disconnected'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$BMW_CONNECTOR_STATE_CONNECTED, 'Name' => $this->Translate('connected'), 'Farbe' => 0x228B22];
        $this->CreateVarProfile('BMW.ConnectorStatus', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [];
        $associations[] = ['Wert' => self::$BMW_CHARGING_STATE_UNKNOWN, 'Name' => $this->Translate('unknown'), 'Farbe' => 0xEE0000];
        $associations[] = ['Wert' => self::$BMW_CHARGING_STATE_NOT, 'Name' => $this->Translate('not charging'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$BMW_CHARGING_STATE_ACTIVE, 'Name' => $this->Translate('charging active'), 'Farbe' => 0x228B22];
        $associations[] = ['Wert' => self::$BMW_CHARGING_STATE_ENDED, 'Name' => $this->Translate('charging ended'), 'Farbe' => 0x0000FF];
        $associations[] = ['Wert' => self::$BMW_CHARGING_STATE_PAUSED, 'Name' => $this->Translate('charging paused'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$BMW_CHARGING_STATE_FULLY, 'Name' => $this->Translate('fully charged'), 'Farbe' => 0x0000FF];
        $associations[] = ['Wert' => self::$BMW_CHARGING_STATE_PARTIAL, 'Name' => $this->Translate('partial charged'), 'Farbe' => 0x0000FF];
        $associations[] = ['Wert' => self::$BMW_CHARGING_STATE_ERROR, 'Name' => $this->Translate('charging error'), 'Farbe' => 0xEE0000];
        $associations[] = ['Wert' => self::$BMW_CHARGING_STATE_INVALID, 'Name' => $this->Translate('invalid state'), 'Farbe' => 0xEE0000];
        $associations[] = ['Wert' => self::$BMW_CHARGING_STATE_PLUGGED_IN, 'Name' => $this->Translate('plugged in'), 'Farbe' => 0x228B22];
        $this->CreateVarProfile('BMW.ChargingStatus', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [];
        $associations[] = ['Wert' => self::$BMW_DOOR_STATE_UNKNOWN, 'Name' => $this->Translate('unknown'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$BMW_DOOR_STATE_OPEN, 'Name' => $this->Translate('open'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$BMW_DOOR_STATE_CLOSED, 'Name' => $this->Translate('closed'), 'Farbe' => -1];
        $this->CreateVarProfile('BMW.DoorState', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [];
        $associations[] = ['Wert' => self::$BMW_DOOR_CLOSURE_UNKNOWN, 'Name' => $this->Translate('unknown'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$BMW_DOOR_CLOSURE_UNLOCKED, 'Name' => $this->Translate('unlocked'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$BMW_DOOR_CLOSURE_LOCKED, 'Name' => $this->Translate('locked'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$BMW_DOOR_CLOSURE_SECURED, 'Name' => $this->Translate('secured'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$BMW_DOOR_CLOSURE_SELECTIVLOCKED, 'Name' => $this->Translate('selectiv locked'), 'Farbe' => -1];
        $this->CreateVarProfile('BMW.DoorClosureState', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [];
        $associations[] = ['Wert' => self::$BMW_WINDOW_STATE_UNKNOWN, 'Name' => $this->Translate('unknown'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$BMW_WINDOW_STATE_OPEN, 'Name' => $this->Translate('open'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$BMW_WINDOW_STATE_INTERMEDIATE, 'Name' => $this->Translate('intermediate'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$BMW_WINDOW_STATE_CLOSED, 'Name' => $this->Translate('closed'), 'Farbe' => -1];
        $this->CreateVarProfile('BMW.WindowState', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [];
        $associations[] = ['Wert' => self::$BMW_TRUNK_STATE_UNKNOWN, 'Name' => $this->Translate('unknown'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$BMW_TRUNK_STATE_OPEN, 'Name' => $this->Translate('open'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$BMW_TRUNK_STATE_CLOSED, 'Name' => $this->Translate('closed'), 'Farbe' => -1];
        $this->CreateVarProfile('BMW.TrunkState', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [];
        $associations[] = ['Wert' => self::$BMW_HOOD_STATE_UNKNOWN, 'Name' => $this->Translate('unknown'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$BMW_HOOD_STATE_OPEN, 'Name' => $this->Translate('open'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$BMW_HOOD_STATE_INTERMEDIATE, 'Name' => $this->Translate('intermediate'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$BMW_HOOD_STATE_CLOSED, 'Name' => $this->Translate('closed'), 'Farbe' => -1];
        $this->CreateVarProfile('BMW.HoodState', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [];
        $associations[] = ['Wert' => self::$BMW_SUNROOF_STATE_UNKNOWN, 'Name' => $this->Translate('unknown'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$BMW_SUNROOF_STATE_OPEN, 'Name' => $this->Translate('open'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$BMW_SUNROOF_STATE_OPEN_TILT, 'Name' => $this->Translate('tilt'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$BMW_SUNROOF_STATE_INTERMEDIATE, 'Name' => $this->Translate('intermediate'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$BMW_SUNROOF_STATE_CLOSED, 'Name' => $this->Translate('closed'), 'Farbe' => -1];
        $this->CreateVarProfile('BMW.SunroofState', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $this->CreateVarProfile('BMW.Mileage', VARIABLETYPE_INTEGER, ' km', 0, 0, 0, 0, 'Distance', '', $reInstall);
        $this->CreateVarProfile('BMW.Heading', VARIABLETYPE_INTEGER, ' °', 0, 360, 0, 0, 'WindDirection', '', $reInstall);

        $this->CreateVarProfile('BMW.TankCapacity', VARIABLETYPE_FLOAT, ' Liter', 0, 0, 0, 0, 'Gauge', '', $reInstall);
        $this->CreateVarProfile('BMW.RemainingRange', VARIABLETYPE_FLOAT, ' km', 0, 0, 0, 0, 'Gauge', '', $reInstall);
        $this->CreateVarProfile('BMW.ChargingLevel', VARIABLETYPE_FLOAT, ' %', 0, 0, 0, 0, '', '', $reInstall);
        $this->CreateVarProfile('BMW.StateofCharge', VARIABLETYPE_FLOAT, ' kWh', 0, 0, 0, 1, '', '', $reInstall);
        $this->CreateVarProfile('BMW.BatteryCapacity', VARIABLETYPE_FLOAT, ' kWh', 0, 0, 0, 1, '', '', $reInstall);
        $this->CreateVarProfile('BMW.Location', VARIABLETYPE_FLOAT, ' °', 0, 0, 0, 5, 'Car', '', $reInstall);
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('user', '');
        $this->RegisterPropertyString('password', '');
        $this->RegisterPropertyInteger('country', self::$BMW_COUNTRY_GERMANY);
        $this->RegisterPropertyString('vin', '');
        $this->RegisterPropertyInteger('model', self::$BMW_MODEL_COMBUSTION);
        $this->RegisterPropertyInteger('brand', self::$BMW_BRAND_BMW);

        $this->RegisterPropertyBoolean('active_climate', false);
        $this->RegisterPropertyBoolean('active_lock', false);
        $this->RegisterPropertyBoolean('active_flash_headlights', false);
        $this->RegisterPropertyBoolean('active_vehicle_finder', false);
        $this->RegisterPropertyBoolean('active_lock_data', false);
        $this->RegisterPropertyBoolean('active_sound_honk', false);

        $this->RegisterPropertyBoolean('active_service', false);
        $this->RegisterPropertyBoolean('active_checkcontrol', false);

        $this->RegisterPropertyBoolean('active_current_position', false);

        $this->RegisterPropertyBoolean('active_googlemap', false);
        $this->RegisterPropertyString('googlemap_api_key', '');
        $this->RegisterPropertyInteger('horizontal_mapsize', 600);
        $this->RegisterPropertyInteger('vertical_mapsize', 400);

        $this->RegisterPropertyInteger('UpdateInterval', 10);

        $this->RegisterAttributeString('ApiSettings', '');
        $this->RegisterAttributeString('ApiRefreshToken', '');
        $this->RegisterAttributeString('RemoteServiceHistory', '');

        $this->RegisterTimer('UpdateData', 0, 'BMW_UpdateData(' . $this->InstanceID . ');');
        $this->RegisterTimer('UpdateRemoteServiceStatus', 0, 'BMW_UpdateRemoteServiceStatus(' . $this->InstanceID . ');');

        $this->InstallVarProfiles(false);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $status = IS_ACTIVE;

        $model = $this->ReadPropertyInteger('model');

        $active_service = $this->ReadPropertyBoolean('active_service');
        $active_checkcontrol = $this->ReadPropertyBoolean('active_checkcontrol');
        $active_climate = $this->ReadPropertyBoolean('active_climate');
        $active_lock = $this->ReadPropertyBoolean('active_lock');
        $active_flash_headlights = $this->ReadPropertyBoolean('active_flash_headlights');
        $active_sound_honk = $this->ReadPropertyBoolean('active_sound_honk');
        $active_vehicle_finder = $this->ReadPropertyBoolean('active_vehicle_finder');
        $active_googlemap = $this->ReadPropertyBoolean('active_googlemap');
        $active_current_position = $this->ReadPropertyBoolean('active_current_position');
        $active_lock_data = $this->ReadPropertyBoolean('active_lock_data');

        if ($active_googlemap && $active_current_position == false) {
            $this->SendDebug(__FUNCTION__, '"active_googlemap" needs "active_current_position"', 0);
            $active_googlemap = false;
            $status = self::$IS_INVALIDCONFIG;
        }

        $vpos = 1;
        $this->MaintainVariable('Mileage', $this->Translate('mileage'), VARIABLETYPE_INTEGER, 'BMW.Mileage', $vpos++, true);
        $this->MaintainVariable('TankCapacity', $this->Translate('tank capacity'), VARIABLETYPE_FLOAT, 'BMW.TankCapacity', $vpos++, true);
        $this->MaintainVariable('RemainingCombinedRange', $this->Translate('remaining range'), VARIABLETYPE_FLOAT, 'BMW.RemainingRange', $vpos++, true);

        $isElectric = $model != self::$BMW_MODEL_COMBUSTION;

        $vpos = 10;
        $this->MaintainVariable('RemainingElectricRange', $this->Translate('remaining electric range'), VARIABLETYPE_FLOAT, 'BMW.RemainingRange', $vpos++, $isElectric);
        $this->MaintainVariable('ChargingLevel', $this->Translate('current battery charge level (SoC)'), VARIABLETYPE_FLOAT, 'BMW.ChargingLevel', $vpos++, $isElectric);
        $this->MaintainVariable('ChargingConnectorStatus', $this->Translate('connector status'), VARIABLETYPE_INTEGER, 'BMW.ConnectorStatus', $vpos++, $isElectric);
        $this->MaintainVariable('ChargingStatus', $this->Translate('charging status'), VARIABLETYPE_INTEGER, 'BMW.ChargingStatus', $vpos++, $isElectric);
        $this->MaintainVariable('ChargingStart', $this->Translate('charging start'), VARIABLETYPE_INTEGER, '~UnixTimestampTime', $vpos++, $isElectric);
        $this->MaintainVariable('ChargingEnd', $this->Translate('charging end'), VARIABLETYPE_INTEGER, '~UnixTimestampTime', $vpos++, $isElectric);
        $this->MaintainVariable('ChargingInfo', $this->Translate('charging info'), VARIABLETYPE_STRING, '', $vpos++, $isElectric);
        $this->MaintainVariable('ChargingSessions', $this->Translate('charging sessions'), VARIABLETYPE_STRING, '~HTMLBox', $vpos++, $isElectric);

        $vpos = 20;
        $this->MaintainVariable('ServiceMessages', $this->Translate('Service messages'), VARIABLETYPE_STRING, '~HTMLBox', $vpos++, $active_service);

        $this->MaintainVariable('CheckControlMessages', $this->Translate('Check-Control messages'), VARIABLETYPE_STRING, '~HTMLBox', $vpos++, $active_checkcontrol);

        $vpos = 50;
        $this->MaintainVariable('TriggerLockDoors', $this->Translate('lock door'), VARIABLETYPE_INTEGER, 'BMW.TriggerRemoteService', $vpos++, $active_lock);
        $this->MaintainVariable('TriggerUnlockDoors', $this->Translate('unlock door'), VARIABLETYPE_INTEGER, 'BMW.TriggerRemoteService', $vpos++, $active_lock);
        if ($active_lock) {
            $this->MaintainAction('TriggerLockDoors', true);
            $this->MaintainAction('TriggerUnlockDoors', true);
        }

        $this->MaintainVariable('TriggerStartClimatisation', $this->Translate('start air conditioner'), VARIABLETYPE_INTEGER, 'BMW.TriggerRemoteService', $vpos++, $active_climate);
        $this->MaintainVariable('TriggerStopClimatisation', $this->Translate('stop air conditioner'), VARIABLETYPE_INTEGER, 'BMW.TriggerRemoteService', $vpos++, $active_climate);
        if ($active_climate) {
            $this->MaintainAction('TriggerStartClimatisation', true);
            $this->MaintainAction('TriggerStopClimatisation', true);
        }

        $this->MaintainVariable('TriggerFlashHeadlights', $this->Translate('flash headlights'), VARIABLETYPE_INTEGER, 'BMW.TriggerRemoteService', $vpos++, $active_flash_headlights);
        if ($active_flash_headlights) {
            $this->MaintainAction('TriggerFlashHeadlights', true);
        }

        $this->MaintainVariable('TriggerSoundHonk', $this->Translate('sound honk'), VARIABLETYPE_INTEGER, 'BMW.TriggerRemoteService', $vpos++, $active_sound_honk);
        if ($active_sound_honk) {
            $this->MaintainAction('TriggerSoundHonk', true);
        }

        $this->MaintainVariable('TriggerLocateVehicle', $this->Translate('locate vehicle'), VARIABLETYPE_INTEGER, 'BMW.TriggerRemoteService', $vpos++, $active_vehicle_finder);
        if ($active_vehicle_finder) {
            $this->MaintainAction('TriggerLocateVehicle', true);
        }

        $this->MaintainVariable('RemoteServiceHistory', $this->Translate('remote service history'), VARIABLETYPE_STRING, '~HTMLBox', $vpos++, true);

        $vpos = 70;
        $this->MaintainVariable('CurrentLatitude', $this->Translate('current latitude'), VARIABLETYPE_FLOAT, 'BMW.Location', $vpos++, $active_current_position);
        $this->MaintainVariable('CurrentLongitude', $this->Translate('current longitude'), VARIABLETYPE_FLOAT, 'BMW.Location', $vpos++, $active_current_position);
        $this->MaintainVariable('CurrentDirection', $this->Translate('current direction'), VARIABLETYPE_INTEGER, 'BMW.Heading', $vpos++, $active_current_position);
        $this->MaintainVariable('LastPositionMessage', $this->Translate('last position message'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $this->MaintainVariable('InMotion', $this->Translate('in motion'), VARIABLETYPE_BOOLEAN, 'BMW.YesNo', $vpos++, $active_current_position);

        $this->MaintainVariable('GoogleMap', $this->Translate('map'), VARIABLETYPE_STRING, '~HTMLBox', $vpos++, $active_googlemap);
        $this->MaintainVariable('GoogleMapType', $this->Translate('map type'), VARIABLETYPE_INTEGER, 'BMW.Googlemap', $vpos++, $active_googlemap);
        $this->MaintainVariable('GoogleMapZoom', $this->Translate('map zoom'), VARIABLETYPE_INTEGER, '~Intensity.100', $vpos++, $active_googlemap);
        if ($active_googlemap) {
            $this->MaintainAction('GoogleMapType', true);
            $this->MaintainAction('GoogleMapZoom', true);
        }

        $vpos = 80;
        $this->MaintainVariable('DoorClosureState', $this->Translate('door closure state'), VARIABLETYPE_INTEGER, 'BMW.DoorClosureState', $vpos++, $active_lock_data);
        $this->MaintainVariable('DoorStateDriverFront', $this->Translate('door driver front'), VARIABLETYPE_INTEGER, 'BMW.DoorState', $vpos++, $active_lock_data);
        $this->MaintainVariable('DoorStateDriverRear', $this->Translate('door driver rear'), VARIABLETYPE_INTEGER, 'BMW.DoorState', $vpos++, $active_lock_data);
        $this->MaintainVariable('DoorStatePassengerFront', $this->Translate('door passenger front'), VARIABLETYPE_INTEGER, 'BMW.DoorState', $vpos++, $active_lock_data);
        $this->MaintainVariable('DoorStatePassengerRear', $this->Translate('door passenger rear'), VARIABLETYPE_INTEGER, 'BMW.DoorState', $vpos++, $active_lock_data);
        $this->MaintainVariable('WindowStateDriverFront', $this->Translate('window driver front'), VARIABLETYPE_INTEGER, 'BMW.WindowState', $vpos++, $active_lock_data);
        $this->MaintainVariable('WindowStateDriverRear', $this->Translate('window driver rear'), VARIABLETYPE_INTEGER, 'BMW.WindowState', $vpos++, $active_lock_data);
        $this->MaintainVariable('WindowStatePassengerFront', $this->Translate('window passenger front'), VARIABLETYPE_INTEGER, 'BMW.WindowState', $vpos++, $active_lock_data);
        $this->MaintainVariable('WindowStatePassengerRear', $this->Translate('window passenger rear'), VARIABLETYPE_INTEGER, 'BMW.WindowState', $vpos++, $active_lock_data);
        $this->MaintainVariable('TrunkState', $this->Translate('trunk'), VARIABLETYPE_INTEGER, 'BMW.TrunkState', $vpos++, $active_lock_data);
        $this->MaintainVariable('HoodState', $this->Translate('hood'), VARIABLETYPE_INTEGER, 'BMW.HoodState', $vpos++, $active_lock_data);

        $this->MaintainVariable('LastUpdateFromVehicle', $this->Translate('last status update'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $this->CleanupOldVersions();

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->SetTimerInterval('UpdateData', 0);
            $this->SetTimerInterval('UpdateRemoteServiceStatus', 0);
            $this->SetStatus(IS_INACTIVE);
            return;
        }

        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');
        $vin = $this->ReadPropertyString('vin');
        if ($user == '' || $password == '' || $vin == '') {
            $status = self::$IS_INVALIDCONFIG;
        }

        $this->SetStatus($status);

        if ($status == IS_ACTIVE) {
            $this->SetUpdateIntervall();
            $this->UpdateRemoteServiceStatus();
        }
    }

    protected function GetFormElements()
    {
        $formElements = [];

        $formElements[] = [
            'type'  => 'Image',
            'image' => 'data:image/png;base64,' . $this->GetBrandImage()
        ];

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Disable instance'
        ];

        $items[] = [
            'name'    => 'user',
            'type'    => 'ValidationTextBox',
            'caption' => 'User'
        ];
        $items[] = [
            'name'    => 'password',
            'type'    => 'PasswordTextBox',
            'caption' => 'Password'
        ];

        $items[] = [
            'type'    => 'Select',
            'name'    => 'country',
            'caption' => 'Country',
            'options' => [
                [
                    'label' => $this->Translate('Germany'),
                    'value' => self::$BMW_COUNTRY_GERMANY
                ],
                [
                    'label' => $this->Translate('Switzerland'),
                    'value' => self::$BMW_COUNTRY_SWITZERLAND
                ],
                [
                    'label' => $this->Translate('Europe'),
                    'value' => self::$BMW_COUNTRY_EUROPE
                ],
                [
                    'label' => $this->Translate('USA'),
                    'value' => self::$BMW_COUNTRY_USA
                ],
                [
                    'label' => $this->Translate('Rest of the World'),
                    'value' => self::$BMW_COUNTRY_OTHER
                ]
            ]
        ];
        $items[] = [
            'type'    => 'Select',
            'name'    => 'brand',
            'caption' => 'Vehicle-brand',
            'options' => [
                [
                    'label' => $this->Translate('BMW'),
                    'value' => self::$BMW_BRAND_BMW
                ],
                [
                    'label' => $this->Translate('Mini'),
                    'value' => self::$BMW_BRAND_MINI
                ]
            ]
        ];
        $items[] = [
            'name'    => 'vin',
            'type'    => 'ValidationTextBox',
            'caption' => 'VIN'
        ];
        $items[] = [
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
                    'label' => $this->Translate('combustion'),
                    'value' => self::$BMW_MODEL_COMBUSTION
                ]
            ]
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Account data',
            'items'   => $items,
        ];

        $formElements[] = [
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
        ];

        $formElements[] = [
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
                    'name'    => 'active_flash_headlights',
                    'type'    => 'CheckBox',
                    'caption' => 'flash headlights'
                ],
                [
                    'name'    => 'active_sound_honk',
                    'type'    => 'CheckBox',
                    'caption' => 'sound honk'
                ],
                [
                    'name'    => 'active_vehicle_finder',
                    'type'    => 'CheckBox',
                    'caption' => 'locate vehicle'
                ],
                [
                    'name'    => 'active_service',
                    'type'    => 'CheckBox',
                    'caption' => 'show service messages'
                ],
                [
                    'name'    => 'active_checkcontrol',
                    'type'    => 'CheckBox',
                    'caption' => 'show check-control messages'
                ],
                [
                    'name'    => 'active_lock_data',
                    'type'    => 'CheckBox',
                    'caption' => 'show detailed lock state'
                ]
            ]
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'map',
            'items'   => [
                [
                    'name'    => 'active_current_position',
                    'type'    => 'CheckBox',
                    'caption' => 'show current position, latitude / longitude'
                ],
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
            ]
        ];

        return $formElements;
    }

    protected function GetFormActions()
    {
        $formActions = [];

        $formActions[] = [
            'type'    => 'Button',
            'label'   => 'Update data',
            'onClick' => 'BMW_UpdateData($id);'
        ];

        $formActions[] = [
            'type'    => 'RowLayout',
            'items'   => [
                [
                    'type'    => 'Select',
                    'name'    => 'carView',
                    'caption' => 'Car view',
                    'options' => [
                        [
                            'label' => $this->Translate('From the front'),
                            'value' => self::$BMW_CARVIEW_FRONT
                        ],
                        [
                            'label' => $this->Translate('Diagonal from front'),
                            'value' => self::$BMW_CARVIEW_FRONTSIDE
                        ],
                        [
                            'label' => $this->Translate('From the side'),
                            'value' => self::$BMW_CARVIEW_SIDE
                        ],
                    ],
                ],
                [
                    'type'    => 'Button',
                    'caption' => 'Load picture',
                    'onClick' => 'BMW_GetCarPicture($id, $carView);'
                ],
            ],
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded ' => false,
            'items'     => [
                [
                    'type'    => 'Button',
                    'label'   => 'Relogin',
                    'onClick' => 'BMW_Relogin($id);'
                ],
                [
                    'type'    => 'Button',
                    'caption' => 'Re-install variable-profiles',
                    'onClick' => 'BMW_InstallVarProfiles($id, true);'
                ]
            ]
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Test area',
            'expanded ' => false,
            'items'     => [
                [
                    'type'    => 'TestCenter',
                ],
            ],
        ];

        $formActions[] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Information',
            'items'   => [
                [
                    'type'    => 'Label',
                    'caption' => $this->InstanceInfo($this->InstanceID),
                ],
            ],
        ];

        return $formActions;
    }

    public function SetUpdateIntervall(int $Minutes = null)
    {
        if (!($Minutes > 0)) {
            $Minutes = $this->ReadPropertyInteger('UpdateInterval');
        }
        $interval = $Minutes * 60 * 1000;
        $this->SendDebug(__FUNCTION__, 'minutes=' . $Minutes, 0);
        $this->SetTimerInterval('UpdateData', $interval);

        $msec = $this->GetTimerInterval('UpdateData');
        $this->SendDebug(__FUNCTION__, 'timer "UpdateData" set to msec=' . $msec, 0);

        $timerList = IPS_GetTimerList();
        foreach ($timerList as $t) {
            $timer = IPS_GetTimer($t);
            if ($timer['InstanceID'] != $this->InstanceID) {
                continue;
            }
            $this->SendDebug(__FUNCTION__, 'timer=' . print_r($timer, true), 0);
        }
    }

    private function GetRegion()
    {
        $country = $this->ReadPropertyInteger('country');
        switch ($country) {
            case self::$BMW_COUNTRY_USA:
                $region = 'NorthAmerica';
                break;
            default:
                $region = 'RestOfWorld';
                break;
        }
        return $region;
    }

    private function GetLang()
    {
        $country = $this->ReadPropertyInteger('country');
        switch ($country) {
            case self::$BMW_COUNTRY_GERMANY:
            case self::$BMW_COUNTRY_SWITZERLAND:
                $lang = 'de';
                break;
            default:
                $lang = 'en';
                break;
        }
        return $lang;
    }

    private function GetBrand()
    {
        $brand = $this->ReadPropertyInteger('brand');
        switch ($brand) {
            case self::$BMW_BRAND_MINI:
                $brand = 'mini';
                break;
            default:
                $brand = 'bmw';
                break;
        }
        return $brand;
    }

    public function Relogin()
    {
        $this->WriteAttributeString('ApiSettings', '');
        $this->WriteAttributeString('ApiRefreshToken', '');
        $this->SetBuffer('AccessToken', '');
        $this->GetAccessToken();
    }

    private function urlsafe_b64encode($string)
    {
        $data = base64_encode($string);
        $data = str_replace(['+', '/'], ['-', '_'], $data);
        $data = rtrim($data, '=');
        return $data;
    }

    private function check_response4error($data)
    {
        $result = false;
        $jdata = json_decode($data, true);
        if (isset($jdata['error'])) {
            $result = 'error ' . $jdata['error'] . ' ' . $jdata['description'];
        }
        return $result;
    }

    private function Login()
    {
        $this->WriteAttributeString('ApiSettings', '');
        $this->WriteAttributeString('ApiRefreshToken', '');
        $this->SetBuffer('AccessToken', '');

        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');
        $region = $this->GetRegion();

        $baseurl = 'https://' . self::$server_urls_eadrax[$region];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2TLS);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 50);
        curl_setopt($ch, CURLOPT_HTTP09_ALLOWED, true);
        curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, '');
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        $this->SendDebug(__FUNCTION__, '*** get config', 0);

        $config_url = $baseurl . '/' . self::$oauth_config_endpoint;
        $header = [
            'ocp-apim-subscription-key: ' . self::$ocp_apim_key[$region],
            'x-user-agent: ' . sprintf(self::$x_user_agent_fmt, $this->GetBrand()),
        ];

        $this->SendDebug(__FUNCTION__, 'http-get, url=' . $config_url, 0);
        $this->SendDebug(__FUNCTION__, '... header=' . print_r($header, true), 0);

        $time_start = microtime(true);

        curl_setopt($ch, CURLOPT_URL, $config_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);

        $statuscode = 0;
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        }
        if ($statuscode == 0) {
            if ($httpcode == 401) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
            } elseif ($httpcode == 403) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (forbidden)';
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode != 200) {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode . '(' . $this->HttpCode2Text($httpcode) . ')';
            }
        }
        if ($statuscode == 0) {
            $curl_info = curl_getinfo($ch);
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->SendDebug(__FUNCTION__, ' => body=' . $body, 0);
        }
        if ($statuscode == 0) {
            $err = $this->check_response4error($body);
            if ($err != false) {
                $statuscode = self::$IS_APIERROR;
            }
        }
        if ($statuscode == 0) {
            $oauth_settings = json_decode($body, true);
            if ($oauth_settings == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'invalid/malformed data';
            }
        }
        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->SetStatus($statuscode);
            return false;
        }
        $this->SendDebug(__FUNCTION__, ' => oauth_settings=' . print_r($oauth_settings, true), 0);
        $this->WriteAttributeString('ApiSettings', $body);

        $this->SendDebug(__FUNCTION__, '*** authenticate, step 1', 0);

        # Setting up PKCS data
        $verifier_bytes = random_bytes(64);
        $code_verifier = $this->urlsafe_b64encode($verifier_bytes);

        $challenge_bytes = hash('sha256', $code_verifier, true);
        $code_challenge = $this->urlsafe_b64encode($challenge_bytes);

        $state_bytes = random_bytes(16);
        $state = $this->urlsafe_b64encode($state_bytes);

        $this->SendDebug(__FUNCTION__, 'code_verifier=' . $code_verifier . ', code_challenge=' . $code_challenge . ', state=' . $state, 0);

        $gcdm_base_url = $oauth_settings['gcdmBaseUrl'];
        $auth_url = $gcdm_base_url . self::$oauth_authenticate_endpoint;

        $header = [
            'Content-Type: application/x-www-form-urlencoded',
        ];

        $oauth_base_values = [
            'client_id'             => $oauth_settings['clientId'],
            'response_type'         => 'code',
            'redirect_uri'          => $oauth_settings['returnUrl'],
            'state'                 => $state,
            'nonce'                 => 'login_nonce',
            'scope'                 => implode(' ', $oauth_settings['scopes']),
            'code_challenge'        => $code_challenge,
            'code_challenge_method' => 'S256',
        ];

        $this->SendDebug(__FUNCTION__, 'oauth_base_values=' . print_r($oauth_base_values, true), 0);

        $postfields = $oauth_base_values;
        $postfields['grant_type'] = 'authorization_code';
        $postfields['username'] = $user;
        $postfields['password'] = $password;

        $this->SendDebug(__FUNCTION__, 'http-post, url=' . $auth_url, 0);
        $this->SendDebug(__FUNCTION__, '... header=' . print_r($header, true), 0);
        $this->SendDebug(__FUNCTION__, '... postfields=' . print_r($postfields, true), 0);

        $time_start = microtime(true);

        curl_setopt($ch, CURLOPT_URL, $auth_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));

        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);

        $statuscode = 0;
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        }
        if ($statuscode == 0) {
            if ($httpcode == 401) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
            } elseif ($httpcode == 403) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (forbidden)';
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode != 200) {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode . '(' . $this->HttpCode2Text($httpcode) . ')';
            }
        }
        if ($statuscode == 0) {
            $curl_info = curl_getinfo($ch);
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);
            preg_match_all('|Set-Cookie: (.*);|U', $head, $results);
            $cookies = explode(';', implode(';', $results[1]));

            $this->SendDebug(__FUNCTION__, ' => body=' . $body, 0);
            $this->SendDebug(__FUNCTION__, ' => cookies=' . print_r($cookies, true), 0);
        }
        if ($statuscode == 0) {
            $err = $this->check_response4error($body);
            if ($err != false) {
                $statuscode = self::$IS_APIERROR;
            }
        }
        if ($statuscode == 0) {
            $jbody = json_decode($body, true);
            if ($jbody == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'invalid/malformed data';
            }
        }
        if ($statuscode == 0) {
            $this->SendDebug(__FUNCTION__, ' => jbody=' . print_r($jbody, true), 0);
            if (isset($jbody['redirect_to']) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'missing element "redirect_to" in "' . $body . '"';
            }
        }
        if ($statuscode == 0) {
            $redirect_uri = substr($jbody['redirect_to'], strlen('redirect_uri='));
            $this->SendDebug(__FUNCTION__, ' => redirect_uri=' . $redirect_uri, 0);
            $redirect_parts = parse_url($redirect_uri);
            $this->SendDebug(__FUNCTION__, ' => redirect_parts=' . print_r($redirect_parts, true), 0);
            if (isset($redirect_parts['query']) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'missing element "query" in "' . $redirect_uri . '"';
            }
        }
        if ($statuscode == 0) {
            parse_str($redirect_parts['query'], $redirect_opts);
            $this->SendDebug(__FUNCTION__, ' => redirect_opts=' . print_r($redirect_opts, true), 0);
            if (isset($redirect_opts['authorization']) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'missing element "authorization" in "' . $redirect_parts['query'] . '"';
            }
        }
        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->SetStatus($statuscode);
            return false;
        }
        $this->SendDebug(__FUNCTION__, 'authorization="' . $redirect_opts['authorization'] . '"', 0);

        $this->SendDebug(__FUNCTION__, '*** authenticate, step 2', 0);

        $postfields = $oauth_base_values;
        $postfields['authorization'] = $redirect_opts['authorization'];

        $header = [
            'Content-Type: application/x-www-form-urlencoded',
        ];
        foreach ($cookies as $cookie) {
            $header[] = 'Cookie: ' . $cookie;
        }

        $this->SendDebug(__FUNCTION__, 'http-post, url=' . $auth_url, 0);
        $this->SendDebug(__FUNCTION__, '... header=' . print_r($header, true), 0);
        $this->SendDebug(__FUNCTION__, '... postfields=' . print_r($postfields, true), 0);

        $time_start = microtime(true);

        curl_setopt($ch, CURLOPT_URL, $auth_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));

        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);

        $statuscode = 0;
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        }
        if ($statuscode == 0) {
            if ($httpcode == 401) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
            } elseif ($httpcode == 403) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (forbidden)';
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode != 302) {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode . '(' . $this->HttpCode2Text($httpcode) . ')';
            }
        }
        if ($statuscode == 0) {
            $curl_info = curl_getinfo($ch);
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->SendDebug(__FUNCTION__, ' => head=' . $head, 0);
            $this->SendDebug(__FUNCTION__, ' => body=' . $body, 0);
            $this->SendDebug(__FUNCTION__, ' => cookies=' . print_r($cookies, true), 0);
        }
        if ($statuscode == 0) {
            $err = $this->check_response4error($body);
            if ($err != false) {
                $statuscode = self::$IS_APIERROR;
            }
        }
        if ($statuscode == 0) {
            preg_match_all('|location: (.*)|', $head, $results);
            $this->SendDebug(__FUNCTION__, ' => results=' . print_r($results, true), 0);
            if (isset($results[1][0]) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'missing element "location" in "' . $head . '"';
            }
        }
        if ($statuscode == 0) {
            $location = $results[1][0];
            $this->SendDebug(__FUNCTION__, ' => location=' . $location, 0);
            $location_parts = parse_url($location);
            $this->SendDebug(__FUNCTION__, ' => location_parts=' . print_r($location_parts, true), 0);

            if (isset($location_parts['query']) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'missing element "query" in "' . $location . '"';
            }
        }
        if ($statuscode == 0) {
            parse_str($location_parts['query'], $location_opts);
            $this->SendDebug(__FUNCTION__, ' => location_opts=' . print_r($location_opts, true), 0);
            if (isset($location_opts['code']) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'missing element "code" in "' . $location_parts['query'] . '"';
            }
        }
        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->SetStatus($statuscode);
            return false;
        }
        $this->SendDebug(__FUNCTION__, 'code="' . $location_opts['code'] . '"', 0);

        $this->SendDebug(__FUNCTION__, '*** get token', 0);

        $oauth_authorization = base64_encode($oauth_settings['clientId'] . ':' . $oauth_settings['clientSecret']);

        $token_url = $oauth_settings['tokenEndpoint'];

        $header[] = 'Authorization: Basic ' . $oauth_authorization;

        $postfields = [
            'code'             => $location_opts['code'],
            'code_verifier'    => $code_verifier,
            'redirect_uri'     => $oauth_settings['returnUrl'],
            'grant_type'       => 'authorization_code',
        ];

        $this->SendDebug(__FUNCTION__, 'http-post, url=' . $token_url, 0);
        $this->SendDebug(__FUNCTION__, '... header=' . print_r($header, true), 0);
        $this->SendDebug(__FUNCTION__, '... postfields=' . print_r($postfields, true), 0);

        $time_start = microtime(true);

        curl_setopt($ch, CURLOPT_URL, $token_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));

        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);

        $statuscode = 0;
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        }
        if ($statuscode == 0) {
            if ($httpcode == 401) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
            } elseif ($httpcode == 403) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (forbidden)';
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode != 200) {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode . '(' . $this->HttpCode2Text($httpcode) . ')';
            }
        }
        if ($statuscode == 0) {
            $curl_info = curl_getinfo($ch);
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->SendDebug(__FUNCTION__, ' => body=' . $body, 0);
        }
        if ($statuscode == 0) {
            $err = $this->check_response4error($body);
            if ($err != false) {
                $statuscode = self::$IS_APIERROR;
            }
        }
        if ($statuscode == 0) {
            $jbody = json_decode($body, true);
            if ($jbody == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'invalid/malformed data';
            }
        }
        if ($statuscode == 0) {
            $this->SendDebug(__FUNCTION__, ' => jbody=' . print_r($jbody, true), 0);
            foreach (['access_token', 'refresh_token', 'expires_in'] as $key) {
                if (isset($jbody[$key]) == false) {
                    $statuscode = self::$IS_INVALIDDATA;
                    $err = 'missing element "' . $key . '" in "' . $body . '"' . PHP_EOL;
                    break;
                }
            }
        }
        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->SetStatus($statuscode);
            return false;
        }

        $this->SetStatus(IS_ACTIVE);

        $access_token = $jbody['access_token'];
        $refresh_token = $jbody['refresh_token'];
        $expiration = time() + $jbody['expires_in'];
        $this->SendDebug(__FUNCTION__, 'new access_token=' . $access_token . ', refresh_token=' . $refresh_token . ', valid until ' . date('d.m.y H:i:s', $expiration), 0);
        $jtoken = [
            'access_token' => $access_token,
            'expiration'   => $expiration,
        ];
        $this->WriteAttributeString('ApiRefreshToken', $refresh_token);
        $this->SetBuffer('AccessToken', json_encode($jtoken));
        return $access_token;
    }

    private function RefreshToken()
    {
        $refresh_token = $this->ReadAttributeString('ApiRefreshToken');
        if ($refresh_token == false) {
            $access_token = $this->Login();
            if ($access_token == false) {
                $this->SendDebug(__FUNCTION__, 'login failed', 0);
            }
            return $access_token;
        }
        $this->SendDebug(__FUNCTION__, 'refresh_token=' . $refresh_token, 0);

        $oauth_settings = json_decode($this->ReadAttributeString('ApiSettings'), true);
        if ($oauth_settings == false) {
            $access_token = $this->Login();
            if ($access_token == false) {
                $this->SendDebug(__FUNCTION__, 'login failed', 0);
            }
            return $access_token;
        }
        $this->SendDebug(__FUNCTION__, 'oauth_settings=' . print_r($oauth_settings, true), 0);

        $region = $this->GetRegion();

        $token_url = $oauth_settings['tokenEndpoint'];

        $oauth_authorization = base64_encode($oauth_settings['clientId'] . ':' . $oauth_settings['clientSecret']);
        $header = [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . $oauth_authorization,
        ];

        $postfields = [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refresh_token,
        ];

        $token_url = $oauth_settings['tokenEndpoint'];

        $this->SendDebug(__FUNCTION__, 'http-post, url=' . $token_url, 0);
        $this->SendDebug(__FUNCTION__, '... header=' . print_r($header, true), 0);
        $this->SendDebug(__FUNCTION__, '... postfields=' . print_r($postfields, true), 0);

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

        $statuscode = 0;
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        }
        if ($statuscode == 0) {
            if ($httpcode == 401) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
            } elseif ($httpcode == 403) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (forbidden)';
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode != 200) {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode . '(' . $this->HttpCode2Text($httpcode) . ')';
            }
        }
        if ($statuscode == 0) {
            $curl_info = curl_getinfo($ch);
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->SendDebug(__FUNCTION__, ' => body=' . $body, 0);
        }
        if ($statuscode == 0) {
            $err = $this->check_response4error($body);
            if ($err != false) {
                $statuscode = self::$IS_APIERROR;
            }
        }
        if ($statuscode == 0) {
            $jbody = json_decode($body, true);
            if ($jbody == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'invalid/malformed data';
            }
        }
        if ($statuscode == 0) {
            $this->SendDebug(__FUNCTION__, ' => jbody=' . print_r($jbody, true), 0);
            foreach (['access_token', 'refresh_token', 'expires_in'] as $key) {
                if (isset($jbody[$key]) == false) {
                    $statuscode = self::$IS_INVALIDDATA;
                    $err = 'missing element "' . $key . '" in "' . $body . '"' . PHP_EOL;
                    break;
                }
            }
        }
        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->SetStatus($statuscode);

            $this->WriteAttributeString('ApiRefreshToken', '');
            $this->SetBuffer('AccessToken', '');

            return false;
        }

        $this->SetStatus(IS_ACTIVE);

        $access_token = $jbody['access_token'];
        $refresh_token = $jbody['refresh_token'];
        $expiration = time() + $jbody['expires_in'];
        $this->SendDebug(__FUNCTION__, 'new access_token=' . $access_token . ', refresh_token=' . $refresh_token . ', valid until ' . date('d.m.y H:i:s', $expiration), 0);
        $jtoken = [
            'access_token' => $access_token,
            'expiration'   => $expiration,
        ];
        $this->WriteAttributeString('ApiRefreshToken', $refresh_token);
        $this->SetBuffer('AccessToken', json_encode($jtoken));
        return $access_token;
    }

    private function GetAccessToken()
    {
        $data = $this->GetBuffer('AccessToken');
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
            $this->SendDebug(__FUNCTION__, 'no/empty buffer "AccessToken"', 0);
        }
        $access_token = $this->RefreshToken();
        return $access_token;
    }

    private function CallAPI($endpoint, $postfields, $params, $header_add)
    {
        $region = $this->GetRegion();

        $url = 'https://' . self::$server_urls_eadrax[$region] . $endpoint;

        if ($params != '') {
            $this->SendDebug(__FUNCTION__, 'params=' . print_r($params, true), 0);
            $n = 0;
            foreach ($params as $param => $value) {
                $url .= ($n++ ? '&' : '?') . $param . '=' . rawurlencode(strval($value));
            }
        }

        $this->SendDebug(__FUNCTION__, 'url=' . $url, 0);

        $access_token = $this->GetAccessToken();
        if ($access_token == false) {
            $this->SendDebug(__FUNCTION__, 'no access_token', 0);
            return false;
        }

        $header_base = [
            'accept'          => 'application/json',
            'x-user-agent'    => sprintf(self::$x_user_agent_fmt, $this->GetBrand()),
            'Authorization'   => 'Bearer ' . $access_token,
            'accept-language' => $this->GetLang(),
        ];
        if ($header_add != '') {
            foreach ($header_add as $key => $val) {
                $header_base[$key] = $val;
            }
        }
        $header = [];
        foreach ($header_base as $key => $val) {
            $header[] = $key . ': ' . $val;
        }

        $mode = $postfields != '' ? 'post' : 'get';

        $this->SendDebug(__FUNCTION__, 'http-' . $mode . ', url=' . $url, 0);
        $this->SendDebug(__FUNCTION__, '... header=' . print_r($header, true), 0);
        if ($postfields != '') {
            $this->SendDebug(__FUNCTION__, '... postfields=' . print_r($postfields, true), 0);
        }

        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2TLS);
        if ($postfields != '') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));
        }
        curl_setopt($ch, CURLOPT_HEADER, true);

        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);

        $statuscode = 0;
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        }
        if ($statuscode == 0) {
            if ($httpcode == 401) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
            } elseif ($httpcode == 403) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (forbidden)';
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode != 200) {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode . '(' . $this->HttpCode2Text($httpcode) . ')';
            }
        }
        if ($statuscode == 0) {
            $curl_info = curl_getinfo($ch);
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            if (ctype_print($body)) {
                $this->SendDebug(__FUNCTION__, ' => body=' . $body, 0);
            } else {
                $this->SendDebug(__FUNCTION__, ' => body potentially contains binary data, size=' . strlen($body), 0);
            }
        }
        if ($statuscode == 0) {
            $err = $this->check_response4error($body);
            if ($err != false) {
                $statuscode = self::$IS_APIERROR;
            }
        }
        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->SetStatus($statuscode);
            return false;
        }

        $jbody = json_decode($body, true);
        if (isset($jbody['errors'])) {
            $this->SendDebug(__FUNCTION__, ' => error=' . $jbody['errors'], 0);
            $body = false;
        }

        $this->SetStatus(IS_ACTIVE);

        return $body;
    }

    private function UpdateVehicleData($data)
    {
        $jdata = json_decode($data, true);

        $properties = $jdata['properties'];
        $this->SendDebug(__FUNCTION__, 'properties=' . print_r($properties, true), 0);
        $status = $jdata['status'];
        $this->SendDebug(__FUNCTION__, 'status=' . print_r($status, true), 0);

        $isChanged = false;

        $active_lock_data = $this->ReadPropertyBoolean('active_lock_data');
        if ($active_lock_data) {
            $val = $this->GetArrayElem($properties, 'doorsAndWindows.doors.driverFront', '');
            $this->SaveValue('DoorStateDriverFront', $this->MapDoorState($val), $isChanged);

            $val = $this->GetArrayElem($properties, 'doorsAndWindows.doors.driverRear', '');
            $this->SaveValue('DoorStateDriverRear', $this->MapDoorState($val), $isChanged);

            $val = $this->GetArrayElem($properties, 'doorsAndWindows.doors.passengerFront', '');
            $this->SaveValue('DoorStatePassengerFront', $this->MapDoorState($val), $isChanged);

            $val = $this->GetArrayElem($properties, 'doorsAndWindows.doors.passengerRear', '');
            $this->SaveValue('DoorStatePassengerRear', $this->MapDoorState($val), $isChanged);

            $val = $this->GetArrayElem($properties, 'doorsAndWindows.windows.driverFront', '');
            $this->SaveValue('WindowStateDriverFront', $this->MapWindowState($val), $isChanged);

            $val = $this->GetArrayElem($properties, 'doorsAndWindows.windows.driverRear', '');
            $this->SaveValue('WindowStateDriverRear', $this->MapWindowState($val), $isChanged);

            $val = $this->GetArrayElem($properties, 'doorsAndWindows.windows.passengerFront', '');
            $this->SaveValue('WindowStatePassengerFront', $this->MapWindowState($val), $isChanged);

            $val = $this->GetArrayElem($properties, 'doorsAndWindows.windows.passengerRear', '');
            $this->SaveValue('WindowStatePassengerRear', $this->MapWindowState($val), $isChanged);

            $val = $this->GetArrayElem($properties, 'doorsAndWindows.trunk', '');
            $this->SaveValue('TrunkState', $this->MapTrunkState($val), $isChanged);

            $val = $this->GetArrayElem($properties, 'doorsAndWindows.hood', '');
            $this->SaveValue('HoodState', $this->MapHoodState($val), $isChanged);

            $areDoorsLocked = $this->GetArrayElem($properties, 'areDoorsLocked', false);
            if (boolval($areDoorsLocked)) {
                $val = self::$BMW_DOOR_CLOSURE_LOCKED;
            } else {
                $val = self::$BMW_DOOR_CLOSURE_UNLOCKED;
            }
            $this->SaveValue('DoorClosureState', $val, $isChanged);
        }

        $val = $this->GetArrayElem($properties, 'lastUpdatedAt', '');
        $this->SaveValue('LastUpdateFromVehicle', strtotime($val), $isChanged);

        $val = $this->GetArrayElem($properties, 'fuelLevel.value', '');
        $this->SaveValue('TankCapacity', floatval($val), $isChanged);

        $val = $this->GetArrayElem($properties, 'combined.distance.value', '');
        if ($val == '') {
            $val = $this->GetArrayElem($properties, 'combustionRange.distance.value', '');
        }
        $this->SaveValue('RemainingCombinedRange', floatval($val), $isChanged);

        $model = $this->ReadPropertyInteger('model');
        if ($model != self::$BMW_MODEL_COMBUSTION) {
            $val = $this->GetArrayElem($properties, 'electricRange.distance.value', '');
            $this->SaveValue('RemainingElectricRange', floatval($val), $isChanged);

            $val = $this->GetArrayElem($properties, 'chargingState.chargePercentage', '');
            $this->SaveValue('ChargingLevel', floatval($val), $isChanged);

            $val = $this->GetArrayElem($properties, 'chargingState.isChargerConnected', '');
            if (boolval($val) == true) {
                $connector_status = self::$BMW_CONNECTOR_STATE_CONNECTED;
            } else {
                $connector_status = self::$BMW_CONNECTOR_STATE_DISCONNECTED;
            }
            $this->SaveValue('ChargingConnectorStatus', $connector_status, $isChanged);

            $val = $this->GetArrayElem($properties, 'chargingState.state', '');
            $charging_status = $this->MapChargingState($val);
            $this->SaveValue('ChargingStatus', $charging_status, $isChanged);

            // status.chargingSettings.targetSoc

            $fuelIndicators = $status['fuelIndicators'];
            if ($fuelIndicators != '') {
                foreach ($fuelIndicators as $fuelIndicator) {
                    $rangeIconId = $this->GetArrayElem($fuelIndicator, 'rangeIconId', '');
                    if ($rangeIconId != 59683 /* Electric */) {
                        continue;
                    }
                    $infoLabel = $this->GetArrayElem($fuelIndicator, 'infoLabel', '');
                    $this->SaveValue('ChargingInfo', $infoLabel, $isChanged);

                    $val = $this->GetArrayElem($fuelIndicator, 'chargingStatusType', '');
                    $chargingStatusType = $this->MapChargingState($val);
                    $this->SaveValue('ChargingStatus', $chargingStatusType, $isChanged);

                    $charging_start = 0;
                    $charging_end = 0;
                    if ($chargingStatusType == self::$BMW_CHARGING_STATE_ACTIVE || $chargingStatusType == self::$BMW_CHARGING_STATE_PLUGGED_IN) {
                        $ts = 0;
                        $r = explode('~', $infoLabel);
                        if (isset($r[1])) {
                            $d = new DateTime($r[1]);
                            $ts = intval($d->format('U'));
                            if ($ts && $ts < time()) {
                                $ts += (60 * 60 * 24);
                            }
                        }
                        if ($chargingStatusType == self::$BMW_CHARGING_STATE_ACTIVE) {
                            $charging_start = 0;
                            $charging_end = $ts;
                        }
                        if ($chargingStatusType == self::$BMW_CHARGING_STATE_PLUGGED_IN) {
                            $charging_start = $ts;
                            $charging_end = 0;
                        }
                    }
                    $this->SaveValue('ChargingStart', $charging_start, $isChanged);
                    $this->SaveValue('ChargingEnd', $charging_end, $isChanged);

                    $s = 'infoLabel=' . $infoLabel;
                    $s .= ', charging_status=' . $charging_status;
                    if ($charging_start) {
                        $s .= ', start=' . date('d.m. H:i:s', $charging_start);
                    }
                    if ($charging_end) {
                        $s .= ', end=' . date('d.m. H:i:s', $charging_end);
                    }
                    $this->SendDebug(__FUNCTION__, $s . ', indicator=' . print_r($fuelIndicator, true), 0);
                }
            }
        }

        $active_current_position = $this->ReadPropertyBoolean('active_current_position');
        if ($active_current_position) {
            $dir = $this->GetArrayElem($properties, 'vehicleLocation.heading', '');
            if ($dir != '') {
                $this->SaveValue('CurrentDirection', intval($dir), $isChanged);
            }

            $lat = $this->GetArrayElem($properties, 'vehicleLocation.coordinates.latitude', '');
            if ($lat != '') {
                $this->SaveValue('CurrentLatitude', floatval($lat), $isChanged);
            }

            $lng = $this->GetArrayElem($properties, 'vehicleLocation.coordinates.longitude', '');
            if ($lng != '') {
                $this->SaveValue('CurrentLongitude', floatval($lng), $isChanged);
            }

            if ($lat != '' && $lng != '') {
                $active_googlemap = $this->ReadPropertyBoolean('active_googlemap');
                if ($active_googlemap) {
                    $maptype = $this->GetValue('GoogleMapType');
                    $zoom = $this->GetValue('GoogleMapZoom');
                    $this->SetGoogleMap($maptype, $zoom);
                }
                $val = $this->GetArrayElem($properties, 'lastUpdatedAt', '');
                $this->SaveValue('LastPositionMessage', strtotime($val), $isChanged);
            }

            $val = $this->GetArrayElem($properties, 'inMotion', '');
            $this->SaveValue('InMotion', boolval($val), $isChanged);
        }

        $val = $this->GetArrayElem($status, 'currentMileage.mileage', '');
        $this->SaveValue('Mileage', intval($val), $isChanged);

        $active_service = $this->ReadPropertyBoolean('active_service');
        if ($active_service) {
            $tbl = '';
            $requiredServices = $this->GetArrayElem($status, 'requiredServices', '');
            if ($requiredServices != '') {
                foreach ($requiredServices as $requiredService) {
                    $title = $requiredService['title'];
                    if (isset($requiredService['longDescription'])) {
                        $desc = $requiredService['longDescription'];
                    } else {
                        $desc = '';
                    }
                    $subtitle = $requiredService['subtitle'];
                    $tbl .= '<tr>' . PHP_EOL;
                    $tbl .= '<td>' . $title . '</td>' . PHP_EOL;
                    $tbl .= '<td>' . $desc . '</td>' . PHP_EOL;
                    $tbl .= '<td>' . $subtitle . '</td>' . PHP_EOL;
                    $tbl .= '</tr>' . PHP_EOL;
                }
            }
            if ($tbl != '') {
                $html = '<style>' . PHP_EOL;
                $html .= 'th, td { padding: 2px 10px; text-align: left; }' . PHP_EOL;
                $html .= '</style>' . PHP_EOL;
                $html .= '<table>' . PHP_EOL;
                $html .= '<tr>' . PHP_EOL;
                $html .= '<th>' . $this->Translate('Service type') . '</th>' . PHP_EOL;
                $html .= '<th>' . $this->Translate('Description') . '</th>' . PHP_EOL;
                $html .= '<th>' . $this->Translate('Due') . '</th>' . PHP_EOL;
                $html .= '</tr>' . PHP_EOL;
                $html .= $tbl;
                $html .= '</table>' . PHP_EOL;
            } else {
                $html = $this->Translate('No required services');
            }

            if ($this->GetValue('ServiceMessages') != $html) {
                $this->SetValue('ServiceMessages', $html);
            }
        }

        $active_checkcontrol = $this->ReadPropertyBoolean('active_checkcontrol');
        if ($active_checkcontrol) {
            $tbl = '';
            $checkControlMessages = $this->GetArrayElem($status, 'checkControlMessages', '');
            if ($checkControlMessages != '') {
                foreach ($checkControlMessages as $checkControlMessages) {
                    $title = $checkControlMessages['title'];
                    $state = $checkControlMessages['state'];
                    $tbl .= '<tr>' . PHP_EOL;
                    $tbl .= '<td>' . $title . '</td>' . PHP_EOL;
                    $tbl .= '<td>' . $state . '</td>' . PHP_EOL;
                    $tbl .= '</tr>' . PHP_EOL;
                }
            }
            if ($tbl != '') {
                $html = '<style>' . PHP_EOL;
                $html .= 'th, td { padding: 2px 10px; text-align: left; }' . PHP_EOL;
                $html .= '</style>' . PHP_EOL;
                $html .= '<table>' . PHP_EOL;
                $html .= '<tr>' . PHP_EOL;
                $html .= '<th>' . $this->Translate('Message') . '</th>' . PHP_EOL;
                $html .= '<th>' . $this->Translate('State') . '</th>' . PHP_EOL;
                $html .= '</tr>' . PHP_EOL;
                $html .= $tbl;
                $html .= '</table>' . PHP_EOL;
            } else {
                $html = $this->Translate('No check-control messages');
            }

            if ($this->GetValue('CheckControlMessages') != $html) {
                $this->SetValue('CheckControlMessages', $html);
            }
        }
    }

    private function GetVehicleData()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'call api ...', 0);

        $vin = $this->ReadPropertyString('vin');

        $result = false;

        $params = [
            'apptimezone'   => strval(date('Z') / 3600) . '.0',
            'appDateTime'   => strval(date('U')),
            'tireGuardMode' => 'ENABLED',
        ];
        $data = $this->CallAPI(self::$vehicles_endpoint, '', $params, '');
        if ($data != false) {
            $jdata = json_decode($data, true);
            $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
            foreach ($jdata as $vehicle) {
                if ($vehicle['vin'] == $vin) {
                    $this->SendDebug(__FUNCTION__, 'vehicle=' . print_r($vehicle, true), 0);
                    $result = json_encode($vehicle);
                    $this->UpdateVehicleData($result);
                    break;
                }
            }
        }
        return $result;
    }

    private function GetChargingStatistics()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'call api ...', 0);

        $result = false;

        $vin = $this->ReadPropertyString('vin');

        $params = [
            'vin'           => $vin,
            'currentDate'   => date('c'),
        ];

        $data = $this->CallAPI(self::$charging_statistics_endpoint, '', $params, '');
        if ($data != false) {
            $jdata = json_decode($data, true);
            $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
        }

        return $result;
    }

    public function GetCarPicture(string $carView = null)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'call api ...', 0);

        $result = false;

        $vin = $this->ReadPropertyString('vin');

        $endpoint = sprintf(self::$vehicle_img_endpoint, $vin);

        $params = [];

        if (is_null($carView) == false) {
            $params['carView'] = $carView;
        }

        $header_add = [
            'accept' => 'image/png',
        ];

        $data = $this->CallAPI($endpoint, '', $params, $header_add);
        if ($data != false) {
            $this->SetMediaData('Car picture', $data, MEDIATYPE_IMAGE, '.png', false);
            $result = true;
        }

        return $result;
    }

    private function UpdateChargingSessions($data)
    {
        $jdata = json_decode($data, true);

        $isChanged = false;

        $sessions = $this->GetArrayElem($jdata, 'chargingSessions.sessions', '');
        $tbl = '';
        if ($sessions != '') {
            foreach ($sessions as $session) {
                $r = explode('_', $session['id']);
                if (isset($r[0])) {
                    $ts = strtotime($r[0]);
                    $tstamp = date('d.m. H:i:s', $ts);
                } else {
                    $tstamp = $session['title'];
                }
                $subtitle = $session['subtitle'];
                $energyCharged = $session['energyCharged'];
                $sessionStatus = $session['sessionStatus'];

                $tbl .= '<tr>' . PHP_EOL;
                $tbl .= '<td>' . $tstamp . '</td>' . PHP_EOL;
                $tbl .= '<td>' . $subtitle . '</td>' . PHP_EOL;
                $tbl .= '<td>' . $energyCharged . '</td>' . PHP_EOL;
                $tbl .= '</tr>' . PHP_EOL;
            }
        }
        if ($tbl != '') {
            $html = '<style>' . PHP_EOL;
            $html .= 'th, td { padding: 2px 10px; text-align: left; }' . PHP_EOL;
            $html .= '</style>' . PHP_EOL;
            $html .= '<table>' . PHP_EOL;
            $html .= '<tr>' . PHP_EOL;
            $html .= '<th>' . $this->Translate('Moment') . '</th>' . PHP_EOL;
            $html .= '<th>' . $this->Translate('Information') . '</th>' . PHP_EOL;
            $html .= '<th>' . $this->Translate('Energy') . '</th>' . PHP_EOL;
            $html .= '</tr>' . PHP_EOL;
            $html .= $tbl;
            $html .= '</table>' . PHP_EOL;
        } else {
            $html = $this->Translate('there are no charging sessions present');
        }

        if ($this->GetValue('ChargingSessions') != $html) {
            $this->SetValue('ChargingSessions', $html);
        }
    }

    private function GetChargingSessions()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'call api ...', 0);

        $vin = $this->ReadPropertyString('vin');

        $result = false;

        $params = [
            'vin'                 => $vin,
            'maxResults'          => 40,
            'include_date_picker' => 'true'
        ];
        $data = $this->CallAPI(self::$charging_sessions_endpoint, '', $params, '');
        if ($data != false) {
            $jdata = json_decode($data, true);
            $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
            $this->UpdateChargingSessions($data);
        }
        return $result;
    }

    private function GetHomePosition()
    {
        $instID = IPS_GetInstanceListByModuleID('{45E97A63-F870-408A-B259-2933F7EABF74}')[0];
        if (IPS_GetKernelVersion() >= 5) {
            $loc = json_decode(IPS_GetProperty($instID, 'Location'), true);
            $lng = $loc['longitude'];
            $lat = $loc['latitude'];
        } else {
            $lng = IPS_GetProperty($instID, 'Longitude');
            $lat = IPS_GetProperty($instID, 'Latitude');
        }
        $pos = [
            'longitude' => number_format($lng, 6, '.', ''),
            'latitude'  => number_format($lat, 6, '.', ''),
        ];
        return $pos;
    }

    protected function ExecuteRemoteService($service, $action)
    {
        $this->SendDebug(__FUNCTION__, 'service=' . $service . ', action=' . $action, 0);

        $vin = $this->ReadPropertyString('vin');

        $endpoint = self::$remoteService_endpoint . '/' . $vin . '/' . strtolower(preg_replace('/_/', '-', $service));

        $postfields = [];
        if ($action != false) {
            $postfields['action'] = $action;
        }

        $pos = $this->GetHomePosition();
        $params = [
            'deviceTime' => date('Y-m-d\TH:i:s'),
            'dlat'       => $pos['latitude'],
            'dlon'       => $pos['longitude'],
        ];
        $data = $this->CallAPI($endpoint, $postfields, $params, '');
        $this->SendDebug(__FUNCTION__, 'service=' . $service . ', action=' . $action . ', result=' . $data, 0);

        $ref_tstamp = strtotime('-1 month');

        $history = json_decode($this->ReadAttributeString('RemoteServiceHistory'), true);
        if ($history == false) {
            $history = [];
        }
        $new_history = [];
        foreach ($history as $event) {
            if ($event['modstamp'] < $ref_tstamp) {
                continue;
            }
            $new_history[] = $event;
        }
        $jdata = json_decode($data, true);
        if ($jdata == false) {
            $event = [
                'service'     => $service,
                'action'      => $action,
                'eventStatus' => 'ERROR',
                'modstamp'    => time(),
            ];
        } else {
            $event = [
                'service'      => $service,
                'action'       => $action,
                'eventStatus'  => 'PENDING',
                'eventId'      => $jdata['eventId'],
                'creationTime' => strtotime($jdata['creationTime']),
                'modstamp'     => time(),
            ];
        }
        $new_history[] = $event;
        $this->SendDebug(__FUNCTION__, 'remote service history=' . print_r($new_history, true), 0);
        $this->WriteAttributeString('RemoteServiceHistory', json_encode($new_history));

        $this->UpdateRemoteServiceStatus();

        return $data;
    }

    public function StartClimateControl()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
        $result = $this->ExecuteRemoteService('CLIMATE_NOW', 'START');
        return $result;
    }

    public function StopClimateControl()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
        $result = $this->ExecuteRemoteService('CLIMATE_NOW', 'STOP');
        return $result;
    }

    public function LockDoors()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
        $result = $this->ExecuteRemoteService('DOOR_LOCK', '');
        return $result;
    }

    public function UnlockDoors()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
        $result = $this->ExecuteRemoteService('DOOR_UNLOCK', '');
        return $result;
    }

    public function FlashHeadlights()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
        $result = $this->ExecuteRemoteService('LIGHT_FLASH', '');
        return $result;
    }

    public function SoundHonk()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
        $result = $this->ExecuteRemoteService('HORN_BLOW', '');
        return $result;
    }

    public function LocateVehicle()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
        $result = $this->ExecuteRemoteService('VEHICLE_FINDER', '');
        return $result;
    }

    public function UpdateRemoteServiceStatus()
    {
        $delete_tstamp = strtotime('-1 month');
        $time2failed = 2 * 60;
        $refresh_interval = self::$UpdateRemoteHistoryInterval;
        $refresh_tstamp = time() - $refresh_interval;

        $history = json_decode($this->ReadAttributeString('RemoteServiceHistory'), true);
        if ($history == false) {
            $history = [];
        }
        $n_pending = 0;
        $new_history = [];
        foreach ($history as $event) {
            if ($event['modstamp'] < $delete_tstamp) {
                continue;
            }
            if ($event['eventStatus'] == 'PENDING' && $event['modstamp'] < $refresh_tstamp) {
                $params = [
                    'eventId' => $event['eventId'],
                ];
                $data = $this->CallAPI(self::$remoteServiceStatus_endpoint, [], $params, '');
                $jdata = json_decode($data, true);
                if ($jdata == false) {
                    continue;
                }

                $event['eventStatus'] = $jdata['eventStatus'];
                $event['modstamp'] = time();

                if ($event['eventStatus'] == 'PENDING' && $event['creationTime'] + $time2failed < time()) {
                    $event['eventStatus'] = 'FAILED';
                    $this->SendDebug(__FUNCTION__, 'status set to FAILED: event=' . print_r($event, true), 0);
                }
                if ($event['service'] == 'VEHICLE_FINDER' && $event['eventStatus'] == 'EXECUTED') {
                    $pos = $this->GetHomePosition();
                    $header_add = [
                        'latitude'  => $pos['latitude'],
                        'longitude' => $pos['longitude'],
                    ];
                    $data = $this->CallAPI(self::$remoteServicePosition_endpoint, [], $params, $header_add);
                    $jdata = json_decode($data, true);
                    if ($jdata != false) {
                        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
                        $status = $this->GetArrayElem($jdata, 'positionData.status', '');
                        if ($status == 'OK') {
                            $dir = $this->GetArrayElem($jdata, 'positionData.position.heading', '');
                            if ($dir != '') {
                                $this->SetValue('CurrentDirection', intval($dir));
                            }

                            $lat = $this->GetArrayElem($jdata, 'positionData.position.latitude', '');
                            if ($lat != '') {
                                $this->SetValue('CurrentLatitude', floatval($lat));
                            }

                            $lng = $this->GetArrayElem($jdata, 'positionData.position.longitude', '');
                            if ($lng != '') {
                                $this->SetValue('CurrentLongitude', floatval($lng));
                            }

                            if ($lat != '' && $lng != '') {
                                $active_googlemap = $this->ReadPropertyBoolean('active_googlemap');
                                if ($active_googlemap) {
                                    $maptype = $this->GetValue('GoogleMapType');
                                    $zoom = $this->GetValue('GoogleMapZoom');
                                    $this->SetGoogleMap($maptype, $zoom);
                                }
                                $this->SendDebug(__FUNCTION__, 'ts=' . $event['creationTime'], 0);
                                $this->SetValue('LastPositionMessage', $event['creationTime']);
                            }
                        } else {
                            $errorDetails = $this->GetArrayElem($jdata, 'errorDetails', '');
                            $this->SendDebug(__FUNCTION__, 'status=' . $status . ', errorDetails=' . print_r($errorDetails, true), 0);
                        }
                    }
                }
            }
            if ($event['eventStatus'] == 'PENDING') {
                $n_pending++;
            }
            $new_history[] = $event;
        }
        $this->WriteAttributeString('RemoteServiceHistory', json_encode($new_history));

        $this->GetRemoteServiceHistory();

        if ($n_pending) {
            $this->SendDebug(__FUNCTION__, 'next check in ' . $refresh_interval . 's', 0);
            $this->SetTimerInterval('UpdateRemoteServiceStatus', $refresh_interval * 1000);
        } else {
            $this->SetTimerInterval('UpdateRemoteServiceStatus', 0);
        }
    }

    public function GetRemoteServiceHistory()
    {
        $service2text = [
            'CLIMATE_NOW'        => 'climate now',
            'CLIMATE_STOP'       => 'stop climate',
            'CLIMATE_LATER'      => 'climate later',
            'CLIMATE_CONTROL'    => 'climate control',
            'DOOR_LOCK'          => 'door lock',
            'DOOR_UNLOCK'        => 'door unlock',
            'LIGHT_FLASH'        => 'light flash',
            'HORN_BLOW'          => 'horn blow',
            'VEHICLE_FINDER'     => 'find vehicle',
            'CHARGE_NOW'         => 'charge now',
            'CHARGING_CONTROL'   => 'charging control',
            'CHARGING_PROFILE'   => 'charge preferences',
            'CHARGE_PREFERENCE'  => 'charge preferences',
        ];
        $status2text = [
            'SUCCESS'       => 'success',
            'PENDING'       => 'pending',
            'IN_PROGRESS'   => 'pending',
            'INITIATED'     => 'initiated',
            'FAILED'        => 'failed',
            'ERROR'         => 'error',
            'CANCELLED'     => 'cancelled',
            'EXECUTED'      => 'executed',
            'NOT_EXECUTED'  => 'not_executed',
        ];

        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'call api ...', 0);

        $vin = $this->ReadPropertyString('vin');
        $endpoint = self::$remoteServiceHistory_endpoint . '/' . $vin;

        $data = $this->CallAPI($endpoint, '', '', '');
        $jdata = json_decode($data, true);

        $history = json_decode($this->ReadAttributeString('RemoteServiceHistory'), true);
        if ($history == false) {
            $history = [];
        }

        $tbl = '';
        foreach ($jdata as $e) {
            $ts = strtotime($e['dateTime']);

            switch ($e['type']) {
                case 'CLIMATIZE_LATER':
                    $service = 'CLIMATE_LATER';
                    break;
                case 'CLIMATIZE_NOW':
                    $service = 'CLIMATE_NOW';
                    foreach ($history as $event) {
                        if ($ts == $event['creationTime'] && $event['service'] == 'CLIMATE_NOW' && $event['action'] == 'STOP') {
                            $service = 'CLIMATE_STOP';
                            break;
                        }
                    }
                    break;
                case 'LOCK':
                    $service = 'DOOR_LOCK';
                    break;
                case 'UNLOCK':
                    $service = 'DOOR_UNLOCK';
                    break;
                case 'LIGHTS':
                    $service = 'LIGHT_FLASH';
                    break;
                case 'HORN':
                    $service = 'HORN_BLOW';
                    break;
                default:
                    $service = $e['type'];
                    break;
            }

            $tstamp = date('d.m. H:i:s', $ts);

            if (isset($service2text[$service])) {
                $_service = $this->Translate($service2text[$service]);
            } else {
                $_service = $this->Translate('unknown service') . ' "' . $service . '"';
            }

            $status = $e['status'];
            if (isset($status2text[$status])) {
                $_status = $this->Translate($status2text[$status]);
            } else {
                $_status = $this->Translate('unknown status') . ' "' . $status . '"';
            }

            $tbl .= '<tr>' . PHP_EOL;
            $tbl .= '<td>' . $tstamp . '</td>' . PHP_EOL;
            $tbl .= '<td>' . $_service . '</td>' . PHP_EOL;
            $tbl .= '<td>' . $_status . '</td>' . PHP_EOL;
            $tbl .= '</tr>' . PHP_EOL;
        }
        if ($tbl != '') {
            $html = '<style>' . PHP_EOL;
            $html .= 'th, td { padding: 2px 10px; text-align: left; }' . PHP_EOL;
            $html .= '</style>' . PHP_EOL;
            $html .= '<table>' . PHP_EOL;
            $html .= '<tr>' . PHP_EOL;
            $html .= '<th>' . $this->Translate('Moment') . '</th>' . PHP_EOL;
            $html .= '<th>' . $this->Translate('Remote service') . '</th>' . PHP_EOL;
            $html .= '<th>' . $this->Translate('State') . '</th>' . PHP_EOL;
            $html .= '</tr>' . PHP_EOL;
            $html .= $tbl;
            $html .= '</table>' . PHP_EOL;
        } else {
            $html = $this->Translate('No information about the course available');
        }

        if ($this->GetValue('RemoteServiceHistory') != $html) {
            $this->SetValue('RemoteServiceHistory', $html);
        }
    }

    public function UpdateData()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'start ...', 0);

        $time_start = microtime(true);

        $this->GetVehicleData();

        $model = $this->ReadPropertyInteger('model');
        if ($model != self::$BMW_MODEL_COMBUSTION) {
            $this->GetChargingStatistics();
            $this->GetChargingSessions();
        }

        $this->GetRemoteServiceHistory();

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, '... finished in ' . $duration . 's', 0);
    }

    public function RequestAction($Ident, $Value)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'ident=' . $Ident . ', value=' . $Value, 0);
        $this->SetValue($Ident, $Value);
        switch ($Ident) {
            case 'TriggerStartClimatisation':
                $this->StartClimateControl();
                break;
            case 'TriggerStopClimatisation':
                $this->StopClimateControl();
                break;
            case 'TriggerLockDoors':
                $this->LockDoors();
                break;
            case 'TriggerUnlockDoors':
                $this->UnlockDoors();
                break;
            case 'TriggerFlashHeadlights':
                $this->FlashHeadlights();
                break;
            case 'TriggerSoundHonk':
                $this->SoundHonk();
                break;
            case 'TriggerLocateVehicle':
                $this->LocateVehicle();
                break;
            case 'GoogleMapType':
                $this->SetGoogleMapType($Value);
                break;
            case 'GoogleMapZoom':
                $this->SetGoogleMapZoom($Value);
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident "' . $Ident . '"', 0);
        }
    }

    private function SetGoogleMap($maptype, $zoom)
    {
        $active_googlemap = $this->ReadPropertyBoolean('active_googlemap');
        $active_current_position = $this->ReadPropertyBoolean('active_current_position');

        if ($active_googlemap == false || $active_current_position == false) {
            return;
        }

        $lat = $this->GetValue('CurrentLatitude');
        $lng = $this->GetValue('CurrentLongitude');
        $map = $this->GetGoogleMapType($maptype);
        $zf = $zoom > 0 ? round(($zoom / 100) * self::$BMW_GOOGLEMAP_ZOOM_MAX) : 0;

        $this->SendDebug(__FUNCTION__, 'lat=' . $lat . ', lng=' . $lng . ', map=' . $maptype . ' => ' . $map . ', zoom=' . $zoom . ' => ' . $zf, 0);

        if ($lat != 0 && $lng != 0) {
            $hsize = $this->ReadPropertyInteger('horizontal_mapsize');
            $vsize = $this->ReadPropertyInteger('vertical_mapsize');

            $markercolor = 'red';

            $api_key = $this->ReadPropertyString('googlemap_api_key');
            $url = 'https://maps.google.com/maps/api/staticmap?key=' . $api_key;

            $pos = number_format(floatval($lat), 6, '.', '') . ',' . number_format(floatval($lng), 6, '.', '');
            $url .= '&center=' . rawurlencode($pos);

            if ($zf > 0) {
                $url .= '&zoom=' . rawurlencode(strval($zf));
            }

            $url .= '&size=' . rawurlencode(strval($hsize) . 'x' . strval($vsize));
            $url .= '&maptype=' . rawurlencode($map);
            $url .= '&markers=' . rawurlencode('color:' . strval($markercolor) . '|' . strval($pos));
            $url .= '&sensor=true';

            $this->SendDebug(__FUNCTION__, 'url=' . $url, 0);
            $html = '<img src="' . $url . '" />';
            $this->SetValue('GoogleMap', $html);
        }
    }

    private function GetGoogleMapType($value)
    {
        switch ($value) {
            case self::$BMW_GOOGLEMAP_TYPE_SATELLITE:
                $maptype = 'satellite';
                break;
            case self::$BMW_GOOGLEMAP_TYPE_HYBRID:
                $maptype = 'hybrid';
                break;
            case self::$BMW_GOOGLEMAP_TYPE_TERRAIN:
                $maptype = 'terrain';
                break;
            case self::$BMW_GOOGLEMAP_TYPE_ROADMAP:
            default:
                $maptype = 'roadmap';
                break;
        }
        return $maptype;
    }

    private function SetGoogleMapType($maptype)
    {
        $this->SendDebug(__FUNCTION__, 'maptype=' . $maptype, 0);
        $zoom = $this->GetValue('GoogleMapZoom');
        $this->SetGoogleMap($maptype, $zoom);
    }

    private function SetGoogleMapZoom($zoom)
    {
        $this->SendDebug(__FUNCTION__, 'zoom=' . $zoom, 0);
        $maptype = $this->GetValue('GoogleMapType');
        $this->SetGoogleMap($maptype, $zoom);
    }

    // Ladezustand
    private function MapChargingState($s)
    {
        $str2enum = [
            'DEFAULT'                => self::$BMW_CHARGING_STATE_NOT,
            'CHARGING'               => self::$BMW_CHARGING_STATE_ACTIVE,
            'COMPLETE'               => self::$BMW_CHARGING_STATE_ENDED,
            'WAITING_FOR_CHARGING'   => self::$BMW_CHARGING_STATE_PAUSED,
            'NOT_CHARGING'           => self::$BMW_CHARGING_STATE_NOT,
            'ERROR'                  => self::$BMW_CHARGING_STATE_ERROR,
            'INVALID'                => self::$BMW_CHARGING_STATE_INVALID,
            'FINISHED_FULLY_CHARGED' => self::$BMW_CHARGING_STATE_FULLY,
            'FINISHED_NOT_FULL'      => self::$BMW_CHARGING_STATE_PARTIAL,
            'FULLY_CHARGED'          => self::$BMW_CHARGING_STATE_FULLY,
            'PLUGGED_IN'             => self::$BMW_CHARGING_STATE_PLUGGED_IN,
        ];

        if (isset($str2enum[$s])) {
            $e = $str2enum[$s];
        } else {
            $this->SendDebug(__FUNCTION__, 'unknown value "' . $s . '"', 0);
            $e = self::$BMW_DOOR_STATE_UNKNOWN;
        }
        return $e;
    }

    // Tür
    private function MapDoorState($s)
    {
        $str2enum = [
            'UNKNOWN' => self::$BMW_DOOR_STATE_UNKNOWN,
            'OPEN'    => self::$BMW_DOOR_STATE_OPEN,
            'CLOSED'  => self::$BMW_DOOR_STATE_CLOSED,
        ];

        if (isset($str2enum[$s])) {
            $e = $str2enum[$s];
        } else {
            $this->SendDebug(__FUNCTION__, 'unknown value "' . $s . '"', 0);
            $e = self::$BMW_DOOR_STATE_UNKNOWN;
        }
        return $e;
    }

    // Türverschluss
    private function MapDoorClosure($s)
    {
        $str2enum = [
            'UNKNOWN'        => self::$BMW_DOOR_CLOSURE_UNKNOWN,
            'UNLOCKED'       => self::$BMW_DOOR_CLOSURE_UNLOCKED,
            'LOCKED'         => self::$BMW_DOOR_CLOSURE_LOCKED,
            'SECURED'        => self::$BMW_DOOR_CLOSURE_SECURED,
            'SELECTIVLOCKED' => self::$BMW_DOOR_CLOSURE_SELECTIVLOCKED,
        ];

        if (isset($str2enum[$s])) {
            $e = $str2enum[$s];
        } else {
            $this->SendDebug(__FUNCTION__, 'unknown value "' . $s . '"', 0);
            $e = self::$BMW_DOOR_CLOSURE_UNKNOWN;
        }
        return $e;
    }

    // Fenster
    private function MapWindowState($s)
    {
        $str2enum = [
            'UNKNOWN'      => self::$BMW_WINDOW_STATE_UNKNOWN,
            'OPEN'         => self::$BMW_WINDOW_STATE_OPEN,
            'INTERMEDIATE' => self::$BMW_HOOD_STATE_INTERMEDIATE,
            'CLOSED'       => self::$BMW_WINDOW_STATE_CLOSED,
        ];

        if (isset($str2enum[$s])) {
            $e = $str2enum[$s];
        } else {
            $this->SendDebug(__FUNCTION__, 'unknown value "' . $s . '"', 0);
            $e = self::$BMW_WINDOW_STATE_UNKNOWN;
        }
        return $e;
    }

    // Motorhaube
    private function MapTrunkState($s)
    {
        $str2enum = [
            'UNKNOWN' => self::$BMW_TRUNK_STATE_UNKNOWN,
            'OPEN'    => self::$BMW_TRUNK_STATE_OPEN,
            'CLOSED'  => self::$BMW_TRUNK_STATE_CLOSED,
        ];

        if (isset($str2enum[$s])) {
            $e = $str2enum[$s];
        } else {
            $this->SendDebug(__FUNCTION__, 'unknown value "' . $s . '"', 0);
            $e = self::$BMW_TRUNK_STATE_UNKNOWN;
        }
        return $e;
    }

    // Kofferraum
    private function MapHoodState($s)
    {
        $str2enum = [
            'UNKNOWN'      => self::$BMW_HOOD_STATE_UNKNOWN,
            'OPEN'         => self::$BMW_HOOD_STATE_OPEN,
            'INTERMEDIATE' => self::$BMW_HOOD_STATE_INTERMEDIATE,
            'CLOSED'       => self::$BMW_HOOD_STATE_CLOSED,
        ];

        if (isset($str2enum[$s])) {
            $e = $str2enum[$s];
        } else {
            $this->SendDebug(__FUNCTION__, 'unknown value "' . $s . '"', 0);
            $e = self::$BMW_HOOD_STATE_UNKNOWN;
        }
        return $e;
    }

    // Schiebedach
    private function MapSunroofState($s)
    {
        $str2enum = [
            'UNKNOWN'      => self::$BMW_SUNROOF_STATE_UNKNOWN,
            'OPEN'         => self::$BMW_SUNROOF_STATE_OPEN,
            'OPEN_TILT'    => self::$BMW_SUNROOF_STATE_OPEN_TILT,
            'INTERMEDIATE' => self::$BMW_SUNROOF_STATE_INTERMEDIATE,
            'CLOSED'       => self::$BMW_SUNROOF_STATE_CLOSED,
        ];

        if (isset($str2enum[$s])) {
            $e = $str2enum[$s];
        } else {
            $this->SendDebug(__FUNCTION__, 'unknown value "' . $s . '"', 0);
            $e = self::$BMW_SUNROOF_STATE_UNKNOWN;
        }
        return $e;
    }

    public function CleanupOldVersions()
    {
        $unused_vars = [
            'bmw_doorDriverFront', 'bmw_doorDriverRear', 'bmw_doorLockState', 'bmw_doorPassengerFront', 'bmw_doorPassengerRear',
            'bmw_trunk', 'bmw_hood',
            'bmw_convertibleRoofState',
            'bmw_windowDriverFront', 'bmw_windowDriverRear', 'bmw_windowPassengerFront', 'bmw_windowPassengerRear',
            'bmw_rearWindow',

            'bmw_socMax', 'bmw_battery_size',
            'bmw_mileage', 'bmw_tank_capacity', 'bmw_remaining_range',
            'bmw_remaining_electric_range',
            'bmw_charging_level', 'bmw_connector_status', 'bmw_charging_status', 'bmw_charging_end', 'bmw_charging_info', 'bmw_charging_sessions',

            'bmw_start_air_conditioner', 'bmw_stop_air_conditioner',
            'bmw_start_lock', 'bmw_start_unlock', 'bmw_start_flash_headlights', 'bmw_start_honk', 'bmw_start_vehicle_finder',

            'bmw_car_picture', 'bmw_car_picture_zoom', 'bmw_perspective',

            'bmw_history', 'bmw_service_history', 'bmw_checkcontrol', 'bmw_service',
            'bmw_position_request_status',

            'bmw_car_googlemap', 'bmw_googlemap_maptype', 'bmw_googlemap_zoom',
            'bmw_current_latitude', 'bmw_current_longitude', 'bmw_current_heading', 'bmw_inMotion',

            'bmw_car_interface', 'bmw_chargingprofile_interface', 'bmw_dynamic_interface', 'bmw_efficiency_interface', 'bmw_history_interface',
            'bmw_image_interface', 'bmw_mapupdate_interface', 'bmw_navigation_interface', 'bmw_remote_services_interface',
            'ServiceMessages_partner_interface', 'bmw_specs_interface', 'bmw_store_interface', 'ServiceMessages_interface',

            'effeciency_charging', 'effeciency_consumption', 'effeciency_driving', 'effeciency_electric',
            'lasttrip_avg_consumed', 'lasttrip_distance', 'lasttrip_duration', 'lasttrip_electric_ratio', 'lasttrip_tstamp',
            'lifetime_distance', 'lifetime_reset_tstamp', 'lifetime_save_liters',

            'bmw_last_status_update',
        ];
        foreach ($unused_vars as $unused_var) {
            $this->UnregisterVariable($unused_var);
        }

        $unused_profiles = [
            'BMW.Perspective', 'BMW.Efficiency', 'BMW.Distance', 'BMW.Duration', 'BMW.Consumption', 'BMW.ElectricRatio', 'BMW.SavedLiters',
        ];
        foreach ($unused_profiles as $unused_profil) {
            if (IPS_VariableProfileExists($unused_profil)) {
                IPS_DeleteVariableProfile($unused_profil);
            }
        }
    }
}
