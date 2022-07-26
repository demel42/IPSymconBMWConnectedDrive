<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';
require_once __DIR__ . '/../libs/images.php';

// define('CHARGE_NOW', true);

class BMWConnectedDrive extends IPSModule
{
    use BMWConnectedDrive\StubsCommonLib;
    use BMWConnectedDriveLocalLib;
    use BMWConnectedDriveImagesLib;

    // Konfigurationen
    private static $server_urls_eadrax = [
        'NorthAmerica' => 'cocoapi.bmwgroup.us',
        'RestOfWorld'  => 'cocoapi.bmwgroup.com',
    ];

    private static $region_map = [
        'NorthAmerica' => 'nas',
        'RestOfWorld'  => 'row',
    ];

    private static $ocp_apim_key = [
        'NorthAmerica' => 'MzFlMTAyZjUtNmY3ZS03ZWYzLTkwNDQtZGRjZTYzODkxMzYy',
        'RestOfWorld'  => 'NGYxYzg1YTMtNzU4Zi1hMzdkLWJiYjYtZjg3MDQ0OTRhY2Zh',
    ];

    private static $x_user_agent_fmt = 'android(SP1A.210812.016.C1);%s;2.5.2(14945);%s';
    private static $user_agent = 'Dart/2.14 (dart:io)';

    private static $oauth_config_endpoint = '/eadrax-ucs/v1/presentation/oauth/config';
    private static $oauth_authenticate_endpoint = '/gcdm/oauth/authenticate';
    private static $oauth_token_endpoint = '/gcdm/oauth/token';

    private static $vehicles_endpoint = '/eadrax-vcs/v2/vehicles';

    private static $remoteService_endpoint = '/eadrax-vrccs/v2/presentation/remote-commands';
    private static $remoteServiceStatus_endpoint = '/eadrax-vrccs/v2/presentation/remote-commands/eventStatus';
    private static $remoteServicePosition_endpoint = '/eadrax-vrccs/v2/presentation/remote-commands/eventPosition';
    private static $remoteServiceHistory_endpoint = '/eadrax-vrccs/v2/presentation/remote-history';

    private static $vehicle_img_endpoint = '/eadrax-ics/v3/presentation/vehicles/%s/images';
    private static $vehicle_poi_endpoint = '/eadrax-dcs/v1/send-to-car/send-to-car';

    private static $charging_statistics_endpoint = '/eadrax-chs/v1/charging-statistics';
    private static $charging_sessions_endpoint = '/eadrax-chs/v1/charging-sessions';

    private static $UpdateRemoteHistoryInterval = 5;

    private $ModuleDir;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->ModuleDir = __DIR__;
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
        $this->RegisterPropertyBoolean('active_tire_pressure', false);
        $this->RegisterPropertyBoolean('active_blow_horn', false);

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

        $this->RegisterAttributeString('UpdateInfo', '');

        $this->InstallVarProfiles(false);

        $this->SetMultiBuffer('VehicleData', '');
        $this->SetMultiBuffer('ChargingStatistics', '');
        $this->SetMultiBuffer('ChargingSessions', '');
        $this->SetMultiBuffer('RemoteServiceHistory', '');

        $this->RegisterTimer('UpdateData', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "UpdateData", "");');
        $this->RegisterTimer('UpdateRemoteServiceStatus', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "UpdateRemoteServiceStatus", "");');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    private function MaintainStateVariable($ident, $use, $vpos)
    {
        $settings = [
            'DoorClosureState' => [
                'desc'    => 'door closure state',
                'vartype' => VARIABLETYPE_INTEGER,
                'varprof' => 'BMW.DoorClosureState',
            ],
            'DoorStateDriverFront' => [
                'desc'    => 'door driver front',
                'vartype' => VARIABLETYPE_INTEGER,
                'varprof' => 'BMW.DoorState',
            ],
            'DoorStateDriverRear' => [
                'desc'    => 'door driver rear',
                'vartype' => VARIABLETYPE_INTEGER,
                'varprof' => 'BMW.DoorState',
            ],
            'DoorStatePassengerFront' => [
                'desc'    => 'door passenger front',
                'vartype' => VARIABLETYPE_INTEGER,
                'varprof' => 'BMW.DoorState',
            ],
            'DoorStatePassengerRear' => [
                'desc'    => 'door passenger rear',
                'vartype' => VARIABLETYPE_INTEGER,
                'varprof' => 'BMW.DoorState',
            ],
            'WindowStateDriverFront' => [
                'desc'    => 'window driver front',
                'vartype' => VARIABLETYPE_INTEGER,
                'varprof' => 'BMW.WindowState',
            ],
            'WindowStateDriverRear' => [
                'desc'    => 'window driver rear',
                'vartype' => VARIABLETYPE_INTEGER,
                'varprof' => 'BMW.WindowState',
            ],
            'WindowStatePassengerFront' => [
                'desc'    => 'window passenger front',
                'vartype' => VARIABLETYPE_INTEGER,
                'varprof' => 'BMW.WindowState',
            ],
            'WindowStatePassengerRear' => [
                'desc'    => 'window passenger rear',
                'vartype' => VARIABLETYPE_INTEGER,
                'varprof' => 'BMW.WindowState',
            ],
            'TrunkState' => [
                'desc'    => 'trunk',
                'vartype' => VARIABLETYPE_INTEGER,
                'varprof' => 'BMW.TrunkState',
            ],
            'HoodState' => [
                'desc'    => 'hood',
                'vartype' => VARIABLETYPE_INTEGER,
                'varprof' => 'BMW.HoodState',
            ],
            'RoofState' => [
                'desc'    => 'roof',
                'vartype' => VARIABLETYPE_INTEGER,
                'varprof' => 'BMW.RoofState',
            ],
            'TirePressureFrontLeft' => [
                'desc'    => 'tire pressure front left',
                'vartype' => VARIABLETYPE_FLOAT,
                'varprof' => 'BMW.TirePressure',
            ],
            'TirePressureFrontRight' => [
                'desc'    => 'tire pressure front right',
                'vartype' => VARIABLETYPE_FLOAT,
                'varprof' => 'BMW.TirePressure',
            ],
            'TirePressureRearLeft' => [
                'desc'    => 'tire pressure read left',
                'vartype' => VARIABLETYPE_FLOAT,
                'varprof' => 'BMW.TirePressure',
            ],
            'TirePressureRearRight' => [
                'desc'    => 'tire pressure read right',
                'vartype' => VARIABLETYPE_FLOAT,
                'varprof' => 'BMW.TirePressure',
            ],
        ];

        if (isset($settings[$ident])) {
            $this->MaintainVariable($ident, $this->Translate($settings[$ident]['desc']), $settings[$ident]['vartype'], $settings[$ident]['varprof'], $vpos, $use);
        }
    }

