<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';
require_once __DIR__ . '/../libs/images.php';

// define('CHARGE_NOW', true);

class BMWConnectedDriveVehicle extends IPSModule
{
    use BMWConnectedDrive\StubsCommonLib;
    use BMWConnectedDriveLocalLib;
    use BMWConnectedDriveImagesLib;

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

        // alte, nicht mehr benötigte, Properties
        $this->RegisterPropertyString('user', '');
        $this->RegisterPropertyString('password', '');
        $this->RegisterPropertyInteger('country', self::$BMW_COUNTRY_GERMANY);
        $this->RegisterPropertyInteger('brand', self::$BMW_BRAND_BMW);

        $this->RegisterPropertyString('vin', '');
        $this->RegisterPropertyInteger('model', self::$BMW_DRIVE_TYPE_COMBUSTION);

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

        $this->RegisterAttributeString('RemoteServiceHistory', '');

        $this->RegisterAttributeString('UpdateInfo', '');

        $this->InstallVarProfiles(false);

        $this->SetBuffer('Summary', '');
        $this->SetMultiBuffer('VehicleData', '');
        $this->SetMultiBuffer('ChargingStatistics', '');
        $this->SetMultiBuffer('ChargingSessions', '');
        $this->SetMultiBuffer('RemoteServiceHistory', '');

        $this->RegisterTimer('UpdateData', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "UpdateData", "");');
        $this->RegisterTimer('UpdateRemoteServiceStatus', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "UpdateRemoteServiceStatus", "");');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);

        $this->ConnectParent('{2B3E3F00-33AC-4A54-8E20-F8B57241913D}');
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

