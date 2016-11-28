<?php
namespace laocc\plugs;


class Async
{

    public static function get($url, array $option = [], callable $callback = null, callable $callback_error = null)
    {
        $option += ['port' => 80, 'timeout' => 30, 'method' => 'get'];
        return self::send($url, $option, $callback, $callback_error);
    }


    public static function post($url, array $option = [], callable $callback = null, callable $callback_error = null)
    {
        $option += ['port' => 80, 'timeout' => 30, 'method' => 'post'];
        return self::send($url, $option, $callback, $callback_error);
    }

    private static function send($url, array $option = [], callable $callback = null, callable $callback_error = null)
    {
        $host = explode('/', $url, 4);
        if (count($host) < 3) throw new \Exception("异步调用地址不是一个合法的URL");
        $version = (strtoupper($host[0]) === 'HTTPS') ? 'HTTPS/2.0' : 'HTTP/1.1';
        $uri = "/{$host[3]}";
        $method = strtoupper($option['method']);
        $fp = fsockopen($host[2], intval($option['port']), $errno, $errstr, $option['timeout']);
        if (!$fp) {
            if (!is_null($callback_error)) $callback_error($errno, $errstr);
        } else {
            fwrite($fp, "{$method} {$uri} {$version}\r\nHost: {$host[2]}\r\nConnection: Close\r\n\r\n");
            if (!is_null($callback)) {
                $value = '';
                while (!feof($fp)) $value .= fgets($fp, 128);
                $callback($value);
            }
            fclose($fp);
        }
        return true;
    }


}