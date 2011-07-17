<?php
/**
 * DASBiT
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE
 *
 * @category   DASBiT
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */

/**
 * @namespace
 */
namespace Dasbit\Http;

/**
 * HTTP Response.
 *
 * @category   DASBiT
 * @package    Dasbit_Http
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */
class Response
{
    /**
     * The HTTP version.
     *
     * @var string
     */
    protected $version;

    /**
     * The HTTP response code.
     *
     * @var int
     */
    protected $code;

    /**
     * The HTTP response headers.
     *
     * @var array
     */
    protected $headers = array();

    /**
     * The HTTP response body.
     *
     * @var string
     */
    protected $body;

    /**
     * HTTP response constructor.
     *
     * @param  integer $code
     * @param  array   $headers
     * @param  string  $body
     * @param  string  $version
     * @return void
     */
    public function __construct($code, array $headers, $body = null, $version = '1.1')
    {
        $this->code = $code;

        foreach ($headers as $name => $value) {
            if (is_integer($name)) {
                $header = explode(':', $value, 2);
                
                if (count($header) !== 2) {
                    continue;
                }

                $name  = trim($header[0]);
                $value = trim($header[1]);
            }

            $this->headers[ucwords(strtolower($name))] = $value;
        }

        $this->body = $body;

        if (!preg_match('(^\d\.\d$)', $version)) {
            $version = '1.1';
        }

        $this->version = $version;
    }

    /**
     * Check whether the response is an error.
     *
     * @return boolean
     */
    public function isError()
    {
        $resultType = floor($this->code / 100);
        
        if ($resultType === 4. || $resultType === 5.) {
            return true;
        }

        return false;
    }

    /**
     * Check whether the response in successful.
     *
     * @return boolean
     */
    public function isSuccessful()
    {
        $resultType = floor($this->code / 100);
        
        if ($resultType === 2. || $resultType === 1.) {
            return true;
        }

        return false;
    }

    /**
     * Check whether the response is a redirection
     *
     * @return boolean
     */
    public function isRedirect()
    {
        $resultType = floor($this->code / 100);
        
        if ($resultType === 3.) {
            return true;
        }

        return false;
    }

    /**
     * Get the response body as string.
     *
     * @return string
     */
    public function getBody()
    {
        $body = '';

        // Decode the body if it was transfer-encoded
        switch (strtolower($this->getHeader('transfer-encoding'))) {
            // Handle chunked body
            case 'chunked':
                $body = self::decodeChunkedBody($this->body);
                break;

            // No transfer encoding, or unknown encoding extension:
            // return body as is
            default:
                $body = $this->body;
                break;
        }

        // Decode any content-encoding (gzip or deflate) if needed
        switch (strtolower($this->getHeader('content-encoding'))) {
            // Handle gzip encoding
            case 'gzip':
                $body = self::decodeGzip($body);
                break;

            // Handle deflate encoding
            case 'deflate':
                $body = self::decodeDeflate($body);
                break;

            default:
                break;
        }

        return $body;
    }

    /**
     * Get the raw response body (as transfered "on wire") as string.
     *
     * @return string
     */
    public function getRawBody()
    {
        return $this->body;
    }

    /**
     * Get the HTTP version of the response.
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Get the HTTP response status code.
     *
     * @return int
     */
    public function getStatus()
    {
        return $this->code;
    }

    /**
     * Get the response headers.
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Get a specific header as string, or null if it is not set.
     *
     * @param  string $header
     * @return mixed
     */
    public function getHeader($header)
    {
        $header = ucwords(strtolower($header));
        
        if (!is_string($header) || !isset($this->headers[$header])) {
            return null;
        }

        return $this->headers[$header];
    }

    /**
     * Get all headers as string.
     *
     * @param  boolean $statusLine
     * @param  string  $br
     * @return string
     */
    public function getHeadersAsString($status_line = true, $br = "\n")
    {
        $result = '';

        if ($statusLine) {
            $result = 'HTTP/ ' . $this->version. ' ' . $this->code . ' ' . $this->message . $br;
        }

        foreach ($this->headers as $name => $value) {
            if (is_string($value)) {
                $result .= $name . ': ' . $value . $br;
            } elseif (is_array($value)) {
                foreach ($value as $subValue) {
                    $result .= $name . ': ' . $subval . $br;
                }
            }
        }

        return $result;
    }

    /**
     * Get the entire response as string.
     *
     * @param  string $br
     * @return string
     */
    public function asString($br = "\n")
    {
        return $this->getHeadersAsString(true, $br) . $br . $this->getRawBody();
    }

    /**
     * Extract the response code from a response string.
     *
     * @param  string $responseString
     * @return integer
     */
    public static function extractCode($responseString)
    {
        preg_match('(^HTTP/[\d\.x]+ (\d+))', $responseString, $match);

        if (isset($match[1])) {
            return (int) $match[1];
        } else {
            return false;
        }
    }

