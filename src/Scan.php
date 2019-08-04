<?php declare(strict_types = 1);


namespace Jeekens\Annotations;



class Scan
{

    /**
     * @var array
     */
    private $ignoreNameSpace = [];

    /**
     * @var array
     */
    private $dir = [];

    /**
     * @var array
     */
    private $ignoreFile = [];

    /**
     * @var array
     */
    private $annotationHandler = [];


    public function __construct(? array $options = null)
    {
        if ($options) {
            class_init($this, $options);
        }
    }

    /**
     * @param array $nameSpace
     */
    public function setIgnoreNameSpace(array $nameSpace)
    {
        $this->ignoreNameSpace = $nameSpace;
    }

    /**
     * @param string|array $nameSpace
     */
    public function addIgnoreNameSpace($nameSpace)
    {
        $this->ignoreNameSpace[] = $nameSpace;
    }

    /**
     * @param array $file
     */
    public function setIgnoreFile(array $file)
    {
        $this->ignoreFile = $file;
    }

    /**
     * @param $file
     *
     * @return $this
     */
    public function addIgnoreFile($file)
    {
        $this->ignoreFile = array_merge(
            $this->ignoreFile,
            (array) $file
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
            (array) $dir
        );
        return $this;
    }

    /**
     * @param string $annotation
     * @param string $handler
     */
    public function addAnnotationHandler(string $annotation, string $handler)
    {
        $this->annotationHandler[$annotation] = $handler;
    }

}
