<?php
namespace demo;

use laocc\plugs\Debug;

class TestController
{

    public function debug()
    {
        Debug::star(__DIR__ . '/../debug/');
        Debug::recode('获取文件名');

        //这儿指定一个固定的文件名，是为了避免在测试站里产生大量临时文件，
        //实际使用时，可以不用指定文件名，或指定随机数组成的文件名
        $file = Debug::filename('test.php');

        echo "<h4>Debug日志文件可在编辑中直接查看，下面内容是上一次的运行结果：</h4>";
        echo "filename:\t{$file}<hr>";

        Debug::recode('开始读取文件');
        $lines = file($file);
        Debug::recode('读取结束');

        if (!$lines) return;
        echo "<pre style='font-size:14px;margin:10px;'>";
        foreach ($lines as $i => $line) if ($i > 0) echo "{$line}";
        echo "</pre>";

        Debug::recode('显示结束');

    }

}