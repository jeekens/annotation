<?php declare(strict_types = 1);


namespace Jeekens\Annotation\Annotations\Assist;


use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Class AnnotationHandler
 *
 * @Annotation
 *
 * @Target("CLASS")
 *
 * @package Jeekens\Annotation\Annotations\Assist
 */
class AnnotationHandler
{

    /**
     * @var string
     * @Required()
     */
    protected $annotation;


    public function __construct(array $values)
    {
        if (isset($values['value'])) {
            $this->annotation = $values['value'];
        }
        if (isset($values['annotation'])) {
            $this->annotation = $values['annotation'];
        }
    }

    public function getAnnotation(): string
    {
        return $this->annotation;
    }

}