            'TankCapacity' => [
                'desc'    => 'tank capacity',
                'vartype' => VARIABLETYPE_FLOAT,
                'varprof' => 'BMW.TankCapacity',
            ],
            'TankLevel' => [
                'desc'    => 'tank level',
                'vartype' => VARIABLETYPE_FLOAT,
                'varprof' => 'BMW.TankLevel',
            ],
        ];

        if (isset($settings[$ident])) {
            $this->MaintainVariable($ident, $this->Translate($settings[$ident]['desc']), $settings[$ident]['vartype'], $settings[$ident]['varprof'], $vpos, $use);
        }
    }

    private function CheckModuleConfiguration()
    {
        $r = [];

        $vin = $this->ReadPropertyString('vin');
        if ($vin == '') {
            $this->SendDebug(__FUNCTION__, '"vin" is empty', 0);
            $r[] = $this->Translate('A registered VIN is required');
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

        if ($this->version2num($oldInfo) < $this->version2num('2.0')) {
            $r[] = $this->Translate('Delete old variables and variableprofiles');
        }

        if ($this->version2num($oldInfo) < $this->version2num('2.7')) {
            $r[] = $this->Translate('Delete old variables and variableprofiles');
        }

        if ($this->version2num($oldInfo) < $this->version2num('2.9.1')) {
            $r[] = $this->Translate('Delete old variables');
        }

        if ($this->version2num($oldInfo) < $this->version2num('3.0')) {
            $r[] = $this->Translate('Generate a I/O instance');
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

        if ($this->version2num($oldInfo) < $this->version2num('3.0')) {
            $user = $this->ReadPropertyString('user');

            $inst = IPS_GetInstance($this->InstanceID);
            $connectionID = $inst['ConnectionID'];
            if (IPS_InstanceExists($connectionID) == false) {
                IPS_DisconnectInstance($this->InstanceID);
                $connectionID = 0;
            }

            $u_connectionID = 0;
            $guid = '{2B3E3F00-33AC-4A54-8E20-F8B57241913D}'; // BMWConnectedDriveIO
            $instIDs = IPS_GetInstanceListByModuleID($guid);
            foreach ($instIDs as $instID) {
                if (IPS_GetProperty($instID, 'user') == $user) {
                    $u_connectionID = $instID;
                    break;
                }
            }

            if ($u_connectionID == 0) {
                if ($connectionID != 0 && IPS_GetProperty($connectionID, 'user') == '') {
                    $u_connectionID = $connectionID;
                } else {
                    $u_connectionID = IPS_CreateInstance($guid);
                }
                IPS_SetProperty($u_connectionID, 'user', $user);
                IPS_SetProperty($u_connectionID, 'password', $this->ReadPropertyString('password'));
                IPS_SetProperty($u_connectionID, 'country', $this->ReadPropertyInteger('country'));
                IPS_SetProperty($u_connectionID, 'brand', $this->ReadPropertyInteger('brand'));
                IPS_ApplyChanges($u_connectionID);
                IPS_SetName($u_connectionID, 'BMWConnectedDrive I/O (' . $user . ')');
            }

            if ($u_connectionID != $connectionID) {
                if ($connectionID != 0) {
                    IPS_DisconnectInstance($this->InstanceID);
                    $connectionID = 0;
                }
                IPS_ConnectInstance($this->InstanceID, $u_connectionID);
            }
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
        $isElectric = $model != self::$BMW_DRIVE_TYPE_COMBUSTION;
        $hasCombustion = $model != self::$BMW_DRIVE_TYPE_ELECTRIC;

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
            $this->MaintainTimer('UpdateRemoteServiceStatus', 1000);
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

        if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) {
            $this->SetUpdateInterval();
            $this->MaintainTimer('UpdateRemoteServiceStatus', 1000);
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
                    'name'    => 'vin',
                    'type'    => 'ValidationTextBox',
                    'caption' => 'VIN'
                ],
                [
                    'type'    => 'Select',
                    'name'    => 'model',
                    'caption' => 'mode of driving',
                    'options' => $this->DriveTypeAsOptions(),
                ],
            ],
            'caption' => 'Vehicle data',
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
                            'label' => $this->Translate('Diagonal from front'),
                            'value' => self::$BMW_CARVIEW_FRONTSIDE
                        ],
                        /*
                        [
                            'label' => $this->Translate('From the front'),
                            'value' => self::$BMW_CARVIEW_FRONT
                        ],
                        [
                            'label' => $this->Translate('From the side'),
                            'value' => self::$BMW_CARVIEW_SIDE
                        ],
                         */
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
            'expanded'  => false,
            'items'     => [
                $this->GetInstallVarProfilesFormItem(),
            ]
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Test area',
            'expanded'  => false,
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

        $hasCombustion = $model != self::$BMW_DRIVE_TYPE_ELECTRIC;

        if ($model != self::$BMW_DRIVE_TYPE_ELECTRIC) {
            if (isset($state['combustionFuelLevel']['remainingFuelLiters'])) {
                $val = $state['combustionFuelLevel']['remainingFuelLiters'];
                $this->MaintainStateVariable('TankCapacity', true, 2);
                $this->SaveValue('TankCapacity', $val, $isChanged);
            } else {
                $this->UnregisterVariable('TankCapacity');
            }
            if (isset($state['combustionFuelLevel']['remainingFuelPercent'])) {
                $val = $state['combustionFuelLevel']['remainingFuelPercent'];
                $this->MaintainStateVariable('TankLevel', true, 2);
                $this->SaveValue('TankLevel', $val, $isChanged);
            } else {
                $this->UnregisterVariable('TankLevel');
            }

            $val = $this->GetArrayElem($state, 'combustionFuelLevel.range', '');
            $this->SaveValue('RemainingCombinedRange', floatval($val), $isChanged);
        }

        if ($model != self::$BMW_DRIVE_TYPE_COMBUSTION) {
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
                        case 'VEHICLE_TUV':
                            $type = $this->Translate('Legal inspection');
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

        $vin = $this->ReadPropertyString('vin');

        if ($this->GetBuffer('Summary') == '') {
            $SendData = [
                'DataID'   => '{67B1E7E9-97C7-43AC-BB2E-723FFE2444FF}', // an BMWConnectedDriveIO
                'CallerID' => $this->InstanceID,
                'Function' => 'GetVehicles'
            ];
            $data = $this->SendDataToParent(json_encode($SendData));
            $vehicles = @json_decode($data, true);
            $this->SendDebug(__FUNCTION__, 'SendData=' . json_encode($SendData) . ', data=' . $data, 0);
            if ($vehicles != false) {
                foreach ($vehicles as $vehicle) {
                    if ($vehicle['vin'] == $vin) {
                        $this->SendDebug(__FUNCTION__, 'vehicle=' . print_r($vehicle, true), 0);
                        $model = $this->GetArrayElem($vehicle, 'attributes.model', '');
                        $year = $this->GetArrayElem($vehicle, 'attributes.year', '');
                        $bodyType = $this->GetArrayElem($vehicle, 'attributes.bodyType', '');
                        $summary = $model . ' (' . $bodyType . '/' . $year . ')';
                        $this->SetSummary($summary);
                        $this->SetBuffer('Summary', $summary);
                        break;
                    }
                }
            }
        }

        $SendData = [
            'DataID'   => '{67B1E7E9-97C7-43AC-BB2E-723FFE2444FF}', // an BMWConnectedDriveIO
            'CallerID' => $this->InstanceID,
            'Function' => 'GetVehicleData',
            'vin'      => $vin,
        ];
        $data = $this->SendDataToParent(json_encode($SendData));
        $this->SendDebug(__FUNCTION__, 'SendData=' . json_encode($SendData) . ', data=' . $data, 0);
        $vehicle = @json_decode($data, true);
        if ($vehicle != false) {
            $this->SendDebug(__FUNCTION__, 'vehicle=' . print_r($vehicle, true), 0);
            $this->UpdateVehicleData($data);
        }
    }

    private function GetChargingStatistics()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $vin = $this->ReadPropertyString('vin');

        $SendData = [
            'DataID'   => '{67B1E7E9-97C7-43AC-BB2E-723FFE2444FF}', // an BMWConnectedDriveIO
            'CallerID' => $this->InstanceID,
            'Function' => 'GetChargingStatistics',
            'vin'      => $vin,
        ];
        $data = $this->SendDataToParent(json_encode($SendData));
        $this->SendDebug(__FUNCTION__, 'SendData=' . json_encode($SendData) . ', data=' . $data, 0);
        if ($data != false) {
            $this->SetMultiBuffer('ChargingStatistics', $data);
            $jdata = @json_decode($data, true);
            $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
        }
    }

    private function GetCarPicture(string $carView = null)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $vin = $this->ReadPropertyString('vin');
        if (is_null($carView)) {
            $carView = self::$BMW_CARVIEW_FRONTSIDE;
        }

        $SendData = [
            'DataID'   => '{67B1E7E9-97C7-43AC-BB2E-723FFE2444FF}', // an BMWConnectedDriveIO
            'CallerID' => $this->InstanceID,
            'Function' => 'GetCarPicture',
            'vin'      => $vin,
            'carView'  => $carView,
        ];
        $data = $this->SendDataToParent(json_encode($SendData));
        $this->SendDebug(__FUNCTION__, 'SendData=' . json_encode($SendData) . ', data=' . $this->LimitOutput($data), 0);
        if ($data != false) {
            $this->SetMediaData('Car picture', base64_decode($data), MEDIATYPE_IMAGE, '.png', false);
        }
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

        $vin = $this->ReadPropertyString('vin');

        $SendData = [
            'DataID'   => '{67B1E7E9-97C7-43AC-BB2E-723FFE2444FF}', // an BMWConnectedDriveIO
            'CallerID' => $this->InstanceID,
            'Function' => 'GetChargingSessions',
            'vin'      => $vin,
        ];
        $data = $this->SendDataToParent(json_encode($SendData));
        $this->SendDebug(__FUNCTION__, 'SendData=' . json_encode($SendData) . ', data=' . $data, 0);
        if ($data != false) {
            $jdata = @json_decode($data, true);
            $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
            $this->UpdateChargingSessions($data);
        }
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

        $SendData = [
            'DataID'   => '{67B1E7E9-97C7-43AC-BB2E-723FFE2444FF}', // an BMWConnectedDriveIO
            'CallerID' => $this->InstanceID,
            'Function' => 'ExecuteRemoteService',
            'vin'      => $vin,
            'service'  => $service,
            'action'   => $action,
        ];
        $data = $this->SendDataToParent(json_encode($SendData));
        $this->SendDebug(__FUNCTION__, 'SendData=' . json_encode($SendData) . ', data=' . $data, 0);

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
        $jdata = @json_decode($data, true);
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

        $vin = $this->ReadPropertyString('vin');

        $jpoi = @json_decode($poi, true);
        if (!isset($jpoi['latitude']) || !isset($jpoi['longitude'])) {
            $this->SendDebug(__FUNCTION__, 'missing coordinates (latitude/longitude) in ' . print_r($jpoi, true), 0);
            return false;
        }
        if (!isset($jpoi['name'])) {
            $jpoi['name'] = '(' . $jpoi['latitude'] . ', ' . $jpoi['longitude'] . ')';
        }

        $SendData = [
            'DataID'   => '{67B1E7E9-97C7-43AC-BB2E-723FFE2444FF}', // an BMWConnectedDriveIO
            'CallerID' => $this->InstanceID,
            'Function' => 'SendPOI',
            'vin'      => $vin,
            'poi'      => json_encode($jpoi),
        ];
        $data = $this->SendDataToParent(json_encode($SendData));
        $this->SendDebug(__FUNCTION__, 'SendData=' . json_encode($SendData) . ', data=' . $data, 0);
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
                $SendData = [
                    'DataID'   => '{67B1E7E9-97C7-43AC-BB2E-723FFE2444FF}', // an BMWConnectedDriveIO
                    'CallerID' => $this->InstanceID,
                    'Function' => 'GetRemoteServiceStatus',
                    'eventId'  => $event['eventId'],
                ];
                $data = $this->SendDataToParent(json_encode($SendData));
                $this->SendDebug(__FUNCTION__, 'SendData=' . json_encode($SendData) . ', data=' . $data, 0);
                $jdata = @json_decode($data, true);
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
                    $SendData = [
                        'DataID'   => '{67B1E7E9-97C7-43AC-BB2E-723FFE2444FF}', // an BMWConnectedDriveIO
                        'CallerID' => $this->InstanceID,
                        'Function' => 'GetRemoteServicePosition',
                        'eventId'  => $event['eventId'],
                    ];
                    $data = $this->SendDataToParent(json_encode($SendData));
                    $this->SendDebug(__FUNCTION__, 'SendData=' . json_encode($SendData) . ', data=' . $data, 0);
                    $jdata = @json_decode($data, true);
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

        $vin = $this->ReadPropertyString('vin');

        $SendData = [
            'DataID'   => '{67B1E7E9-97C7-43AC-BB2E-723FFE2444FF}', // an BMWConnectedDriveIO
            'CallerID' => $this->InstanceID,
            'Function' => 'GetRemoteServiceHistory',
            'vin'      => $vin,
        ];
        $data = $this->SendDataToParent(json_encode($SendData));
        $this->SendDebug(__FUNCTION__, 'SendData=' . json_encode($SendData) . ', data=' . $data, 0);
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
        $this->SendDebug(__FUNCTION__, 'save service history=' . print_r($history, true), 0);

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
        if ($model != self::$BMW_DRIVE_TYPE_COMBUSTION) {
            $this->GetChargingStatistics();
            $this->GetChargingSessions();
        }

        $this->GetRemoteServiceHistory();

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, '... finished in ' . $duration . 's', 0);

        $this->SendDebug(__FUNCTION__, $this->PrintTimer('UpdateData'), 0);
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
