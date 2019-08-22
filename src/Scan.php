<?php declare(strict_types=1);


namespace Jeekens\Annotation;


use Jeekens\Basics\Fs;
use function array_merge;
use function call_user_func_array;
use function class_exists;
use function class_init;
use function closedir;
use Closure;
use function get_class;
use function get_class_from_file;
use function is_dir;
use function opendir;
use function pathinfo;
use function preg_match;
use function readdir;
use ReflectionClass;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Jeekens\Annotation\Annotations\Handler\Handler;
use Jeekens\Annotation\Annotations\Assist\AnnotationHandler;
use Jeekens\Annotation\Annotations\Handler\HandlerInterface;

class Scan
{

    /**
     * @var array
     */
    private $ignoreNameSpaceOrClass = [];

    /**
     * @var array
     */
    private $dir = [];

    /**
     * @var array
     */
    private $ignoreFileOrDir = [];

    /**
     * @var array
     */
    private $observers = [];

    private $ignoreAnnotations = [
        'from', 'author', 'link', 'see', 'license', 'copyright'
    ];


    const BEFORE_SCAN_DIR = 'before_scan_dir';
    const AFTER_SCAN_DIR = 'after_scan_dir';
    const DISCOVERY_DIR = 'discovery_dir';
    const DISCOVERY_FILE = 'discovery_file';
    const BEFORE_IGNORE_ALL_FILE_OR_DIR = 'before_ignore_all_file_or_dir';
    const IGNORE_FILE_OR_DIR = 'ignore_file_or_dir';
    const AFTER_IGNORE_ALL_FILE_OR_DIR = 'after_ignore_all_file_or_dir';
    const DISCOVERY_CLASS = 'discovery_class';
    const AFTER_SCAN_CLASS = 'after_scan_class';
    const IGNORE_CLASS_OR_NAMESPACE = 'ignore_class_or_namespace';
    const BEFORE_IGNORE_ALL_CLASS_OR_NAMESPACE = 'before_ignore_all_class_or_namespace';
    const CLASS_CANNOT_LOAD_PROPERLY = 'class_cannot_load_properly';


    public function __construct(? array $options = null)
    {
        if ($options) {
            class_init($this, $options);
        }
    }

    /**
     * @param array $nameSpaceOrClass
     */
    public function setIgnoreNameSpaceOrClass(array $nameSpaceOrClass)
    {
        $this->ignoreNameSpaceOrClass = $nameSpaceOrClass;
    }

    /**
     * @param string|array $nameSpaceOrClass
     */
    public function addIgnoreNameSpaceOrClass($nameSpaceOrClass)
    {
        $this->ignoreNameSpaceOrClass[] = $nameSpaceOrClass;
    }

    /**
     * @param array $fileOrDir
     */
    public function setIgnoreFileOrDir(array $fileOrDir)
    {
        $this->ignoreFileOrDir = $fileOrDir;
    }

    /**
     * @param $fileOrDir
     *
     * @return $this
     */
    public function addIgnoreFileOrDir($fileOrDir)
    {
        $this->ignoreFileOrDir = array_merge(
            $this->ignoreFileOrDir,
            (array)$fileOrDir
        );
        return $this;
    }

    /**
     * @param array $dir
     */
    public function setDir(array $dir)
    {
        $this->dir = $dir;
    }

    /**
     * @param string|array $dir
     *
     * @return $this
     */
    public function addDir($dir)
    {
        $this->dir = array_merge(
            $this->dir,
            (array)$dir
        );
        return $this;
    }