    /**
     * Extract the HTTP message from a response.
     *
     * @param  string $responseString
     * @return string
     */
    public static function extractMessage($responseString)
    {
        preg_match('(^HTTP/[\d\.x]+ \d+ ([^\r\n]+))', $responseString, $match);

        if (isset($match[1])) {
            return $match[1];
        } else {
            return false;
        }
    }

    /**
     * Extract the HTTP version from a response.
     *
     * @param  string $responseString
     * @return string
     */
    public static function extractVersion($responseString)
    {
        preg_match('(^HTTP/([\d\.x]+) \d+)', $responseString, $match);

        if (isset($match[1])) {
            return $match[1];
        } else {
            return false;
        }
    }

    /**
     * Extract the headers from a response string.
     *
     * @param  string $responseString
     * @return array
     */
    public static function extractHeaders($responseString)
    {
        $headers = array();
        $parts   = preg_split('((?:\r?\n){2})m', $responseString, 2);
        
        if (!$parts[0]) {
            return $headers;
        }

        $lines = explode("\n", $parts[0]);
        unset($parts);
        $lastHeader = null;

        foreach ($lines as $line) {
            $line = trim($line, "\r\n");
            
            if ($line === "") {
                break;
            }

            if (preg_match('(^([\w-]+):\s*(.+))', $line, $match)) {
                unset($lastHeader);
                $headerName  = strtolower($match[1]);
                $headerValue = $match[2];

                if (isset($headers[$headerName])) {
                    if (!is_array($headers[$headerName])) {
                        $headers[$headerName] = array($headers[$headerName]);
                    }

                    $headers[$headerName][] = $headerValue;
                } else {
                    $headers[$headerName] = $headerValue;
                }
                
                $lastHeader = $headerName;
            } elseif (preg_match('(^\s+(.+)$)', $line, $match) && $lastHeader !== null) {
                if (is_array($headers[$lastHeader])) {
                    end($headers[$lastHeader]);
                    $lastHeaderKey = key($headers[$lastHeader]);
                    $headers[$lastHeader][$lastHeaderKey] .= $match[1];
                } else {
                    $headers[$lastHeader] .= $match[1];
                }
            }
        }

        return $headers;
    }

    /**
     * Extract the body from a response string.
     *
     * @param  string $responseString
     * @return string
     */
    public static function extractBody($responseString)
    {
        $parts = preg_split('((?:\r?\n){2})m', $responseString, 2);
        
        if (isset($parts[1])) {
            return $parts[1];
        }
        
        return '';
    }

    /**
     * Decode a "chunked" transfer-encoded body and return the decoded text.
     *
     * @param  string $body
     * @return string
     */
    public static function decodeChunkedBody($body)
    {
        $decBody = '';

        if (function_exists('mb_internal_encoding') && ((int) ini_get('mbstring.func_overload')) & 2) {
            $mbIntEnc = mb_internal_encoding();
            mb_internal_encoding('ASCII');
        }

        while (trim($body)) {
            if (!preg_match('(^([\da-fA-F]+)[^\r\n]*\r\n)sm', $body, $match)) {
                return '';
            }

            $length   = hexdec(trim($match[1]));
            $cut      = strlen($match[0]);
            $decBody .= substr($body, $cut, $length);
            $body     = substr($body, $cut + $length + 2);
        }

        if (isset($mbIntEnc)) {
            mb_internal_encoding($mbIntEnc);
        }

        return $decBody;
    }

    /**
     * Decode a gzip encoded message (when Content-encoding = gzip).
     *
     * @param  string $body
     * @return string
     */
    public static function decodeGzip($body)
    {
        if (!function_exists('gzinflate')) {
            return '';
        }

        return gzinflate(substr($body, 10));
    }

    /**
     * Decode a zlib deflated message (when Content-encoding = deflate).
     *
     * @param  string $body
     * @return string
     */
    public static function decodeDeflate($body)
    {
        if (!function_exists('gzuncompress')) {
            return '';
        }

        $zlibHeader = unpack('n', substr($body, 0, 2));
        
        if ($zlibHeader[1] % 31 == 0) {
            return gzuncompress($body);
        } else {
            return gzinflate($body);
        }
    }

    /**
     * Create a new Response object from a string.
     *
     * @param  string $responseString
     * @return Response
     */
    public static function fromString($responseString)
    {
        $code    = self::extractCode($responseString);
        $headers = self::extractHeaders($responseString);
        $body    = self::extractBody($responseString);
        $version = self::extractVersion($responseString);
        $message = self::extractMessage($responseString);

        return new Response($code, $headers, $body, $version, $message);
    }
}
