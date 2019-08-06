<?php


namespace Jeekens\Annotation\Annotations\Handler;


interface HandlerInterface
{

    public function handle(int $type, object $annotationObject);

    public function setMethodName(string $methodName);

    public function setPropertyName(string $propertyName);

}