    private function CheckModuleConfiguration()
    {
        $r = [];

        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');
        $vin = $this->ReadPropertyString('vin');
        if ($user == '' || $password == '' || $vin == '') {
            $this->SendDebug(__FUNCTION__, '"user", "password" and/or "vin" is empty', 0);
            $r[] = $this->Translate('User and password of the BMW-account are required and and a registered "vin"');
        }

        $active_googlemap = $this->ReadPropertyBoolean('active_googlemap');
        $active_current_position = $this->ReadPropertyBoolean('active_current_position');
        if ($active_googlemap == true && $active_current_position == false) {
            $this->SendDebug(__FUNCTION__, '"active_googlemap" needs "active_current_position"', 0);
            $r[] = $this->Translate('Show position in Map need saving position');
        }

        $api_key = $this->ReadPropertyString('googlemap_api_key');
        if ($active_googlemap == true && $api_key == false) {
            $this->SendDebug(__FUNCTION__, '"active_googlemap" needs "api_key"', 0);
            $r[] = $this->Translate('Show position in GoogleMap need the API-Key');
        }

        return $r;
    }

    private function CheckModuleUpdate(array $oldInfo, array $newInfo)
    {
        $r = [];

        if ($oldInfo != []) {
            if ($this->version2num($oldInfo) < $this->version2num('2.0')) {
                $r[] = $this->Translate('Delete old variables and variableprofiles');
            }

            if ($this->version2num($oldInfo) < $this->version2num('2.7')) {
                $r[] = $this->Translate('Delete old variables and variableprofiles');
            }

            if ($this->version2num($oldInfo) < $this->version2num('2.9.1')) {
                $r[] = $this->Translate('Delete old variables');
            }
        }

        return $r;
    }

    private function CompleteModuleUpdate(array $oldInfo, array $newInfo)
    {
        if ($this->version2num($oldInfo) < $this->version2num('2.0')) {
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

                'TriggerSoundHonk',
            ];
            foreach ($unused_vars as $unused_var) {
                $this->UnregisterVariable($unused_var);
            }

            $unused_profiles = [
                'BMW.Perspective', 'BMW.Efficiency', 'BMW.Distance', 'BMW.Duration', 'BMW.Consumption', 'BMW.ElectricRatio', 'BMW.SavedLiters',
                'BMW.ChargingStatus',
            ];
            foreach ($unused_profiles as $unused_profil) {
                if (IPS_VariableProfileExists($unused_profil)) {
                    IPS_DeleteVariableProfile($unused_profil);
                }
            }
            $this->InstallVarProfiles(false);
        }

        if ($this->version2num($oldInfo) < $this->version2num('2.7')) {
            $unused_vars = [
                'ChargingInfo',
                'InMotion',
                'SunroofState', 'MoonroofState',
            ];
            foreach ($unused_vars as $unused_var) {
                $this->UnregisterVariable($unused_var);
            }

            $unused_profiles = [
                'BMW.SunroofState', 'BMW.MoonroofState'
            ];
            foreach ($unused_profiles as $unused_profil) {
                if (IPS_VariableProfileExists($unused_profil)) {
                    IPS_DeleteVariableProfile($unused_profil);
                }
            }
            $this->InstallVarProfiles(false);
        }

        if ($this->version2num($oldInfo) < $this->version2num('2.9.1')) {
            $this->UnregisterVariable('ChargingInfo');
        }

        return '';
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->MaintainReferences();

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainTimer('UpdateData', 0);
            $this->MaintainTimer('UpdateRemoteServiceStatus', 0);
            $this->MaintainStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainTimer('UpdateData', 0);
            $this->MaintainTimer('UpdateRemoteServiceStatus', 0);
            $this->MaintainStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainTimer('UpdateData', 0);
            $this->MaintainTimer('UpdateRemoteServiceStatus', 0);
            $this->MaintainStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $model = $this->ReadPropertyInteger('model');
        $isElectric = $model != self::$BMW_MODEL_COMBUSTION;
        $hasCombustion = $model != self::$BMW_MODEL_ELECTRIC;

        $active_service = $this->ReadPropertyBoolean('active_service');
        $active_checkcontrol = $this->ReadPropertyBoolean('active_checkcontrol');
        $active_climate = $this->ReadPropertyBoolean('active_climate');
        $active_lock = $this->ReadPropertyBoolean('active_lock');
        $active_flash_headlights = $this->ReadPropertyBoolean('active_flash_headlights');
        $active_blow_horn = $this->ReadPropertyBoolean('active_blow_horn');
        $active_vehicle_finder = $this->ReadPropertyBoolean('active_vehicle_finder');
        $active_googlemap = $this->ReadPropertyBoolean('active_googlemap');
        $active_current_position = $this->ReadPropertyBoolean('active_current_position');
        $active_lock_data = $this->ReadPropertyBoolean('active_lock_data');
        $active_tire_pressure = $this->ReadPropertyBoolean('active_tire_pressure');

        $vpos = 1;
        $this->MaintainVariable('Mileage', $this->Translate('mileage'), VARIABLETYPE_INTEGER, 'BMW.Mileage', $vpos++, true);

        $this->MaintainVariable('TankCapacity', $this->Translate('tank capacity'), VARIABLETYPE_FLOAT, 'BMW.TankCapacity', $vpos++, $hasCombustion);
        $this->MaintainVariable('RemainingCombinedRange', $this->Translate('remaining range'), VARIABLETYPE_FLOAT, 'BMW.RemainingRange', $vpos++, $hasCombustion);

        $vpos = 10;
        $this->MaintainVariable('RemainingElectricRange', $this->Translate('remaining electric range'), VARIABLETYPE_FLOAT, 'BMW.RemainingRange', $vpos++, $isElectric);
        $this->MaintainVariable('ChargingLevel', $this->Translate('current battery charge level (SoC)'), VARIABLETYPE_FLOAT, 'BMW.ChargingLevel', $vpos++, $isElectric);
        $this->MaintainVariable('ChargingConnectorStatus', $this->Translate('connector status'), VARIABLETYPE_INTEGER, 'BMW.ConnectorStatus', $vpos++, $isElectric);
        $this->MaintainVariable('ChargingStatus', $this->Translate('charging status'), VARIABLETYPE_INTEGER, 'BMW.ChargingStatus', $vpos++, $isElectric);
        $this->MaintainVariable('ChargingStart', $this->Translate('charging start'), VARIABLETYPE_INTEGER, '~UnixTimestampTime', $vpos++, $isElectric);
        $this->MaintainVariable('ChargingEnd', $this->Translate('charging end'), VARIABLETYPE_INTEGER, '~UnixTimestampTime', $vpos++, $isElectric);
        $this->MaintainVariable('ChargingPreferences', $this->Translate('charging preferences'), VARIABLETYPE_STRING, '~HTMLBox', $vpos++, $isElectric);
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

