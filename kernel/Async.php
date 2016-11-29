<?php
namespace laocc\plugs;

class Async
{
    private $_option = [];
    private $_url = [];
    private $_index = 0;
    private $_post_key = ['__async_action_', '__async_data_'];
    private $_isServer = false;
    private $_isAsync = true;
    private $_server;
    private $_protect = ['indexAction', 'getRequest', 'getResponse', 'getModuleName', 'getView', 'initView', 'setViewpath', 'getViewpath', 'forward', 'redirect', 'getInvokeArgs', 'getInvokeArg'];

    public function __construct($sev = true)
    {
        if (is_object($sev)) {
            $this->_isServer = true;
            $this->_server = $sev;
        } elseif (is_bool($sev) or is_int($sev)) {
            $this->_isAsync = boolval($sev);
        } elseif ($this->is_url($sev)) {
            $this->_url[0] = $sev;
        } else {
            throw new \Exception('若需要客户端请提供布尔型或一个网址URL参数，若需要服务器端请提供实例类对象参数，而当前参数不是这三种类型的值。');
        }
    }

    /**
     * 是否可用接口，并返回各部分数据
     * @param $url
     * @param null $match
     * @return int
     */
    private function is_url($url, &$match = null)
    {
        return preg_match('/^(https?)\:\/{2}([a-z][\w\.]+\.[a-z]{2,10})(?:\:(\d+))?(\/.+)$/i', $url, $match);
    }

    private function get_protect()
    {
        static $protect;
        if (!is_null($protect)) return $protect;
        $protect = $this->protect;
        if (empty($protect)) {
            $protect = [];
        } elseif (is_string($protect)) {
            $protect = explode(',', $protect);
        }
        $protect += $this->_protect;
        return $protect;
    }

