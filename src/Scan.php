<?php declare(strict_types=1);


namespace Jeekens\Annotation;

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
            if (($files = $this->scanPhpFile($dir)) && !empty($files)) {
                $allFile = array_merge($allFile, $files);
            }
        }
        // 过滤需要忽略的文件
        $notIgnoreFile = array_filter($allFile, function ($file) {
            return $this->fileNotIgnore($file);
        });
        // 扫描所有文件并取出文件内的类名
        $allClass = array_filter(array_map(function ($file) {
            $class = get_class_from_file($file);
            return empty($class) ? null : $class;
        }, $notIgnoreFile));
        // 过滤掉需要忽略的类
        $notIgnoreClass = array_filter($allClass, function ($className) {
            return $this->classNotIgnore($className);
        });
        // 注册注解加载规则
        AnnotationRegistry::registerLoader('class_exists');
        // 初始化需要使用的变量
        $classAnnotations = $propertiesAnnotation = $methodAnnotation = $annotationHandler = [];
        $annotationReader = new AnnotationReader();

        foreach ($notIgnoreClass as $class) {
            // 如果类不能自动加载则跳过注解处理流程
            if (! class_exists($class)) {
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
                        if (is_dir($dir . '/' . $file)) {
                            $this->scanPhpFile($dir . '/' . $file, $files);
                        } else {
                            if (pathinfo($file, PATHINFO_EXTENSION) == 'php') {
                                $files[] = $dir . '/' . $file;
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

}
