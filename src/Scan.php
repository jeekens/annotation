<?php declare(strict_types = 1);


namespace Jeekens\Annotations;



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
    private $annotationHandler = [];


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
     * @param $file
     *
     * @return $this
     */
    public function addIgnoreFileOrDir($fileOrDir)
    {
        $this->ignoreFile = array_merge(
            $this->ignoreFileOrDir,
            (array) $fileOrDir
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
    private function addAnnotationHandler(string $annotation, string $handler)
    {
        $this->annotationHandler[$annotation] = $handler;
    }


    public function run()
    {
        $allFile = [];

        foreach ($this->dir as $dir) {
            if (($files = $this->scanPhpFile($dir)) && ! empty($files)) {
                $allFile = array_merge($allFile, $files);
            }
        }
    }


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
    }

    /**
     * @param string $filePath
     *
     * @return bool
     */
    private function fileIsIgnore(string $filePath)
    {
        foreach ($this->ignoreFileOrDir as $pattern) {
            if (preg_match("#{$pattern}#", $filePath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $className
     *
     * @return bool
     */
    private function classIsIgnore(string $className)
    {
        foreach ($this->ignoreNameSpaceOrClass as $pattern) {
            if (preg_match("#{$pattern}#", $className)) {
                return true;
            }
        }

        return false;
    }

}
