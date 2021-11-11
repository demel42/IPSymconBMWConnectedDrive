<?php

declare(strict_types=1);

trait BMWConnectedDriveCommonLib
{
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

    private function HttpCode2Text($code)
    {
        $code2text = [
            // 1xx – Information
            100 => 'Continue',
            101 => 'Switching Protocols',
            102 => 'Processing',
            103 => 'Early Hints',
            // 2xx – Success
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            207 => 'Multi-Status',
            208 => 'Already Reported',
            226 => 'IM Used',
            // 3xx – Redirection
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found (Moved Temporarily)',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            306 => '(reserviert)',
            307 => 'Temporary Redirect',
            308 => 'Permanent Redirect',
            // 4xx - Client error
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Payload Too Large',
            414 => 'URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Range Not Satisfiable',
            417 => 'Expectation Failed',
            418 => 'I’m a teapot',
            420 => 'Policy Not Fulfilled',
            421 => 'Misdirected Request',
            422 => 'Unprocessable Entity',
            423 => 'Locked',
            424 => 'Failed Dependency',
            425 => 'Too Early',
            426 => 'Upgrade Required',
            428 => 'Precondition Required',
            429 => 'Too Many Requests',
            431 => 'Request Header Fields Too Large',
            444 => 'No Response',
            449 => 'The request should be retried after doing the appropriate action',
            451 => 'Unavailable For Legal Reasons',
            499 => 'Client Closed Request',
            // 5xx - Server error
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version not supported',
            506 => 'Variant Also Negotiates',
            507 => 'Insufficient Storage',
            508 => 'Loop Detected',
            509 => 'Bandwidth Limit Exceeded',
            510 => 'Not Extended',
            511 => 'Network Authentication Required',
            599 => 'Network Connect Timeout Error',
        ];

        $result = isset($code2text[$code]) ? $code2text[$code] : ('Unknown http-error ' . $code);
        return $result;
    }
}
