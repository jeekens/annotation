<?php declare(strict_types=1);


namespace Jeekens\Annotation\Annotations\Handler;


use ReflectionClass;

abstract class Handler implements HandlerInterface
{

    /**
     * Class annotation
     */
    public const TYPE_CLASS = 1;

    /**
     * Property annotation
     */
    public const TYPE_PROPERTY = 2;

    /**
     * Method annotation
     */
    public const TYPE_METHOD = 3;

    /**
     * Class name
     *
     * @var string
     */
    protected $className;

    /**
     * Class reflect
     *
     * @var ReflectionClass
     */
    protected $reflectClass;

    /**
     * Class all annotations
     *
     * @var object[]
     */
    protected $classAnnotations = [];

    /**
     * @var string
     */
    protected $methodName = '';

    /**
     * @var string
     */
    protected $propertyName = '';

    /**
     * Parser constructor.
     *
     * @param string           $className
     * @param ReflectionClass $reflectionClass
     * @param array            $classAnnotations
     */
    public function __construct(string $className, ReflectionClass $reflectionClass, array $classAnnotations)
    {
        $this->className        = $className;
        $this->reflectClass     = $reflectionClass;
        $this->classAnnotations = $classAnnotations;
    }

    /**
     * Set method name
     *
     * @param string $methodName
     */
    public function setMethodName(string $methodName)
    {
        $this->methodName = $methodName;
    }

    /**
     * Set property name
     *
     * @param string $propertyName
     */
    public function setPropertyName(string $propertyName)
    {
        $this->propertyName = $propertyName;
    }

    /**
     * @param string $annotationClassName
     *
     * @return bool
     */
    public function classHasAnnotation(string $annotationClassName)
    {
        foreach ($this->classAnnotations as $annotation) {
            if ($annotation instanceof $annotationClassName) {
                return true;
            }
        }

        return false;
    }

}
