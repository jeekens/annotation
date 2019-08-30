<?php declare(strict_types=1);


namespace Jeekens\Annotation;


use Jeekens\Std\Event\EventsAwareTrait;
use Jeekens\Std\FileSystem\FileSystem;
use Jeekens\Std\Str;
use SplFileInfo;
use SplFileObject;
use function array_merge;
use function class_exists;
use function get_class;
use function in_array;
use function is_array;
use function object_init;
use function preg_match;
use ReflectionClass;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Jeekens\Annotation\Annotations\Handler\Handler;
use Jeekens\Annotation\Annotations\Assist\AnnotationHandler;
use Jeekens\Annotation\Annotations\Handler\HandlerInterface;
use function rtrim;
use function to_array;
use function token_get_all;
use const T_CLASS;
use const T_INTERFACE;
use const T_NAMESPACE;
use const T_NS_SEPARATOR;
use const T_STRING;

/**
 * Class Scan
 *
 * @package Jeekens\Annotation
 */
final class Scan
{

    use EventsAwareTrait;

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
    private $fileExt = ['php'];

    /**
     * @var array
     */
    private $ignoreAnnotations = [
        'from', 'author', 'link', 'see', 'license', 'copyright'
    ];


    public function __construct(? array $options = null)
    {
        if ($options) {
            object_init($this, $options);
        }
    }

    /**
     * 设置需要忽略的注解
     *
     * @param array $annotations
     */
    public function setIgnoreAnnotations(array $annotations)
    {
        $this->ignoreAnnotations = $annotations;
    }

    /**
     * 添加需要忽略的注解
     *
     * @param string|array $annotations
     */
    public function addIgnoreAnnotations($annotations)
    {
        $this->ignoreAnnotations = array_merge($this->ignoreAnnotations, to_array($annotations));
    }

    /**
     * 设置需要忽略的命名空间或类名，支持正则
     *
     * @param string|array $nameSpaceOrClass
     */
    public function setIgnoreNameSpaceOrClass($nameSpaceOrClass)
    {
        $this->ignoreNameSpaceOrClass = to_array($nameSpaceOrClass);
    }

    /**
     * 添加需要忽略的命名空间或类名，支持正则
     *
     * @param string|array $nameSpaceOrClass
     */
    public function addIgnoreNameSpaceOrClass($nameSpaceOrClass)
    {
        $this->ignoreNameSpaceOrClass = array_merge($this->ignoreNameSpaceOrClass, to_array($nameSpaceOrClass));
    }

    /**
     * 设置需要忽略的未鉴或目录
     *
     * @param string|array $fileOrDir
     */
    public function setIgnoreFileOrDir($fileOrDir)
    {
        $this->ignoreFileOrDir = to_array($fileOrDir);
    }

    /**
     * 添加需要忽略的文件或目录
     *
     * @param string|array $fileOrDir
     */
    public function addIgnoreFileOrDir($fileOrDir)
    {
        $this->ignoreFileOrDir = array_merge($this->ignoreFileOrDir, to_array($fileOrDir));
    }

    /**
     * 设置扫描的目录
     *
     * @param $dir
     */
    public function setDir($dir)
    {
        $this->dir = to_array($dir);
    }

    /**
     * 添加需要扫描的目录
     *
     * @param $dir
     */
    public function addDir($dir)
    {
        $this->dir = array_merge($this->dir, to_array($dir));
    }

    /**
     * 开始扫描注解
     *
     * @throws \Doctrine\Common\Annotations\AnnotationException
     * @throws \ReflectionException
     */
    public function run()
    {
        $notIgnoreClass = []; // 未被忽略的类
        // 扫描全部文件夹获取所有文件路径
        foreach ($this->dir as $dir) {

            $dir = FileSystem::getAbsPath($dir);

            $dirScanner = FileSystem::filterDirScanner($dir, function (SplFileInfo $f) {

                if ($f->isDir() && Str::endsWith($f->getFilename(), '..')) {
                    return false;
                }

                if ($f->isFile() && !in_array($f->getExtension(), $this->fileExt)) {
                    return false;
                }

                if (!$this->fileNotIgnore($f->getPathname())) {
                    return false;
                }

                return true;
            });

            /**
             * @var $item SplFileInfo
             */
            foreach ($dirScanner as $item) {
                if ($item->isDir()) {
                    $dirName = rtrim($item->getFilename(), '.');
                    // 发现目录
                    $this->trigger('annotation.discovery.dir', $this, $dirName);
                } elseif ($item->isFile()) {
                    $className = $this->getClassNameFromFile($item->openFile());
                    $isIgnore = empty($className) || !$this->classNotIgnore($className);
                    // 发现文件
                    $this->trigger('annotation.discovery.file', $this, [$item->getPathname(), $isIgnore, $className]);

                    if (!$isIgnore) {
                        $notIgnoreClass[$item->getPathname()] = $className;
                    }
                }
            }

        }

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
                // 类无法正确加载
                $this->trigger('annotation.classNotLoadProperly', $this, [[$class, $file]]);
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
     * @param SplFileObject $file
     * @param bool $interface
     *
     * @return string|null
     */
    protected function getClassNameFromFile(SplFileObject $file, bool $interface = false): ?string
    {
        //Grab the contents of the file
        $contents = $file->fread($file->getSize());
        //Start with a blank namespace and class
        $namespace = $class = "";
        //Set helper values to know that we have found the namespace/class token and need to collect the string values after them
        $getting_namespace = $getting_class = false;
        //Go through each token and evaluate it as necessary
        foreach (token_get_all($contents) as $token) {
            //If this token is the namespace declaring, then flag that the next tokens will be the namespace name
            if (is_array($token) && $token[0] == T_NAMESPACE) {
                $getting_namespace = true;
            }
            //If this token is the class declaring, then flag that the next tokens will be the class name
            if (is_array($token) && $token[0] == T_CLASS || ($interface && $token[0] == T_INTERFACE)) {
                $getting_class = true;
            }
            //While we're grabbing the namespace name...
            if ($getting_namespace === true) {
                //If the token is a string or the namespace separator...
                if (is_array($token) && in_array($token[0], [T_STRING, T_NS_SEPARATOR])) {
                    //Append the token's value to the name of the namespace
                    $namespace .= $token[1];
                } else if ($token === ';') {
                    //If the token is the semicolon, then we're done with the namespace declaration
                    $getting_namespace = false;
                }
            }
            //While we're grabbing the class name...
            if ($getting_class === true) {
                //If the token is a string, it's the name of the class
                if (is_array($token) && $token[0] == T_STRING) {
                    //Store the token's value as the class name
                    $class = $token[1];
                    //Got what we need, stope here
                    break;
                }
            }
        }

        if (empty($class)) return null;
        //Build the fully-qualified class name and return it
        return $namespace ? $namespace . '\\' . $class : $class;
    }

    /**
     * 判断当前文件或目录是否被忽略
     *
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
     * 判断当前类是否被忽略
     *
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

}
