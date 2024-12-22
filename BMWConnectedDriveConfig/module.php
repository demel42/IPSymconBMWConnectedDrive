<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';
require_once __DIR__ . '/../libs/images.php';

class BMWConnectedDriveConfig extends IPSModule
{
    use BMWConnectedDrive\StubsCommonLib;
    use BMWConnectedDriveLocalLib;
    use BMWConnectedDriveImagesLib;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->CommonConstruct(__DIR__);
    }

    public function __destruct()
    {
        $this->CommonDestruct();
    }

    public function Create()
    {
        parent::Create();

        if (IPS_GetKernelVersion() < 7.0) {
            $this->RegisterPropertyInteger('ImportCategoryID', 0);
        }

        $this->RegisterAttributeString('UpdateInfo', json_encode([]));
        $this->RegisterAttributeString('ModuleStats', json_encode([]));
        $this->RegisterAttributeString('DataCache', json_encode([]));

        $this->InstallVarProfiles(false);

        $this->ConnectParent('{2B3E3F00-33AC-4A54-8E20-F8B57241913D}');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $propertyNames = [];
        if (IPS_GetKernelVersion() < 7.0) {
            $propertyNames[] = 'ImportCategoryID';
        }
        $this->MaintainReferences($propertyNames);

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $this->SetupDataCache(24 * 60 * 60);

        $this->MaintainStatus(IS_ACTIVE);
    }

    private function getConfiguratorValues()
    {
        $entries = [];

        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return $entries;
        }

        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent', 0);
            return $entries;
        }

        if (IPS_GetKernelVersion() < 7.0) {
            $catID = $this->ReadPropertyInteger('ImportCategoryID');
            $location = $this->GetConfiguratorLocation($catID);
        } else {
            $location = '';
        }

        $dataCache = $this->ReadDataCache();
        if (isset($dataCache['data']['vehicles'])) {
            $vehicles = $dataCache['data']['vehicles'];
            $this->SendDebug(__FUNCTION__, 'vehicles (from cache)=' . print_r($vehicles, true), 0);
        } else {
            $SendData = [
                'DataID'   => '{67B1E7E9-97C7-43AC-BB2E-723FFE2444FF}', // an BMWConnectedDriveIO
                'CallerID' => $this->InstanceID,
                'Function' => 'GetVehicles'
            ];
            $data = $this->SendDataToParent(json_encode($SendData));
            $vehicles = @json_decode($data, true);
            $this->SendDebug(__FUNCTION__, 'vehicles=' . print_r($vehicles, true), 0);
            if (is_array($vehicles)) {
                $dataCache['data']['vehicles'] = $vehicles;
            }
            $this->WriteDataCache($dataCache, time());
        }

        $guid = '{8FD2A163-E07A-A2A2-58CC-974155FAEE33}'; // BMWConnectedDriveVehicle
        $instIDs = IPS_GetInstanceListByModuleID($guid);

        if (is_array($vehicles)) {
            foreach ($vehicles as $vehicle) {
                $this->SendDebug(__FUNCTION__, 'vehicle=' . print_r($vehicle, true), 0);
                $vin = $vehicle['vin'];

                $model = $this->GetArrayElem($vehicle, 'attributes.model', '');
                $year = $this->GetArrayElem($vehicle, 'attributes.year', '');
                $bodyType = $this->GetArrayElem($vehicle, 'attributes.bodyType', '');
                $driveTrain = $this->GetArrayElem($vehicle, 'attributes.driveTrain', '');
                switch ($driveTrain) {
                    case 'COMBUSTION':
                        $driveType = self::$BMW_DRIVE_TYPE_COMBUSTION;
                        break;
                    case 'PLUGIN_HYBRID':
                    case 'HYBRID':
                        $driveType = self::$BMW_DRIVE_TYPE_HYBRID;
                        break;
                    case 'ELECTRIC':
                        $driveType = self::$BMW_DRIVE_TYPE_ELECTRIC;
                        break;
                    default:
                        $driveType = self::$BMW_DRIVE_TYPE_UNKNOWN;
                        break;
                }

                $instanceID = 0;
                $vehicleName = '';
                foreach ($instIDs as $instID) {
                    if (@IPS_GetProperty($instID, 'vin') == $vin) {
                        $this->SendDebug(__FUNCTION__, 'vehicle found: ' . IPS_GetName($instID) . ' (' . $instID . ')', 0);
                        $instanceID = $instID;
                        $vehicleName = IPS_GetName($instID);
                        break;
                    }
                }

                if ($instanceID && IPS_GetInstance($instanceID)['ConnectionID'] != IPS_GetInstance($this->InstanceID)['ConnectionID']) {
                    continue;
                }

                $entry = [
                    'instanceID'  => $instanceID,
                    'vehicleName' => $vehicleName,
                    'vin'         => $vin,
                    'model'       => $model,
                    'year'        => $year,
                    'bodyType'    => $bodyType,
                    'driveType'   => $this->DriveType2String($driveType),
                    'create'      => [
                        'moduleID'      => $guid,
                        'location'      => $location,
                        'info'          => $model . ' (' . $bodyType . '/' . $year . ')',
                        'configuration' => [
                            'vin'   => $vin,
                            'model' => $driveType,
                        ]
                    ]
                ];
                $entries[] = $entry;
                $this->SendDebug(__FUNCTION__, 'instanceID=' . $instanceID . ', entry=' . print_r($entry, true), 0);
            }
        }

        foreach ($instIDs as $instID) {
            $fnd = false;
            foreach ($entries as $entry) {
                if ($entry['instanceID'] == $instID) {
                    $fnd = true;
                    break;
                }
            }
            if ($fnd) {
                continue;
            }

            if (IPS_GetInstance($instID)['ConnectionID'] != IPS_GetInstance($this->InstanceID)['ConnectionID']) {
                continue;
            }

            $vehicleName = IPS_GetName($instID);
            @$vin = IPS_GetProperty($instID, 'vin');
            @$driveType = IPS_GetProperty($instID, 'model');

            $entry = [
                'instanceID'  => $instID,
                'vehicleName' => $vehicleName,
                'vin'         => $vin,
                'model'       => '',
                'year'        => '',
                'bodyType'    => '',
                'driveType'   => $this->DriveType2String($driveType),
            ];
            $entries[] = $entry;
            $this->SendDebug(__FUNCTION__, 'lost: instanceID=' . $instID . ', entry=' . print_r($entry, true), 0);
        }

        return $entries;
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('BMW configurator');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
        }

        if (IPS_GetKernelVersion() < 7.0) {
            $formElements[] = [
                'type'    => 'SelectCategory',
                'name'    => 'ImportCategoryID',
                'caption' => 'category for BMW vehicles to be created'
            ];
        }

        $entries = $this->getConfiguratorValues();
        $formElements[] = [
            'name'        => 'vehicles',
            'caption'     => 'Vehicles',
            'type'        => 'Configurator',
            'rowCount'    => count($entries),
            'add'         => false,
            'delete'      => false,
            'columns'     => [
                [
                    'caption' => 'Name',
                    'name'    => 'vehicleName',
                    'width'   => 'auto',
                ],
                [
                    'caption' => 'VIN',
                    'name'    => 'vin',
                    'width'   => '200px',
                ],
                [
                    'caption' => 'Model',
                    'name'    => 'model',
                    'width'   => '150px'
                ],
                [
                    'caption' => 'Body type',
                    'name'    => 'bodyType',
                    'width'   => '100px'
                ],
                [
                    'caption' => 'Year',
                    'name'    => 'year',
                    'width'   => '100px'
                ],
                [
                    'caption' => 'Drive type',
                    'name'    => 'driveType',
                    'width'   => '200px'
                ],
            ],
            'values'            => $entries,
            'discoveryInterval' => 60 * 60 * 24,
        ];
        $formElements[] = $this->GetRefreshDataCacheFormAction();

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

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    public function RequestAction($ident, $value)
    {
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }
        switch ($ident) {
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
    }
}