    /**
     * @throws \Doctrine\Common\Annotations\AnnotationException
     * @throws \ReflectionException
     */
    public function run()
    {
        $allFile = [];
        // 扫描全部文件夹获取所有文件路径
        foreach ($this->dir as $dir) {
            $dir = Fs::getAbsPath($dir); // 路径转化为绝对路径
            $this->notify(self::BEFORE_SCAN_DIR, [$dir, $allFile]);
            if (($files = $this->scanPhpFile($dir)) && !empty($files)) {
                $allFile = array_merge($allFile, $files);
            }
            $this->notify(self::AFTER_SCAN_DIR, [$dir, $files, $allFile]);
        }

        $this->notify(self::BEFORE_IGNORE_ALL_FILE_OR_DIR, [$allFile]);

        $notIgnoreFile = []; // 未过滤文件

        // 过滤需要忽略的文件
        foreach ($allFile as $file) {
            if ($this->fileNotIgnore($file)) {
                $notIgnoreFile[] = $file;
            } else {
                $this->notify(self::IGNORE_FILE_OR_DIR, [$file]);
            }
        }

        $this->notify(self::AFTER_IGNORE_ALL_FILE_OR_DIR, [$allFile, $notIgnoreFile]);

        $allClass = []; // 发现的类名

        // 扫描所有文件并取出文件内的类名
        foreach ($notIgnoreFile as $file) {
            $class = get_class_from_file($file);

            if (! empty($class)) {
                $allClass[$file] = $class;
                $this->notify(self::DISCOVERY_CLASS, [$file, $class]);
            }
        }

        $this->notify(self::AFTER_SCAN_CLASS, [$allClass]);

        $notIgnoreClass = []; // 未被忽略的类

        // 过滤掉需要忽略的类
        foreach ($allClass as $file => $className) {
            if ($this->classNotIgnore($className)) {
                $notIgnoreClass[$file] = $className;
            } else {
                $this->notify(self::IGNORE_CLASS_OR_NAMESPACE, [$className, $file]);
            }
        }

        $this->notify(self::BEFORE_IGNORE_ALL_CLASS_OR_NAMESPACE, [$allFile, $allClass, $notIgnoreFile, $notIgnoreClass]);

        AnnotationRegistry::registerLoader('class_exists'); // 注册注解加载规则
        array_map(AnnotationReader::class.'::addGlobalIgnoredName', $this->ignoreAnnotations); // 注册全部需要忽略的注解标签
        // 初始化需要使用的变量
        $classAnnotations = $propertiesAnnotation = $methodAnnotation = $annotationHandler = [];
        $annotationReader = new AnnotationReader();
        $includedFiles = get_included_files();

        foreach ($notIgnoreClass as $file => $class) {
            // 防止扫描时重复加载文件
            $autoload = in_array($file, $includedFiles, true);
            $includedFiles[] = $file;
            // 如果类不能自动加载则跳过注解处理流程
            if (! class_exists($class, !$autoload)) {
                $this->notify(self::CLASS_CANNOT_LOAD_PROPERLY, [$class, $file]);
                continue;
            }
            // 获取当前类的反射api
            $reflectionClass = new ReflectionClass($class);
            $classAnnotationTmp = $annotationReader->getClassAnnotations($reflectionClass);

            if (! empty($classAnnotationTmp)) {
                $classAnnotations[$class]['annotations'] = $classAnnotationTmp;
                // 扫描类级别的注解，判断是否存AnnotationHandler注解，如果存在则表示当前类是一个注解处理类，并保存
                foreach ($classAnnotationTmp as $annotation) {
                    if ($annotation instanceof AnnotationHandler) {
                        $annotationHandler[$annotation->getAnnotation()] = $reflectionClass;
                    }
                }
            }
            // 所有方法的反射api
            $methods = $reflectionClass->getMethods();
            // 扫描所有方法的注解信息，并保存
            foreach ($methods as $method) {
                $methodAnnotationTmp = $annotationReader->getMethodAnnotations($method);

                if (! empty($methodAnnotationTmp)) {
                    $methodAnnotation[$class][$method->getName()] = $methodAnnotationTmp;
                }
            }
            // 获取所有属性的反射api
            $properties = $reflectionClass->getProperties();
            // 扫描属性内的注解信息，并保存
            foreach ($properties as $property) {
                $propertyAnnotationTmp = $annotationReader->getPropertyAnnotations($property);

                if (! empty($propertyAnnotationTmp)) {
                    $propertiesAnnotation[$class][$property->getName()] = $propertyAnnotationTmp;
                }
            }
            // 判断当前类内部是否存在注解，如果存在则保存类的反射api
            if (! (empty($propertiesAnnotation[$class]) && empty($methodAnnotation[$class]) && empty($classAnnotations[$class]))) {
                $classAnnotations[$class]['reflection'] = $reflectionClass;
            }
        }
        // 扫描类级别的注解信息并处理
        foreach ($classAnnotations as $className => $item) {
            if (isset($item['annotations'])) {
                /**
                 * @var $annotation object
                 * @var $handlerReflectionClass ReflectionClass
                 * @var $handler HandlerInterface
                 */
                foreach ($item['annotations'] as $annotation) {
                    $className = get_class($annotation);
                    if (($handlerReflectionClass = $annotationHandler[$className] ?? null)) {
                        $handler = $handlerReflectionClass->newInstanceArgs([
                            $className,
                            $item['reflection'],
                            $item['annotations']
                        ]);
                        $handler->handle(Handler::TYPE_CLASS, $annotation);
                    }
                }
            }
        }
        // 扫描方法级别的注解并处理
        foreach ($methodAnnotation as $className => $methods) {
            foreach ($methods as $method => $annotation) {
                $className = get_class($annotation);
                if (($handlerReflectionClass = $annotationHandler[$className] ?? null)) {
                    $handler = $handlerReflectionClass->newInstanceArgs([
                        $className,
                        $classAnnotations[$className]['reflection'],
                        $classAnnotations[$className]['annotations'] ?? []
                    ]);
                    $handler->setMethodName($method);
                    $handler->handle(Handler::TYPE_METHOD, $annotation);
                }
            }
        }
        // 扫描成员级别的注解并处理
        foreach ($propertiesAnnotation as $className => $properties) {
            foreach ($properties as $property => $annotation) {
                $className = get_class($annotation);
                if (($handlerReflectionClass = $annotationHandler[$className] ?? null)) {
                    $handler = $handlerReflectionClass->newInstanceArgs([
                        $className,
                        $classAnnotations[$className]['reflection'],
                        $classAnnotations[$className]['annotations'] ?? []
                    ]);
                    $handler->setPropertyName($property);
                    $handler->handle(Handler::TYPE_PROPERTY, $annotation);
                }
            }
        }
    }