    /**
     * 显示async服务端所有可用接口
     * @param $object
     */
    private function display_server($trace)
    {
        if (!is_object($this->_server)) return;
        $fun = [];
        $class = get_class($this->_server);
        foreach (get_class_methods($class) as $method) {
            if ($this->action and !preg_match("/.+{$this->action}$/i", $method)) continue;
            $fun[$method] = $method;
        }
        if (empty($fun)) return;


        $self = get_class() . '/listen';
        $file = null;
        foreach ($trace as $trc) {
            if (!isset($trc['object'])) continue;

            if (!is_null($file)) {//已过了入口，查找最终调用者
                if (get_class($trc['object']) === $class) {
                    if (isset($trc['file'])) $file = $trc['file'];//记录最新调用者
                }
                if (get_class($trc['object']) !== $class) {
                    break;//不是调用者，返回最近调用者
                }
                continue;//得到过文件名
            }

            if ("{$trc['class']}/{$trc['function']}" === $self and isset($trc['file'])) {
                $file = $trc['file'];//进入本程序的入口
            }
            if (get_class($trc['object']) === $class) {
                $file = $trc['file'];//调用接口者
                break;
            }//不是真实调用者，继续查找
        }

        if (is_null($file)) return;
        $code = file_get_contents($file);
        if (empty($code)) return;
        $html = <<<HTML
<!DOCTYPE html><html lang="zh-cn"><head>
    <meta charset="UTF-8">
    <style>
    body {margin: 0;padding: 0;font-size: %s;color:#333;font-family:"Source Code Pro", "Arial", "Microsoft YaHei", "msyh", "sans-serif";}
div.nav{width:100%;height:2em;line-height:2em;font-size:2em;background:#17728A;color:#eee;text-indent:1em;border-bottom:2px solid #FFD82F;}
div.item{clear:both;margin:1em;}
div.head{width:100%;height:2em;line-height:2em;background:#50AC74;color:#fff;text-indent:1em;}
pre{margin:0;text-indent:2em;background: #F4FFFC;border:1px solid #50AC74;padding:0.5em 0;}
form{margin:5em;}
label{float:left;height:28px;line-height:28px;}
input{float:left;padding:0;margin:0;border: 1px solid #333;}
input[type=text]{width:10em;height:26px;}
input[type=submit]{width:5em;border-left:0;height:28px;background:#17728A;color:#fff;}
input[type=submit]:hover{background:#17588A;color:#f00;}
h3{width:100%;text-align:center;margin-top:10em;}
</style>
    <title>接口参数</title>
</head><body>
HTML;
        echo $html, "<div class='nav'>{$class} Interface</div>";

        if (is_null($this->password)) {
            echo '<h3>当前接口未设置查看密码，不能查看接口信息。请在创建async服务器时指定密码，如：$async->password = \'password\';</h3>';
            return;
        }

        if (!isset($_GET['pwd']) or $_GET['pwd'] !== $this->password) {
            $form = <<<HTML
<form action="./" method="get">
<label for="pwd">请输入接口查看密码：</label>
<input type="text" name="pwd" id="pwd" value="" autocomplete="off">
<input type="submit" value="进入">
</form>
HTML;
            echo $form, (isset($_GET['pwd']) ? '<label style="color:red;margin-left:2em;">密码错误</label>' : '');
            return;
        }

        preg_match_all('/(\/\*\*(?:.+?)\*\/).+?function\s+(\w+?)\((.*?)\)/is', $code, $match, PREG_SET_ORDER);
        foreach ($match as $item) {
            if (!in_array($item[2], $fun)) continue;
            echo "<div class='item'><div class='head'>{$item[2]}({$item[3]})</div><pre>{$item[1]}</pre></div>";
        }

        echo '</body></html>';
    }


    /**
     * 服务器端侦听请求
     */
    public function listen()
    {
        if (getenv('REQUEST_METHOD') === 'GET') {
            $this->display_server(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS));
            exit;
        }

        if (empty($_POST)) exit;
        if (!$this->_isServer) throw new \Exception('当前Async对象是服务器端，不可调用listen方法');

        $action = isset($_POST[$this->_post_key[0]]) ? $_POST[$this->_post_key[0]] : null;
        $data = isset($_POST[$this->_post_key[1]]) ? $_POST[$this->_post_key[1]] : null;
        if (is_null($data)) exit;
        if (strpos($action, '_') === 0) exit(serialize('禁止调用系统方法'));
        $action .= $this->action;

        $agent = getenv('HTTP_USER_AGENT');
        if (!$agent) exit;
        $host = getenv('HTTP_HOST');
        if (!hash_equals(md5("{$host}:{$data}/{$this->token}"), $agent)) exit(serialize('TOKEN验证失败'));
        if (!method_exists($this->_server, $action) or !is_callable([$this->_server, $action])) {
            exit(serialize("当前服务端不存在{$action}方法。"));
        }
        $data = unserialize($data);
        if (!is_array($data)) $data = [$data];

        ob_start();
        $v = $this->_server->{$action}(...$data + array_fill(0, 10, null));
        if (!is_null($v)) {//优先取return值
            ob_end_clean();
            echo serialize($v);
        } else {
            $v = ob_get_contents();
            ob_end_clean();
            echo serialize($v);
        }
        ob_flush();
        exit;
    }

    /*===========================================client=============================================================*/

    public function __set(string $name, $value)
    {
        $this->_option[$name] = $value;
    }

    public function __get($name)
    {
        return isset($this->_option[$name]) ? $this->_option[$name] : null;
    }

    /**
     * 调用服务器端类方法的魔术方法
     */
    public function __call($name, $arguments)
    {
        if ($this->_isServer) throw new \Exception('当前是服务器端，只可以调用listen方法，若需要客户端的功能，请创建为客户端对象。');

        $success = function ($index, $value) use (&$data) {
            $data = unserialize($value);
        };
        $error = function ($index, $err_no, $err_str) {
            throw new \Exception($err_str, $err_no);
        };
        $this->_isAsync = false;
        $url = $this->_url[0];
        $this->_url = [];
        $this->_url[0] = $this->realUrl($url, $name, $arguments) + ['success_call' => $success, 'error_call' => $error];
        $this->send();
        return $data;
    }


    /**
     * 添加请求，但不会立即执行，send()时一并发送执行
     * @param $url
     * @param $action
     * @param array $data
     * @param callable|null $success_call
     * @param callable|null $error_call
     * @return int
     * @throws \Exception
     */
    public function call($url, $action, $data = [], callable $success_call = null, callable $error_call = null)
    {
        if ($this->_isServer) throw new \Exception('当前Async对象是服务器端，不可调用call方法');
        $this->_url[++$this->_index] = $this->realUrl($url, $action, $data) + ['success_call' => $success_call, 'error_call' => $error_call];
        return $this->_index;
    }

    private function realUrl($url, $action, $data)
    {
        if (!$this->is_url($url, $info)) throw new \Exception("请求调用地址不是一个合法的URL");
        $_data = [$this->_post_key[0] => $action, $this->_post_key[1] => $data = serialize($data)];
        return [
            'version' => (strtoupper($info[1]) === 'HTTPS') ? 'HTTP/2.0' : 'HTTP/1.1',
            'host' => $info[2],
            'port' => intval($info[3] ?: 80),
            'uri' => "/{$info[4]}",
            'url' => $url,
            'agent' => md5("{$info[2]}:{$data}/{$this->token}"),
            'data' => $_data,
        ];
    }

    /**
     * 发请所有请求
     * @param callable|null $success_call
     * @param callable|null $error_call
     * @return bool
     * @throws \Exception
     */
    public function send(callable $success_call = null, callable $error_call = null)
    {
        if ($this->_isServer) throw new \Exception('当前Async对象是服务器端，不可调用send方法');

        foreach ($this->_url as $index => $item) {
            $success_call = $item['success_call'] ?: $success_call;
            $error_call = $item['error_call'] ?: $error_call;
            if (is_null($success_call) and $this->_isAsync === false) throw new \Exception('非异步请求，必须提供处理返回数据的回调函数');

            $fp = fsockopen($item['host'], $item['port'], $err_no, $err_str, intval($this->timeout ?: 1));
            if (!$fp) {
                if (!is_null($error_call)) {
                    $error_call($index, $err_no, $err_str);
                } else {
                    throw new \Exception($err_str, $err_no);
                }
            } else {
                $_data = http_build_query($item['data']);
                $data = "POST {$item['uri']} {$item['version']}\r\n";
                $data .= "Host:{$item['host']}\r\n";
                $data .= "Content-type:application/x-www-form-urlencoded\r\n";
                $data .= "User-Agent:{$item['agent']}\r\n";
                $data .= "Content-length:" . strlen($_data) . "\r\n";
                $data .= "Connection:Close\r\n\r\n{$_data}";

                fwrite($fp, $data);
                if ($this->_isAsync) {
                    if (!is_null($success_call)) {
                        $success_call($index, null);
                    }
                } else {
                    if (!is_null($success_call)) {
                        $value = $tmpValue = '';
                        $len = null;
                        while (!feof($fp)) {
                            $line = fgets($fp);
                            if ($line == "\r\n" and is_null($len)) {
                                $len = 0;//已过信息头区
                            } elseif ($len === 0) {
                                $len = hexdec($line);//下一行的长度
                            } elseif (is_int($len)) {
                                $tmpValue .= $line;//中转数据，防止收到的一行不是一个完整包
                                if (strlen($tmpValue) >= $len) {
                                    $value .= substr($tmpValue, 0, $len);
                                    $tmpValue = '';
                                    $len = 0;//收包后归0
                                }
                            }
                        }
                        $success_call($index, $value);
                    }
                }
                fclose($fp);
            }
        }
        return true;
    }

    /**
     * 清空一个或全部
     * @param null $index
     */
    public function flush($index = null)
    {
        if ($this->_isServer) throw new \Exception('当前Async对象是服务器端，不可调用flush方法');

        if (is_null($index)) {
            $this->_url = [];
            $this->_index = 0;
        } elseif (is_array($index)) {
            array_map('self::flush', $index);
        } else {
            unset($this->_url[$index]);
        }
    }


}