<?php declare(strict_types = 1);


namespace Jeekens\Annotation\Annotations\Handler;


use ReflectionClass;

abstract class Handler
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
    protected $className = '';

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

}
