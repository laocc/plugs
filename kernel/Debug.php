<?php
namespace laocc\plugs;

class Debug
{
    private static $prevTime;
    private static $memory;
    private static $_state;
    private static $_value;
    private static $_log_path;
    private static $_print_format = '% 9.3f';

    private static $_star = [];
    private static $_node = [];
    private static $_trace = [];
    private static $_node_len = 0;
    private static $_file_len = 0;

    public static function star($path = null)
    {
        self::init($path);
    }

    public static function init($path = null)
    {
        if (is_null($path)) $path = (__DIR__ . '/../../../../debug/');

        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS);
        self::$_trace = array_reverse($trace);

        self::$_state = true;
        self::$_log_path = $path;
        self::$_star = [microtime(true), memory_get_usage()];
        self::$prevTime = 0;
        self::$memory = memory_get_usage();
        $time = sprintf(self::$_print_format, self::$prevTime * 1000);
        $memo = sprintf(self::$_print_format, (self::$memory - self::$_star[1]) / 1024);
        $now = sprintf(self::$_print_format, (self::$memory) / 1024);
        self::$_node[0] = ['t' => $time, 'm' => $memo, 'n' => $now, 'g' => 'star'];
        self::$prevTime = microtime(true);
        register_shutdown_function(function () {
            self::save_logs();
        });
        self::recode('STAR');
    }


    /**
     * 创建一个debug点
     * @param $msg
     */
    public static function recode($msg, $prev = null)
    {
        if (self::$_state === false) return;
        $prev = $prev ?: debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        $file = null;
        if (isset($prev['file'])) {
            if ($prev['file'] !== __FILE__) {
                $file = $prev['file'] . " [{$prev['line']}]";
            }
        }
        if (is_array($msg)) $msg = json_encode($msg, 256);
        self::$_node_len = max(iconv_strlen($msg), self::$_node_len);
        self::$_file_len = max(iconv_strlen($file), self::$_file_len);
        $nowMemo = memory_get_usage();
        $time = sprintf(self::$_print_format, (microtime(true) - self::$prevTime) * 1000);
        $memo = sprintf(self::$_print_format, ($nowMemo - self::$memory) / 1024);
        $now = sprintf(self::$_print_format, ($nowMemo) / 1024);
        self::$prevTime = microtime(true);
        self::$memory = $nowMemo;
        self::$_node[] = ['t' => $time, 'm' => $memo, 'n' => $now, 'g' => $msg, 'f' => $file];
    }


    public static function stop()
    {
        if (!self::$_state) return;
        if (!empty(self::$_node)) self::recode('shutdown');//创建一个结束点
        self::$_state = false;
    }

    public static function disable()
    {
        self::$_state = null;
    }

    /**
     * 保存记录到的数据
     */
    private static function save_logs()
    {
        if (empty(self::$_node) or self::$_state === null) return;

        self::recode('END');
        $lenMsg = min(self::$_node_len + 3, 100);
        $lenFile = min(self::$_file_len + 3, 100);

        $data = [];
        $data[] = "<?php \n\n";
        $data[] = "# TIME\t\t" . date('Y-m-d H:i:s') . "\n\n";
        $data[] = "# METHOD\t" . getenv('REQUEST_METHOD') . "\n";
        $data[] = "# SERVER\t" . getenv('SERVER_ADDR') . "\n";
        $data[] = "# CLIENT\t" . getenv('REMOTE_ADDR') . "\n";
        $data[] = "# uAGENT\t" . getenv('HTTP_USER_AGENT') . "\n";
        $data[] = "# REQUEST\t" . getenv('REQUEST_URI') . "\n\n";

        if (!empty(self::$_value)) {
            foreach (self::$_value as $k => &$v) {
                $data[] = "# {$k}\t{$v}\n";
            }
        }

        $data[] = "# Debug开始之前的程序运行顺序\n";
        foreach (self::$_trace as $str => $trace) {
            $str = "{$str}\t";
            if (isset($trace['file'])) $str .= sprintf("%-{$lenFile}s", "{$trace['file']}({$trace['line']})") . "\t";
            if (isset($trace['class'])) $str .= "{$trace['class']}";
            if (isset($trace['type'])) $str .= "{$trace['type']}";
            if (isset($trace['function'])) $str .= "{$trace['function']}()";
            $data[] = "# {$str}\n";
        }


        $star = self::$_node[0];
        unset(self::$_node[0]);
        $data[] = "\n# \t 耗时(ms)\t耗内存(kb)\t内存占用(ms)";
        $data[] = "\n# {$star['t']}\t{$star['m']}\t{$star['n']}\t{$star['g']}\n";
        $data[] = "# " . (str_repeat('-', 100)) . "\n";
        //具体监控点
        foreach (self::$_node as $i => &$row) {
            $data[] = "# {$row['t']}\t{$row['m']}\t{$row['n']}\t" . sprintf("%-{$lenMsg}s", $row['g']) . "\t{$row['f']}\n";
        }
        $data[] = "# " . (str_repeat('-', 100)) . "\n";
        $time = sprintf(self::$_print_format, (microtime(true) - self::$_star[0]) * 1000);
        $memo = sprintf(self::$_print_format, (memory_get_usage() - self::$_star[1]) / 1024);
        $total = sprintf(self::$_print_format, (memory_get_usage()) / 1024);
        $data[] = "# {$time}\t{$memo}\t{$total}\t业务程序部分消耗合计\n";

        file_put_contents(self::filename(), $data, LOCK_EX);
    }

    /**
     * 可以自行指定日志的文件名，注意：只是文件名，不含路径
     * @param null $name
     * @return null|string
     */
    public static function filename($name = null)
    {
        static $_filename;
        if (is_null(self::$_log_path)) return null;
        if (!is_null($_filename)) return $_filename;

        if (!is_dir(self::$_log_path)) @mkdir(self::$_log_path, 0740, true);
        $path = realpath(self::$_log_path) . '/';

        if (!$name) {
            $name = (date('Y-m-d H.i.s') . '_' . microtime(true)) . '.php';
        } else {
            $name = ltrim($name, '/');
        }

        return $_filename = $path . $name;
    }


}