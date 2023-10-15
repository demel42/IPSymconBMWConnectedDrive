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

    private static $x_user_agent_fmt = 'android(SP1A.210812.016.C1);%s;2.12.0(19883);%s';
    private static $user_agent = 'Dart/2.16 (dart:io)';

    private static $oauth_config_endpoint = '/eadrax-ucs/v1/presentation/oauth/config';
    private static $oauth_authenticate_endpoint = '/gcdm/oauth/authenticate';
    private static $oauth_token_endpoint = '/gcdm/oauth/token';

    private static $vehicles_endpoint = '/eadrax-vcs/v4/vehicles';

    private static $remoteService_endpoint = '/eadrax-vrccs/v3/presentation/remote-commands';
    private static $remoteServiceHistory_endpoint = '/eadrax-vrccs/v2/presentation/remote-history';

    private static $vehicle_img_endpoint = '/eadrax-ics/v3/presentation/vehicles/%s/images';
    private static $vehicle_poi_endpoint = '/eadrax-dcs/v1/send-to-car/send-to-car';

    private static $charging_statistics_endpoint = '/eadrax-chs/v1/charging-statistics';
    private static $charging_sessions_endpoint = '/eadrax-chs/v1/charging-sessions';

    private static $charging_endpoint = '/eadrax-crccs/v1/vehicles';

    private static $semaphoreTM = 5 * 1000;

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

        $this->RegisterAttributeString('ApiSettings', '');
        $this->RegisterAttributeString('ApiRefreshToken', '');

        $this->RegisterAttributeString('UpdateInfo', json_encode([]));
        $this->RegisterAttributeString('ApiCallStats', json_encode([]));
        $this->RegisterAttributeString('ModuleStats', json_encode([]));

        $this->InstallVarProfiles(false);

        $this->SetBuffer('LastApiCall', 0);

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
            'label'   => 'Test access',
            'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "TestAccess", "");',
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded'  => false,
            'items'     => [
                $this->GetInstallVarProfilesFormItem(),
                [
                    'type'    => 'Button',
                    'label'   => 'Clear token',
                    'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "ClearToken", "");',
                ],
                $this->GetApiCallStatsFormItem(),
            ]
        ];

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

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
        $r = $this->GetVehicles();
        if ($r == false) {
            $txt .= $this->Translate('invalid account-data') . PHP_EOL;
            $txt .= PHP_EOL;
        } else {
            $txt = $this->Translate('valid account-data') . PHP_EOL;
            $vehicles = json_decode($r, true);
            $n_vehicles = count($vehicles);
            $txt .= $n_vehicles . ' ' . $this->Translate('registered vehicles found');
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
        $this->WriteAttributeString('ApiSettings', '');
        $this->WriteAttributeString('ApiRefreshToken', '');
        $this->SetBuffer('AccessToken', '');
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
        $err = '';
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
        $this->ApiCallsCollect($config_url, $err, $statuscode);

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
        $this->ApiCallsCollect($auth_url, $err, $statuscode);

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
        $this->ApiCallsCollect($auth_url, $err, $statuscode);

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
        $this->ApiCallsCollect($token_url, $err, $statuscode);

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
        $err = '';
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

        $this->ApiCallsCollect($token_url, $err, $statuscode);

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

        $ts = intval($this->GetBuffer('LastApiCall'));
        if ($ts == time()) {
            $this->SendDebug(__FUNCTION__, 'multiple call/second', 0);
            while ($ts == time()) {
                IPS_Sleep(100);
            }
            $this->SetBuffer('LastApiCall', time());
        }

        $access_token = $this->RefreshToken();
        IPS_SemaphoreLeave($this->SemaphoreID);
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

        if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
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

        $ts = intval($this->GetBuffer('LastApiCall'));
        if ($ts == time()) {
            $this->SendDebug(__FUNCTION__, 'multiple call/second', 0);
            while ($ts == time()) {
                IPS_Sleep(100);
            }
            $this->SetBuffer('LastApiCall', time());
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
        $err = '';
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

        $this->ApiCallsCollect($url, $err, $statuscode);
        IPS_SemaphoreLeave($this->SemaphoreID);

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
                case 'GetChargingStatistics':
                    $ret = $this->GetChargingStatistics($jdata['vin']);
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
            'apptimezone'   => strval(round(intval(date('Z')) / 60)), // TZ-Differenz in Minuten
            'appDateTime'   => date('U') . date('v'), // Millisekunden
            'tireGuardMode' => 'ENABLED',
        ];

        $data = $this->CallAPI($endpoint, '', $params, '');
        return $data;
    }

    private function GetVehicleData(string $vin)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $endpoint = self::$vehicles_endpoint . '/state';

        $params = [
            'apptimezone'   => strval(round(intval(date('Z')) / 60)), // TZ-Differenz in Minuten
            'appDateTime'   => date('U') . date('v'), // Millisekunden
            'tireGuardMode' => 'ENABLED',
        ];

        $header_add = [
            'bmw-vin' => $vin,
        ];

        $data = $this->CallAPI($endpoint, '', $params, $header_add);
        return $data;
    }

    private function GetChargingStatistics(string $vin)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $endpoint = self::$charging_statistics_endpoint;

        $params = [
            'vin'           => $vin,
            'currentDate'   => date('c'),
        ];

        $data = $this->CallAPI($endpoint, '', $params, '');
        return $data;
    }

    private function GetCarPicture(string $vin, string $carView)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $endpoint = sprintf(self::$vehicle_img_endpoint, $vin);

        $params = [
            'carView' => $carView,
        ];

        $header_add = [
            'accept' => 'image/png',
        ];

        $data = $this->CallAPI($endpoint, '', $params, $header_add);
        return base64_encode($data);
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

    /*
    VEHICLE_CHARGING_PROFILE_SET_URL = VEHICLE_CHARGING_BASE_URL + "/charging-profile"


    MAP_CHARGING_MODE_TO_REMOTE_SERVICE = {
        ChargingMode.IMMEDIATE_CHARGING: "CHARGING_IMMEDIATELY",
        ChargingMode.DELAYED_CHARGING: "TIME_SLOT",

    CHARGING_MODE_TO_CHARGING_PREFERENCE = {
        ChargingMode.IMMEDIATE_CHARGING: "NO_PRESELECTION"
        ChargingMode.DELAYED_CHARGING: "CHARGING_WINDOW"

        if charging_mode and not charging_mode == ChargingMode.UNKNOWN:
            target_charging_profile["chargingMode"]["type"] = MAP_CHARGING_MODE_TO_REMOTE_SERVICE[charging_mode]
            target_charging_profile["chargingMode"]["chargingPreference"] = CHARGING_MODE_TO_CHARGING_PREFERENCE[
                charging_mode
            ].value

        if precondition_climate:
            target_charging_profile["isPreconditionForDepartureActive"] = precondition_climate

     */

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
