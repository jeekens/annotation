<?php declare(strict_types=1);


namespace Jeekens\Annotation\Annotations\Assist;


use Doctrine\Common\Annotations\Annotation\Target;
use Jeekens\Annotation\Exception\AnnotationException;

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
     */
    protected $annotation;

    /**
     * @param array $values
     *
     * @throws AnnotationException
     */
    public function __construct(array $values)
    {
        if (isset($values['value'])) {
            $this->annotation = $values['value'];
        }

        if (isset($values['annotation'])) {
            $this->annotation = $values['annotation'];
        }

        if (empty($this->annotation)) {
            throw new AnnotationException('Property annotation is not set!');
        }
    }

    public function getAnnotation(): string
    {
        return $this->annotation;
    }

}