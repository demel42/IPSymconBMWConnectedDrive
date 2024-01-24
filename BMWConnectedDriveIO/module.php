<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';
require_once __DIR__ . '/../libs/images.php';

class BMWConnectedDriveIO extends IPSModule
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

    private static $x_user_agent_fmt = 'android(TQ2A.230405.003.B2);%s;3.9.0(27760);%s';
    private static $user_agent = 'Dart/3.0 (dart:io)';

    private static $oauth_config_endpoint = '/eadrax-ucs/v1/presentation/oauth/config';
    private static $oauth_authenticate_endpoint = '/gcdm/oauth/authenticate';
    private static $oauth_token_endpoint = '/gcdm/oauth/token';

    // private static $vehicles_endpoint = '/eadrax-vcs/v5/vehicle-list';
    private static $vehicles_endpoint = '/eadrax-vcs/v4/vehicles';
    private static $vehicle_state_endpoint = '/eadrax-vcs/v4/vehicles/state';

    private static $remoteService_endpoint = '/eadrax-vrccs/v3/presentation/remote-commands';
    private static $remoteServiceHistory_endpoint = '/eadrax-vrccs/v3/presentation/remote-history';

    private static $vehicle_img_endpoint = '/eadrax-ics/v5/presentation/vehicles/images';
    private static $vehicle_poi_endpoint = '/eadrax-dcs/v1/send-to-car/send-to-car';

    private static $charging_statistics_endpoint = '/eadrax-chs/v1/charging-statistics';
    private static $charging_sessions_endpoint = '/eadrax-chs/v1/charging-sessions';

    private static $charging_endpoint = '/eadrax-crccs/v1/vehicles';

    private static $semaphoreTM = 5 * 1000;
    private static $activity_size = 100;

    private $SemaphoreID;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->CommonContruct(__DIR__);
        $this->SemaphoreID = __CLASS__ . '_' . $InstanceID;
    }

    public function __destruct()
    {
        $this->CommonDestruct();
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('user', '');
        $this->RegisterPropertyString('password', '');
        $this->RegisterPropertyInteger('country', self::$BMW_COUNTRY_GERMANY);
        $this->RegisterPropertyInteger('brand', self::$BMW_BRAND_BMW);

        $this->RegisterPropertyBoolean('collectApiCallStats', true);

        $this->RegisterAttributeString('ApiSettings', '');
        $this->RegisterAttributeString('ApiRefreshToken', '');

        $this->RegisterAttributeString('UpdateInfo', json_encode([]));
        $this->RegisterAttributeString('ModuleStats', json_encode([]));

        $this->InstallVarProfiles(false);

        $this->SetBuffer('AccessToken', '');
        $this->SetBuffer('LastApiCall', 0);
        $this->SetBuffer('Cookies', '');
        $this->SetBuffer('Quota', '');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    private function CheckModuleConfiguration()
    {
        $r = [];

        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');
        if ($user == '' || $password == '') {
            $this->SendDebug(__FUNCTION__, '"user" and/or "password" is empty', 0);
            $r[] = $this->Translate('User and password of the BMW-account are required');
        }

        return $r;
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->MaintainReferences();

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

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainStatus(IS_INACTIVE);
            return;
        }

        $this->MaintainStatus(IS_ACTIVE);

        if (IPS_GetKernelRunlevel() == KR_READY) {
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

        if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) {
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
                    'options' => $this->CountryAsOptions(),
                ],
                [
                    'type'    => 'Select',
                    'name'    => 'brand',
                    'caption' => 'Vehicle-brand',
                    'options' => $this->BrandAsOptions(),
                ],
            ],
            'caption' => 'Account data',
        ];

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'collectApiCallStats',
            'caption' => 'Collect data of API calls'
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
            $formActions[] = $this->GetModuleActivityFormAction();

            return $formActions;
        }

        $formActions[] = [
            'type'    => 'Button',
            'label'   => 'Test access',
            'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "TestAccess", "");',
        ];

        $items = [
            $this->GetInstallVarProfilesFormItem(),
            [
                'type'    => 'RowLayout',
                'items'   => [
                    [
                        'type'    => 'Button',
                        'caption' => 'Clear token',
                        'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "ClearToken", "");',
                    ],
                    [
                        'type'    => 'Button',
                        'caption' => 'Reset quota handling',
                        'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "ClearQuota", "");',
                    ],
                ],
            ],
        ];
        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $items[] = $this->GetApiCallStatsFormItem();
        }

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded'  => false,
            'items'     => $items,
        ];

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();
        $formActions[] = $this->GetModuleActivityFormAction();

        return $formActions;
    }

    private function GetRegion()
    {
        $country = $this->ReadPropertyInteger('country');
        $region = $this->Country2Region($country);
        return $region;
    }

    private function GetLang()
    {
        $country = $this->ReadPropertyInteger('country');
        $lang = $this->Country2Lang($country);
        return $lang;
    }

    private function GetBrand()
    {
        $brand = $this->ReadPropertyInteger('brand');
        $code = $this->Brand2Code($brand);
        return $code;
    }

    private function LocalRequestAction($ident, $value)
    {
        $r = true;
        switch ($ident) {
            case 'TestAccess':
                $this->TestAccess();
                break;
            case 'ClearToken':
                $this->ClearToken();
                break;
            case 'ClearQuota':
                $this->ClearQuota();
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
        $r = false;
        switch ($ident) {
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident "' . $ident . '"', 0);
        }
        if ($r) {
            $this->SetValue($ident, $value);
        }
    }

    private function TestAccess()
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            $this->PopupMessage($this->GetStatusText());
            return;
        }

        $txt = '';
        $access_token = $this->GetAccessToken();
        if ($access_token == false) {
            $txt .= $this->Translate('invalid account-data') . PHP_EOL;
            $txt .= PHP_EOL;
        } else {
            $txt = $this->Translate('valid account-data') . PHP_EOL;
            $r = $this->GetVehicles();
            if ($r == false) {
                $txt = $this->Translate('unable to retrieve vehicle list');
            } else {
                $vehicles = json_decode($r, true);
                $n_vehicles = count($vehicles);
                $txt .= $n_vehicles . ' ' . $this->Translate('registered vehicles found');
            }
        }
        $this->SendDebug(__FUNCTION__, 'txt=' . $txt, 0);
        $this->PopupMessage($txt);
    }

    private function ClearToken()
    {
        if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return false;
        }
        $this->clear_wait_time();
        $this->WriteAttributeString('ApiSettings', '');
        $this->WriteAttributeString('ApiRefreshToken', '');
        $this->SetBuffer('AccessToken', '');
        IPS_SemaphoreLeave($this->SemaphoreID);
    }

    private function ClearQuota()
    {
        if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return false;
        }
        $this->clear_wait_time();
        IPS_SemaphoreLeave($this->SemaphoreID);
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
        $result = '';
        $jdata = json_decode($data, true);
        if (isset($jdata['error'])) {
            $result = 'error ' . $jdata['error'] . ' ' . $jdata['description'];
        }
        return $result;
    }

    private function ProcessLogin()
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

        $pre = 'config';
        $this->SendDebug(__FUNCTION__, '*** ' . $pre, 0);

        $config_url = $baseurl . self::$oauth_config_endpoint;

        $callerMSG = 'endpoint "' . $this->extract_endpoint(self::$oauth_config_endpoint) . '"  => ';

        $session_id = $this->uuid_v4();
        $correlation_id = $this->uuid_v4();
        $config_header_values = [
            'accept'                    => 'application/json',
            'accept-language'           => $this->GetLang(),
            'user-agent'                => self::$user_agent,
            'x-user-agent'              => sprintf(self::$x_user_agent_fmt, $this->GetBrand(), self::$region_map[$region]),
            'ocp-apim-subscription-key' => base64_decode(self::$ocp_apim_key[$region]),
            'bmw-session-id'            => $session_id,
            'x-identity-provider'       => 'gcdm',
            'x-correlation-id'          => $correlation_id,
            'bmw-correlation-id'        => $correlation_id,
        ];
        $header = [];
        foreach ($config_header_values as $key => $val) {
            $header[] = $key . ': ' . $val;
        }

        $this->SendDebug(__FUNCTION__, $pre . ' http-get, url=' . $config_url, 0);
        $this->SendDebug(__FUNCTION__, $pre . ' ... header=' . print_r($header, true), 0);

        $time_start = microtime(true);

        curl_setopt($ch, CURLOPT_URL, $config_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $curl_info = curl_getinfo($ch);
        $httpcode = $curl_info['http_code'];
        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, $pre . '  => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);
        $this->SendDebug(__FUNCTION__, $pre . '  => curl_info=' . print_r($curl_info, true), 0);

        $statuscode = 0;
        $err = '';
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        }
        if ($statuscode == 0) {
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->SendDebug(__FUNCTION__, $pre . '  => head=' . $head, 0);
            $this->SendDebug(__FUNCTION__, $pre . '  => body=' . $body, 0);
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
            curl_close($ch);
            $this->SendDebug(__FUNCTION__, $pre . '  => statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->SendDebug(__FUNCTION__, $pre . '  => response=' . $response, 0);
            $this->MaintainStatus($statuscode);
            $this->AddModuleActivity($callerMSG . 'failed (' . $this->GetStatusText() . ')', self::$activity_size);
            return false;
        }

        $this->SendDebug(__FUNCTION__, $pre . '  => oauth_settings=' . print_r($oauth_settings, true), 0);
        $this->WriteAttributeString('ApiSettings', $body);

        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $this->ApiCallCollect($config_url, $err, $statuscode);
        }

        $this->AddModuleActivity($callerMSG . 'succeded (login::' . $pre . ')', self::$activity_size);

        $pre = 'auth#1';
        $this->SendDebug(__FUNCTION__, '*** ' . $pre, 0);

        # Setting up PKCS data
        $verifier_bytes = random_bytes(64);
        $code_verifier = $this->urlsafe_b64encode($verifier_bytes);

        $challenge_bytes = hash('sha256', $code_verifier, true);
        $code_challenge = $this->urlsafe_b64encode($challenge_bytes);

        $state_bytes = random_bytes(22);
        $state = $this->urlsafe_b64encode($state_bytes);
        $nonce_bytes = random_bytes(22);
        $nonce = $this->urlsafe_b64encode($nonce_bytes);

        $this->SendDebug(__FUNCTION__, $pre . ' code_verifier=' . $code_verifier . ', code_challenge=' . $code_challenge . ', state=' . $state . ', nonce=' . $nonce, 0);

        $gcdm_base_url = $oauth_settings['gcdmBaseUrl'];
        $auth_url = $gcdm_base_url . self::$oauth_authenticate_endpoint;

        $callerMSG = 'endpoint "' . $this->extract_endpoint(self::$oauth_authenticate_endpoint) . '" => ';

        $auth_header_values = [
            'accept'                    => 'application/json',
            'accept-language'           => $this->GetLang(),
            'user-agent'                => self::$user_agent,
            'x-user-agent'              => sprintf(self::$x_user_agent_fmt, $this->GetBrand(), self::$region_map[$region]),
            'ocp-apim-subscription-key' => base64_decode(self::$ocp_apim_key[$region]),
            'bmw-session-id'            => $session_id,
            'x-identity-provider'       => 'gcdm',
            'x-correlation-id'          => $correlation_id,
            'bmw-correlation-id'        => $correlation_id,
        ];
        $header = [];
        foreach ($auth_header_values as $key => $val) {
            $header[] = $key . ': ' . $val;
        }

        $oauth_base_values = [
            'client_id'             => $oauth_settings['clientId'],
            'response_type'         => 'code',
            'redirect_uri'          => $oauth_settings['returnUrl'],
            'state'                 => $state,
            'nonce'                 => $nonce,
            'scope'                 => implode(' ', $oauth_settings['scopes']),
            'code_challenge'        => $code_challenge,
            'code_challenge_method' => 'S256',
        ];

        $this->SendDebug(__FUNCTION__, $pre . ' oauth_base_values=' . print_r($oauth_base_values, true), 0);

        $postfields = $oauth_base_values;
        $postfields['grant_type'] = 'authorization_code';
        $postfields['username'] = $user;
        $postfields['password'] = $password;

        $this->SendDebug(__FUNCTION__, $pre . ' http-post, url=' . $auth_url, 0);
        $this->SendDebug(__FUNCTION__, $pre . ' ... header=' . print_r($header, true), 0);
        $this->SendDebug(__FUNCTION__, $pre . ' ... postfields=' . print_r($postfields, true), 0);

        $time_start = microtime(true);

        curl_setopt($ch, CURLOPT_URL, $auth_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));

        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $curl_info = curl_getinfo($ch);
        $httpcode = $curl_info['http_code'];
        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, $pre . '  => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);
        $this->SendDebug(__FUNCTION__, $pre . '  => curl_info=' . print_r($curl_info, true), 0);

        $statuscode = 0;
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        }
        if ($statuscode == 0) {
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->SendDebug(__FUNCTION__, $pre . '  => head=' . $head, 0);
            $this->SendDebug(__FUNCTION__, $pre . '  => body=' . $body, 0);
        }
        if ($statuscode == 0) {
            if (preg_match_all('|Set-Cookie: (.*);|Ui', $head, $matches)) {
                $cookies = implode('; ', $matches[1]);
            } else {
                $cookies = '';
            }
            $this->SetBuffer('Cookies', $cookies);
            $this->SendDebug(__FUNCTION__, $pre . ' save cookies=' . $cookies, 0);
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
            $this->SendDebug(__FUNCTION__, $pre . '  => jbody=' . print_r($jbody, true), 0);
            if (isset($jbody['redirect_to']) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'missing element "redirect_to" in "' . $body . '"';
            }
        }
        if ($statuscode == 0) {
            $redirect_uri = substr($jbody['redirect_to'], strlen('redirect_uri='));
            $this->SendDebug(__FUNCTION__, $pre . '  => redirect_uri=' . $redirect_uri, 0);
            $redirect_parts = parse_url($redirect_uri);
            $this->SendDebug(__FUNCTION__, $pre . '  => redirect_parts=' . print_r($redirect_parts, true), 0);
            if (isset($redirect_parts['query']) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'missing element "query" in "' . $redirect_uri . '"';
            }
        }
        if ($statuscode == 0) {
            parse_str($redirect_parts['query'], $redirect_opts);
            $this->SendDebug(__FUNCTION__, $pre . '  => redirect_opts=' . print_r($redirect_opts, true), 0);
            if (isset($redirect_opts['authorization']) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'missing element "authorization" in "' . $redirect_parts['query'] . '"';
            }
        }
        if ($statuscode) {
            curl_close($ch);
            $this->SendDebug(__FUNCTION__, $pre . '  => statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
            $this->AddModuleActivity($callerMSG . 'failed (' . $this->GetStatusText() . ')', self::$activity_size);
            return false;
        }
        $this->SendDebug(__FUNCTION__, $pre . ' authorization="' . $redirect_opts['authorization'] . '"', 0);

        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $this->ApiCallCollect($auth_url, $err, $statuscode);
        }

        $this->AddModuleActivity($callerMSG . 'succeded (login::' . $pre . ')', self::$activity_size);

        $pre = 'auth#2';
        $this->SendDebug(__FUNCTION__, '*** ' . $pre, 0);

        $gcdm_base_url = $oauth_settings['gcdmBaseUrl'];
        $auth_url = $gcdm_base_url . self::$oauth_authenticate_endpoint;

        $callerMSG = 'endpoint "' . $this->extract_endpoint(self::$oauth_authenticate_endpoint) . '" => ';

        $params = [
            'interaction-id' => $this->uuid_v4(),
            'client-version' => sprintf(self::$x_user_agent_fmt, $this->GetBrand(), self::$region_map[$region]),
        ];
        $n = 0;
        foreach ($params as $param => $value) {
            $auth_url .= ($n++ ? '&' : '?') . $param . '=' . rawurlencode(strval($value));
        }

        $postfields = $oauth_base_values;
        $postfields['authorization'] = $redirect_opts['authorization'];

        $auth_header_values = [
            'accept'                    => 'application/json',
            'accept-language'           => $this->GetLang(),
            'user-agent'                => self::$user_agent,
            'x-user-agent'              => sprintf(self::$x_user_agent_fmt, $this->GetBrand(), self::$region_map[$region]),
            'ocp-apim-subscription-key' => base64_decode(self::$ocp_apim_key[$region]),
            'bmw-session-id'            => $session_id,
            'x-identity-provider'       => 'gcdm',
            'x-correlation-id'          => $correlation_id,
            'bmw-correlation-id'        => $correlation_id,
        ];
        $header = [];
        foreach ($auth_header_values as $key => $val) {
            $header[] = $key . ': ' . $val;
        }

        $this->SendDebug(__FUNCTION__, $pre . ' http-post, url=' . $auth_url, 0);
        $this->SendDebug(__FUNCTION__, $pre . ' ... header=' . print_r($header, true), 0);
        $this->SendDebug(__FUNCTION__, $pre . ' ... postfields=' . print_r($postfields, true), 0);

        $time_start = microtime(true);

        curl_setopt($ch, CURLOPT_URL, $auth_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));

        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $curl_info = curl_getinfo($ch);
        $httpcode = $curl_info['http_code'];
        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, $pre . '  => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);
        $this->SendDebug(__FUNCTION__, $pre . '  => curl_info=' . print_r($curl_info, true), 0);

        $statuscode = 0;
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        }
        if ($statuscode == 0) {
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->SendDebug(__FUNCTION__, $pre . '  => head=' . $head, 0);
            $this->SendDebug(__FUNCTION__, $pre . '  => body=' . $body, 0);
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
            $err = $this->check_response4error($body);
            if ($err != false) {
                $statuscode = self::$IS_APIERROR;
            }
        }
        if ($statuscode == 0) {
            if (preg_match_all('|location: (.*)|', $head, $matches)) {
                if (isset($matches[1][0]) == false) {
                    $statuscode = self::$IS_INVALIDDATA;
                    $err = 'missing element "location" in "' . $head . '"';
                }
            }
        }
        if ($statuscode == 0) {
            $location = $matches[1][0];
            $this->SendDebug(__FUNCTION__, $pre . '  => location=' . $location, 0);
            $location_parts = parse_url($location);
            $this->SendDebug(__FUNCTION__, $pre . '  => location_parts=' . print_r($location_parts, true), 0);

            if (isset($location_parts['query']) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'missing element "query" in "' . $location . '"';
            }
        }
        if ($statuscode == 0) {
            parse_str($location_parts['query'], $location_opts);
            $this->SendDebug(__FUNCTION__, $pre . '  => location_opts=' . print_r($location_opts, true), 0);
            if (isset($location_opts['code']) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'missing element "code" in "' . $location_parts['query'] . '"';
            }
        }
        if ($statuscode) {
            curl_close($ch);
            $this->SendDebug(__FUNCTION__, $pre . '  => statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
            $this->AddModuleActivity($callerMSG . 'failed (' . $this->GetStatusText() . ')', self::$activity_size);
            return false;
        }
        $this->SendDebug(__FUNCTION__, $pre . ' code="' . $location_opts['code'] . '"', 0);

        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $this->ApiCallCollect($auth_url, $err, $statuscode);
        }

        $this->AddModuleActivity($callerMSG . 'succeded (login::' . $pre . ')', self::$activity_size);

        $pre = 'token';
        $this->SendDebug(__FUNCTION__, '*** ' . $pre, 0);

        $token_url = $oauth_settings['tokenEndpoint'];

        $callerMSG = 'endpoint "' . $this->extract_endpoint($token_url) . '" => ';

        $oauth_authorization = base64_encode($oauth_settings['clientId'] . ':' . $oauth_settings['clientSecret']);
        $token_header_values = [
            'accept'                    => 'application/json',
            'accept-language'           => $this->GetLang(),
            'user-agent'                => self::$user_agent,
            'x-user-agent'              => sprintf(self::$x_user_agent_fmt, $this->GetBrand(), self::$region_map[$region]),
            'authorization'             => 'Basic ' . $oauth_authorization,
            'ocp-apim-subscription-key' => base64_decode(self::$ocp_apim_key[$region]),
            'bmw-session-id'            => $session_id,
            'x-identity-provider'       => 'gcdm',
            'x-correlation-id'          => $correlation_id,
            'bmw-correlation-id'        => $correlation_id,
        ];
        $header = [];
        foreach ($token_header_values as $key => $val) {
            $header[] = $key . ': ' . $val;
        }

        $postfields = [
            'code'          => $location_opts['code'],
            'code_verifier' => $code_verifier,
            'redirect_uri'  => $oauth_settings['returnUrl'],
            'grant_type'    => 'authorization_code',
        ];

        $this->SendDebug(__FUNCTION__, $pre . ' http-post, url=' . $token_url, 0);
        $this->SendDebug(__FUNCTION__, $pre . ' ... header=' . print_r($header, true), 0);
        $this->SendDebug(__FUNCTION__, $pre . ' ... postfields=' . print_r($postfields, true), 0);

        $time_start = microtime(true);

        curl_setopt($ch, CURLOPT_URL, $token_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));

        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $curl_info = curl_getinfo($ch);
        $httpcode = $curl_info['http_code'];
        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, $pre . '  => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);
        $this->SendDebug(__FUNCTION__, $pre . '  => curl_info=' . print_r($curl_info, true), 0);

        $statuscode = 0;
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        }
        if ($statuscode == 0) {
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->SendDebug(__FUNCTION__, $pre . '  => head=' . $head, 0);
            $this->SendDebug(__FUNCTION__, $pre . '  => body=' . $body, 0);
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
            $this->SendDebug(__FUNCTION__, $pre . '  => jbody=' . print_r($jbody, true), 0);
            foreach (['access_token', 'refresh_token', 'expires_in'] as $key) {
                if (isset($jbody[$key]) == false) {
                    $statuscode = self::$IS_INVALIDDATA;
                    $err = 'missing element "' . $key . '" in "' . $body . '"' . PHP_EOL;
                    break;
                }
            }
        }
        if ($statuscode) {
            curl_close($ch);
            $this->SendDebug(__FUNCTION__, $pre . '  => statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
            $this->AddModuleActivity($callerMSG . 'failed (' . $this->GetStatusText() . ')', self::$activity_size);
            return false;
        }

        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $this->ApiCallCollect($token_url, $err, $statuscode);
        }

        $access_token = $jbody['access_token'];
        $refresh_token = $jbody['refresh_token'];
        $expiration = time() + $jbody['expires_in'] - 60; // Ablauf um 60s früher
        $this->SendDebug(__FUNCTION__, 'new access_token=' . $access_token . ', refresh_token=' . $refresh_token . ', valid until ' . date('d.m.y H:i:s', $expiration), 0);
        $jtoken = [
            'access_token' => $access_token,
            'expiration'   => $expiration,
        ];
        $this->WriteAttributeString('ApiRefreshToken', $refresh_token);
        $this->SetBuffer('AccessToken', json_encode($jtoken));

        $this->MaintainStatus(IS_ACTIVE);
        $this->AddModuleActivity($callerMSG . 'succeded (login::' . $pre . ')', self::$activity_size);

        curl_close($ch);
        return $access_token;
    }

    private function RefreshToken()
    {
        $refresh_token = $this->ReadAttributeString('ApiRefreshToken');
        if ($refresh_token == false) {
            $access_token = $this->ProcessLogin();
            if ($access_token == false) {
                $this->SendDebug(__FUNCTION__, 'login failed', 0);
            }
            return $access_token;
        }
        $this->SendDebug(__FUNCTION__, 'refresh_token=' . $refresh_token, 0);

        $oauth_settings = json_decode($this->ReadAttributeString('ApiSettings'), true);
        if ($oauth_settings == false) {
            $access_token = $this->ProcessLogin();
            if ($access_token == false) {
                $this->SendDebug(__FUNCTION__, 'login failed', 0);
            }
            return $access_token;
        }
        $this->SendDebug(__FUNCTION__, 'oauth_settings=' . print_r($oauth_settings, true), 0);

        $callerID = isset($_IPS['CallerID']) ? $_IPS['CallerID'] : $this->InstanceID;

        $cookies = $this->GetBuffer('Cookies');
        $this->SendDebug(__FUNCTION__, 'use cookies=' . $cookies, 0);

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
        curl_setopt($ch, CURLOPT_COOKIE, $cookies);

        $oauth_settings = json_decode($this->ReadAttributeString('ApiSettings'), true);

        $pre = 'refresh';
        $this->SendDebug(__FUNCTION__, '*** ' . $pre, 0);

        $token_url = $oauth_settings['tokenEndpoint'];

        $callerMSG = 'endpoint "' . $this->extract_endpoint($token_url) . '" => ';

        $session_id = $this->uuid_v4();
        $correlation_id = $this->uuid_v4();
        $oauth_authorization = base64_encode($oauth_settings['clientId'] . ':' . $oauth_settings['clientSecret']);
        $token_header_values = [
            'accept'              => 'application/json',
            'accept-language'     => $this->GetLang(),
            'user-agent'          => self::$user_agent,
            'x-user-agent'        => sprintf(self::$x_user_agent_fmt, $this->GetBrand(), self::$region_map[$region]),
            'authorization'       => 'Basic ' . $oauth_authorization,
            'bmw-session-id'      => $session_id,
            'x-identity-provider' => 'gcdm',
            'x-correlation-id'    => $correlation_id,
            'bmw-correlation-id'  => $correlation_id,
        ];
        $header = [];
        foreach ($token_header_values as $key => $val) {
            $header[] = $key . ': ' . $val;
        }

        $postfields = [
            'scope'         => implode(' ', $oauth_settings['scopes']),
            'redirect_uri'  => $oauth_settings['returnUrl'],
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refresh_token,
        ];

        $this->SendDebug(__FUNCTION__, $pre . ' http-post, url=' . $token_url, 0);
        $this->SendDebug(__FUNCTION__, $pre . ' ... header=' . print_r($header, true), 0);
        $this->SendDebug(__FUNCTION__, $pre . ' ... postfields=' . print_r($postfields, true), 0);

        $time_start = microtime(true);

        curl_setopt($ch, CURLOPT_URL, $token_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));

        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $curl_info = curl_getinfo($ch);
        $httpcode = $curl_info['http_code'];
        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, $pre . '  => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);
        $this->SendDebug(__FUNCTION__, $pre . '  => curl_info=' . print_r($curl_info, true), 0);

        $statuscode = 0;
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        }
        if ($statuscode == 0) {
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->SendDebug(__FUNCTION__, $pre . '  => head=' . $head, 0);
            $this->SendDebug(__FUNCTION__, $pre . '  => body=' . $body, 0);
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
            $this->SendDebug(__FUNCTION__, $pre . '  => jbody=' . print_r($jbody, true), 0);
            foreach (['access_token', 'refresh_token', 'expires_in'] as $key) {
                if (isset($jbody[$key]) == false) {
                    $statuscode = self::$IS_INVALIDDATA;
                    $err = 'missing element "' . $key . '" in "' . $body . '"' . PHP_EOL;
                    break;
                }
            }
        }
        if ($statuscode) {
            curl_close($ch);
            $this->SendDebug(__FUNCTION__, $pre . '  => statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->WriteAttributeString('ApiRefreshToken', '');
            $this->SetBuffer('AccessToken', '');
            $this->MaintainStatus($statuscode);
            $this->AddModuleActivity($callerMSG . 'failed (' . $this->GetStatusText() . ')', self::$activity_size);
            return false;
        }

        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $this->ApiCallCollect($token_url, $err, $statuscode);
        }

        $access_token = $jbody['access_token'];
        $refresh_token = $jbody['refresh_token'];
        $expiration = time() + $jbody['expires_in'] - 60; // Ablauf um 60s früher
        $this->SendDebug(__FUNCTION__, 'new access_token=' . $access_token . ', refresh_token=' . $refresh_token . ', valid until ' . date('d.m.y H:i:s', $expiration), 0);
        $jtoken = [
            'access_token' => $access_token,
            'expiration'   => $expiration,
        ];
        $this->WriteAttributeString('ApiRefreshToken', $refresh_token);
        $this->SetBuffer('AccessToken', json_encode($jtoken));

        curl_close($ch);

        $this->MaintainStatus(IS_ACTIVE);
        $this->AddModuleActivity($callerMSG . 'succeded (refresh token)', self::$activity_size);
        return $access_token;
    }

    private function GetAccessToken()
    {
        if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return false;
        }
        $data = $this->GetBuffer('AccessToken');
        if ($data != false) {
            $jtoken = json_decode($data, true);
            $access_token = isset($jtoken['access_token']) ? $jtoken['access_token'] : '';
            $expiration = isset($jtoken['expiration']) ? $jtoken['expiration'] : 0;
            if ($expiration > time()) {
                $this->SendDebug(__FUNCTION__, 'old access_token=' . $access_token . ', valid until ' . date('d.m.y H:i:s', $expiration), 0);
                IPS_SemaphoreLeave($this->SemaphoreID);
                return $access_token;
            }
            $this->SendDebug(__FUNCTION__, 'access_token expired', 0);
        } else {
            $this->SendDebug(__FUNCTION__, 'no/empty buffer "AccessToken"', 0);
        }

        $access_token = $this->RefreshToken();
        IPS_SemaphoreLeave($this->SemaphoreID);
        return $access_token;
    }

    private function uuid_v4()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',

        // 32 bits for "time_low"
        mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),

        // 16 bits for "time_mid"
        mt_rand(0, 0xffff),

        // 16 bits for "time_hi_and_version",
        // four most significant bits holds version number 4
        mt_rand(0, 0x0fff) | 0x4000,

        // 16 bits, 8 bits for "clk_seq_hi_res",
        // 8 bits for "clk_seq_low",
        // two most significant bits holds zero and one for variant DCE1.1
        mt_rand(0, 0x3fff) | 0x8000,

        // 48 bits for "node"
        mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    private function get_wait_tstamp()
    {
        $quota = $this->GetBuffer('Quota');
        $jquota = @json_decode($quota, true);
        $tstamp = isset($jquota['tstamp']) ? $jquota['tstamp'] : 0;
        $retry = isset($jquota['retry']) ? $jquota['retry'] : 0;
        $wait_time = isset($jquota['wait_time']) ? $jquota['wait_time'] : 0;

        if ($wait_time > 0) {
            $next_try = $tstamp + $wait_time;
        } else {
            $next_try = 0;
        }
        return $next_try;
    }

    private function set_wait_time()
    {
        $quota = $this->GetBuffer('Quota');
        $jquota = @json_decode($quota, true);
        $tstamp = isset($jquota['tstamp']) ? $jquota['tstamp'] : 0;
        $retry = isset($jquota['retry']) ? $jquota['retry'] : 0;
        $wait_time = isset($jquota['wait_time']) ? $jquota['wait_time'] : 0;
        $old = ($tstamp ? date('d.m.Y H:i:s', $tstamp) : '-') . ', retry=' . $retry . ', wait_time=' . $wait_time . 's';

        $retry++;
        $wait_time = 5 * 60 * $retry;

        $tstamp = time();
        $jquota = [
            'tstamp'    => $tstamp,
            'retry'     => $retry,
            'wait_time' => $wait_time,
        ];
        $this->SetBuffer('Quota', json_encode($jquota));
        $new = ($tstamp ? date('d.m.Y H:i:s', $tstamp) : '-') . ', retry=' . $retry . ', wait_time=' . $wait_time . 's';
        $this->SendDebug(__FUNCTION__, 'old=' . $old . ', new=' . $new, 0);
        return $wait_time;
    }

    private function extract_wait_time($body)
    {
        $quota = $this->GetBuffer('Quota');
        $jquota = @json_decode($quota, true);
        $tstamp = isset($jquota['tstamp']) ? $jquota['tstamp'] : 0;
        $retry = isset($jquota['retry']) ? $jquota['retry'] : 0;
        $wait_time = isset($jquota['wait_time']) ? $jquota['wait_time'] : 0;
        $old = ($tstamp ? date('d.m.Y H:i:s', $tstamp) : '-') . ', retry=' . $retry . ', wait_time=' . $wait_time . 's';

        $wait_time = 0;
        $jbody = @json_decode($body, true);
        if ($jbody == false) {
            return $wait_time;
        }
        if (isset($jbody['message'])) {
            $message = $jbody['message'];
            $this->SendDebug(__FUNCTION__, 'message=' . $message, 0);
            if (preg_match('/quota/i', $message)) {
                if (preg_match('/quota .* in ([0-9]{2}):([0-9]{2}):([0-9]{2}).*$/i', $message, $r)) {
                    $wait_time = $r[1] * 3600 + $r[2] * 60 + $r[3];
                } else {
                    $retry++;
                    $wait_time = 5 * 60 * $retry;
                }
            }
        }
        $tstamp = time();
        $jquota = [
            'tstamp'    => $tstamp,
            'retry'     => $retry,
            'wait_time' => $wait_time,
        ];
        $this->SetBuffer('Quota', json_encode($jquota));
        $new = ($tstamp ? date('d.m.Y H:i:s', $tstamp) : '-') . ', retry=' . $retry . ', wait_time=' . $wait_time . 's';
        $this->SendDebug(__FUNCTION__, 'old=' . $old . ', new=' . $new, 0);
        return $wait_time;
    }

    private function clear_wait_time()
    {
        $quota = $this->GetBuffer('Quota');
        $jquota = @json_decode($quota, true);
        $tstamp = isset($jquota['tstamp']) ? $jquota['tstamp'] : 0;
        $retry = isset($jquota['retry']) ? $jquota['retry'] : 0;
        $wait_time = isset($jquota['wait_time']) ? $jquota['wait_time'] : 0;
        $old = ($tstamp ? date('d.m.Y H:i:s', $tstamp) : '-') . ', retry=' . $retry . ', wait_time=' . $wait_time . 's';

        if ($wait_time) {
            $this->SendDebug(__FUNCTION__, 'clear (old=' . $old . ')', 0);
        }

        $tstamp = time();
        $jquota = [
            'tstamp'    => $tstamp,
            'retry'     => 0,
            'wait_time' => 0,
        ];
        $this->SetBuffer('Quota', json_encode($jquota));
    }

    private function extract_endpoint($url)
    {
        $endpoint = preg_replace(['#^http[s]*://#', '#^[^/]+/#', '#^[/]*eadrax-[a-z]+/v[0-9]+/#', '#^/#', '#\?.*$#'], '', $url);
        return $endpoint;
    }

    private function CallAPI($endpoint, $postfields, $params, $header_add)
    {
        $callerID = isset($_IPS['CallerID']) ? $_IPS['CallerID'] : $this->InstanceID;
        $callerName = $callerID . '(' . IPS_GetName($callerID) . ')';
        $callerMSG = 'endpoint "' . $this->extract_endpoint($endpoint) . '" for ' . $callerName . ' => ';

        $next_try = $this->get_wait_tstamp();
        if ($next_try > time()) {
            $this->SendDebug(__FUNCTION__, 'quota exceeded, allowed again from=' . date('d.m.Y H:i:s', $next_try), 0);
            $this->AddModuleActivity($callerMSG . 'skipped (quota exceeded)', self::$activity_size);
            return false;
        }
        $this->clear_wait_time();

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
            $this->AddModuleActivity($callerMSG . 'failed (no access_token)', self::$activity_size);
            return false;
        }

        if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            $this->AddModuleActivity($callerMSG . 'failed (unable to lock)', self::$activity_size);
            return false;
        }

        $ts = intval($this->GetBuffer('LastApiCall'));
        $this->SendDebug(__FUNCTION__, 'last api call=' . date('d.m.Y H:i:s', $ts), 0);
        $ts += 5; // aus Sicherheitsgründen nur 1 call/5s
        if ($ts >= time()) {
            $w = $ts - time();
            $this->SendDebug(__FUNCTION__, 'calls too fast, wait ' . $w . 's', 0);
            while ($ts >= time()) {
                IPS_Sleep(250);
            }
        }
        $this->SetBuffer('LastApiCall', time());

        $correlation_id = $this->uuid_v4();
        $header_base = [
            'accept'                => 'application/json',
            'accept-language'       => $this->GetLang(),
            'user-agent'            => self::$user_agent,
            'x-user-agent'          => sprintf(self::$x_user_agent_fmt, $this->GetBrand(), self::$region_map[$region]),
            'authorization'         => 'Bearer ' . $access_token,
            'x-identity-provider'   => 'gcdm',
            'x-correlation-id'      => $correlation_id,
            'bmw-correlation-id'    => $correlation_id,
            'bmw-units-preferences' => 'd=KM;v=L;p=B;ec=KWH100KM;fc=L100KM;em=GKM;',
            '24-hour-format'        => (string) 'true',
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

        $cookies = $this->GetBuffer('Cookies');
        $this->SendDebug(__FUNCTION__, 'use cookies=' . $cookies, 0);

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
        curl_setopt($ch, CURLOPT_COOKIE, $cookies);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $curl_info = curl_getinfo($ch);
        $httpcode = $curl_info['http_code'];
        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);
        $this->SendDebug(__FUNCTION__, ' => curl_info=' . print_r($curl_info, true), 0);

        $statuscode = 0;
        $err = '';
        $wait_time = 0;
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        }
        if ($statuscode == 0) {
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->SendDebug(__FUNCTION__, ' => head=' . $head, 0);
            if ($body == '' || ctype_print($body)) {
                $this->SendDebug(__FUNCTION__, ' => body=' . $body, 0);
            } else {
                $this->SendDebug(__FUNCTION__, ' => body potentially contains binary data, size=' . strlen($body), 0);
            }
        }
        if ($statuscode == 0) {
            if ($httpcode == 401) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
            } elseif ($httpcode == 403) {
                $wait_time = $this->extract_wait_time($body);
                if ($wait_time > 0) {
                    $err = 'got http-code ' . $httpcode . ' (forbidden/quota)';
                } else {
                    $statuscode = self::$IS_UNAUTHORIZED;
                    $err = 'got http-code ' . $httpcode . ' (forbidden)';
                }
            } elseif ($httpcode == 429) {
                $wait_time = $this->set_wait_time();
                $err = 'got http-code ' . $httpcode . ' (too many requests)';
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode != 200 && $httpcode != 201) {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode . '(' . $this->HttpCode2Text($httpcode) . ')';
            }
        }
        if ($statuscode == 0) {
            $err = $this->check_response4error($body);
            if ($err != false) {
                $statuscode = self::$IS_APIERROR;
            }
        }
        if ($statuscode == self::$IS_UNAUTHORIZED) {
            $this->SetBuffer('AccessToken', '');
        }

        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $this->ApiCallCollect($url, $err, $statuscode);
        }

        IPS_SemaphoreLeave($this->SemaphoreID);

        if ($wait_time) {
            curl_close($ch);
            $this->MaintainStatus(IS_ACTIVE);
            $this->AddModuleActivity($callerMSG . 'failed (quota exceeded)', self::$activity_size);
            return false;
        }

        if ($statuscode) {
            curl_close($ch);
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->SendDebug(__FUNCTION__, ' => response=' . $response, 0);
            $this->MaintainStatus($statuscode);
            $this->AddModuleActivity($callerMSG . 'failed (' . $this->GetStatusText() . ')', self::$activity_size);
            return false;
        }

        $jbody = json_decode($body, true);
        if (isset($jbody['errors'])) {
            curl_close($ch);
            $this->SendDebug(__FUNCTION__, ' => error=' . $jbody['errors'], 0);
            $this->MaintainStatus(IS_ACTIVE);
            $this->AddModuleActivity($callerMSG . 'done with error (' . $jbody['errors'] . ')', self::$activity_size);
            return false;
        }

        curl_close($ch);
        $this->MaintainStatus(IS_ACTIVE);
        $this->AddModuleActivity($callerMSG . 'succeded', self::$activity_size);
        return $body;
    }

    protected function SendData($buf)
    {
        $data = ['DataID' => '{7D93F416-125A-4CAE-B707-0DB2A2361013}', 'Buffer' => $buf];
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);
        $this->SendDataToChildren(json_encode($data));
    }

    public function ForwardData($data)
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return false;
        }

        $jdata = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);

        $callerID = $jdata['CallerID'];
        $this->SendDebug(__FUNCTION__, 'caller=' . $callerID . '(' . IPS_GetName($callerID) . ')', 0);
        $_IPS['CallerID'] = $callerID;

        $ret = '';

        if (isset($jdata['Function'])) {
            switch ($jdata['Function']) {
                case 'GetVehicles':
                    $ret = $this->GetVehicles();
                    break;
                case 'GetVehicleData':
                    $ret = $this->GetVehicleData($jdata['vin']);
                    break;
                case 'GetCarPicture':
                    $ret = $this->GetCarPicture($jdata['vin'], $jdata['carView']);
                    break;
                case 'GetChargingSessions':
                    $ret = $this->GetChargingSessions($jdata['vin']);
                    break;
                case 'ExecuteRemoteService':
                    $ret = $this->ExecuteRemoteService($jdata['vin'], $jdata['service'], $jdata['action']);
                    break;
                case 'SendPOI':
                    $ret = $this->SendPOI($jdata['vin'], $jdata['poi']);
                    break;
                case 'GetRemoteServiceHistory':
                    $ret = $this->GetRemoteServiceHistory($jdata['vin']);
                    break;
                case 'GetRemoteServiceStatus':
                    $ret = $this->GetRemoteServiceStatus($jdata['eventId']);
                    break;
                case 'GetRemoteServicePosition':
                    $ret = $this->GetRemoteServicePosition($jdata['eventId']);
                    break;
                case 'SetChargingSettings':
                    $ret = $this->SetChargingSettings($jdata['vin'], $jdata['settings']);
                    break;
                default:
                    $this->SendDebug(__FUNCTION__, 'unknown function "' . $jdata['Function'] . '"', 0);
                    break;
            }
        } else {
            $this->SendDebug(__FUNCTION__, 'unknown message-structure', 0);
        }

        $this->SendDebug(__FUNCTION__, 'ret=' . print_r($ret, true), 0);
        return $ret;
    }

    private function GetVehicles()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $endpoint = self::$vehicles_endpoint;

        $params = [
            'apptimezone' => strval(round(intval(date('Z')) / 60)), // TZ-Differenz in Minuten
            'appDateTime' => date('U') . date('v'), // Millisekunden
        ];
        $params = [];

        $data = $this->CallAPI($endpoint, '', $params, '');
        return $data;
    }

    private function GetVehicleData(string $vin)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $endpoint = self::$vehicle_state_endpoint;

        $params = [
            'apptimezone' => strval(round(intval(date('Z')) / 60)), // TZ-Differenz in Minuten
            'appDateTime' => date('U') . date('v'), // Millisekunden
        ];

        $header_add = [
            'bmw-vin' => $vin,
        ];

        $data = $this->CallAPI($endpoint, '', $params, $header_add);
        return $data;
    }

    private function GetCarPicture(string $vin, string $carView)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $endpoint = self::$vehicle_img_endpoint;

        $params = [
            'carView' => $carView,
            'toCrop'  => (string) 'true',
        ];

        $header_add = [
            'accept'               => 'image/png',
            'bmw-vin'              => $vin,
            'bmw-app-vehicle-type' => 'connected',
        ];

        $data = $this->CallAPI($endpoint, '', $params, $header_add);
        return $data !== false ? base64_encode($data) : false;
    }

    private function GetChargingSessions($vin)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $endpoint = self::$charging_sessions_endpoint;

        $params = [
            'vin'                 => $vin,
            'maxResults'          => 40,
            'include_date_picker' => 'true'
        ];

        $data = $this->CallAPI($endpoint, '', $params, '');
        return $data;
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

    private function ExecuteRemoteService(string $vin, string $service, string $action)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return false;
        }

        $this->SendDebug(__FUNCTION__, 'service=' . $service . ', action=' . $action, 0);

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
        return $data;
    }

    private function SendPOI(string $vin, string $poi)
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

        $endpoint = self::$vehicle_poi_endpoint;

        $postfields = [
            'vin'      => $vin,
            'location' => [
                'type'        => 'SHARED_DESTINATION_FROM_EXTERNAL_APP',
                'name'        => $this->GetArrayElem($jpoi, 'name', ''),
                'coordinates' => [
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
        return $data;
    }

    private function GetRemoteServiceHistory(string $vin)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $endpoint = self::$remoteServiceHistory_endpoint . '/' . $vin;

        $data = $this->CallAPI($endpoint, '', '', '');
        return $data;
    }

    private function GetRemoteServiceStatus(string $eventId)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $endpoint = self::$remoteService_endpoint . '/eventStatus';

        $params = [
            'eventId' => $eventId,
        ];

        $data = $this->CallAPI($endpoint, [], $params, '');
        return $data;
    }

    private function GetRemoteServicePosition(string $eventId)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $endpoint = self::$remoteService_endpoint . '/eventPosition';

        $params = [
            'eventId' => $eventId,
        ];

        $pos = $this->GetHomePosition();
        $header_add = [
            'latitude'  => $pos['latitude'],
            'longitude' => $pos['longitude'],
        ];

        $data = $this->CallAPI($endpoint, [], $params, $header_add);
        return $data;
    }

    private function SetChargingSettings(string $vin, string $settings)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $endpoint = self::$charging_endpoint . '/' . $vin . '/charging-settings';

        $jsettings = json_decode($settings, true);

        $postfields = [];
        if (isset($jsettings['chargingTarget'])) {
            $postfields['chargingTarget'] = $jsettings['chargingTarget'];
        }
        if (isset($jsettings['acLimitValue'])) {
            $postfields['acLimitValue'] = $jsettings['acLimitValue'];
        }

        $header_add = [
            'Content-Type' => 'application/json',
        ];

        $data = $this->CallAPI($endpoint, $postfields, [], $header_add);
        return $data;
    }
}
