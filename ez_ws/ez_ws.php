<?php



define("TOP_AUTOLOADER_PATH", dirname(__FILE__));

class Autoloader{

    /**
     * 类库自动加载，写死路径，确保不加载其他文件。
     * @param string $class 对象类名
     * @return void
     */
    public static function autoload($class) {

        $name = $class;
        if(false !== strpos($name,'\\')){
            $name = explode('\\',$class);
            $name = array_pop($name);
        }

        $filename = TOP_AUTOLOADER_PATH."/lib/".$name.".php";

        if(is_file($filename)) {
            include $filename;
            return;
        }

    }
}
spl_autoload_register('Autoloader::autoload');