    /**
     * @param string $dir
     * @param null $files
     *
     * @return array
     */
    private function scanPhpFile(string $dir, &$files = null)
    {
        if ($files == null) {
            $files = array();
        }

        if (is_dir($dir)) {
            if ($handle = opendir($dir)) {
                while (($file = readdir($handle)) !== false) {
                    if ($file != '.' && $file != '..') {
                        $path = $dir . '/' . $file;
                        if (is_dir($path)) {
                            $this->notify(self::DISCOVERY_DIR, [$path]);
                            $this->scanPhpFile($path, $files);
                        } else {
                            if (pathinfo($file, PATHINFO_EXTENSION) == 'php') {
                                $this->notify(self::DISCOVERY_FILE, [$path]);
                                $files[] = $path;
                            }
                        }
                    }
                }
                closedir($handle);
                return $files;
            }
        } else {
            return $files;
        }

        return $files;
    }

    /**
     * @param string $filePath
     *
     * @return bool
     */
    private function fileNotIgnore(string $filePath)
    {
        foreach ($this->ignoreFileOrDir as $pattern) {
            if (preg_match("#{$pattern}#", $filePath)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $className
     *
     * @return bool
     */
    private function classNotIgnore(string $className)
    {
        foreach ($this->ignoreNameSpaceOrClass as $pattern) {
            if (preg_match("#{$pattern}#", $className)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $event
     * @param Closure $closure
     */
    public function addObserver(string $event, Closure $closure)
    {
        $this->observers[$event][] = $closure;
    }

    /**
     * @param string $event
     * @param array $param
     */
    private function notify(string $event, array $param)
    {
        $observers = $this->observers[$event] ?? [];
        foreach($observers as $observer)
        {
            if (empty($param)) {
                $observer();
            } else {
                call_user_func_array($observer, $param);
            }
        }
    }

}