        $this->MaintainVariable('TriggerBlowHorn', $this->Translate('blow horn'), VARIABLETYPE_INTEGER, 'BMW.TriggerRemoteService', $vpos++, $active_blow_horn);
        if ($active_blow_horn) {
            $this->MaintainAction('TriggerBlowHorn', true);
        }

        $this->MaintainVariable('TriggerLocateVehicle', $this->Translate('locate vehicle'), VARIABLETYPE_INTEGER, 'BMW.TriggerRemoteService', $vpos++, $active_vehicle_finder);
        if ($active_vehicle_finder) {
            $this->MaintainAction('TriggerLocateVehicle', true);
        }

        if (defined('CHARGE_NOW')) {
            $this->MaintainVariable('TriggerChargeNow', $this->Translate('charge now'), VARIABLETYPE_INTEGER, 'BMW.TriggerRemoteService', $vpos++, $isElectric);
            if ($isElectric) {
                $this->MaintainAction('TriggerChargeNow', true);
            }
        } else {
            $this->UnregisterVariable('TriggerChargeNow');
        }

        $this->MaintainVariable('RemoteServiceHistory', $this->Translate('remote service history'), VARIABLETYPE_STRING, '~HTMLBox', $vpos++, true);

        $vpos = 70;
        $this->MaintainVariable('CurrentLatitude', $this->Translate('current latitude'), VARIABLETYPE_FLOAT, 'BMW.Location', $vpos++, $active_current_position);
        $this->MaintainVariable('CurrentLongitude', $this->Translate('current longitude'), VARIABLETYPE_FLOAT, 'BMW.Location', $vpos++, $active_current_position);
        $this->MaintainVariable('CurrentDirection', $this->Translate('current direction'), VARIABLETYPE_INTEGER, 'BMW.Heading', $vpos++, $active_current_position);
        $this->MaintainVariable('LastPositionMessage', $this->Translate('last position message'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $this->MaintainVariable('GoogleMap', $this->Translate('map'), VARIABLETYPE_STRING, '~HTMLBox', $vpos++, $active_googlemap);
        $this->MaintainVariable('GoogleMapType', $this->Translate('map type'), VARIABLETYPE_INTEGER, 'BMW.Googlemap', $vpos++, $active_googlemap);
        $this->MaintainVariable('GoogleMapZoom', $this->Translate('map zoom'), VARIABLETYPE_INTEGER, '~Intensity.100', $vpos++, $active_googlemap);
        if ($active_googlemap) {
            $this->MaintainAction('GoogleMapType', true);
            $this->MaintainAction('GoogleMapZoom', true);
        }

        $vpos = 80;
        if ($active_lock_data == false) {
            $idents = [
                'DoorClosureState',
                'DoorStateDriverFront',
                'DoorStateDriverRear',
                'DoorStatePassengerFront',
                'DoorStatePassengerRear',
                'WindowStateDriverFront',
                'WindowStateDriverRear',
                'WindowStatePassengerFront',
                'WindowStatePassengerRear',
                'TrunkState',
                'HoodState',
                'RoofState',
            ];
            foreach ($idents as $ident) {
                $this->MaintainStateVariable($ident, false, 0);
            }
        }
        if ($active_tire_pressure == false) {
            $idents = [
                'TirePressureFrontLeft',
                'TirePressureFrontRight',
                'TirePressureRearLeft',
                'TirePressureRearRight',
            ];
            foreach ($idents as $ident) {
                $this->MaintainStateVariable($ident, false, 0);
            }
        }

        $vpos = 100;
        $this->MaintainVariable('LastUpdateFromVehicle', $this->Translate('last status update'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainTimer('UpdateData', 0);
            $this->MaintainTimer('UpdateRemoteServiceStatus', 0);
            $this->MaintainStatus(IS_INACTIVE);
            return;
        }

        $this->MaintainStatus(IS_ACTIVE);

        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->SetUpdateInterval();
            $this->UpdateRemoteServiceStatus();
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

        if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) {
            $this->SetUpdateInterval();
            $this->UpdateRemoteServiceStatus();
        }
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
        }

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Disable instance'
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => [
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
                ],
                [
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
                ],
                [
                    'name'    => 'vin',
                    'type'    => 'ValidationTextBox',
                    'caption' => 'VIN'
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
                            'label' => $this->Translate('combustion'),
                            'value' => self::$BMW_MODEL_COMBUSTION
                        ]
                    ]
                ],
            ],
            'caption' => 'Account data',
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Update interval',
            'items'   => [
                [
                    'name'    => 'UpdateInterval',
                    'type'    => 'NumberSpinner',
                    'minimum' => 0,
                    'suffix'  => 'minutes',
                    'caption' => 'Update interval'
                ],
            ],
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
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
                    'name'    => 'active_blow_horn',
                    'type'    => 'CheckBox',
                    'caption' => 'blow horn'
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
                ],
                [
                    'name'    => 'active_tire_pressure',
                    'type'    => 'CheckBox',
                    'caption' => 'show tire pressure'
                ],
            ],
            'caption' => 'options',
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
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
                    'width'   => '400px',
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
            ],
            'caption' => 'map',
        ];

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            $formActions[] = $this->GetCompleteUpdateFormAction();

            $formActions[] = $this->GetInformationFormAction();
            $formActions[] = $this->GetReferencesFormAction();

            return $formActions;
        }

        $formActions[] = [
            'type'    => 'Button',
            'label'   => 'Update data',
            'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "UpdateData", "");',
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
                    'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "GetCarPicture", $carView);',
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
                    'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "Relogin", "");',
                ],
                $this->GetInstallVarProfilesFormItem(),
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

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    public function SetUpdateInterval(int $min = null)
    {
        if (is_null($min)) {
            $min = $this->ReadPropertyInteger('UpdateInterval');
        }
        $msec = $min * 60 * 1000;
        $this->MaintainTimer('UpdateData', $msec);
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

    private function Relogin()
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

        $config_url = $baseurl . self::$oauth_config_endpoint;
        $header = [
            'ocp-apim-subscription-key: ' . base64_decode(self::$ocp_apim_key[$region]),
            'user-agent: ' . self::$user_agent,
            'x-user-agent: ' . sprintf(self::$x_user_agent_fmt, $this->GetBrand(), self::$region_map[$region]),
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
            $this->MaintainStatus($statuscode);
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
            $this->MaintainStatus($statuscode);
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
            $this->MaintainStatus($statuscode);
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
            $this->MaintainStatus($statuscode);
            return false;
        }

        $this->MaintainStatus(IS_ACTIVE);

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
            $this->MaintainStatus($statuscode);

            $this->WriteAttributeString('ApiRefreshToken', '');
            $this->SetBuffer('AccessToken', '');

            return false;
        }

        $this->MaintainStatus(IS_ACTIVE);

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
            'user-agent'      => self::$user_agent,
            'x-user-agent'    => sprintf(self::$x_user_agent_fmt, $this->GetBrand(), self::$region_map[$region]),
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
            if (isset($header_base['Content-Type']) && $header_base['Content-Type'] == 'application/json') {
                $s = json_encode($postfields);
            } else {
                $s = http_build_query($postfields);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $s);
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
            } elseif ($httpcode != 200 && $httpcode != 201) {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode . '(' . $this->HttpCode2Text($httpcode) . ')';
            }
        }
        if ($statuscode == 0) {
            $curl_info = curl_getinfo($ch);
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            if ($body == '' || ctype_print($body)) {
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
            $this->MaintainStatus($statuscode);
            return false;
        }

        $jbody = json_decode($body, true);
        if (isset($jbody['errors'])) {
            $this->SendDebug(__FUNCTION__, ' => error=' . $jbody['errors'], 0);
            $body = false;
        }

        $this->MaintainStatus(IS_ACTIVE);

        return $body;
    }

    private function UpdateVehicleData($data)
    {
        $this->SetMultiBuffer('VehicleData', $data);
        $jdata = json_decode($data, true);

        $state = $jdata['state'];
        $this->SendDebug(__FUNCTION__, 'state=' . print_r($state, true), 0);

        $isChanged = false;

        $active_lock_data = $this->ReadPropertyBoolean('active_lock_data');
        if ($active_lock_data) {
            $vpos = 80;

            $doorsState = isset($state['doorsState']) ? $state['doorsState'] : [];
            $this->SendDebug(__FUNCTION__, 'doorsState=' . print_r($doorsState, true), 0);

            if (isset($doorsState['leftFront'])) {
                $val = $doorsState['leftFront'];
                $this->MaintainStateVariable('DoorStateDriverFront', true, $vpos++);
                $this->SaveValue('DoorStateDriverFront', $this->MapDoorState($val), $isChanged);
            }

            if (isset($doorsState['leftRear'])) {
                $val = $doorsState['leftRear'];
                $this->MaintainStateVariable('DoorStateDriverRear', true, $vpos++);
                $this->SaveValue('DoorStateDriverRear', $this->MapDoorState($val), $isChanged);
            }

            if (isset($doorsState['rightFront'])) {
                $val = $doorsState['rightFront'];
                $this->MaintainStateVariable('DoorStatePassengerFront', true, $vpos++);
                $this->SaveValue('DoorStatePassengerFront', $this->MapDoorState($val), $isChanged);
            }

            if (isset($doorsState['rightRear'])) {
                $val = $doorsState['rightRear'];
                $this->MaintainStateVariable('DoorStatePassengerRear', true, $vpos++);
                $this->SaveValue('DoorStatePassengerRear', $this->MapDoorState($val), $isChanged);
            }

            if (isset($doorsState['trunk'])) {
                $val = $doorsState['trunk'];
                $this->MaintainStateVariable('TrunkState', true, $vpos++);
                $this->SaveValue('TrunkState', $this->MapTrunkState($val), $isChanged);
            }

            if (isset($doorsState['hood'])) {
                $val = $doorsState['hood'];
                $this->MaintainStateVariable('HoodState', true, $vpos++);
                $this->SaveValue('HoodState', $this->MapHoodState($val), $isChanged);
            }

            if (isset($doorsState['combinedSecurityState'])) {
                $val = $doorsState['combinedSecurityState'];
                $this->MaintainStateVariable('DoorClosureState', true, $vpos++);
                $this->SaveValue('DoorClosureState', $this->MapDoorClosure($val), $isChanged);
            }

            $windowsState = isset($state['windowsState']) ? $state['windowsState'] : [];
            $this->SendDebug(__FUNCTION__, 'windowsState=' . print_r($windowsState, true), 0);

            if (isset($windowsState['leftFront'])) {
                $val = $windowsState['leftFront'];
                $this->MaintainStateVariable('WindowStateDriverFront', true, $vpos++);
                $this->SaveValue('WindowStateDriverFront', $this->MapWindowState($val), $isChanged);
            }

            if (isset($windowsState['leftRear'])) {
                $val = $windowsState['leftRear'];
                $this->MaintainStateVariable('WindowStateDriverRear', true, $vpos++);
                $this->SaveValue('WindowStateDriverRear', $this->MapWindowState($val), $isChanged);
            }

            if (isset($windowsState['rightFront'])) {
                $val = $windowsState['rightFront'];
                $this->MaintainStateVariable('WindowStatePassengerFront', true, $vpos++);
                $this->SaveValue('WindowStatePassengerFront', $this->MapWindowState($val), $isChanged);
            }

            if (isset($windowsState['rightRear'])) {
                $val = $windowsState['rightRear'];
                $this->MaintainStateVariable('WindowStatePassengerRear', true, $vpos++);
                $this->SaveValue('WindowStatePassengerRear', $this->MapWindowState($val), $isChanged);
            }

            $roofState = isset($state['roofState']) ? $state['roofState'] : [];
            $this->SendDebug(__FUNCTION__, 'roofState=' . print_r($roofState, true), 0);

            if (isset($roofState['roofState'])) {
                $val = $roofState['roofState'];
                $this->MaintainStateVariable('RoofState', true, $vpos++);
                $this->SaveValue('RoofState', $this->MapRoofState($val), $isChanged);
            }
        }

        $active_tire_pressure = $this->ReadPropertyBoolean('active_tire_pressure');
        if ($active_tire_pressure) {
            $vpos = 95;

            $tireState = isset($state['tireState']) ? $state['tireState'] : [];
            $this->SendDebug(__FUNCTION__, 'tireState=' . print_r($tireState, true), 0);

            if (isset($tireState['frontLeft']['status']['currentPressure'])) {
                $val = $tireState['frontLeft']['status']['currentPressure'];
                $this->MaintainStateVariable('TirePressureFrontLeft', true, $vpos++);
                $this->SaveValue('TirePressureFrontLeft', $this->CalcTirePressure($val), $isChanged);
            }
            if (isset($tireState['frontRight']['status']['currentPressure'])) {
                $val = $tireState['frontRight']['status']['currentPressure'];
                $this->MaintainStateVariable('TirePressureFrontRight', true, $vpos++);
                $this->SaveValue('TirePressureFrontRight', $this->CalcTirePressure($val), $isChanged);
            }
            if (isset($tireState['rearLeft']['status']['currentPressure'])) {
                $val = $tireState['rearLeft']['status']['currentPressure'];
                $this->MaintainStateVariable('TirePressureRearLeft', true, $vpos++);
                $this->SaveValue('TirePressureRearLeft', $this->CalcTirePressure($val), $isChanged);
            }
            if (isset($tireState['rearRight']['status']['currentPressure'])) {
                $val = $tireState['rearRight']['status']['currentPressure'];
                $this->MaintainStateVariable('TirePressureRearRight', true, $vpos++);
                $this->SaveValue('TirePressureRearRight', $this->CalcTirePressure($val), $isChanged);
            }
        }

        $val = $this->GetArrayElem($state, 'lastUpdatedAt', '');
        $this->SaveValue('LastUpdateFromVehicle', strtotime($val), $isChanged);

        $model = $this->ReadPropertyInteger('model');

        $hasCombustion = $model != self::$BMW_MODEL_ELECTRIC;

        if ($model != self::$BMW_MODEL_ELECTRIC) {
            $val = $this->GetArrayElem($state, 'combustionFuelLevel.remainingFuelLiters', '');
            $this->SaveValue('TankCapacity', floatval($val), $isChanged);

            $val = $this->GetArrayElem($state, 'combustionFuelLevel.range', '');
            $this->SaveValue('RemainingCombinedRange', floatval($val), $isChanged);
        }

        if ($model != self::$BMW_MODEL_COMBUSTION) {
            $electricChargingState = isset($state['electricChargingState']) ? $state['electricChargingState'] : [];
            $this->SendDebug(__FUNCTION__, 'electricChargingState=' . print_r($electricChargingState, true), 0);

            $val = $this->GetArrayElem($electricChargingState, 'range', '');
            $this->SaveValue('RemainingElectricRange', floatval($val), $isChanged);

            $val = $this->GetArrayElem($electricChargingState, 'chargingLevelPercent', '');
            $this->SaveValue('ChargingLevel', floatval($val), $isChanged);

            $val = $this->GetArrayElem($electricChargingState, 'isChargerConnected', '');
            if (boolval($val) == true) {
                $connector_status = self::$BMW_CONNECTOR_STATE_CONNECTED;
            } else {
                $connector_status = self::$BMW_CONNECTOR_STATE_DISCONNECTED;
            }
            $this->SaveValue('ChargingConnectorStatus', $connector_status, $isChanged);

            $val = $this->GetArrayElem($electricChargingState, 'chargingStatus', '');
            $chargingStatus = $this->MapChargingState($val);
            if ($connector_status == self::$BMW_CONNECTOR_STATE_DISCONNECTED && $chargingStatus == self::$BMW_CHARGING_STATE_INVALID) {
                $chargingStatus = self::$BMW_CHARGING_STATE_NOT;
            }
            $this->SaveValue('ChargingStatus', $chargingStatus, $isChanged);

            $chargingProfile = $this->GetArrayElem($state, 'chargingProfile', '');
            $this->SendDebug(__FUNCTION__, 'chargingProfile=' . print_r($chargingProfile, true), 0);

            $html = '<style>' . PHP_EOL;
            $html .= 'th, td { padding: 2px 10px; text-align: left; }' . PHP_EOL;
            $html .= '</style>' . PHP_EOL;
            $html .= '<table>' . PHP_EOL;

            $targetSoc = $this->GetArrayElem($chargingProfile, 'chargingSettings.targetSoc', '');
            if ($targetSoc != '') {
                $html .= '<tr>' . PHP_EOL;
                $html .= '<td>' . $this->Translate('Charging target') . '</td>' . PHP_EOL;
                $html .= '<td>' . $targetSoc . '%</td>' . PHP_EOL;
                $html .= '</tr>' . PHP_EOL;
            }

            $chargingMode = $this->GetArrayElem($chargingProfile, 'chargingMode', '');
            switch ($chargingMode) {
                case 'delayedCharging':
                    $s = $this->Translate('time window') . sprintf(
                        ' (%02d:%02d - %02d:%02d)',
                        $this->GetArrayElem($chargingProfile, 'reductionOfChargeCurrent.start.hour', 0),
                        $this->GetArrayElem($chargingProfile, 'reductionOfChargeCurrent.start.minute', 0),
                        $this->GetArrayElem($chargingProfile, 'reductionOfChargeCurrent.end.hour', 0),
                        $this->GetArrayElem($chargingProfile, 'reductionOfChargeCurrent.end.minute', 0),
                    );
                    break;
                case 'immediateCharging':
                    $s = $this->Translate('immediately');
                    break;
                default:
                    $s = '';
                    break;
            }

            $html .= '<tr>' . PHP_EOL;
            $html .= '<td>' . $this->Translate('Charging mode') . '</td>' . PHP_EOL;
            $html .= '<td>' . $s . '</td>' . PHP_EOL;
            $html .= '</tr>' . PHP_EOL;

            $departureTimes = $this->GetArrayElem($chargingProfile, 'departureTimes', '');
            $this->SendDebug(__FUNCTION__, 'departureTimes=' . print_r($departureTimes, true), 0);
            $action = $this->GetArrayElem($departureTimes[3], 'action', '');
            if ($action == 'activate') {
                $title = $this->Translate('Upcoming departure');

                $h = $this->GetArrayElem($departureTimes[3], 'timeStamp.hour', 0);
                $m = $this->GetArrayElem($departureTimes[3], 'timeStamp.minute', 0);
                $time = sprintf('%02d:%02d', $h, $m);

                $ref = date('H', time()) * 60 + date('i', time());
                if ($h * 60 + $m > $ref) {
                    $day = $this->Translate('today');
                } else {
                    $day = $this->Translate('tomorrow');
                }

                $spec = $time . ' ' . $day;

                $html .= '<tr>' . PHP_EOL;
                $html .= '<td>' . $title . '</td>' . PHP_EOL;
                $html .= '<td>' . $spec . '</td>' . PHP_EOL;
                $html .= '</tr>' . PHP_EOL;
            } else {
                $title = $this->Translate('Weekly departure');
                for ($i = 0; $i < 4; $i++) {
                    $action = $this->GetArrayElem($departureTimes[$i], 'action', '');
                    if ($action != 'activate') {
                        continue;
                    }

                    $h = $this->GetArrayElem($departureTimes[$i], 'timeStamp.hour', 0);
                    $m = $this->GetArrayElem($departureTimes[$i], 'timeStamp.minute', 0);
                    $time = sprintf('%02d:%02d', $h, $m);

                    $wdays = [];
                    $timerWeekDays = $this->GetArrayElem($departureTimes[$i], 'timerWeekDays', '');
                    foreach ($timerWeekDays as $timerWeekDay) {
                        $wdays[] = $this->Translate($timerWeekDay);
                    }

                    $spec = $time . ' ' . $this->Translate('on') . ' ' . implode(', ', $wdays);

                    $html .= '<tr>' . PHP_EOL;
                    $html .= '<td>' . $title . '</td>' . PHP_EOL;
                    $html .= '<td>' . $spec . '</td>' . PHP_EOL;
                    $html .= '</tr>' . PHP_EOL;

                    $title = '&nbsp;';
                }
            }

            $html .= '<tr>' . PHP_EOL;
            $html .= '</tr>' . PHP_EOL;

            $climatisationOn = $this->GetArrayElem($chargingProfile, 'chargingSettings.climatisationOn', false);
            $html .= '<tr>' . PHP_EOL;
            $html .= '<td>' . $this->Translate('Air conditioning departure time') . '</td>' . PHP_EOL;
            $html .= '<td>' . $this->Translate($climatisationOn ? 'Yes' : 'No') . '</td>' . PHP_EOL;
            $html .= '</tr>' . PHP_EOL;

            $html .= '<table>' . PHP_EOL;

            $this->SetValue('ChargingPreferences', $html);

            switch ($chargingStatus) {
                case self::$BMW_CHARGING_STATE_PLUGGED_IN:
                    $h = $this->GetArrayElem($chargingProfile, 'reductionOfChargeCurrent.start.hour', 0);
                    $m = $this->GetArrayElem($chargingProfile, 'reductionOfChargeCurrent.start.minute', 0);
                    $ts = mktime($h, $m, 0);
                    if ($ts && $ts < time()) {
                        $ts += 60 * 60 * 24;
                    }
                    $charging_start = $ts;
                    $charging_end = 0;
                    break;
                case self::$BMW_CHARGING_STATE_ACTIVE:
                    $charging_start = $this->GetValue('ChargingStart');
                    if ($charging_start == 0) {
                        $charging_start = time();
                    }
                    $m = $this->GetArrayElem($electricChargingState, 'remainingChargingMinutes', 0);
                    $charging_end = time() + ($m * 60);
                    break;
                default:
                    $charging_start = 0;
                    $charging_end = 0;
                    break;
            }

            $this->SaveValue('ChargingStart', $charging_start, $isChanged);
            $this->SaveValue('ChargingEnd', $charging_end, $isChanged);
        }

        $active_current_position = $this->ReadPropertyBoolean('active_current_position');
        if ($active_current_position) {
            $location = isset($state['location']) ? $state['location'] : [];
            $this->SendDebug(__FUNCTION__, 'location=' . print_r($location, true), 0);

            $dir = $this->GetArrayElem($location, 'heading', '');
            if ($dir != '') {
                $this->SaveValue('CurrentDirection', intval($dir), $isChanged);
            }

            $lat = $this->GetArrayElem($location, 'coordinates.latitude', '');
            if ($lat != '') {
                $this->SaveValue('CurrentLatitude', floatval($lat), $isChanged);
            }

            $lng = $this->GetArrayElem($location, 'coordinates.longitude', '');
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
                $val = $this->GetArrayElem($state, 'lastUpdatedAt', '');
                $this->SaveValue('LastPositionMessage', strtotime($val), $isChanged);
            }
        }

        $val = $this->GetArrayElem($state, 'currentMileage', 0);
        $this->SaveValue('Mileage', intval($val), $isChanged);

        $active_service = $this->ReadPropertyBoolean('active_service');
        if ($active_service) {
            $requiredServices = $this->GetArrayElem($state, 'requiredServices', '');
            $this->SendDebug(__FUNCTION__, 'requiredServices=' . print_r($requiredServices, true), 0);

            $tbl = '';
            if ($requiredServices != '') {
                foreach ($requiredServices as $requiredService) {
                    $description = $this->GetArrayElem($requiredService, 'description', '');
                    $dateTime = $this->GetArrayElem($requiredService, 'dateTime', '');
                    $tstamp = $dateTime != '' ? date('m/Y', strtotime($dateTime)) : '';
                    $type = $this->GetArrayElem($requiredService, 'type', '');
                    switch ($type) {
                        case 'OIL':
                            $type = $this->Translate('Oil');
                            break;
                        case 'VEHICLE_CHECK':
                            $type = $this->Translate('Vehicle check');
                            break;
                        case 'TIRE_WEAR_REAR':
                            $type = $this->Translate('Tire wear rear');
                            break;
                        case 'TIRE_WEAR_FRONT':
                            $type = $this->Translate('Tire wear front');
                            break;
                        case 'BRAKE_FLUID':
                            $type = $this->Translate('Break fluid');
                            break;
                        case 'EMISSION_CHECK':
                            $type = $this->Translate('Emission check');
                            break;
                        default:
                            $type = $this->Translate('Unknown type') . ' ˝' . $type . '"';
                            break;
                    }

                    $tbl .= '<tr>' . PHP_EOL;
                    $tbl .= '<td>' . $type . '</td>' . PHP_EOL;
                    $tbl .= '<td>' . $description . '</td>' . PHP_EOL;
                    $tbl .= '<td>' . $tstamp . '</td>' . PHP_EOL;
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

            $this->SetValue('ServiceMessages', $html);
        }

        $active_checkcontrol = $this->ReadPropertyBoolean('active_checkcontrol');
        if ($active_checkcontrol) {
            $checkControlMessages = $this->GetArrayElem($state, 'checkControlMessages', '');
            $this->SendDebug(__FUNCTION__, 'checkControlMessages=' . print_r($checkControlMessages, true), 0);

            $tbl = '';
            $checkControlMessages = $this->GetArrayElem($state, 'checkControlMessages', '');
            if ($checkControlMessages != '') {
                foreach ($checkControlMessages as $checkControlMessages) {
                    $type = $this->GetArrayElem($checkControlMessages, 'type', '');
                    switch ($type) {
                        case 'ENGINE_OIL':
                            $type = $this->Translate('Engine oil');
                            break;
                        case 'TIRE_PRESSURE':
                            $type = $this->Translate('Tire pressure');
                            break;
                        default:
                            $type = $this->Translate('Unknown type') . ' ˝' . $type . '"';
                            break;
                    }
                    $severity = $this->GetArrayElem($checkControlMessages, 'severity', '');
                    switch ($severity) {
                        case 'LOW':
                            $severity = $this->Translate('OK');
                            break;
                        default:
                            $severity = $this->Translate('Unknown severity') . ' ˝' . $severity . '"';
                            break;
                    }
                    $tbl .= '<tr>' . PHP_EOL;
                    $tbl .= '<td>' . $type . '</td>' . PHP_EOL;
                    $tbl .= '<td>' . $severity . '</td>' . PHP_EOL;
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

            $this->SetValue('CheckControlMessages', $html);
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
            'apptimezone'   => strval(round(intval(date('Z')) / 60)), // TZ-Differenz in Minuten
            'appDateTime'   => date('U') . date('v'), // Millisekunden
            'tireGuardMode' => 'ENABLED',
        ];
        $url = self::$vehicles_endpoint;
        $data = $this->CallAPI($url, '', $params, '');
        if ($data != false) {
            $jdata = json_decode($data, true);
            foreach ($jdata as $vehicle) {
                if ($vehicle['vin'] == $vin) {
                    $this->SendDebug(__FUNCTION__, 'vehicle=' . print_r($vehicle, true), 0);
                    $model = $this->GetArrayElem($vehicle, 'attributes.model', '');
                    $year = $this->GetArrayElem($vehicle, 'attributes.year', '');
                    $bodyType = $this->GetArrayElem($vehicle, 'attributes.bodyType', '');
                    $this->SetSummary($model . ' (' . $bodyType . '/' . $year . ')');
                    break;
                }
            }
        }

        /*
21.07.2022, 15:25:03 |       GetVehicleData | vehicle=Array
(
    [vin] => WBAWY510800E34942
    [mappingInfo] => Array
        (
            [isAssociated] =>
            [isLmmEnabled] =>
            [mappingStatus] => CONFIRMED
            [isPrimaryUser] => 1
        )

    [appVehicleType] => CONNECTED
    [attributes] => Array
        (
            [lastFetched] => 2022-07-21T13:25:03.374Z
            [model] => X3 xDrive30d
            [year] => 2016
            [color] => 4281545523
            [brand] => BMW
            [driveTrain] => COMBUSTION
            [headUnitType] => NBT_EVO
            [hmiVersion] => ID5
            [softwareVersionCurrent] => Array
                (
                    [puStep] => Array
                        (
                            [month] => 11
                            [year] => 16
                        )

                    [iStep] => 502
                    [seriesCluster] => F025
                )

            [softwareVersionExFactory] => Array
                (
                    [puStep] => Array
                        (
                            [month] => 11
                            [year] => 16
                        )

                    [iStep] => 502
                    [seriesCluster] => F025
                )

            [telematicsUnit] => ATM1
            [bodyType] => F25
            [countryOfOrigin] => DE
            [a4aType] => BLUETOOTH
            [driverGuideInfo] => Array
                (
                    [androidAppScheme] => com.bmwgroup.driversguide.row
                    [iosAppScheme] => bmwdriversguide:///open
                    [androidStoreUrl] => https://play.google.com/store/apps/details?id=com.bmwgroup.driversguide.row
                    [iosStoreUrl] => https://apps.apple.com/de/app/id714042749?mt=8
                )

        )

)





         */

        $url = self::$vehicles_endpoint . '/' . $vin . '/state';
        $data = $this->CallAPI($url, '', $params, '');
        if ($data != false) {
            $jdata = json_decode($data, true);
            $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
            $this->UpdateVehicleData($data);
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
            $this->SetMultiBuffer('ChargingStatistics', $data);
            $jdata = json_decode($data, true);
            $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
        }

        return $result;
    }

    private function GetCarPicture(string $carView = null)
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
        $this->SetMultiBuffer('ChargingSessions', $data);
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

        $this->SetValue('ChargingSessions', $html);
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
        $jdata = $data == false ? false : json_decode($data, true);
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
            return false;
        }

        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
        $result = $this->ExecuteRemoteService('CLIMATE_NOW', 'START');
        return true;
    }

    public function StopClimateControl()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return false;
        }

        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
        $result = $this->ExecuteRemoteService('CLIMATE_NOW', 'STOP');
        return true;
    }

    public function LockDoors()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return false;
        }

        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
        $result = $this->ExecuteRemoteService('DOOR_LOCK', '');
        return true;
    }

    public function UnlockDoors()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return false;
        }

        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
        $result = $this->ExecuteRemoteService('DOOR_UNLOCK', '');
        return true;
    }

    public function FlashHeadlights()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return false;
        }

        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
        $result = $this->ExecuteRemoteService('LIGHT_FLASH', '');
        return true;
    }

    public function BlowHorn()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return false;
        }

        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
        $result = $this->ExecuteRemoteService('HORN_BLOW', '');
        return true;
    }

    public function LocateVehicle()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return false;
        }

        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
        $result = $this->ExecuteRemoteService('VEHICLE_FINDER', '');
        return true;
    }

    public function ChargeNow()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return false;
        }

        $this->SendDebug(__FUNCTION__, 'call api ...', 0);
        $result = $this->ExecuteRemoteService('CHARGE_NOW', '');
        return true;
    }

    public function SendPOI(string $poi)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return false;
        }

        $jpoi = json_decode($poi, true);
        if (!isset($jpoi['latitude']) || !isset($jpoi['longitude'])) {
            $this->SendDebug(__FUNCTION__, 'missing coordinates (latitude/longitude) in ' . print_r($jpoi, true), 0);
            return false;
        }
        if (!isset($jpoi['name'])) {
            $jpoi['name'] = '(' . $jpoi['latitude'] . ', ' . $jpoi['longitude'] . ')';
        }

        $vin = $this->ReadPropertyString('vin');

        $endpoint = self::$vehicle_poi_endpoint;

        $postfields = [
            'vin'      => $vin,
            'location' => [
                'type'            => 'SHARED_DESTINATION_FROM_EXTERNAL_APP',
                'name'            => $this->GetArrayElem($jpoi, 'name', ''),
                'coordinates'     => [
                    'latitude'  => number_format((float) $jpoi['latitude'], 6, '.', ''),
                    'longitude' => number_format((float) $jpoi['longitude'], 6, '.', ''),
                ],
                'locationAddress' => [
                    'street'     => $this->GetArrayElem($jpoi, 'street', ''),
                    'postalCode' => $this->GetArrayElem($jpoi, 'postalCode', ''),
                    'city'       => $this->GetArrayElem($jpoi, 'city', ''),
                    'country'    => $this->GetArrayElem($jpoi, 'country', ''),
                ],
            ],
        ];

        $header_add = [
            'Content-Type' => 'application/json',
        ];

        $pos = $this->GetHomePosition();
        $params = [
            'deviceTime' => date('Y-m-d\TH:i:s'),
            'dlat'       => $pos['latitude'],
            'dlon'       => $pos['longitude'],
        ];
        $data = $this->CallAPI($endpoint, $postfields, $params, $header_add);
        $this->SendDebug(__FUNCTION__, 'poi=' . print_r($poi, true) . ', result=' . $data, 0);
        return $data;
    }

    private function UpdateRemoteServiceStatus()
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
            $this->MaintainTimer('UpdateRemoteServiceStatus', $refresh_interval * 1000);
        } else {
            $this->MaintainTimer('UpdateRemoteServiceStatus', 0);
        }
    }

    private function GetRemoteServiceHistory()
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
            'REMOTE360'          => 'Remote 3D View',
        ];
        $status2text = [
            'SUCCESS'       => 'success',
            'PENDING'       => 'pending',
            'IN_PROGRESS'   => 'pending',
            'INITIATED'     => 'initiated',
            'FAILED'        => 'failed',
            'FAILURE'       => 'failed',
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
        if ($data == false) {
            return;
        }
        $this->SetMultiBuffer('RemoteServiceHistory', $data);
        $jdata = json_decode($data, true);
        if ($jdata == false) {
            $jdata = [];
        }

        $history = json_decode($this->ReadAttributeString('RemoteServiceHistory'), true);
        if ($history == false) {
            $history = [];
        }
        $this->SendDebug(__FUNCTION__, 'history=' . print_r($history, true), 0);

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
                        $event_ts = $this->GetArrayElem($event, 'creationTime', '');
                        $event_service = $this->GetArrayElem($event, 'service', '');
                        $event_action = $this->GetArrayElem($event, 'action', '');
                        if ($event_ts == $ts && $event_service == 'CLIMATE_NOW' && $event_action == 'STOP') {
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

        $this->SetValue('RemoteServiceHistory', $html);
    }

    private function UpdateData()
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

        $this->SendDebug(__FUNCTION__, $this->PrintTimer('UpdateData'), 0);
    }

    private function LocalRequestAction($ident, $value)
    {
        $r = true;
        switch ($ident) {
            case 'UpdateData':
                $this->UpdateData();
                break;
            case 'UpdateRemoteServiceStatus':
                $this->UpdateRemoteServiceStatus();
                break;
            case 'Relogin':
                $this->Relogin();
                break;
            case 'GetCarPicture':
                $this->GetCarPicture($value);
                break;
            default:
                $r = false;
                break;
        }
        return $r;
    }

    public function RequestAction($ident, $value)
    {
        if ($this->LocalRequestAction($ident, $value)) {
            return;
        }
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }

        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'ident=' . $ident . ', value=' . $value, 0);
        $r = false;
        switch ($ident) {
            case 'TriggerStartClimatisation':
                $r = $this->StartClimateControl();
                break;
            case 'TriggerStopClimatisation':
                $r = $this->StopClimateControl();
                break;
            case 'TriggerLockDoors':
                $r = $this->LockDoors();
                break;
            case 'TriggerUnlockDoors':
                $r = $this->UnlockDoors();
                break;
            case 'TriggerFlashHeadlights':
                $r = $this->FlashHeadlights();
                break;
            case 'TriggerBlowHorn':
                $r = $this->BlowHorn();
                break;
            case 'TriggerChargeNow':
                $r = $this->ChargeNow();
                break;
            case 'TriggerLocateVehicle':
                $r = $this->LocateVehicle();
                break;
            case 'GoogleMapType':
                $r = $this->SetGoogleMapType($value);
                break;
            case 'GoogleMapZoom':
                $r = $this->SetGoogleMapZoom($value);
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident "' . $ident . '"', 0);
        }
        if ($r) {
            $this->SetValue($ident, $value);
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
        return true;
    }

    private function SetGoogleMapZoom($zoom)
    {
        $this->SendDebug(__FUNCTION__, 'zoom=' . $zoom, 0);
        $maptype = $this->GetValue('GoogleMapType');
        $this->SetGoogleMap($maptype, $zoom);
        return true;
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
            'TARGET_REACHED'         => self::$BMW_CHARGING_STATE_TARGET,
        ];

        if (isset($str2enum[$s])) {
            $e = $str2enum[$s];
        } else {
            $this->SendDebug(__FUNCTION__, 'unknown value "' . $s . '"', 0);
            $e = self::$BMW_CHARGING_STATE_UNKNOWN;
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
    private function MapRoofState($s)
    {
        $str2enum = [
            'UNKNOWN'      => self::$BMW_ROOF_STATE_UNKNOWN,
            'OPEN'         => self::$BMW_ROOF_STATE_OPEN,
            'OPEN_TILT'    => self::$BMW_ROOF_STATE_OPEN_TILT,
            'INTERMEDIATE' => self::$BMW_ROOF_STATE_INTERMEDIATE,
            'CLOSED'       => self::$BMW_ROOF_STATE_CLOSED,
        ];

        if (isset($str2enum[$s])) {
            $e = $str2enum[$s];
        } else {
            $this->SendDebug(__FUNCTION__, 'unknown value "' . $s . '"', 0);
            $e = self::$BMW_ROOF_STATE_UNKNOWN;
        }
        return $e;
    }

    // Reifendruck
    private function CalcTirePressure($s)
    {
        return floatval($s) / 100;
    }

    public function GetRawData(string $name)
    {
        $data = $this->GetMultiBuffer($name);
        $this->SendDebug(__FUNCTION__, 'name=' . $name . ', size=' . strlen($data), 0);
        return $data;
    }
}
