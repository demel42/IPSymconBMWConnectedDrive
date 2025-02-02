<?php

declare(strict_types=1);

trait BMWConnectedDriveLocalLib
{
    public static $IS_UNAUTHORIZED = IS_EBASE + 10;
    public static $IS_FORBIDDEN = IS_EBASE + 11;
    public static $IS_SERVERERROR = IS_EBASE + 12;
    public static $IS_HTTPERROR = IS_EBASE + 13;
    public static $IS_INVALIDDATA = IS_EBASE + 14;
    public static $IS_APIERROR = IS_EBASE + 15;
    public static $IS_NOCAPTCHA = IS_EBASE + 16;
    public static $IS_NOTLOGGEDON = IS_EBASE + 17;

    private function GetFormStatus()
    {
        $formStatus = $this->GetCommonFormStatus();

        $formStatus[] = ['code' => self::$IS_UNAUTHORIZED, 'icon' => 'error', 'caption' => 'Instance is inactive (unauthorized)'];
        $formStatus[] = ['code' => self::$IS_FORBIDDEN, 'icon' => 'error', 'caption' => 'Instance is inactive (forbidden)'];
        $formStatus[] = ['code' => self::$IS_SERVERERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (server error)'];
        $formStatus[] = ['code' => self::$IS_HTTPERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (http error)'];
        $formStatus[] = ['code' => self::$IS_INVALIDDATA, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid data)'];
        $formStatus[] = ['code' => self::$IS_APIERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (api error)'];
        $formStatus[] = ['code' => self::$IS_NOCAPTCHA, 'icon' => 'error', 'caption' => 'Instance is inactive (no captcha)'];
        $formStatus[] = ['code' => self::$IS_NOTLOGGEDON, 'icon' => 'error', 'caption' => 'Instance is inactive (not logged on)'];

        return $formStatus;
    }

    public static $STATUS_INVALID = 0;
    public static $STATUS_VALID = 1;
    public static $STATUS_RETRYABLE = 2;

    private function CheckStatus()
    {
        switch ($this->GetStatus()) {
            case IS_ACTIVE:
                $class = self::$STATUS_VALID;
                break;
            case self::$IS_UNAUTHORIZED:
            case self::$IS_FORBIDDEN:
            case self::$IS_SERVERERROR:
            case self::$IS_HTTPERROR:
            case self::$IS_INVALIDDATA:
            case self::$IS_APIERROR:
                $class = self::$STATUS_RETRYABLE;
                break;
            default:
                $class = self::$STATUS_INVALID;
                break;
        }

        return $class;
    }

    // Model
    private static $BMW_DRIVE_TYPE_UNKNOWN = 0;
    private static $BMW_DRIVE_TYPE_ELECTRIC = 1;
    private static $BMW_DRIVE_TYPE_HYBRID = 2;
    private static $BMW_DRIVE_TYPE_COMBUSTION = 3;

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
    private static $BMW_CHARGING_STATE_TARGET = 9;

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
    private static $BMW_ROOF_STATE_UNKNOWN = 0;
    private static $BMW_ROOF_STATE_OPEN = 1;
    private static $BMW_ROOF_STATE_OPEN_TILT = 2;
    private static $BMW_ROOF_STATE_INTERMEDIATE = 3;
    private static $BMW_ROOF_STATE_CLOSED = 4;

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
    private static $BMW_COUNTRY_AUSTRIA = 5;
    private static $BMW_COUNTRY_LUXEMBOURG = 6;
    private static $BMW_COUNTRY_NETHERLANDS = 7;
    private static $BMW_COUNTRY_FRANCE = 8;

    // Marken
    private static $BMW_BRAND_BMW = 0;
    private static $BMW_BRAND_MINI = 1;

    // Ansicht des Fahrzeugbilds
    private static $BMW_CARVIEW_FRONTSIDE = 'AngleSideViewForty'; // VehicleStatus
    private static $BMW_CARVIEW_FRONT = 'FrontView';
    private static $BMW_CARVIEW_SIDE = 'SideViewLeft';

    private function InstallVarProfiles(bool $reInstall = false)
    {
        $this->SendDebug(__FUNCTION__, 'reInstall=' . $this->bool2str($reInstall), 0);

        $associations = [
            ['Wert' => false, 'Name' => $this->Translate('No'), 'Farbe' => -1],
            ['Wert' => true, 'Name' => $this->Translate('Yes'), 'Farbe' => 0xEE0000],
        ];
        $this->CreateVarProfile('BMW.YesNo', VARIABLETYPE_BOOLEAN, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => 0, 'Name' => $this->Translate('Start'), 'Farbe' => 0x3ADF00],
        ];
        $this->CreateVarProfile('BMW.TriggerRemoteService', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, 'Execute', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$BMW_GOOGLEMAP_TYPE_ROADMAP, 'Name' => $this->Translate('roadmap'), 'Farbe' => 0x3ADF00],
            ['Wert' => self::$BMW_GOOGLEMAP_TYPE_SATELLITE, 'Name' => $this->Translate('satellite'), 'Farbe' => 0x3ADF00],
            ['Wert' => self::$BMW_GOOGLEMAP_TYPE_HYBRID, 'Name' => $this->Translate('hybrid'), 'Farbe' => 0x3ADF00],
            ['Wert' => self::$BMW_GOOGLEMAP_TYPE_TERRAIN, 'Name' => $this->Translate('terrain'), 'Farbe' => 0x3ADF00],
        ];
        $this->CreateVarProfile('BMW.Googlemap', VARIABLETYPE_INTEGER, '', 0, 3, 0, 0, 'Car', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$BMW_CONNECTOR_STATE_UNKNOWN, 'Name' => $this->Translate('unknown'), 'Farbe' => 0xEE0000],
            ['Wert' => self::$BMW_CONNECTOR_STATE_DISCONNECTED, 'Name' => $this->Translate('disconnected'), 'Farbe' => -1],
            ['Wert' => self::$BMW_CONNECTOR_STATE_CONNECTED, 'Name' => $this->Translate('connected'), 'Farbe' => 0x228B22],
        ];
        $this->CreateVarProfile('BMW.ConnectorStatus', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$BMW_CHARGING_STATE_UNKNOWN, 'Name' => $this->Translate('unknown'), 'Farbe' => 0xEE0000],
            ['Wert' => self::$BMW_CHARGING_STATE_NOT, 'Name' => $this->Translate('not charging'), 'Farbe' => -1],
            ['Wert' => self::$BMW_CHARGING_STATE_ACTIVE, 'Name' => $this->Translate('charging active'), 'Farbe' => 0x228B22],
            ['Wert' => self::$BMW_CHARGING_STATE_ENDED, 'Name' => $this->Translate('charging ended'), 'Farbe' => 0x0000FF],
            ['Wert' => self::$BMW_CHARGING_STATE_PAUSED, 'Name' => $this->Translate('charging paused'), 'Farbe' => -1],
            ['Wert' => self::$BMW_CHARGING_STATE_FULLY, 'Name' => $this->Translate('fully charged'), 'Farbe' => 0x0000FF],
            ['Wert' => self::$BMW_CHARGING_STATE_PARTIAL, 'Name' => $this->Translate('partial charged'), 'Farbe' => 0x0000FF],
            ['Wert' => self::$BMW_CHARGING_STATE_ERROR, 'Name' => $this->Translate('charging error'), 'Farbe' => 0xEE0000],
            ['Wert' => self::$BMW_CHARGING_STATE_INVALID, 'Name' => $this->Translate('invalid state'), 'Farbe' => 0xEE0000],
            ['Wert' => self::$BMW_CHARGING_STATE_PLUGGED_IN, 'Name' => $this->Translate('plugged in'), 'Farbe' => 0x228B22],
            ['Wert' => self::$BMW_CHARGING_STATE_TARGET, 'Name' => $this->Translate('target reached'), 'Farbe' => 0x0000FF],
        ];
        $this->CreateVarProfile('BMW.ChargingStatus', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$BMW_DOOR_STATE_UNKNOWN, 'Name' => $this->Translate('unknown'), 'Farbe' => -1],
            ['Wert' => self::$BMW_DOOR_STATE_OPEN, 'Name' => $this->Translate('open'), 'Farbe' => -1],
            ['Wert' => self::$BMW_DOOR_STATE_CLOSED, 'Name' => $this->Translate('closed'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('BMW.DoorState', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$BMW_DOOR_CLOSURE_UNKNOWN, 'Name' => $this->Translate('unknown'), 'Farbe' => -1],
            ['Wert' => self::$BMW_DOOR_CLOSURE_UNLOCKED, 'Name' => $this->Translate('unlocked'), 'Farbe' => -1],
            ['Wert' => self::$BMW_DOOR_CLOSURE_LOCKED, 'Name' => $this->Translate('locked'), 'Farbe' => -1],
            ['Wert' => self::$BMW_DOOR_CLOSURE_SECURED, 'Name' => $this->Translate('secured'), 'Farbe' => -1],
            ['Wert' => self::$BMW_DOOR_CLOSURE_SELECTIVLOCKED, 'Name' => $this->Translate('selectiv locked'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('BMW.DoorClosureState', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$BMW_WINDOW_STATE_UNKNOWN, 'Name' => $this->Translate('unknown'), 'Farbe' => -1],
            ['Wert' => self::$BMW_WINDOW_STATE_OPEN, 'Name' => $this->Translate('open'), 'Farbe' => -1],
            ['Wert' => self::$BMW_WINDOW_STATE_INTERMEDIATE, 'Name' => $this->Translate('intermediate'), 'Farbe' => -1],
            ['Wert' => self::$BMW_WINDOW_STATE_CLOSED, 'Name' => $this->Translate('closed'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('BMW.WindowState', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$BMW_TRUNK_STATE_UNKNOWN, 'Name' => $this->Translate('unknown'), 'Farbe' => -1],
            ['Wert' => self::$BMW_TRUNK_STATE_OPEN, 'Name' => $this->Translate('open'), 'Farbe' => -1],
            ['Wert' => self::$BMW_TRUNK_STATE_CLOSED, 'Name' => $this->Translate('closed'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('BMW.TrunkState', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$BMW_HOOD_STATE_UNKNOWN, 'Name' => $this->Translate('unknown'), 'Farbe' => -1],
            ['Wert' => self::$BMW_HOOD_STATE_OPEN, 'Name' => $this->Translate('open'), 'Farbe' => -1],
            ['Wert' => self::$BMW_HOOD_STATE_INTERMEDIATE, 'Name' => $this->Translate('intermediate'), 'Farbe' => -1],
            ['Wert' => self::$BMW_HOOD_STATE_CLOSED, 'Name' => $this->Translate('closed'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('BMW.HoodState', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$BMW_ROOF_STATE_UNKNOWN, 'Name' => $this->Translate('unknown'), 'Farbe' => -1],
            ['Wert' => self::$BMW_ROOF_STATE_OPEN, 'Name' => $this->Translate('open'), 'Farbe' => -1],
            ['Wert' => self::$BMW_ROOF_STATE_OPEN_TILT, 'Name' => $this->Translate('tilt'), 'Farbe' => -1],
            ['Wert' => self::$BMW_ROOF_STATE_INTERMEDIATE, 'Name' => $this->Translate('intermediate'), 'Farbe' => -1],
            ['Wert' => self::$BMW_ROOF_STATE_CLOSED, 'Name' => $this->Translate('closed'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('BMW.RoofState', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $this->CreateVarProfile('BMW.Mileage', VARIABLETYPE_INTEGER, ' km', 0, 0, 0, 0, 'Distance', '', $reInstall);
        $this->CreateVarProfile('BMW.Heading', VARIABLETYPE_INTEGER, ' °', 0, 360, 0, 0, 'WindDirection', '', $reInstall);

        $this->CreateVarProfile('BMW.TankCapacity', VARIABLETYPE_FLOAT, ' Liter', 0, 0, 0, 0, 'Gauge', '', $reInstall);
        $this->CreateVarProfile('BMW.TankLevel', VARIABLETYPE_FLOAT, ' %', 0, 0, 0, 0, '', '', $reInstall);
        $this->CreateVarProfile('BMW.RemainingRange', VARIABLETYPE_FLOAT, ' km', 0, 0, 0, 0, 'Gauge', '', $reInstall);
        $this->CreateVarProfile('BMW.ChargingLevel', VARIABLETYPE_FLOAT, ' %', 0, 0, 0, 0, '', '', $reInstall);
        $this->CreateVarProfile('BMW.StateofCharge', VARIABLETYPE_FLOAT, ' kWh', 0, 0, 0, 1, '', '', $reInstall);
        $this->CreateVarProfile('BMW.BatteryCapacity', VARIABLETYPE_FLOAT, ' kWh', 0, 0, 0, 1, '', '', $reInstall);
        $this->CreateVarProfile('BMW.Location', VARIABLETYPE_FLOAT, ' °', 0, 0, 0, 5, 'Car', '', $reInstall);
        $this->CreateVarProfile('BMW.TirePressure', VARIABLETYPE_FLOAT, ' bar', 0, 0, 0, 1, '', '', $reInstall);
    }

    private function DriveTypeMapping()
    {
        return [
            self::$BMW_DRIVE_TYPE_ELECTRIC => [
                'caption' => 'electric',
            ],
            self::$BMW_DRIVE_TYPE_HYBRID => [
                'caption' => 'hybrid',
            ],
            self::$BMW_DRIVE_TYPE_COMBUSTION => [
                'caption' => 'combustion',
            ],
        ];
    }

    private function DriveTypeAsOptions()
    {
        $maps = $this->DriveTypeMapping();
        $opts = [];
        foreach ($maps as $u => $e) {
            $opts[] = [
                'caption' => $e['caption'],
                'value'   => $u,
            ];
        }
        return $opts;
    }

    private function DriveType2String($driveType)
    {
        $maps = $this->DriveTypeMapping();
        if (isset($maps[$driveType])) {
            $ret = $this->Translate($maps[$driveType]['caption']);
        } else {
            $ret = $this->Translate('Unknown drive type') . ' ' . $driveType;
        }
        return $ret;
    }

    private function BrandMapping()
    {
        return [
            self::$BMW_BRAND_BMW => [
                'caption' => 'BMW',
                'code'    => 'bmw',
            ],
            self::$BMW_BRAND_MINI => [
                'caption' => 'Mini',
                'code'    => 'mini',
            ],
        ];
    }

    private function BrandAsOptions()
    {
        $maps = $this->BrandMapping();
        $opts = [];
        foreach ($maps as $u => $e) {
            $opts[] = [
                'caption' => $e['caption'],
                'value'   => $u,
            ];
        }
        return $opts;
    }

    private function Brand2String($brand)
    {
        $maps = $this->BrandMapping();
        if (isset($maps[$brand])) {
            $ret = $this->Translate($maps[$brand]['caption']);
        } else {
            $ret = $this->Translate('Unknown brand') . ' ' . $brand;
        }
        return $ret;
    }

    private function Brand2Code($brand)
    {
        $maps = $this->BrandMapping();
        if (isset($maps[$brand])) {
            $ret = $maps[$brand]['code'];
        } else {
            $ret = 'bmw';
        }
        return $ret;
    }

    private function CountryMapping()
    {
        return [
            self::$BMW_COUNTRY_GERMANY => [
                'caption' => 'Germany',
                'region'  => 'RestOfWorld',
                'lang'    => 'de-DE',
            ],
            self::$BMW_COUNTRY_SWITZERLAND => [
                'caption' => 'Switzerland',
                'region'  => 'RestOfWorld',
                'lang'    => 'de-CH',
            ],
            self::$BMW_COUNTRY_AUSTRIA => [
                'caption' => 'Austria',
                'region'  => 'RestOfWorld',
                'lang'    => 'de-AT',
            ],
            self::$BMW_COUNTRY_LUXEMBOURG => [
                'caption' => 'Luxembourg',
                'region'  => 'RestOfWorld',
                'lang'    => 'de-LU',
            ],
            self::$BMW_COUNTRY_NETHERLANDS => [
                'caption' => 'Netherlands',
                'region'  => 'RestOfWorld',
                'lang'    => 'nl-NL',
            ],
            self::$BMW_COUNTRY_FRANCE => [
                'caption' => 'France',
                'region'  => 'RestOfWorld',
                'lang'    => 'fr-FR',
            ],
            self::$BMW_COUNTRY_EUROPE => [
                'caption' => 'Europe',
                'region'  => 'RestOfWorld',
                'lang'    => 'en-GB',
            ],
            self::$BMW_COUNTRY_USA => [
                'caption' => 'USA',
                'region'  => 'NorthAmerica',
                'lang'    => 'en-US',
            ],
            self::$BMW_COUNTRY_OTHER => [
                'caption' => 'Rest of the World',
                'region'  => 'RestOfWorld',
                'lang'    => 'en-US',
            ]
        ];
    }

    private function CountryAsOptions()
    {
        $maps = $this->CountryMapping();
        $opts = [];
        foreach ($maps as $u => $e) {
            $opts[] = [
                'caption' => $e['caption'],
                'value'   => $u,
            ];
        }
        return $opts;
    }

    private function Country2String($country)
    {
        $maps = $this->CountryMapping();
        if (isset($maps[$country])) {
            $ret = $this->Translate($maps[$country]['caption']);
        } else {
            $ret = $this->Translate('Unknown country') . ' ' . $country;
        }
        return $ret;
    }

    private function Country2Region($country)
    {
        $maps = $this->CountryMapping();
        if (isset($maps[$country])) {
            $ret = $maps[$country]['region'];
        } else {
            $ret = 'RestOfWorld';
        }
        return $ret;
    }

    private function Country2Lang($country)
    {
        $maps = $this->CountryMapping();
        if (isset($maps[$country])) {
            $ret = $maps[$country]['lang'];
        } else {
            $ret = 'en';
        }
        return $ret;
    }
}
